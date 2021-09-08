<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Class that handles UPE payment method.
*
* @extends WC_Stripe_Payment_Gateway
*
* @since x.x.x
*/
class WC_Stripe_UPE_Payment_Gateway extends WC_Stripe_Payment_Gateway {

	const ID = 'stripe';

	const UPE_AVAILABLE_METHODS = [
		WC_Stripe_UPE_Payment_Method_CC::class,
		WC_Stripe_UPE_Payment_Method_Giropay::class,
		WC_Stripe_UPE_Payment_Method_Eps::class,
		WC_Stripe_UPE_Payment_Method_Bancontact::class,
		WC_Stripe_UPE_Payment_Method_Ideal::class,
	];

	const UPE_APPEARANCE_TRANSIENT = 'wc_stripe_upe_appearance';

	/**
	 * Stripe intents that are treated as successfully created.
	 *
	 * @type array
	 */
	const SUCCESSFUL_INTENT_STATUS = [ 'succeeded', 'requires_capture', 'processing' ];

	/**
	 * Notices (array)
	 *
	 * @var array
	 */
	public $notices = [];

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Alternate credit card statement name
	 *
	 * @var bool
	 */
	public $statement_descriptor;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Api access publishable key
	 *
	 * @var string
	 */
	public $publishable_key;

	/**
	 * Array mapping payment method string IDs to classes
	 *
	 * @var array
	 */
	public $payment_methods = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->method_title = __( 'Stripe UPE', 'woocommerce-gateway-stripe' );
		/* translators: link */
		$this->method_description = __( 'Accept debit and credit cards in 135+ currencies, methods such as Alipay, and one-touch checkout with Apple Pay.', 'woocommerce-gateway-stripe' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			'tokenization',
		];

		$this->payment_methods = [];
		foreach ( self::UPE_AVAILABLE_METHODS as $payment_method_class ) {
			$payment_method                                     = new $payment_method_class();
			$this->payment_methods[ $payment_method->get_id() ] = $payment_method;
		}

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = get_option( 'woocommerce_stripe_settings' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'];
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-settings.php';
		unset( $this->form_fields['inline_cc_form'] );
	}

	/**
	 * Maybe override the parent admin_options method.
	 */
	public function admin_options() {
		if ( ! WC_Stripe_Feature_Flags::is_upe_settings_redesign_enabled() ) {
			parent::admin_options();

			return;
		}

		do_action( 'wc_stripe_gateway_admin_options_wrapper', $this );
	}

