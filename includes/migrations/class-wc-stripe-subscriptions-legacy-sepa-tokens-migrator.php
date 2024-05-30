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

			// The tokens for the Legacy SEPA gateway aren't available when the Legacy experience is disabled.
			// Let's retrieve a token that can be used instead.
			$updated_token = $this->get_updated_sepa_token_by_source_id( $source_id, $user_id );

			$this->set_subscription_updated_payment_method( $subscription, $updated_token );
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
		// This is what we do in WC_Stripe_Subscriptions_Trait::maybe_update_source_on_subscription_order,
		// but shouldn't we use WCS_Payment_Tokens::update_subscription_token() instead? Or WC_Order::add_payment_token()?
		// The latter is the one used by WooPayments.

		// Add a meta to the subscription to flag that its token got updated.
		$subscription->update_meta_data( self::LEGACY_TOKEN_PAYMENT_METHOD_META_KEY, WC_Gateway_Stripe_Sepa::ID );
		$subscription->update_meta_data( self::SOURCE_ID_META_KEY, $token->get_token() );

		$subscription->set_payment_method( $token->get_gateway_id() );

		$subscription->save();
	}
}
