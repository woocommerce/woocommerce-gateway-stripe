<?php
/**
 * Class WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Migrator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles migrating the tokens of Subscriptions using SEPA's Legacy gateway ID.
 *
 * This class extends the WCS_Background_Repairer for scheduling and running the individual migration actions.
 */
class WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Migrator extends WCS_Background_Repairer {

	/**
	 * Subscription meta key used to store the payment method used before migration.
	 *
	 * @var string
	 */
	const LEGACY_TOKEN_PAYMENT_METHOD_META_KEY = '_migrated_sepa_payment_method';

	/**
	 * WC_Logger instance.
	 *
	 * @todo Use WC_Stripe_Logger?
	 *
	 * @var WC_Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = wc_get_logger();
	}

	/**
	 * Conditionally calls the method to update the payment method of the subscription.
	 *
	 * We validate whether it must be updated first.
	 *
	 * @param int $subscription_id ID of the subscription to be processed.
	 */
	public function maybe_update_subscription_legacy_payment_method( $subscription_id ) {
		try {
			// No need to update the tokens if Legacy is enabled.
			if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
				return;
			}

			$this->logger->info( sprintf( 'Migrating subscription #%1$d.', $subscription_id ) );

			$subscription = $this->get_subscription_to_migrate( $subscription_id );

			$this->update_subscription_legacy_payment_method( $subscription );
		} catch ( \Exception $e ) {
			$this->logger->info( $e->getMessage() );
		}
	}

	/**
	 * Gets the subscription to migrate if it should be migrated.
	 *
	 * Only allows migration if:
	 * - The WooCommerce Subscription extension is active
	 * - The subscription ID is a valid subscription
	 * - The subscription has not already been migrated
	 *
	 * @param int $subscription_id The ID of the subscription to migrate.
	 * @return WC_Subscription The Subscription object for which its token must be updated.
	 * @throws \Exception Skip the migration if the request is invalid.
	 */
	private function get_subscription_to_migrate( $subscription_id ) {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. The WooCommerce Subscriptions extension is not active.', $subscription_id ) );
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. Subscription not found.', $subscription_id ) );
		}

		$migrated_legacy_token_id = $subscription->get_meta( self::LEGACY_TOKEN_PAYMENT_METHOD_META_KEY, true );

		if ( ! empty( $migrated_legacy_token_id ) ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%1$d (%2$s). Token has already been updated.', $subscription_id, $migrated_legacy_token_id ) );
		}

		return $subscription;
	}

	/**
	 * Updates the payment method for the subscription.
	 *
	 * @param WC_Subscription $subscription Subscriptions for which the payment method must be updated.
	 */
	private function update_subscription_legacy_payment_method( WC_Subscription $subscription ) {
		$sepa_gateway_id = WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID;

		// Update the payment method associated with the subscription.
		$subscription->set_payment_method( $sepa_gateway_id );

		// Add a meta to the subscription to flag that its token got updated.
		$subscription->update_meta_data( self::LEGACY_TOKEN_PAYMENT_METHOD_META_KEY, WC_Gateway_Stripe_Sepa::ID );

		$subscription->save();
	}

	/**
	 * Gets the batch of subscriptions using the Legacy SEPA payment method to be updated.
	 *
	 * @param int $page The page of results to fetch.
	 *
	 * @return int[] The IDs of the subscriptions to migrate.
	 */
	public function get_items_to_repair( $page ) {
		$items_to_repair = wc_get_orders(
			[
				'return'         => 'ids',
				'type'           => 'shop_subscription',
				'limit'          => 100,
				'status'         => 'any',
				'paged'          => $page,
				'payment_method' => WC_Gateway_Stripe_Sepa::ID,
				'order'          => 'ASC',
				'orderby'        => 'ID',
			]
		);

		if ( empty( $items_to_repair ) ) {
			$this->logger->info( 'Finished scheduling subscription migrations.' );
		}

		return $items_to_repair;
	}

	/**
	 * Triggers the conditional payment method update for the given subscription.
	 *
	 * This is the callback for the repair hook. Used as a wrapper for the method that does the actual processing.
	 *
	 * @param int $subscription_id ID of the subscription to be processed.
	 */
	public function repair_item( $subscription_id ) {
		$this->maybe_update_subscription_legacy_payment_method( $subscription_id );
	}
}