	/**
	 * Outputs scripts used for stripe payment
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		$asset_path   = WC_STRIPE_PLUGIN_PATH . '/build/checkout_upe.asset.php';
		$version      = WC_STRIPE_VERSION;
		$dependencies = [];
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}

		wp_register_script(
			'stripe',
			'https://js.stripe.com/v3/',
			[],
			'3.0',
			true
		);

		wp_register_script(
			'wc-stripe-upe-classic',
			WC_STRIPE_PLUGIN_URL . '/build/upe_classic.js',
			array_merge( [ 'stripe', 'wc-checkout' ], $dependencies ),
			$version,
			true
		);
		wp_set_script_translations(
			'wc-stripe-upe-classic',
			'woocommerce-gateway-stripe'
		);

		wp_localize_script(
			'wc-stripe-upe-classic',
			'wc_stripe_upe_params',
			apply_filters( 'wc_stripe_upe_params', $this->javascript_params() )
		);

		wp_enqueue_script( 'wc-stripe-upe-classic' );
	}

	/**
	 * Returns the JavaScript configuration object used on the product, cart, and checkout pages.
	 *
	 * @return array  The configuration object to be loaded to JS.
	 */
	public function javascript_params() {
		global $wp;

		$stripe_params = [
			'isUPEEnabled' => true,
			'key'          => $this->publishable_key,
			'locale'       => WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( get_locale() ),
		];

		$sepa_elements_options = apply_filters(
			'wc_stripe_sepa_elements_options',
			[
				'supportedCountries' => [ 'SEPA' ],
				'placeholderCountry' => WC()->countries->get_base_country(),
				'style'              => [ 'base' => [ 'fontSize' => '15px' ] ],
			]
		);

		$enabled_billing_fields = [];
		foreach ( WC()->checkout()->get_checkout_fields( 'billing' ) as $billing_field => $billing_field_options ) {
			if ( ! isset( $billing_field_options['enabled'] ) || $billing_field_options['enabled'] ) {
				$enabled_billing_fields[] = $billing_field;
			}
		}

		$stripe_params['isCheckout']               = is_checkout() && empty( $_GET['pay_for_order'] ); // wpcs: csrf ok.
		$stripe_params['return_url']               = $this->get_stripe_return_url();
		$stripe_params['ajax_url']                 = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$stripe_params['createPaymentIntentNonce'] = wp_create_nonce( 'wc_stripe_create_payment_intent_nonce' );
		$stripe_params['updatePaymentIntentNonce'] = wp_create_nonce( 'wc_stripe_update_payment_intent_nonce' );
		$stripe_params['upeAppeareance']           = get_transient( self::UPE_APPEARANCE_TRANSIENT );
		$stripe_params['saveUPEAppearanceNonce']   = wp_create_nonce( 'wc_stripe_save_upe_appearance_nonce' );
		$stripe_params['paymentMethodsConfig']     = $this->get_enabled_payment_method_config();
		$stripe_params['accountDescriptor']        = 'accountDescriptor'; // TODO: this should be added to the Stripe settings page or remove it from here.
		$stripe_params['genericErrorMessage']      = __( 'There was a problem processing the payment. Please check your email inbox and refresh the page to try again.', 'woocommerce-gateway-stripe' );
		$stripe_params['sepaElementsOptions']      = $sepa_elements_options;
		$stripe_params['enabledBillingFields']     = $enabled_billing_fields;

		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$order_id                    = absint( get_query_var( 'order-pay' ) );
			$stripe_params['orderId']    = $order_id;
			$stripe_params['isOrderPay'] = true;
			$order                       = wc_get_order( $order_id );

			if ( is_a( $order, 'WC_Order' ) ) {
				$stripe_params['orderReturnURL'] = esc_url_raw(
					add_query_arg(
						[
							'order_id'          => $order_id,
							'wc_payment_method' => self::ID,
							'_wpnonce'          => wp_create_nonce( 'wc_stripe_process_redirect_order_nonce' ),
						],
						$this->get_return_url( $order )
					)
				);
			}
		}

