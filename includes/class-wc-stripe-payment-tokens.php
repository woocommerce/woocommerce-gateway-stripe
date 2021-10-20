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
	 * Extract the payment token from the provided request.
	 *
	 * TODO: Once php requirement is bumped to >= 7.1.0 set return type to ?\WC_Payment_Token
	 * since the return type is nullable, as per
	 * https://www.php.net/manual/en/functions.returning-values.php#functions.returning-values.type-declaration
	 *
	 * @param array $request Associative array containing payment request information.
	 *
	 * @return \WC_Payment_Token|NULL
	 */
	public static function get_token_from_request( array $request ) {
		$payment_method    = ! is_null( $request['payment_method'] ) ? $request['payment_method'] : null;
		$token_request_key = 'wc-' . $payment_method . '-payment-token';
		if (
			! isset( $request[ $token_request_key ] ) ||
			'new' === $request[ $token_request_key ]
			) {
			return null;
		}

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$token = \WC_Payment_Tokens::get( wc_clean( $request[ $token_request_key ] ) );

		// If the token doesn't belong to this gateway or the current user it's invalid.
		if ( ! $token || $payment_method !== $token->get_gateway_id() || $token->get_user_id() !== get_current_user_id() ) {
			return null;
		}

		return $token;
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
	 * Gets saved tokens from Stripe, if they don't already exist in WooCommerce.
	 *
	 * @param array  $tokens     Array of tokens
	 * @param string $user_id    WC User ID
	 * @param string $gateway_id WC Gateway ID
	 *
	 * @return array
	 */
	public function woocommerce_get_customer_payment_tokens( $tokens, $user_id, $gateway_id ) {
		if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return $this->woocommerce_get_customer_upe_payment_tokens( $tokens, $user_id, $gateway_id );
		} else {
			return $this->woocommerce_get_customer_payment_tokens_legacy( $tokens, $user_id, $gateway_id );
		}
	}

	/**
	 * Gets saved tokens from Sources API if they don't already exist in WooCommerce.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param array $tokens
	 * @return array
	 */
	public function woocommerce_get_customer_payment_tokens_legacy( $tokens, $customer_id, $gateway_id ) {
		if ( is_user_logged_in() && class_exists( 'WC_Payment_Token_CC' ) ) {
			$stored_tokens = [];

			foreach ( $tokens as $token ) {
				$stored_tokens[ $token->get_token() ] = $token;
			}

			if ( 'stripe' === $gateway_id ) {
				$stripe_customer = new WC_Stripe_Customer( $customer_id );
				$stripe_sources  = $stripe_customer->get_sources();

				foreach ( $stripe_sources as $source ) {
					if ( isset( $source->type ) && 'card' === $source->type ) {
						if ( ! isset( $stored_tokens[ $source->id ] ) ) {
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
						} else {
							unset( $stored_tokens[ $source->id ] );
						}
					} else {
						if ( ! isset( $stored_tokens[ $source->id ] ) && 'card' === $source->object ) {
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
						} else {
							unset( $stored_tokens[ $source->id ] );
						}
					}
				}
			}

			if ( 'stripe_sepa' === $gateway_id ) {
				$stripe_customer = new WC_Stripe_Customer( $customer_id );
				$stripe_sources  = $stripe_customer->get_sources();

				foreach ( $stripe_sources as $source ) {
					if ( isset( $source->type ) && 'sepa_debit' === $source->type ) {
						if ( ! isset( $stored_tokens[ $source->id ] ) ) {
							$token = new WC_Payment_Token_SEPA();
							$token->set_token( $source->id );
							$token->set_gateway_id( 'stripe_sepa' );
							$token->set_last4( $source->sepa_debit->last4 );
							$token->set_user_id( $customer_id );
							$token->save();
							$tokens[ $token->get_id() ] = $token;
						} else {
							unset( $stored_tokens[ $source->id ] );
						}
					}
				}
			}
		}

		return $tokens;
	}

	/**
	 * Gets saved tokens from Intentions API if they don't already exist in WooCommerce.
	 *
	 * @param array  $tokens     Array of tokens
	 * @param string $user_id    WC User ID
	 * @param string $gateway_id WC Gateway ID
	 *
	 * @return array
	 */
	public function woocommerce_get_customer_upe_payment_tokens( $tokens, $user_id, $gateway_id ) {
		if ( ( ! empty( $gateway_id ) && WC_Stripe_UPE_Payment_Gateway::ID !== $gateway_id ) || ! is_user_logged_in() ) {
			return $tokens;
		}

		if ( count( $tokens ) >= get_option( 'posts_per_page' ) ) {
			// The tokens data store is not paginated and only the first "post_per_page" (defaults to 10) tokens are retrieved.
			// Having 10 saved credit cards is considered an unsupported edge case, new ones that have been stored in Stripe won't be added.
			return $tokens;
		}

		$gateway                  = new WC_Stripe_UPE_Payment_Gateway();
		$reusable_payment_methods = array_filter( $gateway->get_upe_enabled_payment_method_ids(), [ $gateway, 'is_enabled_for_saved_payments' ] );
		$customer                 = new WC_Stripe_Customer( $user_id );
		$remaining_tokens         = [];

		foreach ( $tokens as $token ) {
			if ( WC_Stripe_UPE_Payment_Gateway::ID === $token->get_gateway_id() ) {
				$payment_method_type = $this->get_payment_method_type_from_token( $token );
				if ( ! in_array( $payment_method_type, $reusable_payment_methods, true ) ) {
					// Remove saved token from list, if payment method is not enabled.
					unset( $tokens[ $token->get_id() ] );
				} else {
					// Store relevant existing tokens here.
					// We will use this list to check whether these methods still exist on Stripe's side.
					$remaining_tokens[ $token->get_token() ] = $token;
				}
			}
		}

		$retrievable_payment_method_types = [];
		foreach ( $reusable_payment_methods as $payment_method_id ) {
			$upe_payment_method = $gateway->payment_methods[ $payment_method_id ];
			if ( ! in_array( $upe_payment_method->get_retrievable_type(), $retrievable_payment_method_types, true ) ) {
				$retrievable_payment_method_types[] = $upe_payment_method->get_retrievable_type();
			}
		}

		foreach ( $retrievable_payment_method_types as $payment_method_id ) {
			$payment_methods = $customer->get_payment_methods( $payment_method_id );

			// Prevent unnecessary recursion, WC_Payment_Token::save() ends up calling 'woocommerce_get_customer_payment_tokens' in some cases.
			remove_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );
			foreach ( $payment_methods as $payment_method ) {
				if ( ! isset( $remaining_tokens[ $payment_method->id ] ) ) {
					$payment_method_type = $this->get_original_payment_method_type( $payment_method );
					if ( ! in_array( $payment_method_type, $reusable_payment_methods, true ) ) {
						continue;
					}
					// Create new token for new payment method and add to list.
					$upe_payment_method         = $gateway->payment_methods[ $payment_method_type ];
					$token                      = $upe_payment_method->create_payment_token_for_user( $user_id, $payment_method );
					$tokens[ $token->get_id() ] = $token;
				} else {
					// Count that existing token for payment method is still present on Stripe.
					// Remaining IDs in $remaining_tokens no longer exist with Stripe and will be eliminated.
					unset( $remaining_tokens[ $payment_method->id ] );
				}
			}
			add_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );
		}

		// Eliminate remaining payment methods no longer known by Stripe.
		// Prevent unnecessary recursion, when deleting tokens.
		remove_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );
		foreach ( $remaining_tokens as $token ) {
			unset( $tokens[ $token->get_id() ] );
			$token->delete();
		}
		add_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );

		return $tokens;
	}

	/**
	 * Returns original type of payment method from Stripe payment method response,
	 * after checking whether payment method is SEPA method generated from another type.
	 *
	 * @param object $payment_method Stripe payment method JSON object.
	 *
	 * @return string Payment method type/ID
	 */
	private function get_original_payment_method_type( $payment_method ) {
		if ( WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID === $payment_method->type ) {
			if ( ! is_null( $payment_method->sepa_debit->generated_from->charge ) ) {
				return $payment_method->sepa_debit->generated_from->charge->payment_method_details->type;
			}
			if ( ! is_null( $payment_method->sepa_debit->generated_from->setup_attempt ) ) {
				return $payment_method->sepa_debit->generated_from->setup_attempt->payment_method_details->type;
			}
		}
		return $payment_method->type;
	}

	/**
	 * Returns original Stripe payment method type from payment token
	 *
	 * @param WC_Payment_Token $payment_token WC Payment Token (CC or SEPA)
	 *
	 * @return string
	 */
	private function get_payment_method_type_from_token( $payment_token ) {
		$type = $payment_token->get_type();
		if ( 'CC' === $type ) {
			return 'card';
		} elseif ( 'sepa' === $type ) {
			return $payment_token->get_payment_method_type();
		} else {
			return $type;
		}
	}

	/**
	 * Controls the output for SEPA on the my account page.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param  array            $item         Individual list item from woocommerce_saved_payment_methods_list
	 * @param  WC_Payment_Token $payment_token The payment token associated with this method entry
	 * @return array                           Filtered item
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
		$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
		if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			if ( WC_Stripe_UPE_Payment_Gateway::ID === $token->get_gateway_id() ) {
				$stripe_customer->detach_payment_method( $token->get_token() );
			}
		} else {
			if ( 'stripe' === $token->get_gateway_id() || 'stripe_sepa' === $token->get_gateway_id() ) {
				$stripe_customer->delete_source( $token->get_token() );
			}
		}
	}

	/**
	 * Set as default in Stripe.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function woocommerce_payment_token_set_default( $token_id ) {
		$token           = WC_Payment_Tokens::get( $token_id );
		$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );

		if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			if ( WC_Stripe_UPE_Payment_Gateway::ID === $token->get_gateway_id() ) {
				$stripe_customer->set_default_payment_method( $token->get_token() );
			}
		} else {
			if ( 'stripe' === $token->get_gateway_id() || 'stripe_sepa' === $token->get_gateway_id() ) {
				$stripe_customer->set_default_source( $token->get_token() );
			}
		}
	}
}

new WC_Stripe_Payment_Tokens();
