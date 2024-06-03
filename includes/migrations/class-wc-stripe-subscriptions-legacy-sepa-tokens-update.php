<?php
/**
 * Class WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles migrating the tokens of Subscriptions using SEPA's Legacy gateway ID.
 *
 * We have two different payment gateway IDs for SEPA depending on whether we use the Legacy experience:
 *   - stripe_sepa, when the Legacy experience is enabled (Sources API).
 *   - stripe_sepa_debit, when the Updated experience is enabled (Payment Methods API).
 *
 * When purchasing a subscription using the Legacy experience (Sources API), we set stripe_sepa as the associated payment method gateway.
 * When purchasing a subscription using the Updated experience (Payment Methods API), we set stripe_sepa_debit as the associated payment method gateway.
 *
 * Because we use a different payment gateway ID when disabling the Legacy experience (switching to the Payment Methods API),
 * WooCommerce detects that the stripe_sepa payment gateway as no longer available.
 * This causes the Subscription to change to Manual renewal, and automatic renewals to fail.
 *
 * This class fixes failing automatic renewals by:
 *   - Retrieving all the subscriptions that are using the stripe_sepa payment gateway.
 *   - Iterating over each subscription.
 *   - Retrieving an Updated (Payment Methods API) token based on the Legacy (Sources API) token associated with the subscription.
 *       - If none is found, we create a new Updated (Payment Methods API) token based on the Legacy (Sources API) token.
 *       - If it can't be created, we use the default SEPA token for the customer if available.
 *       - If a default SEPA token doesn't exist, we skip the migration.
 *   - Associating this replacement token to the subscription.
 *
 * This class extends the WCS_Background_Repairer for scheduling and running the individual migration actions.
 */
class WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update extends WCS_Background_Repairer {

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
	 * Constructor
	 *
	 * @param WC_Logger_Interface $logger The WC_Logger instance.
	 */
	public function __construct( WC_Logger_Interface $logger ) {
		$this->logger = $logger;

		$this->scheduled_hook = 'wc_stripe_schedule_subscriptions_legacy_sepa_token_repairs';
		$this->repair_hook    = 'wc_stripe_subscriptions_legacy_sepa_token_repair';
		$this->log_handle     = 'woocommerce-gateway-stripe-subscriptions-legacy-sepa-tokens-repairs';
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
			! WC_Stripe_Feature_Flags::is_upe_checkout_enabled()
		) {
			return;
		}

		// Schedule the repair without checking if there are subscriptions to be migrated.
		// This will be handled in the scheduled action.
		$this->init();
		$this->schedule_repair();
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
				'paged'          => $page,
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
	 * Conditionally calls the method to update the payment method of the subscription.
	 *
	 * We validate whether it must be updated first.
	 *
	 * @param int $subscription_id ID of the subscription to be processed.
	 */
	private function maybe_update_subscription_legacy_payment_method( $subscription_id ) {
		try {
			$this->log( sprintf( 'Migrating subscription #%1$d.', $subscription_id ) );

			$subscription = $this->get_subscription_to_migrate( $subscription_id );
			$source_id    = $subscription->get_meta( self::SOURCE_ID_META_KEY );
			$user_id      = $subscription->get_user_id();

			// The tokens for the Legacy SEPA gateway aren't available when the Legacy experience is disabled.
			// Let's retrieve a token that can be used instead.
			$updated_token = $this->get_updated_sepa_token_by_source_id( $source_id, $user_id );

			$this->set_subscription_updated_payment_method( $subscription, $updated_token );
		} catch ( \Exception $e ) {
			$this->log( $e->getMessage() );
		}
	}

	/**
	 * Gets the subscription to update.
	 *
	 * Only allows migration if:
	 * - The Legacy experience is disabled
	 * - The WooCommerce Subscription extension is active
	 * - The subscription ID is a valid subscription
	 *
	 * @param int $subscription_id The ID of the subscription to migrate.
	 * @return WC_Subscription The Subscription object for which its token must be updated.
	 * @throws \Exception Skip the migration if the request is invalid.
	 */
	private function get_subscription_to_migrate( $subscription_id ) {
		if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. The Legacy experience is enabled.', $subscription_id ) );
		}

		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. The WooCommerce Subscriptions extension is not active.', $subscription_id ) );
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. Subscription not found.', $subscription_id ) );
		}

		return $subscription;
	}

	/**
	 * Returns an updated token to be used for the subscription, given the source ID.
	 *
	 * If no token is found, it will return the default token for the customer.
	 *
	 * @param string  $source_id The Source or Payment Method ID associated with the subscription.
	 * @param integer $user_id   The WordPress User ID to whom the subscription belongs.
	 * @throws \Exception If no replacement token is found.
	 * @return WC_Payment_Token
	 */
	private function get_updated_sepa_token_by_source_id( string $source_id, int $user_id ): WC_Payment_Token {
		$default_token           = false;
		$updated_sepa_gateway_id = WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID;

		// This method creates an updated token behind the scenes if it doesn't exist.
		$customer_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, $updated_sepa_gateway_id );

		foreach ( $customer_tokens as $token ) {
			// Return the token once we find it.
			if ( $source_id === $token->get_token() ) {
				return $token;
			}

			// Let's store the default token in case we don't find the one we're looking for.
			if ( $token->is_default() ) {
				$default_token = $token;
			}
		}

		// We can't proceed with updating the subscription if we don't have a token to use.
		if ( ! $default_token ) {
			throw new \Exception( '---- Skipping migration of subscription. No replacement token was found.' );
		}

		return $default_token;
	}

	/**
	 * Sets the updated payment method for the subscription.
	 *
	 * @param WC_Subscription  $subscription Subscription for which the payment method must be updated.
	 * @param WC_Payment_Token $token        The token to be set as the payment method for the subscription.
	 */
	private function set_subscription_updated_payment_method( WC_Subscription $subscription, WC_Payment_Token $token ) {
		// Add a meta to the subscription to flag that its token got updated.
		$subscription->update_meta_data( self::LEGACY_TOKEN_PAYMENT_METHOD_META_KEY, WC_Gateway_Stripe_Sepa::ID );
		$subscription->update_meta_data( self::SOURCE_ID_META_KEY, $token->get_token() );
		$subscription->set_payment_method( $token->get_gateway_id() );

		$subscription->save();
	}
}
