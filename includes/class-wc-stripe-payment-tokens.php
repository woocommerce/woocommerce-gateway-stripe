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
	 * List of reusable payment gateways by payment method.
	 *
	 * The keys are the possible values for the type of the PaymentMethod object in Stripe.
	 * https://docs.stripe.com/api/payment_methods/object#payment_method_object-type
	 *
	 * The values are the related gateway ID we use for them in the extension.
	 */
	const UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD = [
		WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID         => WC_Stripe_UPE_Payment_Gateway::ID,
		WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID       => WC_Stripe_UPE_Payment_Gateway::ID,
		WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID => WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
		WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID      => WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
		WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID       => WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
		WC_Stripe_UPE_Payment_Method_Sofort::STRIPE_ID     => WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sofort::STRIPE_ID,
	];

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

			try {
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

								if ( WC_Stripe_Helper::is_card_payment_method( $source ) ) {
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
			} catch ( WC_Stripe_Exception $e ) {
				wc_add_notice( $e->getLocalizedMessage(), 'error' );
				WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
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
		if (
			! is_user_logged_in() ||
			( ! empty( $gateway_id ) && ! in_array( $gateway_id, self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD, true ) )
		) {
			return $tokens;
		}

		if ( count( $tokens ) >= get_option( 'posts_per_page' ) ) {
			// The tokens data store is not paginated and only the first "post_per_page" (defaults to 10) tokens are retrieved.
			// Having 10 saved credit cards is considered an unsupported edge case, new ones that have been stored in Stripe won't be added.
			return $tokens;
		}

		try {
			$stored_tokens = [];

			$deprecated_tokens = [];

			foreach ( $tokens as $token ) {
				if ( in_array( $token->get_gateway_id(), self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD, true ) ) {

					// APM tokens from before Split PE was in place that will get removed.
					if ( 'stripe' === $token->get_gateway_id() && 'sepa' === $token->get_type() ) {
						$deprecated_tokens[ $token->get_token() ] = $token;
						continue;
					}

					$stored_tokens[ $token->get_token() ] = $token;
				}
			}

			$gateway  = WC_Stripe::get_instance()->get_main_stripe_gateway();
			$customer = new WC_Stripe_Customer( $user_id );

			// Retrieve the payment methods for the enabled reusable gateways.
			$payment_methods = [];
			foreach ( self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD as $payment_method_type => $reausable_gateway_id ) {

				// The payment method type doesn't match the ones we use. Nothing to do here.
				if ( ! isset( $gateway->payment_methods[ $payment_method_type ] ) ) {
					continue;
				}

				$payment_method_instance = $gateway->payment_methods[ $payment_method_type ];
				if ( $payment_method_instance->is_enabled() ) {
					$payment_methods[] = $customer->get_payment_methods( $payment_method_type );
				}
			}

			$payment_methods = array_merge( ...$payment_methods );

			// Prevent unnecessary recursion, WC_Payment_Token::save() ends up calling 'woocommerce_get_customer_payment_tokens' in some cases.
			remove_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );

			foreach ( $payment_methods as $payment_method ) {
				if ( ! isset( $payment_method->type ) ) {
					continue;
				}

				// Retrieve the real APM behind SEPA PaymentMethods.
				$payment_method_type = $this->get_original_payment_method_type( $payment_method );

				if (
					! isset( $stored_tokens[ $payment_method->id ] ) &&
					( $this->is_valid_payment_method_type_for_gateway( $payment_method_type, $gateway_id ) || empty( $gateway_id ) )
				) {
					$token                      = $this->add_token_to_user( $payment_method, $customer );
					$tokens[ $token->get_id() ] = $token;
				} else {
					unset( $stored_tokens[ $payment_method->id ] );
				}
			}
			add_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );

			remove_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );

			// Remove the payment methods that no longer exist in Stripe's side.
			foreach ( $stored_tokens as $token ) {
				unset( $tokens[ $token->get_id() ] );
				$token->delete();
			}

			// Remove the APM tokens from before Split PE was in place.
			foreach ( $deprecated_tokens as $token ) {
				unset( $tokens[ $token->get_id() ] );
				$token->delete();
			}

			add_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );

		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
		}

		return $tokens;
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
		try {
			if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
				if ( in_array( $token->get_gateway_id(), self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD, true ) ) {
					$stripe_customer->detach_payment_method( $token->get_token() );
				}
			} else {
				if ( 'stripe' === $token->get_gateway_id() || 'stripe_sepa' === $token->get_gateway_id() ) {
					$stripe_customer->delete_source( $token->get_token() );
				}
			}
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
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

		try {
			if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
				if ( WC_Stripe_UPE_Payment_Gateway::ID === $token->get_gateway_id() ) {
					$stripe_customer->set_default_payment_method( $token->get_token() );
				}
			} else {
				if ( 'stripe' === $token->get_gateway_id() || 'stripe_sepa' === $token->get_gateway_id() ) {
					$stripe_customer->set_default_source( $token->get_token() );
				}
			}
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Returns boolean value if payment method type matches relevant payment gateway.
	 *
	 * @param string $payment_method_type Stripe payment method type ID.
	 * @param string $gateway_id          WC Stripe gateway ID.
	 * @return bool                       True, if payment method type matches gateway, false if otherwise.
	 */
	private function is_valid_payment_method_type_for_gateway( $payment_method_type, $gateway_id ) {
		$reusable_gateway = self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $payment_method_type ];
		return self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $payment_method_type ] === $gateway_id;
	}

	/**
	 * Creates and add a token to an user, based on the PaymentMethod object.
	 *
	 * @param   array              $payment_method                              Payment method to be added.
	 * @param   WC_Stripe_Customer $user                                        WC_Stripe_Customer we're processing the tokens for.
	 * @return  WC_Payment_Token_CC|WC_Payment_Token_Link|WC_Payment_Token_SEPA The WC object for the payment token.
	 */
	private function add_token_to_user( $payment_method, WC_Stripe_Customer $customer ) {
		// Clear cached payment methods.
		$customer->clear_cache();

		$payment_method_type = $this->get_original_payment_method_type( $payment_method );
		$gateway_id          = self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $payment_method_type ];

		switch ( $payment_method_type ) {
			case WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID:
				$token = new WC_Payment_Token_CC();
				$token->set_expiry_month( $payment_method->card->exp_month );
				$token->set_expiry_year( $payment_method->card->exp_year );
				$token->set_card_type( strtolower( $payment_method->card->brand ) );
				$token->set_last4( $payment_method->card->last4 );
				break;

			case WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID:
				$token = new WC_Payment_Token_Link();
				$token->set_email( $payment_method->link->email );
				$token->set_payment_method_type( $payment_method_type );
				break;

			default:
				$token = new WC_Payment_Token_SEPA();
				$token->set_last4( $payment_method->sepa_debit->last4 );
				$token->set_payment_method_type( $payment_method_type );
		}

		$token->set_gateway_id( $gateway_id );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $customer->get_user_id() );
		$token->save();

		return $token;
	}

	/**
	 * Returns the original type of payment method from Stripe's PaymentMethod object.
	 *
	 * APMs like iDEAL, Bancontact, and Sofort get their PaymentMethod object type set to SEPA.
	 * This method checks the extra data within the PaymentMethod object to determine the
	 * original APM type that was used to create the PaymentMethod.
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
}

new WC_Stripe_Payment_Tokens();
