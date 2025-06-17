<?php
/**
 * Class WC_Stripe_Subscriptions_Repairer_Legacy_SEPA_Tokens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles repairing the Subscriptions using SEPA's Legacy payment method.
 *
 * This class extends the WCS_Background_Repairer for scheduling and running the individual migration actions.
 */
class WC_Stripe_Subscriptions_Repairer_Legacy_SEPA_Tokens extends \WCS_Background_Repairer {

	const LEGACY_SEPA_SUBSCRIPTIONS_COUNT = 'woocommerce_stripe_subscriptions_with_legacy_sepa';

	/**
	 * The transient key used to store the progress of the repair.
	 *
	 * @var string
	 */
	private $action_progress_transient = 'wc_stripe_legacy_sepa_tokens_repair_progress';

	/**
	 * The transient key used to store whether we should display the notice to the user.
	 *
	 * @var string
	 */
	private $display_notice_transient = 'wc_stripe_legacy_sepa_tokens_repair_notice';

	/**
	 * The scheduled hook.
	 *
	 * @var string
	 */
	protected $scheduled_hook;

	/**
	 * The repair hook.
	 *
	 * @var string
	 */
	protected $repair_hook;

	/**
	 * Constructor
	 *
	 * @param WC_Logger_Interface $logger The WC_Logger instance.
	 */
	public function __construct( WC_Logger_Interface $logger ) {
		$this->logger = $logger;

		$this->scheduled_hook = 'wc_stripe_schedule_subscriptions_legacy_sepa_token_repairs';
		$this->repair_hook    = 'wc_stripe_subscriptions_legacy_sepa_token_repair';
		$this->log_handle     = 'woocommerce-gateway-stripe-subscriptions-legacy-sepa-tokens-repairs';

		// Repair subscriptions prior to renewal as a backstop. Hooked onto 0 to run before the actual renewal.
		add_action( 'woocommerce_scheduled_subscription_payment', [ $this, 'maybe_migrate_before_renewal' ], 0 );

		add_action( 'admin_notices', [ $this, 'display_admin_notice' ] );

		add_filter( 'woocommerce_debug_tools', [ $this, 'add_debug_tool' ] );
	}

	/**
	 * Conditionally schedules the repair of subscriptions using the Legacy SEPA payment method.
	 *
	 * Don't run if either of these conditions are met:
	 *    - The WooCommerce Subscriptions extension isn't active.
	 *    - The Legacy checkout experience is enabled (aka UPE is disabled).
	 */
	public function maybe_update() {
		if (
			! class_exists( 'WC_Subscriptions' ) ||
			! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ||
			'yes' === get_option( 'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated' )
		) {
			return;
		}

		// Schedule the repair without checking if there are subscriptions to be migrated.
		// This will be handled in the scheduled action.
		$this->schedule_repair();

		// Display the admin notice to inform the user that the repair is in progress. Limited to 3 days.
		set_transient( $this->display_notice_transient, 'yes', 3 * DAY_IN_SECONDS );

		// Prevent the repair from being scheduled again.
		update_option( 'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated', 'yes' );
	}

	/**
	 * Triggers the conditional payment method update for the given subscription.
	 *
	 * This is the callback for the repair hook.
	 *
	 * @param int $subscription_id ID of the subscription to be processed.
	 */
	public function repair_item( $subscription_id ) {
		try {
			$this->log( sprintf( 'Migrating subscription #%1$d.', $subscription_id ) );

			$token_updater = new WC_Stripe_Subscriptions_Legacy_SEPA_Token_Update();
			$token_updater->maybe_update_subscription_legacy_payment_method( $subscription_id );

			$this->log( sprintf( 'Successful migration of subscription #%1$d.', $subscription_id ) );

			delete_transient( $this->action_progress_transient );
		} catch ( \Exception $e ) {
			$this->log( $e->getMessage() );
		}
	}

	/**
	 * Schedules an individual action to migrate a subscription.
	 *
	 * Overrides the parent class function to make two changes:
	 * 1. Don't schedule an action if one already exists.
	 * 2. Schedules the migration to happen in two minutes instead of in one hour.
	 * 3. Delete the transient which stores the progress of the repair.
	 *
	 * @param int $item The ID of the subscription to migrate.
	 */
	protected function update_item( $item ) {
		if ( ! as_next_scheduled_action( $this->repair_hook, [ 'repair_object' => $item ] ) ) {
			as_schedule_single_action( gmdate( 'U' ) + ( 2 * MINUTE_IN_SECONDS ), $this->repair_hook, [ 'repair_object' => $item ] );
		}

		unset( $this->items_to_repair[ $item ] );
		delete_transient( $this->action_progress_transient );
	}

