<?php
/**
 * Class WC_Stripe_Express_Checkout_Password_Cleanup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Removes account passwords that were stored in order metadata.
 */
final class WC_Stripe_Express_Checkout_Password_Cleanup {

	public const BATCH_HOOK = 'wc_stripe_express_checkout_password_cleanup_batch';

	private const META_KEY = 'account_password';

	private const COMPLETE_OPTION = 'wc_stripe_express_checkout_password_cleanup_complete';

	private const LOCK_OPTION = 'wc_stripe_express_checkout_password_cleanup_lock';

	private const RESULT_OPTION = 'wc_stripe_express_checkout_password_cleanup_result';

	private const EMAIL_ATTEMPTED_OPTION = 'wc_stripe_express_checkout_password_cleanup_email_attempted';

	private const NOTICE_DISMISSED_OPTION = 'wc_stripe_express_checkout_password_cleanup_notice_dismissed';

	private const USER_META_KEY = 'wc_stripe_express_checkout_password_cleanup_required';

	private const BATCH_SIZE = 20;

	private const LOCK_TTL = 15 * MINUTE_IN_SECONDS;

	private const RETRY_COUNT_OPTION = 'wc_stripe_express_checkout_password_cleanup_retry_count';

	private const RETRY_DELAYS_MINUTES = [ 2, 4, 8, 16, 32, 60 ];

	/**
	 * Starts cleanup during the normal plugin update routine.
	 *
	 * @return void
	 */
	public function maybe_migrate(): void {
		if ( self::is_cleanup_complete() && ( ! self::get_cleanup_result()['affected'] || 'yes' === get_option( self::EMAIL_ATTEMPTED_OPTION ) ) ) {
			return;
		}

		$this->schedule_batch();
	}

	/**
	 * Processes one batch without depending on another plugin upgrade.
	 */
	public function process_batch(): void {
		// Queue recovery before work that could time out or encounter a live lock.
		$this->schedule_batch( $this->get_retry_delay() );

		$lock_value = WC_Stripe_Option_Lock::acquire( self::LOCK_OPTION, self::LOCK_TTL );
		if ( null === $lock_value ) {
			return;
		}

		try {
			if ( ! self::is_cleanup_complete() ) {
				// Record the attempt before work so a fatal also contributes to backoff.
				$retry_count = min( count( self::RETRY_DELAYS_MINUTES ) - 1, absint( get_option( self::RETRY_COUNT_OPTION, 0 ) ) + 1 );
				update_option( self::RETRY_COUNT_OPTION, $retry_count, false );
				$records = $this->get_records_batch();
				if ( null === $records ) {
					return;
				}

				if ( ! empty( $records ) ) {
					$batch_data = $this->prepare_batch( $records );
					$this->mark_affected_users( $batch_data['user_ids'] );
					$result = [
						'affected'   => true,
						'user_count' => $this->count_affected_users(),
					];

					if ( ! $this->save_result( $result ) ) {
						WC_Stripe_Logger::warning( 'Unable to persist the express checkout password cleanup result.' );
					}

					$deleted = $this->delete_records( $records );
					if ( $deleted > 0 ) {
						update_option( self::RETRY_COUNT_OPTION, 0, false );
						$this->schedule_batch();
					}

					$remaining_records = $this->get_records_batch();
					if ( null === $remaining_records ) {
						return;
					}
					if ( $this->get_record_ids( $remaining_records ) === $this->get_record_ids( $records ) ) {
						WC_Stripe_Logger::warning( 'Unable to remove order metadata during express checkout password cleanup.' );
					}
					if ( ! empty( $remaining_records ) ) {
						if ( $deleted > 0 ) {
							$this->schedule_batch( 0 );
						}
						return;
					}
				}

				if ( ! $this->mark_complete() ) {
					return;
				}
				delete_option( self::RETRY_COUNT_OPTION );
				$this->schedule_batch( HOUR_IN_SECONDS );
			}

			$this->send_completion_email_if_needed();
			if ( ! self::get_cleanup_result()['affected'] || 'yes' === get_option( self::EMAIL_ATTEMPTED_OPTION ) ) {
				as_unschedule_all_actions( self::BATCH_HOOK, [], WC_Stripe_Action_Scheduler_Service::GROUP_ID );
			}
		} finally {
			WC_Stripe_Option_Lock::release( self::LOCK_OPTION, $lock_value );
		}
	}