		return $stripe_params;
	}

	/**
	 * Gets payment method settings to pass to client scripts
	 *
	 * @return array
	 */
	private function get_enabled_payment_method_config() {
		$settings                = [];
		$enabled_payment_methods = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_at_checkout' ] );

		foreach ( $enabled_payment_methods as $payment_method ) {
			$settings[ $payment_method ] = [
				'isReusable' => $this->payment_methods[ $payment_method ]->is_reusable(),
			];
		}

		return $settings;
	}

	/**
	 * Returns the list of enabled payment method types for UPE.
	 *
	 * @return string[]
	 */
	public function get_upe_enabled_payment_method_ids() {
		return $this->get_option(
			'upe_checkout_experience_accepted_payments',
			[
				'card',
			]
		);
	}

	/**
	 * Returns the list of available payment method types for UPE.
	 * See https://stripe.com/docs/stripe-js/payment-element#web-create-payment-intent for a complete list.
	 *
	 * @return string[]
	 */
	public function get_upe_available_payment_methods() {
		$available_payment_methods = [];

		foreach ( self::UPE_AVAILABLE_METHODS as $payment_method_class ) {
			$available_payment_methods[] = $payment_method_class::STRIPE_ID;
		}

		return $available_payment_methods;
	}

	/**
	 * Renders the UPE input fields needed to get the user's payment information on the checkout page
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->supports( 'tokenization' ) && is_checkout();

			// Output the form HTML.
			?>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>

			<?php if ( $this->testmode ) : ?>
				<p class="testmode-info">
					<?php
					echo sprintf(
						/* translators: link to Stripe testing page */
						__( '<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="%s" target="_blank">here</a>.', 'woocommerce-gateway-stripe' ),
						'https://stripe.com/docs/testing'
					);
					?>
				</p>
			<?php endif; ?>

			<?php
			if ( $display_tokenization ) {
				$this->tokenization_script();
				$this->saved_payment_methods();
			}
			?>

			<fieldset id="wc-stripe-upe-form" class="wc-upe-form wc-payment-form">
				<div id="wc-stripe-upe-element"></div>
				<div id="wc-stripe-upe-errors" role="alert"></div>
				<input id="wc-stripe-payment-method-upe" type="hidden" name="wc-stripe-payment-method-upe" />
				<input id="wc_stripe_selected_upe_payment_type" type="hidden" name="wc_stripe_selected_upe_payment_type" />

				<?php
				$methods_enabled_for_saved_payments = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_for_saved_payments' ] );
				if ( $this->is_saved_cards_enabled() && ! empty( $methods_enabled_for_saved_payments ) ) {
					if ( is_user_logged_in() ) {
						$this->save_payment_method_checkbox();
					}
				}
				?>

			</fieldset>
			<?php
		} catch ( Exception $e ) {
			// Output the error message.
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
			?>
			<div>
				<?php
				echo esc_html__( 'An error was encountered when preparing the payment form. Please try again later.', 'woocommerce-gateway-stripe' );
				?>
			</div>
			<?php
		}
	}


	/**
	 * Process the payment for a given order.
	 *
	 * @param int $order_id Order ID to process the payment for.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment( $order_id ) {
		if ( $this->is_using_saved_payment_method() ) {
			return $this->process_payment_with_saved_payment_method( $order_id );
		}

		$payment_intent_id         = isset( $_POST['wc_payment_intent_id'] ) ? wc_clean( wp_unslash( $_POST['wc_payment_intent_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order                     = wc_get_order( $order_id );
		$amount                    = $order->get_total();
		$currency                  = $order->get_currency();
		$converted_amount          = WC_Stripe_Helper::get_stripe_amount( $amount, $currency );
		$payment_needed            = 0 < $converted_amount;
		$save_payment_method       = ! empty( $_POST[ 'wc-' . self::ID . '-new-payment-method' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selected_upe_payment_type = ! empty( $_POST['wc_stripe_selected_upe_payment_type'] ) ? wc_clean( wp_unslash( $_POST['wc_stripe_selected_upe_payment_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $payment_intent_id ) {
			if ( $payment_needed ) {
				$request = [
					'amount'      => $converted_amount,
					'currency'    => $currency,
					/* translators: 1) blog name 2) order number */
					'description' => sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() ),
				];

				// Get user/customer for order.
				$customer_id = $this->get_stripe_customer_id( $order );
				if ( ! empty( $customer_id ) ) {
					$request['customer'] = $customer_id;
				} else {
					$user                = $this->get_user_from_order( $order );
					$customer            = new WC_Stripe_Customer( $user->ID );
					$request['customer'] = $customer->update_or_create_customer();// Update customer or create customer if customer does not exist.
				}

				if ( '' !== $selected_upe_payment_type ) {
					// Only update the payment_method_types if we have a reference to the payment type the customer selected.
					$request['payment_method_types'] = [ $selected_upe_payment_type ];
				}

				if ( $save_payment_method ) {
					$request['setup_future_usage'] = 'off_session';
				}

				$request['metadata'] = $this->get_metadata_from_order( $order );

				WC_Stripe_API::request_with_level3_data(
					$request,
					"payment_intents/$payment_intent_id",
					$this->get_level3_data_from_order( $order ),
					$order
				);
			}
		}

		return [
			'result'         => 'success',
			'payment_needed' => $payment_needed,
			'redirect_url'   => wp_sanitize_redirect(
				esc_url_raw(
					add_query_arg(
						[
							'order_id'            => $order_id,
							'wc_payment_method'   => self::ID,
							'_wpnonce'            => wp_create_nonce( 'wc_stripe_process_redirect_order_nonce' ),
							'save_payment_method' => $save_payment_method ? 'yes' : 'no',
						],
						$this->get_return_url( $order )
					)
				)
			),
		];
	}

	/**
	 * Process payment using saved payment method.
	 * This follows WC_Gateway_Stripe::process_payment,
	 * but uses Payment Methods instead of Sources.
	 *
	 * @param int $order_id   The order ID being processed.
	 * @param bool $can_retry Should we retry on fail.
	 */
	public function process_payment_with_saved_payment_method( $order_id, $can_retry = true ) {
		try {
			$order = wc_get_order( $order_id );

			if ( $this->maybe_process_pre_orders( $order_id ) ) {
				// TODO: Implement pre-orders using Intents API.
				return;
			}

			$token          = WC_Stripe_Payment_Tokens::get_token_from_request( $_POST );
			$payment_method = WC_Stripe_API::request( [], 'payment_methods/' . $token->get_token(), 'GET' );
			$payment_needed = 0 < $order->get_total();

			$this->maybe_disallow_prepaid_card( $payment_method );
			$this->save_payment_method_to_order( $order, $payment_method );

			WC_Stripe_Logger::log( "Info: Begin processing payment with saved payment method for order $order_id for the amount of {$order->get_total()}" );

			// If we are retrying request, maybe intent has been saved to order.
			$intent = $this->get_intent_from_order( $order );

			$enabled_payment_methods = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_at_checkout' ] );
			if ( $payment_needed ) {
				// This will throw exception if not valid.
				$this->validate_minimum_order_amount( $order );

				$request_details = $this->generate_payment_request( $order, $payment_method );
				$level3_data     = $this->get_level3_data_from_order( $order );
				$endpoint        = false !== $intent ? "payment_intents/$intent->id" : 'payment_intents';
				$request         = [
					'payment_method'       => $payment_method->id,
					'payment_method_types' => $enabled_payment_methods,
					'amount'               => WC_Stripe_Helper::get_stripe_amount( $order->get_total() ),
					'currency'             => strtolower( $order->get_currency() ),
					'description'          => $request_details['description'],
					'metadata'             => $request_details['metadata'],
					'customer'             => $payment_method->customer,
				];
				if ( false === $intent ) {
					$request['capture_method'] = ( 'true' === $request_details['capture'] ) ? 'automatic' : 'manual';
					$request['confirm']        = 'true';
				}

				$intent = WC_Stripe_API::request_with_level3_data(
					$request,
					$endpoint,
					$level3_data,
					$order
				);
			} else {
				$endpoint = false !== $intent ? "setup_intents/$intent->id" : 'setup_intents';
				$request  = [
					'payment_method'       => $payment_method->id,
					'payment_method_types' => $enabled_payment_methods,
					'customer'             => $payment_method->customer,
				];
				if ( false === $intent ) {
					$request['confirm'] = 'true';
				}

				$intent = WC_Stripe_API::request(
					$request,
					$endpoint,
					'POST'
				);
			}
			$this->save_intent_to_order( $order, $intent );

			if ( ! empty( $intent->error ) ) {
				$this->maybe_remove_non_existent_customer( $intent->error, $order );

				// We want to retry (apparently).
				if ( $this->is_retryable_error( $intent->error ) ) {
					return $this->retry_after_error( $intent, $order, $can_retry );
				}

				$this->throw_localized_message( $intent, $order );
			}

			if ( 'requires_action' === $intent->status ) {
				if ( isset( $intent->next_action->type ) && 'redirect_to_url' === $intent->next_action->type && ! empty( $intent->next_action->redirect_to_url->url ) ) {
					return [
						'result'   => 'success',
						'redirect' => $intent->next_action->redirect_to_url->url,
					];
				} else {
					return [
						'result'   => 'success',
						// Include a new nonce for update_order_status to ensure the update order
						// status call works when a guest user creates an account during checkout.
						'redirect' => sprintf(
							'#wc-stripe-confirm-%s:%s:%s:%s',
							$payment_needed ? 'pi' : 'si',
							$order_id,
							$intent->client_secret,
							wp_create_nonce( 'wc_stripe_update_order_status_nonce' )
						),
					];
				}
			}

			list( $payment_method_type, $payment_method_details ) = $this->get_payment_method_data_from_intent( $intent );

			// Use the last charge within the intent to proceed.
			if ( isset( $intent->charges ) ) {
				$charge = end( $intent->charges->data );
				$this->process_response( $charge, $order );
			} else {
				// TODO: Add implementation for setup intents.
				$this->process_response( $intent, $order );
			}
			$this->set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details );

			// Remove cart.
			if ( isset( WC()->cart ) ) {
				WC()->cart->empty_cart();
			}

			// Return thank you page redirect.
			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];

		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			/* translators: error message */
			$order->update_status( 'failed' );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}

	/**
	 * Processes UPE redirect payments.
	 *
	 * @param int    $order_id The order ID being processed.
	 * @param string $intent_id The Stripe setup/payment intent ID for the order payment.
	 * @param bool   $save_payment_method Boolean representing whether payment method for order should be saved.
	 *
	 * @since x.x.x
	 * @version x.x.x
	 */
	public function process_upe_redirect_payment( $order_id, $intent_id, $save_payment_method ) {
		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) ) {
			return;
		}

		if ( $order->has_status( [ 'processing', 'completed', 'on-hold' ] ) ) {
			return;
		}

		WC_Stripe_Logger::log( "Begin processing UPE redirect payment for order $order_id for the amount of {$order->get_total()}" );

		try {
			$this->process_order_for_confirmed_intent( $order, $intent_id, $save_payment_method );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			/* translators: localized exception message */
			$order->update_status( 'failed', sprintf( __( 'UPE payment failed: %s', 'woocommerce-gateway-stripe' ), $e->getMessage() ) );

			wc_add_notice( $e->getMessage(), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * Update order and maybe save payment method for an order after an intent has been created and confirmed.
	 *
	 * @param WC_Order $order               Order being processed.
	 * @param string   $intent_id           Stripe setup/payment ID.
	 * @param bool     $save_payment_method Boolean representing whether payment method for order should be saved.
	 */
	public function process_order_for_confirmed_intent( $order, $intent_id, $save_payment_method ) {
		$payment_needed = 0 < $order->get_total();

		// Get payment intent to confirm status.
		if ( $payment_needed ) {
			$intent = WC_Stripe_API::retrieve( 'payment_intents/' . $intent_id . '?expand[]=payment_method' );
		} else {
			$intent = WC_Stripe_API::retrieve( 'setup_intents/' . $intent_id . '?expand[]=payment_method' );
		}
		$error = $intent->last_payment_error;

		if ( ! empty( $error ) ) {
			WC_Stripe_Logger::log( 'Error when processing payment: ' . $error->message );
			throw new WC_Stripe_Exception( __( "We're not able to process this payment. Please try again later.", 'woocommerce-gateway-stripe' ) );
		}

		list( $payment_method_type, $payment_method_details ) = $this->get_payment_method_data_from_intent( $intent );

		if ( ! isset( $this->payment_methods[ $payment_method_type ] ) ) {
			return;
		}
		$payment_method = $this->payment_methods[ $payment_method_type ];

		if ( $save_payment_method && $payment_method->is_reusable() ) {
			$user = $this->get_user_from_order( $order );
			// Clear transient, since we are adding a new payment method.
			$customer = new WC_Stripe_Customer( $user->ID );
			$customer->clear_cache();
			$token = $payment_method->add_token_to_user( $user, $intent->payment_method );
			$this->add_token_to_order( $order, $token );
		}

		// Use the last charge within the intent to proceed.
		if ( isset( $intent->charges ) ) {
			$charge = end( $intent->charges->data );
			$this->process_response( $charge, $order );
		} else {
			// TODO: Add implementation for setup intents.
			$this->process_response( $intent, $order );
		}
		$this->save_intent_to_order( $order, $intent );
		$this->set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details );
	}

	/**
	 * Save payment method to order.
	 *
	 * @param WC_Order $order For to which the source applies.
	 * @param stdClass $payment_method Stripe Payment Method.
	 */
	public function save_payment_method_to_order( $order, $payment_method ) {
		if ( $payment_method->customer ) {
			$order->update_meta_data( '_stripe_customer_id', $payment_method->customer );
		}
		$order->update_meta_data( '_stripe_payment_method_id', $payment_method->id );

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}
	}

	/**
	 * Retries the payment process once an error occured.
	 *
	 * @param object   $intent     The Payment Intent response from the Stripe API.
	 * @param WC_Order $order      An order that is being paid for.
	 * @param bool     $can_retry
	 * @throws WC_Stripe_Exception If the payment is not accepted.
	 * @return array|void
	 */
	public function retry_after_error( $intent, $order, $can_retry ) {
		if ( ! $can_retry ) {
			$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-gateway-stripe' );
			$order->add_order_note( $localized_message );
			throw new WC_Stripe_Exception( print_r( $intent, true ), $localized_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.
		}

		// Don't do anymore retries after this.
		if ( 5 <= $this->retry_interval ) {
			return $this->process_payment_with_saved_payment_method( $order->get_id(), false );
		}

		sleep( $this->retry_interval );
		$this->retry_interval++;

		return $this->process_payment_with_saved_payment_method( $order->get_id(), true );
	}

	/**
	 * Checks if a payment method object represents a prepaid credit card and
	 * throws an exception if it is one, but that is not allowed.
	 *
	 * @since 4.2.0
	 * @param object $prepared_source The object with source details.
	 * @throws WC_Stripe_Exception An exception if the card is prepaid, but prepaid cards are not allowed.
	 */
	public function maybe_disallow_prepaid_card( $payment_method ) {
		// Check if we don't allow prepaid credit cards.
		if ( apply_filters( 'wc_stripe_allow_prepaid_card', true ) || ! $this->is_prepaid_card( $payment_method ) ) {
			return;
		}

		$localized_message = __( 'Sorry, we\'re not accepting prepaid cards at this time. Your credit card has not been charged. Please try with alternative payment method.', 'woocommerce-gateway-stripe' );
		throw new WC_Stripe_Exception( print_r( $payment_method, true ), $localized_message );
	}

	/**
	 * Checks if card on Payment Method is a prepaid card.
	 *
	 * @since 4.0.6
	 * @param object $payment_method
	 * @return bool
	 */
	public function is_prepaid_card( $payment_method ) {
		return (
			$payment_method
			&& ( 'card' === $payment_method->type )
			&& 'prepaid' === $payment_method->card->funding
		);
	}

	/**
	 * Get WC User from WC Order.
	 *
	 * @param WC_Order $order
	 *
	 * @return WC_User
	 */
	public function get_user_from_order( $order ) {
		$user = $order->get_user();
		if ( false === $user ) {
			$user = wp_get_current_user();
		}
		return $user;
	}

	/**
	 * Function to be used with array_filter
	 * to filter UPE payment methods supported with current checkout
	 *
	 * @param string $payment_method_id Stripe payment method.
	 *
	 * @return bool
	 */
	public function is_enabled_at_checkout( $payment_method_id ) {
		if ( ! isset( $this->payment_methods[ $payment_method_id ] ) ) {
			return false;
		}
		return $this->payment_methods[ $payment_method_id ]->is_enabled_at_checkout();
	}

	/**
	 * Function to be used with array_filter
	 * to filter UPE payment methods that support saved payments
	 *
	 * @param string $payment_method_id Stripe payment method.
	 *
	 * @return bool
	 */
	public function is_enabled_for_saved_payments( $payment_method_id ) {
		if ( ! isset( $this->payment_methods[ $payment_method_id ] ) ) {
			return false;
		}
		return $this->payment_methods[ $payment_method_id ]->is_reusable();
	}

	// TODO: Actually validate.
	public function validate_upe_checkout_experience_accepted_payments_field( $key, $value ) {
		return $value;
	}

	/**
	 * Checks if the setting to allow the user to save cards is enabled.
	 *
	 * @return bool Whether the setting to allow saved cards is enabled or not.
	 */
	public function is_saved_cards_enabled() {
		return 'yes' === $this->get_option( 'saved_cards' );
	}

	/**
	 * Set formatted readable payment method title for order,
	 * using payment method details from accompanying charge.
	 *
	 * @param WC_Order   $order WC Order being processed.
	 * @param string     $payment_method_type Stripe payment method key.
	 * @param array|bool $payment_method_details Array of payment method details from charge or false.
	 *
	 * @since x.x.x
	 * @version x.x.x
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_type, $payment_method_details ) {
		if ( ! isset( $this->payment_methods[ $payment_method_type ] ) ) {
			return;
		}

		$payment_method_title = $this->payment_methods[ $payment_method_type ]->get_title( $payment_method_details );

		$order->set_payment_method( self::ID );
		$order->set_payment_method_title( $payment_method_title );
		$order->save();
	}

	/**
	 * Saves the payment token to the order.
	 *
	 * @param WC_Order         $order The order.
	 * @param WC_Payment_Token $token The token to save.
	 */
	public function add_token_to_order( $order, $token ) {
		$payment_token = $this->get_payment_token( $order );

		// This could lead to tokens being saved twice in an order's payment tokens, but it is needed so that shoppers
		// may re-use a previous card for the same subscription, as we consider the last token to be the active one.
		// We can't remove the previous entry for the token because WC_Order does not support removal of tokens [1] and
		// we can't delete the token as it might be used somewhere else.
		// [1] https://github.com/woocommerce/woocommerce/issues/11857.
		if ( is_null( $payment_token ) || $token->get_id() !== $payment_token->get_id() ) {
			$order->add_payment_token( $token );
		}

		// TODO: implement this when Subscriptions added
		// $this->maybe_add_token_to_subscription_order( $order, $token );
	}

	/**
	 * Retrieve payment token from a subscription or order.
	 *
	 * @param WC_Order $order Order or subscription object.
	 *
	 * @return null|WC_Payment_Token Last token associated with order or subscription.
	 */
	protected function get_payment_token( $order ) {
		$order_tokens = $order->get_payment_tokens();
		$token_id     = end( $order_tokens );
		return ! $token_id ? null : WC_Payment_Tokens::get( $token_id );
	}

	/**
	 * This is overloading the upe checkout experience type on the settings page.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_upe_checkout_experience_accepted_payments_html( $key, $data ) {
		$data['description'] = '<div id="wc_stripe_upe_method_selection"><p><strong>Payments accepted on checkout</strong></p></div>
			<table class="wc_gateways widefat form-table" cellspacing="0" aria-describedby="wc_stripe_upe_method_selection">
			<thead>
				<tr>
					<th class="name">Method</th>
					<th class="status">Enabled</th>
					<th class="description">Description</th>
				</tr>
			</thead>
			<tbody>';

		foreach ( $this->payment_methods as $method_id => $method ) {
			$method_enabled       = in_array( $method_id, $this->get_upe_enabled_payment_method_ids(), true ) ? 'enabled' : 'disabled';
			$data['description'] .= '<tr data-upe_method_id="' . $method_id . '">
					<td class="name" width=""><a href="#" class="wc-payment-gateway-method-title">' . $method_id . '</a><span class="wc-payment-gateway-method-name">&nbsp;–&nbsp;Subtext goes here.</span></td>
					<td class="status" width="1%"><a class="wc-payment-upe-method-toggle-' . $method_enabled . '" href="#"><span class="woocommerce-input-toggle woocommerce-input-toggle--' . $method_enabled . '" aria-label="The &quot;' . $method_id . '&quot; payment method is currently ' . $method_enabled . '">' . ( 'enabled' === $method_enabled ? 'Yes' : 'No' ) . '</span></a></td>
					<td class="description" width="">Long description text goes here.</td>
				</tr>';
		}

		$data['description'] .= '</tbody>
			</table>
			<span id="wc_stripe_upe_change_notice" class="hidden">' . __( 'You must save your changes.', 'woocommerce-gateway-stripe' ) . '</span>';

		return $this->generate_title_html( $key, $data );
	}

	/**
	 * This is overloading the title type so the oauth url is only fetched if we are on the settings page.
	 *
	 * TODO: This is duplicate code from WC_Gateway_Stripe.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_stripe_account_keys_html( $key, $data ) {
		if ( woocommerce_gateway_stripe()->connect->is_connected() ) {
			$reset_link = add_query_arg(
				[
					'_wpnonce'                     => wp_create_nonce( 'reset_stripe_api_credentials' ),
					'reset_stripe_api_credentials' => true,
				],
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' )
			);

			$api_credentials_text = sprintf(
			/* translators: %1, %2, %3, and %4 are all HTML markup tags */
				__( '%1$sClear all Stripe account keys.%2$s %3$sThis will disable any connection to Stripe.%4$s', 'woocommerce-gateway-stripe' ),
				'<a id="wc_stripe_connect_button" href="' . $reset_link . '" class="button button-secondary">',
				'</a>',
				'<span style="color:red;">',
				'</span>'
			);
		} else {
			$oauth_url = woocommerce_gateway_stripe()->connect->get_oauth_url();

			if ( ! is_wp_error( $oauth_url ) ) {
				$api_credentials_text = sprintf(
				/* translators: %1, %2 and %3 are all HTML markup tags */
					__( '%1$sSetup or link an existing Stripe account.%2$s By clicking this button you agree to the %3$sTerms of Service%2$s. Or, manually enter Stripe account keys below.', 'woocommerce-gateway-stripe' ),
					'<a id="wc_stripe_connect_button" href="' . $oauth_url . '" class="button button-primary">',
					'</a>',
					'<a href="https://wordpress.com/tos">'
				);
			} else {
				$api_credentials_text = __( 'Manually enter Stripe keys below.', 'woocommerce-gateway-stripe' );
			}
		}
		$data['description'] = $api_credentials_text;
		return $this->generate_title_html( $key, $data );
	}

	/**
	 * Extacts the Stripe intent's payment_method_type and payment_method_details values.
	 *
	 * @param $intent   Stripe's intent response.
	 * @return string[] List with 2 values: payment_method_type and payment_method_details.
	 */
	private function get_payment_method_data_from_intent( $intent ) {
		$payment_method_type    = '';
		$payment_method_details = false;

		if ( 'payment_intent' === $intent->object ) {
			if ( ! empty( $intent->charges ) && 0 < $intent->charges->total_count ) {
				$charge                 = end( $intent->charges->data );
				$payment_method_details = (array) $charge->payment_method_details;
				$payment_method_type    = ! empty( $payment_method_details ) ? $payment_method_details['type'] : '';
			}
		} elseif ( 'setup_intent' === $intent->object ) {
			$payment_method_options = array_keys( $intent->payment_method_options );
			$payment_method_type    = ! empty( $payment_method_options ) ? $payment_method_options[0] : '';
			// Setup intents don't have details, keep the false value.
		}

		return [ $payment_method_type, $payment_method_details ];
	}

	/**
	 * Prepares Stripe metadata for a given order.
	 *
	 * @param WC_Order $order Order being processed.
	 *
	 * @return array Array of keyed metadata values.
	 */
	public function get_metadata_from_order( $order ) {

		// TODO: change this after adding the subscriptions trait: $this->is_payment_recurring( $order->get_id() ) ? Payment_Type::RECURRING() : Payment_Type::SINGLE();
		$payment_type = 'single';
		$name         = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
		$email        = sanitize_email( $order->get_billing_email() );

		return [
			'customer_name'  => $name,
			'customer_email' => $email,
			'site_url'       => esc_url( get_site_url() ),
			'order_id'       => $order->get_id(),
			'order_key'      => $order->get_order_key(),
			'payment_type'   => $payment_type,
		];
	}
}
