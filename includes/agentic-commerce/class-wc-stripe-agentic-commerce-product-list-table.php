<?php
/**
 * Products list-table surface for the Agentic Commerce exclude flag.
 *
 * Adds bulk actions, a quick-edit checkbox, a status column, and a status
 * filter dropdown to the Products screen so merchants can toggle catalog-sync
 * exclusion for many products at once instead of opening each product editor.
 *
 * Admin UI only: the flag's read/write contract lives in
 * {@see WC_Stripe_Agentic_Commerce_Product_Exclusion}.
 *
 * @internal Not part of the plugin's public API; may change without notice.
 * @package WooCommerce_Stripe
 * @since 10.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk edit, quick edit, column, and filter for the Agentic Commerce exclude flag.
 *
 * @internal
 * @since 10.9.0
 */
class WC_Stripe_Agentic_Commerce_Product_List_Table {

	/**
	 * Bulk action key: set the exclude flag on the selected products.
	 */
	private const ACTION_EXCLUDE = 'wc_stripe_agentic_exclude';

	/**
	 * Bulk action key: clear the exclude flag on the selected products.
	 */
	private const ACTION_INCLUDE = 'wc_stripe_agentic_include';

	/**
	 * List-table column key.
	 */
	private const COLUMN_KEY = 'wc_stripe_agentic_sync';

	/**
	 * Hidden inline-data class carrying each row's exclude state for the
	 * quick-edit script. Rendered only for supported types, so its absence
	 * tells the script to hide the checkbox. Must stay in sync with
	 * `assets/js/stripe-agentic-product-quick-edit.js`.
	 */
	private const INLINE_DATA_CLASS = 'wc_stripe_agentic_excluded';

	/**
	 * GET parameter for the sync-status filter dropdown ('synced' or 'excluded').
	 */
	private const FILTER_QUERY_VAR = 'wc_stripe_agentic_sync_status';

	/**
	 * Redirect query args carrying the bulk-action result count for the notice.
	 */
	private const NOTICE_ARG_EXCLUDED = 'wc_stripe_agentic_excluded';
	private const NOTICE_ARG_INCLUDED = 'wc_stripe_agentic_included';