	private function get_retry_delay(): int {
		if ( self::is_cleanup_complete() ) {
			return HOUR_IN_SECONDS;
		}

		$retry_count = min( count( self::RETRY_DELAYS_MINUTES ) - 1, absint( get_option( self::RETRY_COUNT_OPTION, 0 ) ) );
		return self::RETRY_DELAYS_MINUTES[ $retry_count ] * MINUTE_IN_SECONDS;
	}

	private function schedule_batch( int $delay = 2 * MINUTE_IN_SECONDS ): void {
		$timestamp = time() + $delay;
		// Claimed actions cannot provide recovery; later recovery jobs must not delay productive batches.
		$pending = as_get_scheduled_actions(
			[
				'hook'         => self::BATCH_HOOK,
				'args'         => [],
				'group'        => WC_Stripe_Action_Scheduler_Service::GROUP_ID,
				'status'       => ActionScheduler_Store::STATUS_PENDING,
				'claimed'      => false,
				'date'         => $timestamp,
				'date_compare' => '<=',
				'per_page'     => 1,
			],
			'ids'
		);
		if ( ! empty( $pending ) ) {
			return;
		}

		if ( ! as_schedule_single_action( $timestamp, self::BATCH_HOOK, [], WC_Stripe_Action_Scheduler_Service::GROUP_ID ) ) {
			WC_Stripe_Logger::warning( 'Unable to schedule the express checkout password cleanup batch.' );
		}
	}

	/**
	 * Identifies rows independently of mutable metadata values.
	 *
	 * @param array<array{storage: string, row_id: int}> $records Metadata rows.
	 * @return string[]
	 */
	private function get_record_ids( array $records ): array {
		$ids = array_map(
			static function ( array $record ): string {
				return $record['storage'] . ':' . $record['row_id'];
			},
			$records
		);
		sort( $ids, SORT_STRING );
		return $ids;
	}

	/**
	 * Returns whether cleanup has finished for this site.
	 *
	 * @return bool
	 */
	private static function is_cleanup_complete(): bool {
		return 'yes' === get_option( self::COMPLETE_OPTION );
	}

	/**
	 * Returns the fixed-size cleanup summary.
	 *
	 * @return array{affected: bool, user_count: int}
	 */
	public static function get_cleanup_result(): array {
		$result = get_option( self::RESULT_OPTION, [] );
		if ( ! is_array( $result ) ) {
			$result = [];
		}

		return [
			'affected'   => ! empty( $result['affected'] ),
			'user_count' => absint( $result['user_count'] ?? 0 ),
		];
	}

	/**
	 * Returns accounts marked for possible later remediation on this site.
	 *
	 * @return int[]
	 */
	public static function get_affected_user_ids(): array {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", self::get_user_meta_key() );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return self::sanitize_ids( $wpdb->get_col( $query ) );
	}

	/**
	 * Returns whether the completed-cleanup notice should be displayed.
	 *
	 * @return bool
	 */
	public static function should_show_notice(): bool {
		if ( ! self::is_cleanup_complete() || 'yes' === get_option( self::NOTICE_DISMISSED_OPTION ) ) {
			return false;
		}

		$result = self::get_cleanup_result();

		return $result['affected'];
	}

	/**
	 * Dismisses the completed-cleanup notice without removing the account markers.
	 *
	 * @return void
	 */
	public static function dismiss_notice(): void {
		update_option( self::NOTICE_DISMISSED_OPTION, 'yes', false );
	}

