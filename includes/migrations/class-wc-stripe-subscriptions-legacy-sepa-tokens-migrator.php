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
	 * Subscription meta key used to store the associated source ID.
	 *
	 * @var string
	 */
	const SOURCE_ID_META_KEY = '_stripe_source_id';

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
		$this->scheduled_hook = 'stripe_schedule_legacy_sepa_token_repairs';
		$this->repair_hook    = 'stripe_legacy_sepa_token_repair';

		$this->logger = wc_get_logger();
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
			$source_id    = $subscription->get_meta( self::SOURCE_ID_META_KEY );
			$user_id      = $subscription->get_user_id();

			// It's possible that a token using the updated gateway ID already exists.
			// We create these when the shopper vistis the Checkout and the My account > Payment methods page.
			$updated_token = $this->get_subscription_token(
				$source_id,
				$user_id,
				WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID
			);

			// Create an updated token from the legacy one.
			if ( ! $updated_token ) {
				$legacy_token = $this->get_subscription_token(
					$source_id,
					$user_id,
					WC_Gateway_Stripe_Sepa::ID
				);
			}

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
		// This is what we do in WC_Stripe_Subscriptions_Trait::maybe_update_source_on_subscription_order,
		// but shouldn't we use WCS_Payment_Tokens::update_subscription_token() instead? Or WC_Order::add_payment_token()?
		// The latter is the one used by WooPayments.
		$sepa_gateway_id = WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID;

		// Update the payment method associated with the subscription.
		$subscription->set_payment_method( $sepa_gateway_id );

		// Add a meta to the subscription to flag that its token got updated.
		$subscription->update_meta_data( self::LEGACY_TOKEN_PAYMENT_METHOD_META_KEY, WC_Gateway_Stripe_Sepa::ID );

		$subscription->save();
	}

	/**
	 * Returns the token associated with a subscription.
	 *
	 * @todo Is there a better way to retrieve these? WC_Order::get_payment_tokens() returns an empty array for some reason.
	 *
	 * @param string $subscription_source_id   The Source or Payment Method ID associated with the subscription.
	 * @param int    $subscription_customer_id The WordPress User ID to whom the subscription belongs.
	 * @param string $gateway_id               The ID of the payment gateway for which we're retrieving the tokens.
	 * @return WC_Payment_Token|false
	 */
	private function get_subscription_token( string $subscription_source_id, int $subscription_customer_id, string $gateway_id ) {

		// We specify the Gateway ID because
		// - The Legacy SEPA gateway would be unavailable and its tokens are not retrieved.
		// - The token value could be the same between two tokens with different gateway IDs,
		//   like stripe_sepa (The Legacy gateway) and stripe_sepa_debit (The Updated gateway).
		$customer_tokens = WC_Payment_Tokens::get_tokens(
			[
				'user_id'    => $subscription_customer_id,
				'gateway_id' => $gateway_id,
			]
		);

		foreach ( $customer_tokens as $token ) {
			if ( $subscription_source_id === $token->get_token() ) {
				return $token;
			}
		}

		return false;
	}
}
