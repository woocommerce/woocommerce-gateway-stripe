<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles and process WC payment tokens API.
 * Seen in checkout page and my account->add payment method page.
 *
 * @since 4.0.0
 */
class WC_Stripe_Payment_Tokens {
	private static $_this;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		self::$_this = $this;

		add_filter( 'woocommerce_get_customer_payment_tokens', array( $this, 'woocommerce_get_customer_payment_tokens' ), 10, 3 );
		add_action( 'woocommerce_payment_token_deleted', array( $this, 'woocommerce_payment_token_deleted' ), 10, 2 );
		add_action( 'woocommerce_payment_token_set_default', array( $this, 'woocommerce_payment_token_set_default' ) );
	}

	/**
	 * Public access to instance object.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_instance() {
		return self::$_this;
	}

	/**
	 * Gets saved tokens from API if they don't already exist in WooCommerce.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param array $tokens
	 * @return array
	 */
	public function woocommerce_get_customer_payment_tokens( $tokens, $customer_id, $gateway_id ) {
		if ( is_user_logged_in() && 'stripe' === $gateway_id && class_exists( 'WC_Payment_Token_CC' ) ) {
			$stripe_customer = new WC_Stripe_Customer( $customer_id );
			$stripe_cards    = $stripe_customer->get_sources();
			$stored_tokens   = array();

			foreach ( $tokens as $token ) {
				$stored_tokens[] = $token->get_token();
			}

			foreach ( $stripe_cards as $card ) {
				if ( ! in_array( $card->id, $stored_tokens ) ) {
					$token = new WC_Payment_Token_CC();
					$token->set_token( $card->id );
					$token->set_gateway_id( 'stripe' );

					if ( 'three_d_secure' === $card->type ) {
						continue;
					}

					if ( 'source' === $card->object ) {
						$card = $card->card;
					}

					$token->set_card_type( strtolower( $card->brand ) );
					$token->set_last4( $card->last4 );
					$token->set_expiry_month( $card->exp_month );
					$token->set_expiry_year( $card->exp_year );
					$token->set_user_id( $customer_id );
					$token->save();
					$tokens[ $token->get_id() ] = $token;
				}
			}
		}

		return $tokens;
	}

	/**
	 * Delete token from Stripe.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function woocommerce_payment_token_deleted( $token_id, $token ) {
		if ( 'stripe' === $token->get_gateway_id() ) {
			$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
			$stripe_customer->delete_source( $token->get_token() );
		}
	}

	/**
	 * Set as default in Stripe.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function woocommerce_payment_token_set_default( $token_id ) {
		$token = WC_Payment_Tokens::get( $token_id );
		if ( 'stripe' === $token->get_gateway_id() ) {
			$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
			$stripe_customer->set_default_source( $token->get_token() );
		}
	}
}

new WC_Stripe_Payment_Tokens();