	/**
	 * Gets a bounded batch from both physical order stores.
	 *
	 * @return array<int, array{storage: string, row_id: int, order_id: int, meta_value: string}>|null
	 */
	private function get_records_batch() {
		$legacy_records = $this->get_legacy_records( self::BATCH_SIZE );
		if ( null === $legacy_records ) {
			return null;
		}

		$remaining = self::BATCH_SIZE - count( $legacy_records );
		if ( 0 === $remaining ) {
			return $legacy_records;
		}

		$hpos_records = $this->get_hpos_records( $remaining );
		if ( null === $hpos_records ) {
			return null;
		}

		return array_merge( $legacy_records, $hpos_records );
	}

	/**
	 * Gets affected metadata stored in the legacy post tables.
	 *
	 * @param int $limit Maximum rows to return.
	 * @return array<int, array{storage: string, row_id: int, order_id: int, meta_value: string}>|null
	 */
	private function get_legacy_records( int $limit ) {
		global $wpdb;

		$wpdb->last_error = '';

		// Placeholder posts can retain the synchronized legacy copy after HPOS becomes authoritative.
		$query = $wpdb->prepare(
			"SELECT pm.meta_id AS row_id, pm.post_id AS order_id, pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			AND p.post_type IN ( 'shop_order', 'shop_order_placehold', 'shop_subscription' )
			ORDER BY pm.meta_id ASC
			LIMIT %d",
			self::META_KEY,
			$limit
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( ! empty( $wpdb->last_error ) || ! is_array( $rows ) ) {
			WC_Stripe_Logger::warning( 'Unable to query legacy order metadata during express checkout password cleanup.' );
			return null;
		}

		return $this->normalize_records( $rows, 'legacy' );
	}

	/**
	 * Gets affected metadata stored in the HPOS tables.
	 *
	 * @param int $limit Maximum rows to return.
	 * @return array<int, array{storage: string, row_id: int, order_id: int, meta_value: string}>|null
	 */
	private function get_hpos_records( int $limit ) {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wc_orders';
		$meta_table   = $wpdb->prefix . 'wc_orders_meta';

		$orders_table_exists = $this->table_exists( $orders_table );
		$meta_table_exists   = $this->table_exists( $meta_table );
		if ( null === $orders_table_exists || null === $meta_table_exists ) {
			return null;
		}
		if ( ! $orders_table_exists || ! $meta_table_exists ) {
			return [];
		}

		$wpdb->last_error = '';

		$query = $wpdb->prepare(
			"SELECT om.id AS row_id, om.order_id, om.meta_value
			FROM %i om
			INNER JOIN %i orders ON orders.id = om.order_id
			WHERE om.meta_key = %s
			AND orders.type IN ( 'shop_order', 'shop_subscription' )
			ORDER BY om.id ASC
			LIMIT %d",
			$meta_table,
			$orders_table,
			self::META_KEY,
			$limit
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( ! empty( $wpdb->last_error ) || ! is_array( $rows ) ) {
			WC_Stripe_Logger::warning( 'Unable to query HPOS order metadata during express checkout password cleanup.' );
			return null;
		}

		return $this->normalize_records( $rows, 'hpos' );
	}

	/**
	 * Checks whether a database table exists.
	 *
	 * @param string $table_name Table name.
	 * @return bool|null
	 */
	private function table_exists( string $table_name ) {
		global $wpdb;

		$wpdb->last_error = '';
		$query            = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( $query );
		if ( ! empty( $wpdb->last_error ) ) {
			WC_Stripe_Logger::warning( 'Unable to inspect HPOS tables during express checkout password cleanup.' );
			return null;
		}

		return $table_name === $result;
	}

	/**
	 * Normalizes database rows.
	 *
	 * @param array[] $rows Raw database rows.
	 * @param string  $storage Storage identifier.
	 * @return array<int, array{storage: string, row_id: int, order_id: int, meta_value: string}>
	 */
	private function normalize_records( array $rows, string $storage ): array {
		$records = [];
		foreach ( $rows as $row ) {
			$row_id   = absint( $row['row_id'] ?? 0 );
			$order_id = absint( $row['order_id'] ?? 0 );
			if ( 0 === $row_id || 0 === $order_id || ! isset( $row['meta_value'] ) || ! is_string( $row['meta_value'] ) ) {
				continue;
			}

			$records[] = [
				'storage'    => $storage,
				'row_id'     => $row_id,
				'order_id'   => $order_id,
				'meta_value' => $row['meta_value'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			];
		}

		return $records;
	}

	/**
	 * Resolves affected accounts while the plaintext value is still available.
	 *
	 * @param array<int, array{storage: string, row_id: int, order_id: int, meta_value: string}> $records Metadata rows.
	 * @return array{user_ids: int[]}
	 */
	private function prepare_batch( array $records ): array {
		$values_by_order = [];
		foreach ( $records as $record ) {
			$values_by_order[ $record['order_id'] ][ hash( 'sha256', $record['meta_value'] ) ] = $record['meta_value'];
		}

		$affected_user_ids = [];
		$checked_values    = [];

		foreach ( $values_by_order as $order_id => $passwords ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$user = $this->get_order_user( $order );
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			foreach ( $passwords as $password_hash => $password ) {
				$check_key = $user->ID . ':' . $password_hash;
				if ( isset( $checked_values[ $check_key ] ) ) {
					continue;
				}
				$checked_values[ $check_key ] = true;

				if ( wp_check_password( $password, $user->user_pass, $user->ID ) ) {
					$affected_user_ids[] = $user->ID;
				}
			}
		}

		return [
			'user_ids' => self::sanitize_ids( $affected_user_ids ),
		];
	}

	/**
	 * Resolves the account associated with an affected order.
	 *
	 * @param WC_Order $order Order object.
	 * @return WP_User|false
	 */
	private function get_order_user( WC_Order $order ) {
		$customer_id = $order->get_customer_id();
		if ( $customer_id > 0 ) {
			return get_user_by( 'id', $customer_id );
		}

		$email = sanitize_email( trim( (string) $order->get_billing_email() ) );
		if ( empty( $email ) || ! is_email( $email ) ) {
			return false;
		}

		return get_user_by( 'email', $email );
	}

	/**
	 * Deletes only the metadata rows selected for this batch from each physical store.
	 *
	 * @param array<int, array{storage: string, row_id: int, order_id: int, meta_value: string}> $records Metadata rows.
	 * @return int Number of deleted rows.
	 */
	private function delete_records( array $records ): int {
		global $wpdb;

		$hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
		$order_ids       = [];
		$deleted         = 0;
		foreach ( $records as $record ) {
			$order_ids[] = $record['order_id'];
			if ( 'legacy' === $record['storage'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$deleted += (int) $wpdb->delete(
					$wpdb->postmeta,
					[
						'meta_id'  => $record['row_id'],
						'meta_key' => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					],
					[ '%d', '%s' ]
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$deleted += (int) $wpdb->delete(
					$hpos_meta_table,
					[
						'id'       => $record['row_id'],
						'meta_key' => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					],
					[ '%d', '%s' ]
				);
			}
		}

		$order_ids = self::sanitize_ids( $order_ids );
		foreach ( $order_ids as $order_id ) {
			clean_post_cache( $order_id );
			wp_cache_delete( $order_id, 'post_meta' );
			wp_cache_delete( WC_Order::generate_meta_cache_key( $order_id, 'orders' ), 'orders' );
		}

		$this->clear_woocommerce_order_caches( $order_ids );

		return $deleted;
	}

	/**
	 * Clears WooCommerce caches after deleting directly from the metadata tables.
	 *
	 * @param int[] $order_ids Affected order IDs.
	 * @return void
	 */
	private function clear_woocommerce_order_caches( array $order_ids ): void {
		$data_store_class  = 'Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\OrdersTableDataStore';
		$order_cache_class = 'Automattic\\WooCommerce\\Caches\\OrderCache';
		if ( empty( $order_ids ) || ! function_exists( 'wc_get_container' ) ) {
			return;
		}

		try {
			if ( class_exists( $order_cache_class ) ) {
				$order_cache = wc_get_container()->get( $order_cache_class );
				foreach ( $order_ids as $order_id ) {
					$order_cache->remove( $order_id );
				}
			}

			if ( class_exists( $data_store_class ) ) {
				$data_store = wc_get_container()->get( $data_store_class );
				if ( is_object( $data_store ) && is_callable( [ $data_store, 'clear_cached_data' ] ) ) {
					$data_store->clear_cached_data( $order_ids );
				}
			}
		} catch ( Throwable $e ) {
			WC_Stripe_Logger::warning( 'Unable to clear order metadata caches during express checkout password cleanup.' );
		}
	}

	/**
	 * Persists the cleanup summary without autoloading it on every request.
	 *
	 * @param array{affected: bool, user_count: int} $result Cleanup result.
	 * @return bool
	 */
	private function save_result( array $result ): bool {
		update_option( self::RESULT_OPTION, $result, false );

		return self::get_cleanup_result() === $result;
	}

	/**
	 * Marks accounts using a site-prefixed key so multisite stores remain isolated.
	 *
	 * @param int[] $user_ids User IDs.
	 * @return void
	 */
	private function mark_affected_users( array $user_ids ): void {
		$meta_key = self::get_user_meta_key();
		foreach ( $user_ids as $user_id ) {
			add_user_meta( $user_id, $meta_key, 'yes', true );
		}
	}

	/**
	 * Counts accounts marked on this site.
	 *
	 * @return int
	 */
	private function count_affected_users(): int {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s", self::get_user_meta_key() );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Gets the site-specific affected-account marker.
	 *
	 * @return string
	 */
	private static function get_user_meta_key(): string {
		global $wpdb;

		return $wpdb->prefix . self::USER_META_KEY;
	}

	/**
	 * Marks cleanup complete after both stores have been verified.
	 *
	 * @return bool
	 */
	private function mark_complete(): bool {
		update_option( self::COMPLETE_OPTION, 'yes', false );

		return self::is_cleanup_complete();
	}

	/**
	 * Attempts the aggregate admin email at most once.
	 *
	 * @param array{affected: bool, user_count: int} $result Cleanup result.
	 * @return void
	 */
	private function send_email_once( array $result ): void {
		$recipient = sanitize_email( (string) get_option( 'admin_email' ) );
		if ( empty( $recipient ) || ! is_email( $recipient ) ) {
			WC_Stripe_Logger::warning( 'Unable to send the express checkout password cleanup email because the admin email is invalid.' );
			return;
		}

		if ( ! add_option( self::EMAIL_ATTEMPTED_OPTION, 'yes', '', false ) ) {
			return;
		}

		$subject = __( '[WooCommerce Stripe] Customer password data removed from order records', 'woocommerce-gateway-stripe' );
		$message = __( 'WooCommerce Stripe removed account password data from order records.', 'woocommerce-gateway-stripe' );
		if ( $result['user_count'] > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: affected customer account count */
				_n( '%d customer account was identified as still using a password that appeared in the removed data.', '%d customer accounts were identified as still using a password that appeared in the removed data.', $result['user_count'], 'woocommerce-gateway-stripe' ),
				$result['user_count']
			);
		}
		$message .= ' ' . __( 'No customer passwords were reset. A notice with this information is also available in the WordPress admin.', 'woocommerce-gateway-stripe' );

		if ( ! wp_mail( $recipient, $subject, $message ) ) {
			delete_option( self::EMAIL_ATTEMPTED_OPTION );
			WC_Stripe_Logger::warning( 'The express checkout password cleanup email could not be sent.' );
		}
	}

	/**
	 * Sends the completion email when affected data was removed.
	 *
	 * @return void
	 */
	private function send_completion_email_if_needed(): void {
		$result = self::get_cleanup_result();
		if ( $result['affected'] ) {
			$this->send_email_once( $result );
		}
	}

	/**
	 * Sanitizes an identifier list.
	 *
	 * @param mixed $ids Candidate identifiers.
	 * @return int[]
	 */
	private static function sanitize_ids( $ids ): array {
		if ( ! is_array( $ids ) ) {
			return [];
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		sort( $ids, SORT_NUMERIC );

		return $ids;
	}
}
