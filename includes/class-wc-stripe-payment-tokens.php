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

		add_filter( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );
		add_filter( 'woocommerce_payment_methods_list_item', [ $this, 'get_account_saved_payment_methods_list_item_sepa' ], 10, 2 );
		add_filter( 'woocommerce_get_credit_card_type_label', [ $this, 'normalize_sepa_label' ] );
		add_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );
		add_action( 'woocommerce_payment_token_set_default', [ $this, 'woocommerce_payment_token_set_default' ] );
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
	 * Normalizes the SEPA IBAN label on My Account page.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $label
	 * @return string $label
	 */
	public function normalize_sepa_label( $label ) {
		if ( 'sepa iban' === strtolower( $label ) ) {
			return 'SEPA IBAN';
		}

		return $label;
	}

	/**
	 * Checks if customer has saved payment methods.
	 *
	 * @since 4.1.0
	 * @param int $customer_id
	 * @return bool
	 */
	public static function customer_has_saved_methods( $customer_id ) {
		$gateways = [ 'stripe', 'stripe_sepa' ];

		if ( empty( $customer_id ) ) {
			return false;
		}

		$has_token = false;

		foreach ( $gateways as $gateway ) {
			$tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id, $gateway );

			if ( ! empty( $tokens ) ) {
				$has_token = true;
				break;
			}
		}

		return $has_token;
	}

	/**
	 * Creates and add a token to an user, based on the payment_method object
	 *
	 * @param   stdClass                                        $payment_method Stripe payment method instance to be added.
	 * @param   WC_Stripe_Customer                              $user User to attach payment method to.
	 * @return  WC_Payment_Token_CC|WC_Payment_Token_SEPA The WC object for the payment token.
	 */
	public function add_token_to_user( $payment_method, $user ) {
		// Clear cached payment methods.
		$user->clear_cache();

		// TODO: change 'sepa_debit' to the SEPA reference in the class that will be created to match Stripe's value
		// maybe we will need to update the SEPA implementation below as well, depending on how's the new class implementation
		if ( 'sepa_debit' === $payment_method->type ) {
			$token = new WC_Payment_Token_SEPA();
			$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
			$token->set_last4( $payment_method->sepa->last4 );
		} else {
			$payment_id = WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID;
			$token      = new WC_Payment_Token_CC();
			$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
			$token->set_expiry_month( $payment_method->$payment_id->exp_month );
			$token->set_expiry_year( $payment_method->$payment_id->exp_year );
			$token->set_card_type( strtolower( $payment_method->$payment_id->brand ) );
			$token->set_last4( $payment_method->$payment_id->last4 );
		}
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user->get_user_id() );
		$token->save();

		return $token;
	}

	/**
	 * Adds a payment method to a user.
	 *
	 * @param stdClass           $payment_method Payment method to be added.
	 * @param WC_Stripe_Customer $user User to attach payment method to.
	 * @return WC_Payment_Token_CC The newly created token.
	 */
	public function add_payment_method_to_user( $payment_method, $user ) {
		return $this->add_token_to_user( $payment_method, $user );
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
		if ( is_user_logged_in() && class_exists( 'WC_Payment_Token_CC' ) ) {
			$stored_tokens = [];

			foreach ( $tokens as $token ) {
				$stored_tokens[] = $token->get_token();
			}

			if ( 'stripe' === $gateway_id ) {
				$stripe_customer = new WC_Stripe_Customer( $customer_id );
				$stripe_sources  = $stripe_customer->get_sources();

				foreach ( $stripe_sources as $source ) {
					if ( isset( $source->type ) && 'card' === $source->type ) {
						if ( ! in_array( $source->id, $stored_tokens ) ) {
							$token = new WC_Payment_Token_CC();
							$token->set_token( $source->id );
							$token->set_gateway_id( 'stripe' );

							if ( 'source' === $source->object && 'card' === $source->type ) {
								$token->set_card_type( strtolower( $source->card->brand ) );
								$token->set_last4( $source->card->last4 );
								$token->set_expiry_month( $source->card->exp_month );
								$token->set_expiry_year( $source->card->exp_year );
							}

							$token->set_user_id( $customer_id );
							$token->save();
							$tokens[ $token->get_id() ] = $token;
						}
					} else {
						if ( ! in_array( $source->id, $stored_tokens ) && 'card' === $source->object ) {
							$token = new WC_Payment_Token_CC();
							$token->set_token( $source->id );
							$token->set_gateway_id( 'stripe' );
							$token->set_card_type( strtolower( $source->brand ) );
							$token->set_last4( $source->last4 );
							$token->set_expiry_month( $source->exp_month );
							$token->set_expiry_year( $source->exp_year );
							$token->set_user_id( $customer_id );
							$token->save();
							$tokens[ $token->get_id() ] = $token;
						}
					}
				}
			}

			if ( 'stripe_sepa' === $gateway_id ) {
				$stripe_customer = new WC_Stripe_Customer( $customer_id );
				$stripe_sources  = $stripe_customer->get_sources();

				foreach ( $stripe_sources as $source ) {
					if ( isset( $source->type ) && 'sepa_debit' === $source->type ) {
						if ( ! in_array( $source->id, $stored_tokens ) ) {
							$token = new WC_Payment_Token_SEPA();
							$token->set_token( $source->id );
							$token->set_gateway_id( 'stripe_sepa' );
							$token->set_last4( $source->sepa_debit->last4 );
							$token->set_user_id( $customer_id );
							$token->save();
							$tokens[ $token->get_id() ] = $token;
						}
					}
				}
			}
		}

		return $tokens;
	}

	/**
	 * Controls the output for SEPA on the my account page.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param  array                 $item Individual list item from woocommerce_saved_payment_methods_list
	 * @param  WC_Payment_Token_SEPA $payment_token The payment token associated with this method entry
	 * @return array                 Filtered item
	 */
	public function get_account_saved_payment_methods_list_item_sepa( $item, $payment_token ) {
		if ( 'sepa' === strtolower( $payment_token->get_type() ) ) {
			$item['method']['last4'] = $payment_token->get_last4();
			$item['method']['brand'] = esc_html__( 'SEPA IBAN', 'woocommerce-gateway-stripe' );
		}

		return $item;
	}

	/**
	 * Delete token from Stripe.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function woocommerce_payment_token_deleted( $token_id, $token ) {
		if ( 'stripe' === $token->get_gateway_id() || 'stripe_sepa' === $token->get_gateway_id() ) {
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

		if ( 'stripe' === $token->get_gateway_id() || 'stripe_sepa' === $token->get_gateway_id() ) {
			$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
			$stripe_customer->set_default_source( $token->get_token() );
		}
	}
}

new WC_Stripe_Payment_Tokens();