	/**
	 * Gets the batch of subscriptions using the Legacy SEPA payment method to be updated.
	 *
	 * @param int $page The page of results to fetch.
	 *
	 * @return int[] The IDs of the subscriptions to migrate.
	 */
	protected function get_items_to_repair( $page ) {
		$items_to_repair = wc_get_orders(
			[
				'return'         => 'ids',
				'type'           => 'shop_subscription',
				'posts_per_page' => 20,
				'status'         => 'any',
				'paged'          => $page,
				'payment_method' => WC_Gateway_Stripe_Sepa::ID,
				'order'          => 'ASC',
				'orderby'        => 'ID',
			]
		);

		if ( empty( $items_to_repair ) ) {
			$this->log( 'Finished scheduling subscription migrations.' );
		}

		return $items_to_repair;
	}

	/**
	 * Updates subscriptions which need updating prior to it renewing.
	 *
	 * This function is a backstop to prevent subscription renewals from failing if we haven't ran the repair yet.
	 *
	 * @param int $subscription_id The subscription ID which is about to renew.
	 */
	public function maybe_migrate_before_renewal( $subscription_id ) {
		if ( ! class_exists( 'WC_Subscriptions' ) || ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return;
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return;
		}

		// Run the full repair if the subscription is using the Legacy SEPA gateway ID.
		if ( $subscription->get_payment_method() === WC_Gateway_Stripe_Sepa::ID ) {
			$this->repair_item( $subscription_id );

			// Unschedule the repair action as it's no longer needed.
			as_unschedule_action( $this->repair_hook, [ 'repair_object' => $subscription_id ] );

			// Returning at this point because the source will be updated by the repair_item method called above.
			return;
		}

		// It's possible that the Legacy SEPA gateway ID was updated by the repairing above, but that the Stripe account
		// hadn't been migrated from src_ to pm_ at the time.
		// Thus, we keep checking if the associated payment method is a source in subsequent renewals.
		$subscription_source = $subscription->get_meta( '_stripe_source_id' );

		if ( 0 === strpos( $subscription_source, 'src_' ) ) {
			$token_updater = new WC_Stripe_Subscriptions_Legacy_SEPA_Token_Update();
			$token_updater->maybe_update_subscription_source( $subscription );
		}
	}

	/**
	 * Displays an admin notice to inform the user that the repair is in progress.
	 *
	 * This notice is displayed on the Subscriptions list table page and includes information about the progress of the repair.
	 * What % of the repair is complete, or when the next scheduled action is expected to run.
	 */
	public function display_admin_notice() {

		if ( ! class_exists( 'WC_Subscriptions' ) || ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return;
		}

		// Only display this on the subscriptions list table page.
		if ( ! $this->is_admin_subscriptions_list_table_screen() ) {
			return;
		}

		// The notice is only displayed for up to 3 days after disabling the setting.
		$display_notice = get_transient( $this->display_notice_transient ) === 'yes';

		if ( ! $display_notice ) {
			return;
		}

		// If there are no subscriptions to be migrated, remove the transient so we don't show the notice.
		// Don't return early so we can show the notice at least once.
		if ( ! $this->has_legacy_sepa_subscriptions() ) {
			delete_transient( $this->display_notice_transient );
		}

		$action_progress = $this->get_scheduled_action_counts();

		if ( ! $action_progress ) {
			return;
		}

		// If we're still in the process of scheduling jobs, show a note to the user.
		if ( (bool) as_next_scheduled_action( $this->scheduled_hook ) ) {
			// translators: %1$s: <strong> tag, %2$s: </strong> tag, %3$s: <i> tag. %4$s: </i> tag.
			$progress = sprintf( __( '%1$sProgress: %2$s %3$sWe are still identifying all subscriptions that require updating.%4$s', 'woocommerce-gateway-stripe' ), '<strong>', '</strong>', '<i>', '</i>' );
		} else {
			// All scheduled actions have run, so we're done.
			if ( 0 === absint( $action_progress['pending'] ) ) {
				// Remove the transient to prevent the notice from showing again.
				delete_transient( $this->display_notice_transient );
			}

			// Calculate the percentage of completed actions.
			$total_action_count = $action_progress['pending'] + $action_progress['complete'];
			$compete_percentage = $total_action_count ? floor( ( $action_progress['complete'] / $total_action_count ) * 100 ) : 0;

			// translators: %1$s: <strong> tag, %2$s: </strong> tag, %3$s: percentage complete.
			$progress = sprintf( __( '%1$sProgress: %2$s %3$s%% complete', 'woocommerce-gateway-stripe' ), '<strong>', '</strong>', $compete_percentage );
		}

		// Note: We're using a Subscriptions class to generate the admin notice, however, it's safe to use given the context of this class.
		$notice = new WCS_Admin_Notice( 'notice notice-warning is-dismissible' );
		$notice->set_html_content(
			'<h4>' . esc_html__( 'SEPA subscription update in progress', 'woocommerce-gateway-stripe' ) . '</h4>' .
			'<p>' . __( "We are currently updating customer subscriptions that use the legacy Stripe SEPA Direct Debit payment method. During this update, you may notice that some subscriptions appear as manual renewals. Don't worry—renewals will continue to process as normal. Please be aware this process may take some time.", 'woocommerce-gateway-stripe' ) . '</p>' .
			'<p>' . $progress . '</p>'
		);

		$notice->display();
	}

