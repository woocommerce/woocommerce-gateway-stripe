<?php
/**
 * Class WC_Stripe_Subscriptions_Legacy_SEPA_Token_Update
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
 * This class updates the following for the given subscription:
 *   - The associated gateway ID to the one used for the updated checkout experience `stripe_sepa_debit`, so it doesn't switch to Manual Renewal.
 *   - The payment method used for renewals to the migrated pm_, if any.
 */
class WC_Stripe_Subscriptions_Legacy_SEPA_Token_Update {

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
	 * Gateway ID for the Updated SEPA payment method.
	 *
	 * @var string
	 */
	private $updated_sepa_gateway_id = WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID;

	/**
	 * Conditionally updates the payment method of a subscription and creates a new token for it.
	 *
	 * @param int $subscription_id The ID of the subscription to update.
	 * @throws \Exception When updating the payment method of the subscription was skipped.
	 */
	public function maybe_update_subscription_legacy_payment_method( $subscription_id ) {
		$subscription = $this->get_subscription_to_migrate( $subscription_id );

		// Update the subscription with the updated SEPA gateway ID.
		$this->set_subscription_updated_payment_gateway_id( $subscription );

		// Update the payment method to the migrated pm_.
		$this->maybe_update_subscription_source( $subscription );
	}

	/**
	 * Attempts to update the payment method for renewals from Sources to PaymentMethods.
	 *
	 * @param WC_Subscription $subscription The subscription for which the payment method must be updated.
	 */
	public function maybe_update_subscription_source( WC_Subscription $subscription ) {
		try {
			$this->set_subscription_updated_payment_method( $subscription );
			$subscription->add_order_note( __( 'Stripe Gateway: The payment method used for renewals was updated from Sources to PaymentMethods.', 'woocommerce-gateway-stripe' ) );
		} catch ( \Exception $e ) {
			WC_Stripe_Logger::log( $e->getMessage() );
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
	 * @param int $subscription_id The ID of the subscription to update.
	 * @return WC_Subscription An instance of the subscription to be updated.
	 * @throws \Exception When the subscription can't be updated.
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
	 * Updates the payment method used for renewals to the migrated pm_, if any.
	 *
	 * The subscription is using a source for renewals at this point.
	 * When the migration runs on the Stripe account, there will be a payment method (pm_) migrated from the source (src_).
	 * This method updates the subscription to use the migrated payment method (pm_) for renewals, if it exists.
	 *
	 * @param WC_Subscription $subscription The subscription to update.
	 * @throws \Exception When the subscription is already using a pm_ or its src_ hasn't been migrated to a pm_.
	 */
	private function set_subscription_updated_payment_method( WC_Subscription $subscription ) {
		$source_id = $subscription->get_meta( self::SOURCE_ID_META_KEY );

		// Bail out if the subscription is already using a pm_.
		if ( 0 !== strpos( $source_id, 'src_' ) ) {
			throw new \Exception( sprintf( 'The subscription is not using a Stripe Source for renewals.', $subscription->get_id() ) );
		}

		// Retrieve the source object from the API.
		$source_object = WC_Stripe_API::get_payment_method( $source_id );

		// Bail out, if the source object isn't expected to be migrated. eg Card sources are not migrated.
		if ( isset( $source_object->type ) && 'card' === $source_object->type ) {
			throw new \Exception( sprintf( 'Skipping migration of Source for subscription #%d. Source is a card.', $subscription->get_id() ) );
		}

		// Bail out if the src_ hasn't been migrated to pm_ yet.
		if ( ! isset( $source_object->metadata->migrated_payment_method ) ) {
			throw new \Exception( sprintf( 'The Source has not been migrated to PaymentMethods on the Stripe account.', $subscription->get_id() ) );
		}

		// Get the payment method ID that was migrated from the source.
		$migrated_payment_method_id = $source_object->metadata->migrated_payment_method;

		// And set it as the payment method for the subscription.
		$subscription->update_meta_data( self::SOURCE_ID_META_KEY, $migrated_payment_method_id );
		$subscription->save();
	}

	/**
	 * Sets the updated SEPA gateway ID for the subscription.
	 *
	 * @param WC_Subscription $subscription Subscription for which the payment method must be updated.
	 */
	private function set_subscription_updated_payment_gateway_id( WC_Subscription $subscription ) {
		// The subscription is not using the legacy SEPA gateway ID.
		if ( WC_Gateway_Stripe_Sepa::ID !== $subscription->get_payment_method() ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. Subscription is not using the legacy SEPA payment method.', $subscription->get_id() ) );
		}

		// Add a meta to the subscription to flag that its token got updated.
		$subscription->update_meta_data( self::LEGACY_TOKEN_PAYMENT_METHOD_META_KEY, WC_Gateway_Stripe_Sepa::ID );
		$subscription->set_payment_method( $this->updated_sepa_gateway_id );

		$subscription->save();
	}
}
