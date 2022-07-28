<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Class that handles UPE payment method.
*
* @extends WC_Gateway_Stripe
*
* @since 5.5.0
*/
class WC_Stripe_UPE_Payment_Gateway extends WC_Gateway_Stripe {

	const ID = 'stripe';

	const UPE_AVAILABLE_METHODS = [
		WC_Stripe_UPE_Payment_Method_CC::class,
		WC_Stripe_UPE_Payment_Method_Giropay::class,
		WC_Stripe_UPE_Payment_Method_Eps::class,
		WC_Stripe_UPE_Payment_Method_Bancontact::class,
		WC_Stripe_UPE_Payment_Method_Boleto::class,
		WC_Stripe_UPE_Payment_Method_Ideal::class,
		WC_Stripe_UPE_Payment_Method_Oxxo::class,
		WC_Stripe_UPE_Payment_Method_Sepa::class,
		WC_Stripe_UPE_Payment_Method_P24::class,
		WC_Stripe_UPE_Payment_Method_Sofort::class,
		WC_Stripe_UPE_Payment_Method_Link::class,
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
	 * Are saved cards enabled
	 *
	 * @var bool
	 */
	public $saved_cards;

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
	 * @var WC_Stripe_UPE_Payment_Method[]
	 */
	public $payment_methods = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->method_title = __( 'Stripe', 'woocommerce-gateway-stripe' );
		/* translators: link */
		$this->method_description = __( 'Accept debit and credit cards in 135+ currencies, methods such as SEPA, and one-touch checkout with Apple Pay.', 'woocommerce-gateway-stripe' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
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

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// Check if pre-orders are enabled and add support for them.
		$this->maybe_init_pre_orders();

		$main_settings              = get_option( 'woocommerce_stripe_settings' );
		$this->title                = ! empty( $this->get_option( 'title_upe' ) ) ? $this->get_option( 'title_upe' ) : $this->form_fields['title_upe']['default'];
		$this->description          = '';
		$this->enabled              = $this->get_option( 'enabled' );
		$this->saved_cards          = 'yes' === $this->get_option( 'saved_cards' );
		$this->testmode             = ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'];
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		$enabled_at_checkout_payment_methods = $this->get_upe_enabled_at_checkout_payment_method_ids();
		if ( count( $enabled_at_checkout_payment_methods ) === 1 ) {
			$this->title = $this->payment_methods[ $enabled_at_checkout_payment_methods[0] ]->get_title();
		}

		// When feature flags are enabled, title shows the count of enabled payment methods in settings page only.
		if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() && WC_Stripe_Feature_Flags::is_upe_preview_enabled() && isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) {
			$enabled_payment_methods_count = count( $this->get_upe_enabled_payment_method_ids() );
			$this->title                   = $enabled_payment_methods_count ?
				/* translators: $1. Count of enabled payment methods. */
				sprintf( _n( '%d payment method', '%d payment methods', $enabled_payment_methods_count, 'woocommerce-gateway-stripe' ), $enabled_payment_methods_count )
				: $this->method_title;
		}

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		// Needed for 3DS compatibility when checking out with PRBs..
		// Copied from WC_Gateway_Stripe::__construct().
		add_filter( 'woocommerce_payment_successful_result', [ $this, 'modify_successful_payment_result' ], 99999, 2 );
	}

	/**
	 * Hides refund through stripe when payment method does not allow refund
	 *
	 * @param WC_Order $order
	 *
	 * @return array|bool
	 */
	public function can_refund_order( $order ) {
		$upe_payment_type = $order->get_meta( '_stripe_upe_payment_type' );

		if ( ! $upe_payment_type ) {
			return true;
		}

		return $this->payment_methods[ $upe_payment_type ]->can_refund_via_stripe();
	}

	/**
	 * Return the gateway icon - None for UPE.
	 */
	public function get_icon() {
		return apply_filters( 'woocommerce_gateway_icon', null, $this->id );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-settings.php';
		unset( $this->form_fields['inline_cc_form'] );
		unset( $this->form_fields['title'] );
		unset( $this->form_fields['description'] );
	}

