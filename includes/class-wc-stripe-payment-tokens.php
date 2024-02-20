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

	const UPE_REUSABLE_GATEWAYS = [
		// Link payment methods are saved under the main Stripe gateway.
		WC_Stripe_UPE_Payment_Gateway::ID,
		WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
		WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
		WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
		WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sofort::STRIPE_ID,
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

		if ( ! is_user_logged_in() || ( ! empty( $gateway_id ) && ! in_array( $gateway_id, WC_Stripe_Helper::get_stripe_gateway_ids(), true ) ) ) {
			return $tokens;
		}

		if ( count( $tokens ) >= get_option( 'posts_per_page' ) ) {
			// The tokens data store is not paginated and only the first "post_per_page" (defaults to 10) tokens are retrieved.
			// Having 10 saved credit cards is considered an unsupported edge case, new ones that have been stored in Stripe won't be added.
			return $tokens;
		}

		try {
			$gateway  = WC_Stripe::get_instance()->get_main_stripe_gateway();
			$customer = new WC_Stripe_Customer( $user_id );

			// Payment methods that exist in Stripe.
			$stripe_payment_methods     = [];
			$stripe_payment_methods_ids = [];

			// List of the types already retrieved to avoid pulling redundant information.
			$types_retrieved_from_stripe = [];

			// IDs of the payment methods that exist locally.
			$locally_stored_payment_methods_ids = [];

			// 1. Check if there's any discrepancy between the locally saved payment methods and those saved on Stripe's side.
			// 2. If local payment methods are not found on Stripe's side, delete them.
			// 3. If payment methods are found on Stripe's side but not locally, create them.
			foreach ( $tokens as $token ) {
				$token_gateway_id = $token->get_gateway_id();

				// The gateway ID of the token doesn't belong to our gateways.
				if ( ! in_array( $token_gateway_id, self::UPE_REUSABLE_GATEWAYS, true ) ) {
					continue;
				}

				$payment_method_type = $this->get_payment_method_type_from_token( $token );

				// The payment method type doesn't match the ones we use. Nothing to do here.
				if ( ! isset( $gateway->payment_methods[ $payment_method_type ] ) ) {
					continue;
				}

				$payment_method_instance    = $gateway->payment_methods[ $payment_method_type ];
				$payment_method_instance_id = $payment_method_instance->id;

				// Card tokens are the only ones expected to have a mismatch between the token's gateway ID and the payment method instance ID.
				if (
					'stripe_card' === $token_gateway_id &&
					'card' !== $payment_method_instance_id &&
					$token_gateway_id !== $payment_method_instance_id
				) {
					continue;
				}

				// Don't display the payment method if the gateway isn't enabled.
				if ( ! $payment_method_instance->is_enabled() ) {
					unset( $tokens[ $token->get_id() ] );
					continue;
				}

				// Get the slug for the payment method type expected by the Stripe API.
				$payment_method_retrievable_type = $payment_method_instance->get_retrievable_type();

				// Avoid redundancy by only processing the payment methods for each type once.
				if ( ! in_array( $payment_method_retrievable_type, $types_retrieved_from_stripe, true ) ) {

					$payment_methods_for_type   = $customer->get_payment_methods( $payment_method_retrievable_type );
					$stripe_payment_methods     = array_merge( $stripe_payment_methods, $payment_methods_for_type );
					$stripe_payment_methods_ids = array_merge( $stripe_payment_methods_ids, wp_list_pluck( $payment_methods_for_type, 'id' ) );

					$types_retrieved_from_stripe[] = $payment_method_retrievable_type;
				}

				// Delete the local payment method if it doesn't exist in Stripe.
				if ( ! in_array( $token->get_token(), $stripe_payment_methods_ids, true ) ) {
					unset( $tokens[ $token->get_id() ] );

					// Prevent unnecessary recursion when deleting tokens.
					remove_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );

					$token->delete();

					add_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );
				} else {
					$locally_stored_payment_methods_ids[] = $token->get_token();
				}
			}

			// Prevent unnecessary recursion, WC_Payment_Token::save() ends up calling 'woocommerce_get_customer_payment_tokens' in some cases.
			remove_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );

			// Create a local payment method if it exists in Stripe but not locally.
			foreach ( $stripe_payment_methods as $stripe_payment_method ) {

				// Create a new token for the payment method and add it to the list.
				if ( ! in_array( $stripe_payment_method->id, $locally_stored_payment_methods_ids, true ) ) {
					$payment_method_type = $stripe_payment_method->type;

					// The payment method type doesn't match the ones we use. Nothing to do here.
					if ( ! isset( $gateway->payment_methods[ $payment_method_type ] ) ) {
						continue;
					}

					$payment_method_instance = $gateway->payment_methods[ $payment_method_type ];
					$token                   = $payment_method_instance->create_payment_token_for_user( $user_id, $stripe_payment_method );

					$tokens[ $token->get_id() ] = $token;
				}
			}

			add_action( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );

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
				if ( in_array( $token->get_gateway_id(), self::UPE_REUSABLE_GATEWAYS, true ) ) {
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
}

new WC_Stripe_Payment_Tokens();