	/**
	 * Register the list-table hooks. Admin-only — the should-sync filter that
	 * enforces the flag during a feed run lives in the exclusion storage class.
	 *
	 * @since 10.9.0
	 * @return void
	 */
	public function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Don't register the list-table UI at all when the merchant has Agentic
		// Commerce disabled — the toggle has nothing to act on in that state.
		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return;
		}

		add_filter( 'bulk_actions-edit-product', [ $this, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-product', [ $this, 'handle_bulk_actions' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'render_bulk_action_notice' ] );
		add_filter( 'removable_query_args', [ $this, 'register_removable_query_args' ] );

		// Register the column late so it lands after WooCommerce's own columns.
		add_filter( 'manage_edit-product_columns', [ $this, 'register_column' ], 20 );
		add_action( 'manage_product_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_action( 'add_inline_data', [ $this, 'render_inline_data' ], 10, 2 );

		add_action( 'restrict_manage_posts', [ $this, 'render_sync_status_filter' ] );
		add_action( 'pre_get_posts', [ $this, 'filter_products_by_sync_status' ] );

		add_action( 'quick_edit_custom_box', [ $this, 'render_quick_edit_field' ], 10, 2 );
		add_action( 'woocommerce_product_quick_edit_save', [ $this, 'save_quick_edit' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add the exclude/include bulk actions to the Products list table.
	 *
	 * @since 10.9.0
	 * @param array $bulk_actions Registered bulk actions.
	 * @return array
	 */
	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions[ self::ACTION_EXCLUDE ] = __( 'Exclude from Agentic Commerce sync', 'woocommerce-gateway-stripe' );
		$bulk_actions[ self::ACTION_INCLUDE ] = __( 'Include in Agentic Commerce sync', 'woocommerce-gateway-stripe' );
		return $bulk_actions;
	}

	/**
	 * Apply an exclude/include bulk action to the selected products.
	 *
	 * Mirrors the meta box's guards (feature gate, supported product types) so
	 * bulk edit can't diverge from single-product edit, and adds a per-product
	 * capability check because bulk handlers receive raw IDs, not an
	 * already-vetted post.
	 *
	 * @since 10.9.0
	 * @param string $redirect_url URL to redirect to after the action.
	 * @param string $action       The bulk action being performed.
	 * @param array  $post_ids     Selected product IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_url, $action, $post_ids ) {
		if ( self::ACTION_EXCLUDE !== $action && self::ACTION_INCLUDE !== $action ) {
			return $redirect_url;
		}

		// Defensive: init() only hooks this when the feature is on, but the
		// callback is public, so re-check before writing meta.
		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return $redirect_url;
		}

		$excluded = self::ACTION_EXCLUDE === $action;
		$updated  = 0;
		$changed  = false;

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( ! current_user_can( 'edit_product', $post_id ) ) {
				continue;
			}

			$product = wc_get_product( $post_id );
			if ( ! WC_Stripe_Agentic_Commerce_Product_Exclusion::supports( $product ) ) {
				continue;
			}

			if ( WC_Stripe_Agentic_Commerce_Product_Exclusion::set_excluded( $post_id, $excluded ) ) {
				$changed = true;
			}
			++$updated;
		}

		// One resync for the whole batch: schedule_full_resync_now() dedupes via
		// as_has_scheduled_action(), but calling it per product would still run a
		// queue lookup for every row.
		if ( $changed ) {
			( new WC_Stripe_Agentic_Commerce_Integration() )->schedule_full_resync_now();
		}

		return add_query_arg(
			[ $excluded ? self::NOTICE_ARG_EXCLUDED : self::NOTICE_ARG_INCLUDED => $updated ],
			remove_query_arg( [ self::NOTICE_ARG_EXCLUDED, self::NOTICE_ARG_INCLUDED ], $redirect_url )
		);
	}

	/**
	 * Show the result notice after an exclude/include bulk action.
	 *
	 * @since 10.9.0
	 * @return void
	 */
	public function render_bulk_action_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		if ( isset( $_GET[ self::NOTICE_ARG_EXCLUDED ] ) ) {
			$count = absint( $_GET[ self::NOTICE_ARG_EXCLUDED ] );
			/* translators: %s: number of products. */
			$message = _n( '%s product excluded from the Agentic Commerce catalog sync.', '%s products excluded from the Agentic Commerce catalog sync.', $count, 'woocommerce-gateway-stripe' );
		} elseif ( isset( $_GET[ self::NOTICE_ARG_INCLUDED ] ) ) {
			$count = absint( $_GET[ self::NOTICE_ARG_INCLUDED ] );
			/* translators: %s: number of products. */
			$message = _n( '%s product included in the Agentic Commerce catalog sync.', '%s products included in the Agentic Commerce catalog sync.', $count, 'woocommerce-gateway-stripe' );
		} else {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( $message, number_format_i18n( $count ) ) )
		);
	}

	/**
	 * Let WP strip the notice args from the URL after the notice is shown, so
	 * a page refresh doesn't repeat a stale count.
	 *
	 * @since 10.9.0
	 * @param array $args Query args WP removes via the referer polyfill.
	 * @return array
	 */
	public function register_removable_query_args( $args ) {
		$args[] = self::NOTICE_ARG_EXCLUDED;
		$args[] = self::NOTICE_ARG_INCLUDED;
		return $args;
	}

	/**
	 * URL of the Products list pre-filtered to excluded products.
	 *
	 * Single source for the filter query var, so links elsewhere (settings page)
	 * can't drift from the dropdown's key/value.
	 *
	 * @since 10.9.0
	 * @return string
	 */
	public static function get_excluded_products_url(): string {
		return admin_url( 'edit.php?post_type=product&' . self::FILTER_QUERY_VAR . '=excluded' );
	}

	/**
	 * Add the sync-status column to the Products list table.
	 *
	 * @since 10.9.0
	 * @param array $columns Registered columns.
	 * @return array
	 */
	public function register_column( $columns ) {
		$columns[ self::COLUMN_KEY ] = __( 'Agentic Commerce', 'woocommerce-gateway-stripe' );
		return $columns;
	}

	/**
	 * Render the sync-status column for a row. Display only — the quick-edit
	 * script reads its state from the inline-data div, not this column, so a
	 * plugin unsetting the column can't break quick edit.
	 *
	 * @since 10.9.0
	 * @param string $column  Column key being rendered.
	 * @param int    $post_id Row's post ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ): void {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		$product = wc_get_product( (int) $post_id );
		if ( ! WC_Stripe_Agentic_Commerce_Product_Exclusion::supports( $product ) ) {
			echo '<span aria-hidden="true">&mdash;</span>';
			return;
		}

		if ( WC_Stripe_Agentic_Commerce_Product_Exclusion::is_excluded( $product ) ) {
			esc_html_e( 'Excluded', 'woocommerce-gateway-stripe' );
			return;
		}

		// The feed query only selects published products, so anything else is
		// never exported regardless of the flag or the eligibility verdict.
		if ( 'publish' !== $product->get_status() ) {
			printf(
				'<span title="%s">%s</span>',
				esc_attr__( 'Only published products are synced.', 'woocommerce-gateway-stripe' ),
				esc_html__( 'Not synced', 'woocommerce-gateway-stripe' )
			);
			return;
		}

		// The exclude toggle is off, but the full eligibility verdict can still
		// say no — sync rules like catalog visibility, or a third-party filter.
		// Show that as its own state so "Synced" is never a lie; the quick-edit
		// checkbox is unaffected because it reads the toggle alone from the
		// inline-data div.
		if ( ! WC_Stripe_Agentic_Commerce_Product_Mapper::should_sync_product( $product ) ) {
			printf(
				'<span title="%s">%s</span>',
				esc_attr__( 'Kept out of the catalog by sync eligibility rules, not by the exclude toggle.', 'woocommerce-gateway-stripe' ),
				esc_html__( 'Not synced', 'woocommerce-gateway-stripe' )
			);
			return;
		}

		esc_html_e( 'Synced', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Emit the row's exclude state into WP's hidden `#inline_{id}` div.
	 *
	 * This is the quick-edit script's data source: rendered only for supported
	 * types, so a missing div tells the script to hide and disable the
	 * checkbox for that row.
	 *
	 * @since 10.9.0
	 * @param WP_Post      $post             Row's post.
	 * @param WP_Post_Type $post_type_object Post type of the current screen.
	 * @return void
	 */
	public function render_inline_data( $post, $post_type_object ): void {
		if ( ! $post_type_object instanceof WP_Post_Type || 'product' !== $post_type_object->name ) {
			return;
		}

		$product = wc_get_product( $post instanceof WP_Post ? $post->ID : 0 );
		if ( ! WC_Stripe_Agentic_Commerce_Product_Exclusion::supports( $product ) ) {
			return;
		}

		printf(
			'<div class="%s">%s</div>',
			esc_attr( self::INLINE_DATA_CLASS ),
			WC_Stripe_Agentic_Commerce_Product_Exclusion::is_excluded( $product ) ? 'yes' : 'no'
		);
	}

	/**
	 * Render the sync-status filter dropdown above the Products list table.
	 *
	 * @since 10.9.0
	 * @param string $post_type Current screen's post type.
	 * @return void
	 */
	public function render_sync_status_filter( $post_type ): void {
		if ( 'product' !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
		$current = isset( $_GET[ self::FILTER_QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER_QUERY_VAR ] ) ) : '';

		printf(
			'<select name="%s"><option value="">%s</option><option value="synced"%s>%s</option><option value="excluded"%s>%s</option></select>',
			esc_attr( self::FILTER_QUERY_VAR ),
			esc_html__( 'Filter by Agentic Commerce', 'woocommerce-gateway-stripe' ),
			selected( 'synced', $current, false ),
			esc_html__( 'Not excluded', 'woocommerce-gateway-stripe' ),
			selected( 'excluded', $current, false ),
			esc_html__( 'Excluded products', 'woocommerce-gateway-stripe' )
		);
	}

	/**
	 * Narrow the Products list query by the exclude toggle.
	 *
	 * The toggle is the only state that exists as queryable meta; the fuller
	 * eligibility verdict the column shows is computed per row at render time
	 * and cannot be expressed as SQL. Hence the option label "Not excluded"
	 * rather than "Synced".
	 *
	 * @since 10.9.0
	 * @param WP_Query $query Query being prepared.
	 * @return void
	 */
	public function filter_products_by_sync_status( $query ): void {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		if ( 'product' !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter.
		$status = isset( $_GET[ self::FILTER_QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER_QUERY_VAR ] ) ) : '';
		if ( 'synced' !== $status && 'excluded' !== $status ) {
			return;
		}

		$meta_key = WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key();

		if ( 'excluded' === $status ) {
			$clause = [
				'key'   => $meta_key,
				'value' => 'yes',
			];
		} else {
			// Unset meta means "synced": the flag is only written once a product
			// has been toggled at least once.
			$clause = [
				'relation' => 'OR',
				[
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $meta_key,
					'value'   => 'yes',
					'compare' => '!=',
				],
			];

			// Drafts and unsupported types can never sync regardless of the
			// toggle, so even the "Not excluded" view keeps them out rather
			// than padding the results with rows the feed will never export.
			$tax_query   = $query->get( 'tax_query' );
			$tax_query   = is_array( $tax_query ) ? $tax_query : [];
			$tax_query[] = [
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => WC_Stripe_Agentic_Commerce_Product_Exclusion::get_supported_types(),
			];
			$query->set( 'tax_query', $tax_query );
			$query->set( 'post_status', 'publish' );
		}

		// The unset query var is '' — normalize without wrapping that into [ '' ].
		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : [];
		$meta_query[] = $clause;
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Render the exclude checkbox inside the quick-edit form.
	 *
	 * Fires once per custom column in the quick-edit template row; the actual
	 * checked state is applied per product by the quick-edit script, since WP
	 * clones this template fresh for every row.
	 *
	 * @since 10.9.0
	 * @param string $column_name Column the quick-edit box is rendered for.
	 * @param string $post_type   Current post type.
	 * @return void
	 */
	public function render_quick_edit_field( $column_name, $post_type ): void {
		if ( self::COLUMN_KEY !== $column_name || 'product' !== $post_type ) {
			return;
		}

		// The hidden marker travels with the checkbox so the save handler can
		// tell "field never rendered" (skip) from "checkbox unchecked" (clear
		// the flag) — an unchecked checkbox is simply absent from the POST.
		// Both ship disabled: the quick-edit script enables them only after it
		// has populated the checkbox from the row's real state, so if the
		// script never runs (deregistered, or quick edit replaced by another
		// plugin) the untouched fields stay out of the POST instead of
		// submitting an always-unchecked box that would clear a stored flag.
		printf(
			'<fieldset class="inline-edit-col-right"><div class="inline-edit-col"><label class="alignleft"><input type="hidden" name="%1$s" value="1" disabled><input type="checkbox" name="%2$s" value="yes" disabled><span class="checkbox-title">%3$s</span></label></div></fieldset>',
			esc_attr( self::quick_edit_marker_field() ),
			esc_attr( WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key() ),
			esc_html__( 'Exclude from the Stripe Agentic Commerce catalog sync', 'woocommerce-gateway-stripe' )
		);
	}

	/**
	 * POST field marking that the quick-edit exclude field was rendered and
	 * enabled for the submitted row.
	 *
	 * @since 10.9.0
	 * @return string
	 */
	private static function quick_edit_marker_field(): string {
		return WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key() . '_present';
	}

	/**
	 * Persist the quick-edit checkbox. Runs on `woocommerce_product_quick_edit_save`,
	 * which WooCommerce fires only after verifying its quick-edit nonce and WP has
	 * vetted the user's capability for this post.
	 *
	 * @since 10.9.0
	 * @param mixed $product Product being quick-edited (hook input, validated here).
	 * @return void
	 */
	public function save_quick_edit( $product ): void {
		// Defensive: init() only hooks this when the feature is on, but the
		// callback is public, so re-check before an absent checkbox could clobber
		// a stored 'yes'.
		if ( ! WC_Stripe_Agentic_Commerce_Integration::is_merchant_enabled() ) {
			return;
		}

		// Mirror the meta box's type gate so quick edit can't clobber a stored
		// 'yes' on a type the checkbox never renders a state for.
		if ( ! WC_Stripe_Agentic_Commerce_Product_Exclusion::supports( $product ) ) {
			return;
		}

		// No marker means the field never made it into the submission — the
		// quick-edit script never enabled it (unsupported type, script not
		// run), or another plugin removed the column so the field never
		// rendered. Treating that like an unchecked checkbox would clobber a
		// stored 'yes'.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC verified its quick-edit nonce before firing this hook.
		if ( ! isset( $_POST[ self::quick_edit_marker_field() ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC verified its quick-edit nonce before firing this hook.
		$meta_key     = WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key();
		$posted_value = isset( $_POST[ $meta_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) : '';
		$excluded     = 'yes' === $posted_value;

		$changed = WC_Stripe_Agentic_Commerce_Product_Exclusion::set_excluded( $product->get_id(), $excluded );

		// Request immediate sync with Stripe instead of waiting for the next scheduled sync.
		if ( $changed ) {
			( new WC_Stripe_Agentic_Commerce_Integration() )->schedule_full_resync_now();
		}
	}

	/**
	 * Enqueue the quick-edit state script on the Products list screen.
	 *
	 * @since 10.9.0
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ): void {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'wc-stripe-agentic-product-quick-edit',
			plugins_url( 'assets/js/stripe-agentic-product-quick-edit' . $suffix . '.js', WC_STRIPE_MAIN_FILE ),
			[ 'jquery', 'inline-edit-post' ],
			WC_STRIPE_VERSION,
			true
		);

		// Without an explicit width the fixed-layout list table can crush the
		// column until its text wraps vertically at larger font sizes.
		wp_add_inline_style(
			'list-tables',
			'.wp-list-table .column-' . self::COLUMN_KEY . ' { width: 10%; }'
		);
	}
}