	/**
	 * Outputs scripts used for stripe payment
	 */
	public function payment_scripts() {
		if (
			! is_product()
			&& ! WC_Stripe_Helper::has_cart_or_checkout_on_current_page()
			&& ! isset( $_GET['pay_for_order'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& ! is_add_payment_method_page() ) {
			return;
		}

		if ( is_product() && ! WC_Stripe_Helper::should_load_scripts_on_product_page() ) {
			return;
		}

		if ( is_cart() && ! WC_Stripe_Helper::should_load_scripts_on_cart_page() ) {
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

		wp_register_style(
			'wc-stripe-upe-classic',
			WC_STRIPE_PLUGIN_URL . '/build/upe_classic.css',
			[],
			$version
		);

		wp_enqueue_script( 'wc-stripe-upe-classic' );
		wp_enqueue_style( 'wc-stripe-upe-classic' );

		wp_register_style( 'stripelink_styles', plugins_url( 'assets/css/stripe-link.css', WC_STRIPE_MAIN_FILE ), [], WC_STRIPE_VERSION );
		wp_enqueue_style( 'stripelink_styles' );
	}

	/**
	 * Returns the JavaScript configuration object used on the product, cart, and checkout pages.
	 *
	 * @return array  The configuration object to be loaded to JS.
	 */
	public function javascript_params() {
		global $wp;

		$stripe_params = [
			'title'        => $this->title,
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
		$stripe_params['createSetupIntentNonce']   = wp_create_nonce( 'wc_stripe_create_setup_intent_nonce' );
		$stripe_params['updateFailedOrderNonce']   = wp_create_nonce( 'wc_stripe_update_failed_order_nonce' );
		$stripe_params['upeAppeareance']           = get_transient( self::UPE_APPEARANCE_TRANSIENT );
		$stripe_params['saveUPEAppearanceNonce']   = wp_create_nonce( 'wc_stripe_save_upe_appearance_nonce' );
		$stripe_params['paymentMethodsConfig']     = $this->get_enabled_payment_method_config();
		$stripe_params['genericErrorMessage']      = __( 'There was a problem processing the payment. Please check your email inbox and refresh the page to try again.', 'woocommerce-gateway-stripe' );
		$stripe_params['accountDescriptor']        = $this->statement_descriptor;
		$stripe_params['addPaymentReturnURL']      = wc_get_account_endpoint_url( 'payment-methods' );
		$stripe_params['sepaElementsOptions']      = $sepa_elements_options;
		$stripe_params['enabledBillingFields']     = $enabled_billing_fields;

		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			if ( $this->is_subscriptions_enabled() && $this->is_changing_payment_method_for_subscription() ) {
				$stripe_params['isChangingPayment']   = true;
				$stripe_params['addPaymentReturnURL'] = esc_url_raw( home_url( add_query_arg( [] ) ) );

				if ( $this->is_setup_intent_success_creation_redirection() && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wpnonce'] ) ) ) ) {
					$setup_intent_id                 = isset( $_GET['setup_intent'] ) ? wc_clean( wp_unslash( $_GET['setup_intent'] ) ) : '';
					$token                           = $this->create_token_from_setup_intent( $setup_intent_id, wp_get_current_user() );
					$stripe_params['newTokenFormId'] = '#wc-' . $token->get_gateway_id() . '-payment-token-' . $token->get_id();
				}
				return $stripe_params;
			}

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

		// Pre-orders and free trial subscriptions don't require payments.
		$stripe_params['isPaymentNeeded'] = $this->is_payment_needed( isset( $order_id ) ? $order_id : null );

		return array_merge( $stripe_params, WC_Stripe_Helper::get_localized_messages() );
	}

	/**
	 * Gets payment method settings to pass to client scripts
	 *
	 * @return array
	 */
	private function get_enabled_payment_method_config() {
		$settings                = [];
		$enabled_payment_methods = $this->get_upe_enabled_at_checkout_payment_method_ids();

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
		return $this->get_option( 'upe_checkout_experience_accepted_payments', [ 'card' ] );
	}

	/**
	 * Returns the list of enabled payment method types that will function with the current checkout.
	 *
	 * @param int|null $order_id
	 * @return string[]
	 */
	public function get_upe_enabled_at_checkout_payment_method_ids( $order_id = null ) {
		$is_automatic_capture_enabled = $this->is_automatic_capture_enabled();
		$available_method_ids         = [];
		foreach ( $this->get_upe_enabled_payment_method_ids() as $payment_method_id ) {
			if ( ! isset( $this->payment_methods[ $payment_method_id ] ) ) {
				continue;
			}

			$method = $this->payment_methods[ $payment_method_id ];
			if ( $method->is_enabled_at_checkout( $order_id ) === false ) {
				continue;
			}

			if ( ! $is_automatic_capture_enabled && $method->requires_automatic_capture() ) {
				continue;
			}

			$available_method_ids[] = $payment_method_id;
		}

		return $available_method_ids;
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
					printf(
					/* translators: 1) HTML strong open tag 2) HTML strong closing tag 3) HTML anchor open tag 2) HTML anchor closing tag */
						esc_html__( '%1$sTest mode:%2$s use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed %3$shere%4$s.', 'woocommerce-gateway-stripe' ),
						'<strong>',
						'</strong>',
						'<a href="https://stripe.com/docs/testing" target="_blank">',
						'</a>'
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
			</fieldset>
			<?php
			$methods_enabled_for_saved_payments = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_for_saved_payments' ] );
			if ( $this->is_saved_cards_enabled() && ! empty( $methods_enabled_for_saved_payments ) ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
				if ( is_user_logged_in() ) {
					$this->save_payment_method_checkbox( $force_save_payment );
				}
			}
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
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force save the payment source.
	 * @param mix  $previous_error Any error message from previous request.
	 * @param bool $use_order_source Whether to use the source, which should already be attached to the order.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		if ( $this->maybe_change_subscription_payment_method( $order_id ) ) {
			return $this->process_change_subscription_payment_method( $order_id );
		}

		if ( $this->is_using_saved_payment_method() ) {
			return $this->process_payment_with_saved_payment_method( $order_id );
		}

		$payment_intent_id         = isset( $_POST['wc_payment_intent_id'] ) ? wc_clean( wp_unslash( $_POST['wc_payment_intent_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order                     = wc_get_order( $order_id );
		$payment_needed            = $this->is_payment_needed( $order_id );
		$save_payment_method       = $this->has_subscription( $order_id ) || ! empty( $_POST[ 'wc-' . self::ID . '-new-payment-method' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selected_upe_payment_type = ! empty( $_POST['wc_stripe_selected_upe_payment_type'] ) ? wc_clean( wp_unslash( $_POST['wc_stripe_selected_upe_payment_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$statement_descriptor                  = ! empty( $this->get_option( 'statement_descriptor' ) ) ? str_replace( "'", '', $this->get_option( 'statement_descriptor' ) ) : '';
		$short_statement_descriptor            = ! empty( $this->get_option( 'short_statement_descriptor' ) ) ? str_replace( "'", '', $this->get_option( 'short_statement_descriptor' ) ) : '';
		$is_short_statement_descriptor_enabled = ! empty( $this->get_option( 'is_short_statement_descriptor_enabled' ) ) && 'yes' === $this->get_option( 'is_short_statement_descriptor_enabled' );
		$descriptor                            = null;
		if ( 'card' === $selected_upe_payment_type && $is_short_statement_descriptor_enabled && ! ( empty( $short_statement_descriptor ) && empty( $statement_descriptor ) ) ) {
			// Use the shortened statement descriptor for card transactions only
			$descriptor = WC_Stripe_Helper::get_dynamic_statement_descriptor( $short_statement_descriptor, $order, $statement_descriptor );
		} elseif ( ! empty( $statement_descriptor ) ) {
			$descriptor = WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor );
		}

		if ( $payment_intent_id ) {
			if ( $payment_needed ) {
				$amount           = $order->get_total();
				$currency         = $order->get_currency();
				$converted_amount = WC_Stripe_Helper::get_stripe_amount( $amount, $currency );

				$request = [
					'amount'               => $converted_amount,
					'currency'             => $currency,
					'statement_descriptor' => $descriptor,
					/* translators: 1) blog name 2) order number */
					'description'          => sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() ),
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
					if ( WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID === $selected_upe_payment_type ) {
						if ( in_array(
							WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
							$this->get_upe_enabled_payment_method_ids(),
							true
						) ) {
							$request['payment_method_types'] = [
								WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
								WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
							];
						}
					}
					$this->set_payment_method_title_for_order( $order, $selected_upe_payment_type );
					if ( ! $this->payment_methods[ $selected_upe_payment_type ]->is_allowed_on_country( $order->get_billing_country() ) ) {
						throw new \Exception( __( 'This payment method is not available on the selected country', 'woocommerce-gateway-stripe' ) );
					}
				}

				if ( $save_payment_method ) {
					$request['setup_future_usage'] = 'off_session';
				}

				$request['metadata'] = $this->get_metadata_from_order( $order );

				WC_Stripe_Helper::add_payment_intent_to_order( $payment_intent_id, $order );
				$order->update_status( 'pending', __( 'Awaiting payment.', 'woocommerce-gateway-stripe' ) );
				$order->update_meta_data( '_stripe_upe_payment_type', $selected_upe_payment_type );
				$order->save();

				$this->stripe_request(
					"payment_intents/$payment_intent_id",
					$request,
					$order
				);
			}
		} else {
			return parent::process_payment( $order_id, $retry, $force_save_source, $previous_error, $use_order_source );
		}

		return [
			'result'         => 'success',
			'payment_needed' => $payment_needed,
			'order_id'       => $order_id,
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
				return $this->process_pre_order( $order_id );
			}

			$token                   = WC_Stripe_Payment_Tokens::get_token_from_request( $_POST );
			$payment_method          = $this->stripe_request( 'payment_methods/' . $token->get_token(), [], null, 'GET' );
			$prepared_payment_method = $this->prepare_payment_method( $payment_method );

			$this->maybe_disallow_prepaid_card( $payment_method );
			$this->save_payment_method_to_order( $order, $prepared_payment_method );

			WC_Stripe_Logger::log( "Info: Begin processing payment with saved payment method for order $order_id for the amount of {$order->get_total()}" );

			// If we are retrying request, maybe intent has been saved to order.
			$intent = $this->get_intent_from_order( $order );

			$enabled_payment_methods = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_at_checkout' ] );
			$payment_needed          = $this->is_payment_needed( $order_id );

			if ( $payment_needed ) {
				// This will throw exception if not valid.
				$this->validate_minimum_order_amount( $order );

				$request_details = $this->generate_payment_request( $order, $prepared_payment_method );
				$endpoint        = false !== $intent ? "payment_intents/$intent->id" : 'payment_intents';
				$request         = [
					'payment_method'       => $payment_method->id,
					'payment_method_types' => array_values( $enabled_payment_methods ),
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

				$intent = $this->stripe_request(
					$endpoint,
					$request,
					$order
				);
			} else {
				$endpoint = false !== $intent ? "setup_intents/$intent->id" : 'setup_intents';
				$request  = [
					'payment_method'       => $payment_method->id,
					'payment_method_types' => array_values( $enabled_payment_methods ),
					'customer'             => $payment_method->customer,
				];
				if ( false === $intent ) {
					$request['confirm'] = 'true';
					// SEPA setup intents require mandate data.
					if ( in_array( 'sepa_debit', array_values( $enabled_payment_methods ), true ) ) {
						$request['mandate_data'] = [
							'customer_acceptance' => [
								'type'   => 'online',
								'online' => [
									'ip_address' => WC_Geolocation::get_ip_address(),
									'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '', // @codingStandardsIgnoreLine
								],
							],
						];
					}
				}

				$intent = $this->stripe_request( $endpoint, $request );
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

			if ( 'requires_action' === $intent->status || 'requires_confirmation' === $intent->status ) {
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

			if ( $payment_needed ) {
				// Use the last charge within the intent to proceed.
				$this->process_response( end( $intent->charges->data ), $order );
			} else {
				$order->payment_complete();
			}
			$this->set_payment_method_title_for_order( $order, $payment_method_type );

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
	 * Check for a UPE redirect payment method on order received page or setup intent on payment methods page.
	 *
	 * @since 5.6.0
	 * @version 5.6.0
	 */
	public function maybe_process_upe_redirect() {
		if ( $this->is_payment_methods_page() ) {
			if ( $this->is_setup_intent_success_creation_redirection() ) {
				if ( isset( $_GET['redirect_status'] ) && 'succeeded' === $_GET['redirect_status'] ) {
					$user_id  = wp_get_current_user()->ID;
					$customer = new WC_Stripe_Customer( $user_id );
					$customer->clear_cache();
					wc_add_notice( __( 'Payment method successfully added.', 'woocommerce-gateway-stripe' ) );

					// The newly created payment method does not inherit the customers' billing info, so we manually
					// trigger an update; in case of failure we log the error and continue because the payment method's
					// billing info will be updated when the customer makes a purchase anyway.
					try {
						$setup_intent_id = isset( $_GET['setup_intent'] ) ? wc_clean( wp_unslash( $_GET['setup_intent'] ) ) : '';
						$setup_intent    = $this->stripe_request( 'setup_intents/' . $setup_intent_id, [], null, 'GET' );

						$customer_data         = WC_Stripe_Customer::map_customer_data( null, new WC_Customer( $user_id ) );
						$payment_method_object = $this->stripe_request(
							'payment_methods/' . $setup_intent->payment_method,
							[
								'billing_details' => [
									'name'    => $customer_data['name'],
									'email'   => $customer_data['email'],
									'phone'   => $customer_data['phone'],
									'address' => $customer_data['address'],
								],
							]
						);

						do_action( 'woocommerce_stripe_add_payment_method', $user_id, $payment_method_object );
					} catch ( Exception $e ) {
						WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
					}
				} else {
					wc_add_notice( __( 'Failed to add payment method.', 'woocommerce-gateway-stripe' ), 'error', [ 'icon' => 'error' ] );
				}
			}
			return;
		}

		if ( ! is_order_received_page() ) {
			return;
		}

		$payment_method = isset( $_GET['wc_payment_method'] ) ? wc_clean( wp_unslash( $_GET['wc_payment_method'] ) ) : '';
		if ( self::ID !== $payment_method ) {
			return;
		}

		$is_nonce_valid = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wpnonce'] ) ), 'wc_stripe_process_redirect_order_nonce' );
		if ( ! $is_nonce_valid || empty( $_GET['wc_payment_method'] ) ) {
			return;
		}

		if ( ! empty( $_GET['payment_intent_client_secret'] ) ) {
			$intent_id = isset( $_GET['payment_intent'] ) ? wc_clean( wp_unslash( $_GET['payment_intent'] ) ) : '';
		} elseif ( ! empty( $_GET['setup_intent_client_secret'] ) ) {
			$intent_id = isset( $_GET['setup_intent'] ) ? wc_clean( wp_unslash( $_GET['setup_intent'] ) ) : '';
		} else {
			return;
		}

		$order_id            = isset( $_GET['order_id'] ) ? wc_clean( wp_unslash( $_GET['order_id'] ) ) : '';
		$save_payment_method = isset( $_GET['save_payment_method'] ) ? 'yes' === wc_clean( wp_unslash( $_GET['save_payment_method'] ) ) : false;

		if ( empty( $intent_id ) || empty( $order_id ) ) {
			return;
		}

		$this->process_upe_redirect_payment( $order_id, $intent_id, $save_payment_method );
	}

	/**
	 * Processes UPE redirect payments.
	 *
	 * @param int    $order_id The order ID being processed.
	 * @param string $intent_id The Stripe setup/payment intent ID for the order payment.
	 * @param bool   $save_payment_method Boolean representing whether payment method for order should be saved.
	 *
	 * @since 5.5.0
	 * @version 5.5.0
	 */
	public function process_upe_redirect_payment( $order_id, $intent_id, $save_payment_method ) {
		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) ) {
			return;
		}

		if ( $order->has_status( [ 'processing', 'completed', 'on-hold' ] ) ) {
			return;
		}

		if ( $order->get_meta( '_stripe_upe_redirect_processed', true ) ) {
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
		$payment_needed = $this->is_payment_needed( $order->get_id() );

		// Get payment intent to confirm status.
		if ( $payment_needed ) {
			$intent = $this->stripe_request( 'payment_intents/' . $intent_id . '?expand[]=payment_method' );
			$error  = isset( $intent->last_payment_error ) ? $intent->last_payment_error : false;
		} else {
			$intent = $this->stripe_request( 'setup_intents/' . $intent_id . '?expand[]=payment_method&expand[]=latest_attempt' );
			$error  = isset( $intent->last_setup_error ) ? $intent->last_setup_error : false;
		}

		if ( ! empty( $error ) ) {
			WC_Stripe_Logger::log( 'Error when processing payment: ' . $error->message );
			throw new WC_Stripe_Exception( __( "We're not able to process this payment. Please try again later.", 'woocommerce-gateway-stripe' ) );
		}

		list( $payment_method_type, $payment_method_details ) = $this->get_payment_method_data_from_intent( $intent );

		if ( ! isset( $this->payment_methods[ $payment_method_type ] ) ) {
			return;
		}
		$payment_method = $this->payment_methods[ $payment_method_type ];

		if ( $this->maybe_process_pre_orders( $order->get_id() ) ) {
			// If this is a pre-order, simply mark the order as pre-ordered and allow
			// the subsequent logic to save the payment method and proceed to complete the order.
			$this->mark_order_as_pre_ordered( $order->get_id() );
			$save_payment_method = true;
		}

		if ( $save_payment_method && $payment_method->is_reusable() ) {
			$payment_method_object = null;
			if ( $payment_method->get_id() !== $payment_method->get_retrievable_type() ) {
				$generated_payment_method_id = $payment_method_details[ $payment_method_type ]->generated_sepa_debit;
				$payment_method_object       = $this->stripe_request( "payment_methods/$generated_payment_method_id", [], null, 'GET' );
			} else {
				$payment_method_object = $intent->payment_method;
			}
			$user                    = $this->get_user_from_order( $order );
			$customer                = new WC_Stripe_Customer( $user->ID );
			$prepared_payment_method = $this->prepare_payment_method( $payment_method_object );

			$customer->clear_cache();
			$this->save_payment_method_to_order( $order, $prepared_payment_method );
			do_action( 'woocommerce_stripe_add_payment_method', $user->get_id(), $payment_method_object );
		}

		if ( $payment_needed ) {
			// Use the last charge within the intent to proceed.
			$this->process_response( end( $intent->charges->data ), $order );
		} else {
			$order->payment_complete();
		}
		$this->save_intent_to_order( $order, $intent );
		$this->set_payment_method_title_for_order( $order, $payment_method_type );
		$order->update_meta_data( '_stripe_upe_redirect_processed', true );
		$order->save();
	}

	/**
	 * Converts payment method into object similar to prepared source
	 * compatible with wc_stripe_payment_metadata and wc_stripe_generate_payment_request filters.
	 *
	 * @param object           $payment_method Stripe payment method object response.
	 *
	 * @return object
	 */
	public function prepare_payment_method( $payment_method ) {
		return (object) [
			'customer'              => $payment_method->customer,
			'source'                => null,
			'source_object'         => null,
			'payment_method'        => $payment_method->id,
			'payment_method_object' => $payment_method,
		];
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
		// Save the payment method id as `source_id`, because we use both `sources` and `payment_methods` APIs.
		$order->update_meta_data( '_stripe_source_id', $payment_method->payment_method );

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}

		$this->maybe_update_source_on_subscription_order( $order, $payment_method );
	}

	/**
	 * Retries the payment process once an error occured.
	 *
	 * @param object   $intent            The Payment Intent response from the Stripe API.
	 * @param WC_Order $order             An order that is being paid for.
	 * @param bool     $retry             A flag that indicates whether another retry should be attempted.
	 * @param bool     $force_save_source Force save the payment source.
	 * @param mixed    $previous_error    Any error message from previous request.
	 * @param bool     $use_order_source  Whether to use the source, which should already be attached to the order.
	 * @throws WC_Stripe_Exception If the payment is not accepted.
	 * @return array|void
	 */
	public function retry_after_error( $intent, $order, $retry, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		if ( ! $retry ) {
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
	 * Returns true if a payment is needed for the current cart or order.
	 * Pre-Orders and Subscriptions may not require an upfront payment, so we need to check whether
	 * or not the payment is necessary to decide for either a setup intent or a payment intent.
	 *
	 * @since 5.8.0
	 *
	 * @param int $order_id The order ID being processed.
	 *
	 * @return bool Whether a payment is necessary.
	 */
	public function is_payment_needed( $order_id = null ) {
		if ( $this->is_pre_order_item_in_cart() || ( ! empty( $order_id ) && $this->has_pre_order( $order_id ) ) ) {
			$pre_order_product = ( ! empty( $order_id ) ) ? $this->get_pre_order_product_from_order( $order_id ) : $this->get_pre_order_product_from_cart();
			// Only one pre-order product is allowed per cart,
			// so we can return if it's charged upfront.
			return $this->is_pre_order_product_charged_upfront( $pre_order_product );
		}

		// Free trial subscriptions without a sign up fee, or any other type
		// of order with a `0` amount should fall into the logic below.
		$amount = is_null( WC()->cart ) ? 0 : WC()->cart->get_total( false );
		$order  = isset( $order_id ) ? wc_get_order( $order_id ) : null;
		if ( is_a( $order, 'WC_Order' ) ) {
			$amount = $order->get_total();
		}

		$converted_amount = WC_Stripe_Helper::get_stripe_amount( $amount, strtolower( get_woocommerce_currency() ) );

		return 0 < $converted_amount;
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
	 * @return WP_User
	 */
	public function get_user_from_order( $order ) {
		$user = $order->get_user();
		if ( false === $user ) {
			$user = wp_get_current_user();
		}
		return $user;
	}

	/**
	 * Checks if gateway should be available to use.
	 *
	 * @since 5.6.0
	 */
	public function is_available() {
		$methods_enabled_for_saved_payments = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_for_saved_payments' ] );
		if ( is_add_payment_method_page() && count( $methods_enabled_for_saved_payments ) === 0 ) {
			return false;
		}

		return parent::is_available();
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
		return $this->saved_cards;
	}

	/**
	 * Set formatted readable payment method title for order,
	 * using payment method details from accompanying charge.
	 *
	 * @param WC_Order   $order WC Order being processed.
	 * @param string     $payment_method_type Stripe payment method key.
	 *
	 * @since 5.5.0
	 * @version 5.5.0
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_type ) {
		if ( ! isset( $this->payment_methods[ $payment_method_type ] ) ) {
			return;
		}

		$payment_method_title = $this->payment_methods[ $payment_method_type ]->get_label();

		$order->set_payment_method( self::ID );
		$order->set_payment_method_title( $payment_method_title );
		$order->save();
	}

	/**
	 * This is overloading the upe checkout experience type on the settings page.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_upe_checkout_experience_accepted_payments_html( $key, $data ) {
		$stripe_account      = $this->stripe_request( 'account' );
		$stripe_capabilities = isset( $stripe_account->capabilities ) ? (array) $stripe_account->capabilities : [];
		$data['description'] = '<p>' . __( "Select payments available to customers at checkout. We'll only show your customers the most relevant payment methods based on their currency and location.", 'woocommerce-gateway-stripe' ) . '</p>
		<table class="wc_gateways widefat form-table wc-stripe-upe-method-selection" cellspacing="0" aria-describedby="wc_stripe_upe_method_selection">
			<thead>
				<tr>
					<th class="name wc-stripe-upe-method-selection__name">' . esc_html__( 'Method', 'woocommerce-gateway-stripe' ) . '</th>
					<th class="status wc-stripe-upe-method-selection__status">' . esc_html__( 'Enabled', 'woocommerce-gateway-stripe' ) . '</th>
					<th class="description wc-stripe-upe-method-selection__description">' . esc_html__( 'Description', 'woocommerce-gateway-stripe' ) . '</th>
				</tr>
			</thead>
			<tbody>';

		$is_automatic_capture_enabled = $this->is_automatic_capture_enabled();

		foreach ( $this->payment_methods as $method_id => $method ) {
			$method_enabled       = in_array( $method_id, $this->get_upe_enabled_payment_method_ids(), true ) && ( $is_automatic_capture_enabled || ! $method->requires_automatic_capture() ) ? 'enabled' : 'disabled';
			$method_enabled_label = 'enabled' === $method_enabled ? __( 'enabled', 'woocommerce-gateway-stripe' ) : __( 'disabled', 'woocommerce-gateway-stripe' );
			$capability_id        = "{$method_id}_payments"; // "_payments" is a suffix that comes from Stripe API, except when it is "transfers", which does not apply here
			$method_status        = isset( $stripe_capabilities[ $capability_id ] ) ? $stripe_capabilities[ $capability_id ] : 'inactive';
			$subtext_messages     = $method->get_subtext_messages( $method_status );
			$aria_label           = sprintf(
				/* translators: $1%s payment method ID, $2%s "enabled" or "disabled" */
				esc_attr__( 'The &quot;%1$s&quot; payment method is currently %2$s', 'woocommerce-gateway-stripe' ),
				$method_id,
				$method_enabled_label
			);
			$manual_capture_tip = sprintf(
				/* translators: $1%s payment method label */
				__( '%1$s is not available to your customers when manual capture is enabled.', 'woocommerce-gateway-stripe' ),
				$method->get_label()
			);
			$data['description'] .= '<tr data-upe_method_id="' . $method_id . '">
					<td class="name wc-stripe-upe-method-selection__name" width="">
						' . $method->get_label() . '
						' . ( empty( $subtext_messages ) ? '' : '<span class="wc-payment-gateway-method-name">&nbsp;–&nbsp;' . $subtext_messages . '</span>' ) . '
					</td>
					<td class="status wc-stripe-upe-method-selection__status" width="1%">
						<a class="wc-payment-upe-method-toggle-' . $method_enabled . '" href="#">
							<span class="woocommerce-input-toggle woocommerce-input-toggle--' . $method_enabled . '" aria-label="' . $aria_label . '">
							' . ( 'enabled' === $method_enabled ? __( 'Yes', 'woocommerce-gateway-stripe' ) : __( 'No', 'woocommerce-gateway-stripe' ) ) . '
							</span>
						</a>'
						. ( ! $is_automatic_capture_enabled && $method->requires_automatic_capture() ? '<span class="tips dashicons dashicons-warning" style="margin-top: 1px; margin-right: -25px; margin-left: 5px; color: red" data-tip="' . $manual_capture_tip . '" />' : '' ) .
					'</td>
					<td class="description wc-stripe-upe-method-selection__description" width="">' . $method->get_description() . '</td>
				</tr>';
		}

		$data['description'] .= '</tbody>
			</table>
			<p><a class="button" target="_blank" href="https://dashboard.stripe.com/account/payments/settings">' . __( 'Get more payment methods', 'woocommerce-gateway-stripe' ) . '</a></p>
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
			if ( ! empty( $intent->latest_attempt ) && ! empty( $intent->latest_attempt->payment_method_details ) ) {
				$payment_method_details = (array) $intent->latest_attempt->payment_method_details;
				$payment_method_type    = $payment_method_details['type'];
			} elseif ( ! empty( $intent->payment_method ) ) {
				$payment_method_details = $intent->payment_method;
				$payment_method_type    = $payment_method_details->type;
			}
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
		$payment_type = $this->is_payment_recurring( $order->get_id() ) ? 'recurring' : 'single';
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

	/**
	 * Returns true when viewing payment methods page.
	 *
	 * @return bool
	 */
	private function is_payment_methods_page() {
		global $wp;

		$page_id = wc_get_page_id( 'myaccount' );

		return ( $page_id && is_page( $page_id ) && ( isset( $wp->query_vars['payment-methods'] ) ) );
	}

	/**
	 * True if the request contains the values that indicates a redirection after a successful setup intent creation.
	 *
	 * @return bool
	 */
	private function is_setup_intent_success_creation_redirection() {
		return ( ! empty( $_GET['setup_intent_client_secret'] ) & ! empty( $_GET['setup_intent'] ) & ! empty( $_GET['redirect_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Adds a token to current user from a setup intent id.
	 *
	 * @param string  $setup_intent_id ID of the setup intent.
	 * @param WP_User $user            User to add token to.
	 *
	 * @return WC_Payment_Token_CC|WC_Payment_Token_WCPay_SEPA The added token.
	 *
	 * @since 5.8.0
	 * @version 5.8.0
	 */
	public function create_token_from_setup_intent( $setup_intent_id, $user ) {
		try {
			$setup_intent = $this->stripe_request( 'setup_intents/' . $setup_intent_id );
			if ( ! empty( $setup_intent->last_payment_error ) ) {
				throw new WC_Stripe_Exception( __( "We're not able to add this payment method. Please try again later.", 'woocommerce-gateway-stripe' ) );
			}

			$payment_method_id     = $setup_intent->payment_method;
			$payment_method_object = $this->stripe_request( 'payment_methods/' . $payment_method_id );

			$payment_method = $this->payment_methods[ $payment_method_object->type ];

			$customer = new WC_Stripe_Customer( wp_get_current_user()->ID );
			$customer->clear_cache();

			return $payment_method->create_payment_token_for_user( $user->ID, $payment_method_object );
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error', [ 'icon' => 'error' ] );
			WC_Stripe_Logger::log( 'Error when adding payment method: ' . $e->getMessage() );
			return [
				'result' => 'error',
			];
		}
	}

	/**
	 * Wrapper function to manage requests to WC_Stripe_API.
	 *
	 * @param string   $path   Stripe API endpoint path to query.
	 * @param string   $params Parameters for request body.
	 * @param WC_Order $order  WC Order for request.
	 * @param string   $method HTTP method for request.
	 *
	 * @return object JSON response object.
	 */
	protected function stripe_request( $path, $params = null, $order = null, $method = 'POST' ) {
		if ( is_null( $params ) ) {
			return WC_Stripe_API::retrieve( $path );
		}
		if ( ! is_null( $order ) ) {
			$level3_data = $this->get_level3_data_from_order( $order );
			return WC_Stripe_API::request_with_level3_data( $params, $path, $level3_data, $order );
		}
		return WC_Stripe_API::request( $params, $path, $method );
	}

}
