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
	 * It will return the updated token if it already exists, otherwise it will create one from the legacy token.
	 * If no legacy token is found, it will return the default token for the customer.
	 *
	 * @param string  $source_id The Source or Payment Method ID associated with the subscription.
	 * @param integer $user_id   The WordPress User ID to whom the subscription belongs.
	 * @throws \Exception If no replacement token is found.
	 * @return WC_Payment_Token
	 */
	private function get_updated_sepa_token_by_source_id( string $source_id, int $user_id ): WC_Payment_Token {
		// It's possible that a token using the updated gateway ID already exists. Retrieve it if so.
		// We create these when the shopper vistis the Checkout and the My account > Payment methods page.
		$updated_token = $this->get_subscription_token(
			$source_id,
			$user_id,
			WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID
		);

		// Return the updated token if we already have it.
		if ( $updated_token ) {
			return $updated_token;
		}

		// Retrieve the legacy SEPA token used for the subscription.
		$legacy_token = $this->get_subscription_token(
			$source_id,
			$user_id,
			WC_Gateway_Stripe_Sepa::ID
		);

		if ( $legacy_token ) {
			// Create an updated token from the legacy SEPA one, using the UPE SEPA gateway.
			$updated_token = $this->create_updated_token_from_legacy( $legacy_token );
		} else {
			// Use the default token for the customer when we can't find the associated legacy SEPA one.
			$updated_token = $this->get_customer_default_token( $user_id );
		}

		// We can't proceed with updating the subscription if we don't have a token to use.
		if ( ! $updated_token ) {
			throw new \Exception( sprintf( '---- Skipping migration of subscription #%d. No replacement token was found.', $subscription_id ) );
		}

		return $updated_token;
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

	/**
	 * Creates an updated token using the UPE SEPA gateway from the legacy SEPA token.
	 *
	 * @param WC_Payment_Token $legacy_token The legacy token from which the updated one will be created.
	 * @return WC_Payment_Token The updated token.
	 */
	private function create_updated_token_from_legacy( WC_Payment_Token $legacy_token ): WC_Payment_Token {
		$updated_token = new WC_Payment_Token_SEPA();

		$updated_token->set_last4( $legacy_token->get_last4() );
		$updated_token->set_payment_method_type( $legacy_token->get_type() );
		$updated_token->set_gateway_id( $legacy_token->get_gateway_id() );
		$updated_token->set_token( $legacy_token->get_token() );
		$updated_token->set_user_id( $legacy_token->get_user_id() );

		$updated_token->save();

		return $updated_token;
	}

	/**
	 * Returns the default token associated with the given customer.
	 *
	 * @param int $customer_id The customer's WordPress User ID for whom we want to retrieve the default token.
	 * @return WC_Payment_Token|false
	 */
	private function get_customer_default_token( int $customer_id ) {
		$customer_tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id );

		foreach ( $customer_tokens as $token ) {
			if ( $token->is_default() ) {
				return $token;
			}
		}

		return false;
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
		$subscription->set_payment_method_title( $token->get_title() );

		$subscription->save();
	}
}
