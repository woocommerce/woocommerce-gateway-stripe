<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores Stripe event processing state as posts of a hidden post type.
 *
 * The event ID is kept in `post_name`, the event type in `post_title`, the event creation time in
 * `post_date`/`post_date_gmt`, and the order the event was applied to in `post_parent`.
 */
class WC_Stripe_Event_Post_Store implements WC_Stripe_Event_Store_Interface {
	public const POST_TYPE = 'wc_stripe_event';

	/**
	 * Post status for each store status. Prefixed because post statuses are global, and `pending`
	 * would replace the core one.
	 */
	private const POST_STATUSES = [
		self::STATUS_PENDING    => 'wc_stripe_pending',
		self::STATUS_PROCESSING => 'wc_stripe_processing',
		self::STATUS_PROCESSED  => 'wc_stripe_processed',
		self::STATUS_FAILED     => 'wc_stripe_failed',
	];

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Returns the shared instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks the post type registration.
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register' ] );
	}

	/**
	 * Registers the post type and its statuses.
	 */
	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'can_export'   => false,
				'query_var'    => false,
				'rewrite'      => false,
			]
		);

		foreach ( self::POST_STATUSES as $post_status ) {
			register_post_status( $post_status, [ 'internal' => true ] );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get( array $event_ids ): array {
		if ( ! $event_ids ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'              => self::POST_TYPE,
				'post_status'            => array_values( self::POST_STATUSES ),
				'post_name__in'          => $event_ids,
				'posts_per_page'         => -1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// WP_Query lowercases post_name__in and MySQL compares case-insensitively, so match the same way.
		$found = [];
		foreach ( $posts as $post ) {
			$record = $this->get_record_from_post( $post );
			if ( $record ) {
				$found[ strtolower( $post->post_name ) ] = $record;
			}
		}

		$records = [];
		foreach ( $event_ids as $event_id ) {
			if ( isset( $found[ strtolower( $event_id ) ] ) ) {
				$records[ $event_id ] = $found[ strtolower( $event_id ) ];
			}
		}

		return $records;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_by_status( string $status, int $limit ): array {
		if ( ! isset( self::POST_STATUSES[ $status ] ) ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'              => self::POST_TYPE,
				'post_status'            => self::POST_STATUSES[ $status ],
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'posts_per_page'         => $limit,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return array_values( array_filter( array_map( [ $this, 'get_record_from_post' ], $posts ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function save( WC_Stripe_Event_Record $record ): bool {
		$event_id = $record->id;
		if ( '' === $event_id || ! isset( self::POST_STATUSES[ $record->status ] ) ) {
			return false;
		}

		$post = [
			'post_type'   => self::POST_TYPE,
			'post_status' => self::POST_STATUSES[ $record->status ],
			'post_name'   => $event_id,
			'post_title'  => $record->type,
			'post_parent' => $record->order_id ?? 0,
		];

		if ( $record->created > 0 ) {
			$post['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $record->created );
			$post['post_date']     = get_date_from_gmt( $post['post_date_gmt'] );
		}

		$post_id = $this->get_post_id( $event_id );
		if ( $post_id ) {
			$post['ID'] = $post_id;
		}

		// wp_insert_post() and wp_update_post() run post_name through sanitize_title(), which lowercases the case-sensitive event ID.
		$keep_event_id = static function ( $post_data ) use ( $event_id ) {
			if ( self::POST_TYPE === ( $post_data['post_type'] ?? '' ) ) {
				$post_data['post_name'] = $event_id;
			}
			return $post_data;
		};

		add_filter( 'wp_insert_post_data', $keep_event_id );
		try {
			$result = $post_id ? wp_update_post( $post, true ) : wp_insert_post( $post, true );
		} finally {
			remove_filter( 'wp_insert_post_data', $keep_event_id );
		}

		return ! is_wp_error( $result ) && $result > 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_all(): int {
		$deleted = 0;

		do {
			$post_ids = get_posts(
				[
					'post_type'   => self::POST_TYPE,
					'post_status' => array_values( self::POST_STATUSES ),
					'fields'      => 'ids',
					'numberposts' => 100,
				]
			);

			$deleted_in_batch = 0;
			foreach ( $post_ids as $post_id ) {
				if ( wp_delete_post( (int) $post_id, true ) ) {
					++$deleted_in_batch;
				}
			}

			$deleted += $deleted_in_batch;
			// Posts that cannot be deleted would be returned again, so stop instead of looping forever.
		} while ( $post_ids && $deleted_in_batch );

		return $deleted;
	}

	/**
	 * Returns the ID of the post recording an event, or 0 when there is none.
	 *
	 * @param string $event_id Stripe event ID.
	 * @return int
	 */
	private function get_post_id( string $event_id ): int {
		$post_ids = get_posts(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => array_values( self::POST_STATUSES ),
				'name'        => $event_id,
				'fields'      => 'ids',
				'numberposts' => 1,
			]
		);

		return $post_ids ? (int) $post_ids[0] : 0;
	}

	/**
	 * Builds a record from a post of this store's post type.
	 *
	 * @param WP_Post $post Event post.
	 * @return WC_Stripe_Event_Record|null The record, or null for a post status this store does not use.
	 */
	private function get_record_from_post( WP_Post $post ): ?WC_Stripe_Event_Record {
		$status = array_search( $post->post_status, self::POST_STATUSES, true );
		if ( false === $status ) {
			return null;
		}

		return new WC_Stripe_Event_Record(
			$post->post_name,
			$status,
			$post->post_title,
			(int) strtotime( $post->post_date_gmt . ' UTC' ),
			$post->post_parent ? (int) $post->post_parent : null
		);
	}
}