	/**
	 * Checks if the current screen is the subscriptions list table.
	 *
	 * @return bool True if the current screen is the subscriptions list table, false otherwise.
	 */
	private function is_admin_subscriptions_list_table_screen() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! is_object( $screen ) ) {
			return false;
		}

		// Check if we are on the subscriptions list table page in a HPOS or WP_Post context.
		return in_array( $screen->id, [ 'woocommerce_page_wc-orders--shop_subscription', 'edit-shop_subscription' ], true );
	}

	/**
	 * Fetches the number of pending and completed migration scheduled actions.
	 *
	 * @return array|bool The counts of pending and completed actions. False if the Action Scheduler store is not available.
	 */
	private function get_scheduled_action_counts() {
		$action_counts = get_transient( $this->action_progress_transient );

		// If the transient is not set, calculate the action counts.
		if ( false === $action_counts ) {
			$store = ActionScheduler::store();

			if ( ! $store ) {
				return false;
			}

			$action_counts = [
				'pending'  => (int) $store->query_actions(
					[
						'hook'   => $this->repair_hook,
						'status' => ActionScheduler_Store::STATUS_PENDING,
					],
					'count'
				),
				'complete' => (int) $store->query_actions(
					[
						'hook'   => $this->repair_hook,
						'status' => ActionScheduler_Store::STATUS_COMPLETE,
					],
					'count'
				),
			];

			set_transient( $this->action_progress_transient, $action_counts, 10 * MINUTE_IN_SECONDS );
		}

		return $action_counts;
	}

	/**
	 * Registers the repair tool for the Legacy SEPA token migration.
	 *
	 * @param array $tools The existing repair tools.
	 *
	 * @return array The updated repair tools.
	 */
	public function add_debug_tool( $tools ) {
		// We don't need to show the tool if the WooCommerce Subscriptions extension isn't active or the UPE checkout isn't enabled
		if ( ! class_exists( 'WC_Subscriptions' ) || ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return $tools;
		}

		// Don't show the tool if the repair is already in progress or there are no subscriptions to migrate.
		if ( (bool) as_next_scheduled_action( $this->scheduled_hook ) || (bool) as_next_scheduled_action( $this->repair_hook ) || ! $this->has_legacy_sepa_subscriptions() ) {
			return $tools;
		}

		$tools['stripe_legacy_sepa_tokens'] = [
			'name'     => __( 'Stripe Legacy SEPA Token Update', 'woocommerce-gateway-stripe' ),
			'desc'     => __( 'This will restart the legacy Stripe SEPA update process.', 'woocommerce-gateway-stripe' ),
			'button'   => __( 'Restart SEPA token update', 'woocommerce-gateway-stripe' ),
			'callback' => [ $this, 'restart_update' ],
		];

		return $tools;
	}

	/**
	 * Checks if there are subscriptions using the Legacy SEPA payment method.
	 *
	 * @return bool True if there are subscriptions using the Legacy SEPA payment method, false otherwise.
	 */
	private function has_legacy_sepa_subscriptions() {
		$cached_legacy_sepa_subscriptions_count = get_transient( self::LEGACY_SEPA_SUBSCRIPTIONS_COUNT );

		if ( false !== $cached_legacy_sepa_subscriptions_count ) {
			return $cached_legacy_sepa_subscriptions_count > 0;
		}

		$subscriptions       = wc_get_orders(
			[
				'return'         => 'ids',
				'type'           => 'shop_subscription',
				'status'         => 'any',
				'posts_per_page' => 1,
				'payment_method' => WC_Gateway_Stripe_Sepa::ID,
			]
		);
		$subscriptions_count = count( $subscriptions );

		set_transient( self::LEGACY_SEPA_SUBSCRIPTIONS_COUNT, $subscriptions_count, 12 * HOUR_IN_SECONDS );

		return $subscriptions_count > 0;
	}

	/**
	 * Restarts the legacy token update process.
	 */
	public function restart_update() {
		// Clear the option to allow the update to be scheduled again.
		delete_option( 'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated' );

		$this->maybe_update();
	}
}
