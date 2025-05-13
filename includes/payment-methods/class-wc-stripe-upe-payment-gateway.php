<?php

use Automattic\WooCommerce\Enums\OrderStatus;

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

	/**
	 * Upe Available Methods
	 *
	 * @type WC_Stripe_UPE_Payment_Method[]
	 */
	const UPE_AVAILABLE_METHODS = [
		WC_Stripe_Payment_Methods::CARD              => WC_Stripe_UPE_Payment_Method_CC::class,
		WC_Stripe_Payment_Methods::ACH               => WC_Stripe_UPE_Payment_Method_ACH::class,
		WC_Stripe_Payment_Methods::ALIPAY            => WC_Stripe_UPE_Payment_Method_Alipay::class,
		WC_Stripe_Payment_Methods::AMAZON_PAY        => WC_Stripe_UPE_Payment_Method_Amazon_Pay::class,
		WC_Stripe_Payment_Methods::BLIK              => WC_Stripe_UPE_Payment_Method_BLIK::class,
		WC_Stripe_Payment_Methods::GIROPAY           => WC_Stripe_UPE_Payment_Method_Giropay::class,
		WC_Stripe_Payment_Methods::KLARNA            => WC_Stripe_UPE_Payment_Method_Klarna::class,
		WC_Stripe_Payment_Methods::AFFIRM            => WC_Stripe_UPE_Payment_Method_Affirm::class,
		WC_Stripe_Payment_Methods::AFTERPAY_CLEARPAY => WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::class,
		WC_Stripe_Payment_Methods::EPS               => WC_Stripe_UPE_Payment_Method_Eps::class,
		WC_Stripe_Payment_Methods::BANCONTACT        => WC_Stripe_UPE_Payment_Method_Bancontact::class,
		WC_Stripe_Payment_Methods::BOLETO            => WC_Stripe_UPE_Payment_Method_Boleto::class,
		WC_Stripe_Payment_Methods::IDEAL             => WC_Stripe_UPE_Payment_Method_Ideal::class,
		WC_Stripe_Payment_Methods::OXXO              => WC_Stripe_UPE_Payment_Method_Oxxo::class,
		WC_Stripe_Payment_Methods::SEPA_DEBIT        => WC_Stripe_UPE_Payment_Method_Sepa::class,
		WC_Stripe_Payment_Methods::P24               => WC_Stripe_UPE_Payment_Method_P24::class,
		WC_Stripe_Payment_Methods::SOFORT            => WC_Stripe_UPE_Payment_Method_Sofort::class,
		WC_Stripe_Payment_Methods::MULTIBANCO        => WC_Stripe_UPE_Payment_Method_Multibanco::class,
		WC_Stripe_Payment_Methods::LINK              => WC_Stripe_UPE_Payment_Method_Link::class,
		WC_Stripe_Payment_Methods::WECHAT_PAY        => WC_Stripe_UPE_Payment_Method_Wechat_Pay::class,
		WC_Stripe_Payment_Methods::CASHAPP_PAY       => WC_Stripe_UPE_Payment_Method_Cash_App_Pay::class,
		WC_Stripe_Payment_Methods::ACSS_DEBIT        => WC_Stripe_UPE_Payment_Method_ACSS::class,
		WC_Stripe_Payment_Methods::BACS_DEBIT        => WC_Stripe_UPE_Payment_Method_Bacs_Debit::class,
		WC_Stripe_Payment_Methods::BECS_DEBIT        => WC_Stripe_UPE_Payment_Method_Becs_Debit::class,
	];

	/**
	 * Stripe intents that are treated as successfully created.
	 *
	 * @type array
	 *
	 * @deprecated 9.1.0
	 */
	const SUCCESSFUL_INTENT_STATUS = [ 'succeeded', 'requires_capture', 'processing' ];

	/**
	 * Transient name for appearance settings.
	 *
	 * @type string
	 */
	const APPEARANCE_TRANSIENT = 'wc_stripe_appearance';

	/**
	 * Transient name for appearance settings on the block checkout.
	 *
	 * @type string
	 */
	const BLOCKS_APPEARANCE_TRANSIENT = 'wc_stripe_blocks_appearance';

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
	 * Should SEPA tokens be used for other payment methods (iDEAL and Bancontact)
	 *
	 * @var bool
	 */
	public $sepa_tokens_for_other_methods;

	/**
	 * Is Single Payment Element enabled?
	 *
	 * @var bool
	 *
	 * @deprecated 9.5.0 Use `oc_enabled`.
	 */
	public $spe_enabled;

	/**
	 * Is Optimized Checkout enabled?
	 *
	 * @var bool
	 */
	public $oc_enabled;

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
	 * Instance of WC_Stripe_Intent_Controller.
	 *
	 * @var WC_Stripe_Intent_Controller
	 */
	public $intent_controller;

	/**
	 * WC_Stripe_Action_Scheduler_Service instance for scheduling ActionScheduler jobs.
	 *
	 * @var WC_Stripe_Action_Scheduler_Service
	 */
	public $action_scheduler_service;

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

		$enabled_payment_methods = $this->get_upe_enabled_payment_method_ids();
		$is_sofort_enabled       = in_array( WC_Stripe_Payment_Methods::SOFORT, $enabled_payment_methods, true );

		$main_settings    = WC_Stripe_Helper::get_stripe_settings();
		$this->oc_enabled = WC_Stripe_Feature_Flags::is_oc_available() && 'yes' === $this->get_option( 'optimized_checkout_element' );

		$this->payment_methods = [];
		foreach ( self::UPE_AVAILABLE_METHODS as $payment_method_class ) {
			// Show ACH only if feature is enabled.
			if ( WC_Stripe_UPE_Payment_Method_ACH::class === $payment_method_class && ! WC_Stripe_Feature_Flags::is_ach_lpm_enabled() ) {
				continue;
			}

			// Show ACSS only if feature is enabled.
			if ( WC_Stripe_UPE_Payment_Method_ACSS::class === $payment_method_class && ! WC_Stripe_Feature_Flags::is_acss_lpm_enabled() ) {
				continue;
			}

			// Consider Bacs only if the feature is enabled.
			if ( WC_Stripe_UPE_Payment_Method_Bacs_Debit::class === $payment_method_class && ! WC_Stripe_Feature_Flags::is_bacs_lpm_enabled() ) {
				continue;
			}

			// Show BECS Debit only if feature is enabled.
			if ( WC_Stripe_UPE_Payment_Method_Becs_Debit::class === $payment_method_class && ! WC_Stripe_Feature_Flags::is_becs_debit_lpm_enabled() ) {
				continue;
			}

			// Show BLIK only if feature is enabled.
			if ( WC_Stripe_UPE_Payment_Method_BLIK::class === $payment_method_class && ! WC_Stripe_Feature_Flags::is_blik_lpm_enabled() ) {
				continue;
			}

			/** Show Sofort if it's already enabled. Hide from the new merchants and keep it for the old ones who are already using this gateway, until we remove it completely.
			 * Stripe is deprecating Sofort https://support.stripe.com/questions/sofort-is-being-deprecated-as-a-standalone-payment-method.
			 */
			if ( WC_Stripe_UPE_Payment_Method_Sofort::class === $payment_method_class && ! $is_sofort_enabled ) {
				continue;
			}

			// Show giropay only on the orders page to allow refunds. It was deprecated.
			if ( WC_Stripe_UPE_Payment_Method_Giropay::class === $payment_method_class && ! $this->is_order_details_page() && ! $this->is_refund_request() ) {
				continue;
			}

			$payment_method                                     = new $payment_method_class();
			$this->payment_methods[ $payment_method->get_id() ] = $payment_method;
		}

		$this->intent_controller        = new WC_Stripe_Intent_Controller();
		$this->action_scheduler_service = new WC_Stripe_Action_Scheduler_Service();

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// Check if pre-orders are enabled and add support for them.
		$this->maybe_init_pre_orders();

		$this->title                         = $this->payment_methods['card']->get_title();
		$this->description                   = $this->payment_methods['card']->get_description();
		$this->enabled                       = $this->get_option( 'enabled' );
		$this->sepa_tokens_for_other_methods = 'yes' === $this->get_option( 'sepa_tokens_for_other_methods' );
		$this->saved_cards                   = 'yes' === $this->get_option( 'saved_cards' );
		$this->testmode                      = WC_Stripe_Mode::is_test();
		$this->publishable_key               = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key                    = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor          = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		// When feature flags are enabled, title shows the count of enabled payment methods in settings page only.
		if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() && WC_Stripe_Feature_Flags::is_upe_preview_enabled() && isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] ) {
			$enabled_payment_methods_count = count( $enabled_payment_methods );
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

		add_action( 'wp_footer', [ $this, 'payment_scripts' ] );

		// Display the correct fees on the order page.
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_fee' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_payout' ], 20 );

		// Needed for 3DS compatibility when checking out with PRBs..
		// Copied from WC_Gateway_Stripe::__construct().
		add_filter( 'woocommerce_payment_successful_result', [ $this, 'modify_successful_payment_result' ], 99999, 2 );

		// Update the current request logged_in cookie after a guest user is created to avoid nonce inconsistencies.
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );

		add_action( 'wc_ajax_wc_stripe_save_appearance', [ $this, 'save_appearance_ajax' ] );

		add_filter( 'woocommerce_saved_payment_methods_list', [ $this, 'filter_saved_payment_methods_list' ], 10, 2 );

		// Add hooks for clearing appearance transients when theme is updated
		add_action( 'customize_save_after', [ $this, 'clear_appearance_transients' ] );
		add_action( 'save_post', [ $this, 'clear_appearance_transients_block_theme' ], 10, 2 );

		// Hide action buttons for pending orders if they take a while to be confirmed.
		add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'filter_my_account_my_orders_actions' ], 10, 2 );
	}

	/**
	 * Returns the payment method instance for the given payment method name.
	 *
	 * @param $payment_method string The payment method name.
	 * @return WC_Stripe_UPE_Payment_Method|null The payment method instance.
	 */
	public static function get_payment_method_instance( $payment_method ) {
		$payment_method_class = self::UPE_AVAILABLE_METHODS[ $payment_method ] ?? null;
		if ( ! $payment_method_class ) {
			return null;
		}
		return new $payment_method_class();
	}

	/**
	 * Returns the HTML for the bundled payment instructions when Smart Checkout is enabled.
	 *
	 * @return string
	 *
	 * @deprecated 9.5.0 Use `get_testing_instructions_for_optimized_checkout()` instead.
	 */
	public static function get_testing_instructions_for_smart_checkout() {
		return static::get_testing_instructions_for_optimized_checkout();
	}

	/**
	 * Returns the HTML for the bundled payment instructions when Optimized Checkout (previously known as Smart Checkout and SPE) is enabled.
	 *
	 * @return string
	 */
	public static function get_testing_instructions_for_optimized_checkout() {
		$instructions          = '';
		$base_instruction_html = '<div id="wc-stripe-payment-method-instructions-%s" class="wc-stripe-payment-method-instruction" style="display: none;">%s</div>';
		foreach ( self::UPE_AVAILABLE_METHODS as $payment_method_class ) {
			$payment_method_instructions = ( new $payment_method_class() )->get_testing_instructions( true );
			if ( $payment_method_instructions ) {
				$instructions .= sprintf( $base_instruction_html, $payment_method_class::STRIPE_ID, $payment_method_instructions );
			}
		}

		return $instructions;
	}

	/**
	 * Removes all saved payment methods when the setting to save cards is disabled.
	 *
	 * @param  array $list         List of payment methods passed from wc_get_customer_saved_methods_list().
	 * @param  int   $customer_id  The customer to fetch payment methods for.
	 * @return array               Filtered list of customers payment methods.
	 */
	public function filter_saved_payment_methods_list( $list, $customer_id ) {
		if ( ! $this->saved_cards ) {
			return [];
		}
		return $list;
	}

	/**
	 * Proceed with current request using new login session (to ensure consistent nonce).
	 *
	 * @param string $cookie New cookie value.
	 */
	public function set_cookie_on_current_request( $cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
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
	 * Gets the payment method's icon.
	 *
	 * @return string The icon HTML.
	 */
	public function get_icon() {
		$icons = WC_Stripe::get_instance()->get_main_stripe_gateway()->payment_icons();
		return isset( $icons['cards'] ) ? apply_filters( 'woocommerce_gateway_icon', $icons['cards'], $this->id ) : parent::get_icon();
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
			&& ! parent::is_valid_pay_for_order_endpoint()
			&& ! is_add_payment_method_page() ) {
			return;
		}

		if ( is_product() && ! WC_Stripe_Helper::should_load_scripts_on_product_page() ) {
			return;
		}

		if ( is_cart() && ! WC_Stripe_Helper::should_load_scripts_on_cart_page() ) {
			return;
		}

		// Bail if Stripe is not enabled.
		if ( 'no' === $this->enabled ) {
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
			WC_STRIPE_PLUGIN_URL . '/build/upe-classic.js',
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
			WC_STRIPE_PLUGIN_URL . '/build/upe-classic.css',
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

		$is_change_payment_method = $this->is_changing_payment_method_for_subscription();
		$stripe_params            = [
			'gatewayId'    => self::ID,
			'title'        => $this->title,
			'isUPEEnabled' => true,
			'key'          => $this->publishable_key,
			'locale'       => WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( get_locale() ),
			'apiVersion'   => WC_Stripe_API::STRIPE_API_VERSION,
		];

		$enabled_billing_fields = [];
		foreach ( WC()->checkout()->get_checkout_fields( 'billing' ) as $billing_field => $billing_field_options ) {
			if ( ! isset( $billing_field_options['enabled'] ) || $billing_field_options['enabled'] ) {
				$enabled_billing_fields[] = $billing_field;
			}
		}

		$express_checkout_helper = new WC_Stripe_Express_Checkout_Helper();

		$stripe_params['isCheckout']                       = ( is_checkout() || has_block( 'woocommerce/checkout' ) ) && empty( $_GET['pay_for_order'] ); // wpcs: csrf ok.
		$stripe_params['return_url']                       = $this->get_stripe_return_url();
		$stripe_params['ajax_url']                         = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$stripe_params['theme_name']                       = get_option( 'stylesheet' );
		$stripe_params['testMode']                         = $this->testmode;
		$stripe_params['createPaymentIntentNonce']         = wp_create_nonce( 'wc_stripe_create_payment_intent_nonce' );
		$stripe_params['updatePaymentIntentNonce']         = wp_create_nonce( 'wc_stripe_update_payment_intent_nonce' );
		$stripe_params['createSetupIntentNonce']           = wp_create_nonce( 'wc_stripe_create_setup_intent_nonce' );
		$stripe_params['createAndConfirmSetupIntentNonce'] = wp_create_nonce( 'wc_stripe_create_and_confirm_setup_intent_nonce' );
		$stripe_params['updateFailedOrderNonce']           = wp_create_nonce( 'wc_stripe_update_failed_order_nonce' );
		$stripe_params['paymentMethodsConfig']             = $this->get_enabled_payment_method_config();
		$stripe_params['genericErrorMessage']              = __( 'There was a problem processing the payment. Please check your email inbox and refresh the page to try again.', 'woocommerce-gateway-stripe' );
		$stripe_params['accountDescriptor']                = $this->statement_descriptor;
		$stripe_params['addPaymentReturnURL']              = wc_get_account_endpoint_url( 'payment-methods' );
		$stripe_params['enabledBillingFields']             = $enabled_billing_fields;
		$stripe_params['cartContainsSubscription']         = $this->is_subscription_item_in_cart();
		$stripe_params['accountCountry']                   = WC_Stripe::get_instance()->account->get_account_country();
		$stripe_params['isPaymentRequestEnabled']          = $express_checkout_helper->is_payment_request_enabled();
		$stripe_params['isAmazonPayEnabled']               = $express_checkout_helper->is_amazon_pay_enabled();
		$stripe_params['isLinkEnabled']                    = $express_checkout_helper->is_link_enabled();

		// Add appearance settings.
		$stripe_params['appearance']          = get_transient( $this->get_appearance_transient_key() );
		$stripe_params['blocksAppearance']    = get_transient( $this->get_appearance_transient_key( true ) );
		$stripe_params['saveAppearanceNonce'] = wp_create_nonce( 'wc_stripe_save_appearance_nonce' );

		// ECE feature flag
		$stripe_params['isECEEnabled'] = WC_Stripe_Feature_Flags::is_stripe_ece_enabled();

		// Amazon Pay feature flag.
		$stripe_params['isAmazonPayAvailable'] = WC_Stripe_Feature_Flags::is_amazon_pay_available();

		// ACH LPM Feature flag.
		$stripe_params['is_ach_enabled'] = WC_Stripe_Feature_Flags::is_ach_lpm_enabled();

		// ACSS LPM Feature flag.
		$stripe_params['is_acss_enabled'] = WC_Stripe_Feature_Flags::is_acss_lpm_enabled();

		// BLIK LPM Feature flag.
		$stripe_params['is_blik_enabled'] = WC_Stripe_Feature_Flags::is_blik_lpm_enabled();

		// BECS Debit LPM Feature flag.
		$stripe_params['is_becs_debit_enabled'] = WC_Stripe_Feature_Flags::is_becs_debit_lpm_enabled();

		// Optimized Checkout feature flag + setting.
		$stripe_params['isOCEnabled'] = $this->oc_enabled;

		// Single Payment Element payment method parent configuration ID
		$stripe_params['paymentMethodConfigurationParentId'] = WC_Stripe_Payment_Method_Configurations::get_parent_configuration_id();

		$cart_total = ( WC()->cart ? WC()->cart->get_total( '' ) : 0 );
		$currency   = get_woocommerce_currency();

		$stripe_params['cartTotal'] = WC_Stripe_Helper::get_stripe_amount( $cart_total, strtolower( $currency ) );
		$stripe_params['currency']  = $currency;

		if ( parent::is_valid_pay_for_order_endpoint() || $is_change_payment_method ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			$stripe_params['orderId'] = $order_id;

			// Make billing country available for subscriptions as well, so country-restricted payment methods can be shown.
			if ( is_a( $order, 'WC_Order' ) ) {
				$stripe_params['customerData'] = [ 'billing_country' => $order->get_billing_country() ];
			}

			if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() && $is_change_payment_method ) {
				$stripe_params['isChangingPayment']   = true;
				$stripe_params['addPaymentReturnURL'] = wp_sanitize_redirect( esc_url_raw( home_url( add_query_arg( [] ) ) ) );

				if ( $this->is_setup_intent_success_creation_redirection() && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wpnonce'] ) ) ) ) {
					$setup_intent_id                 = isset( $_GET['setup_intent'] ) ? wc_clean( wp_unslash( $_GET['setup_intent'] ) ) : '';
					$token                           = $this->create_token_from_setup_intent( $setup_intent_id, wp_get_current_user() );
					$stripe_params['newTokenFormId'] = '#wc-' . $token->get_gateway_id() . '-payment-token-' . $token->get_id();
				}
				return $stripe_params;
			}

			$stripe_params['isOrderPay'] = true;

			// Additional params for order pay page, when the order was successfully loaded.
			if ( is_a( $order, 'WC_Order' ) ) {
				$order_currency                  = $order->get_currency();
				$stripe_params['currency']       = $order_currency;
				$stripe_params['cartTotal']      = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $order_currency );
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
		} elseif ( is_wc_endpoint_url( 'add-payment-method' ) ) {
			$stripe_params['cartTotal']    = 0;
			$stripe_params['customerData'] = [ 'billing_country' => WC()->customer->get_billing_country() ];
		}

		// Pre-orders and free trial subscriptions don't require payments.
		$stripe_params['isPaymentNeeded'] = $this->is_payment_needed( isset( $order_id ) ? $order_id : null );

		// Some saved tokens need to override the default token label on the checkout.
		if ( has_block( 'woocommerce/checkout' ) ) {
			$stripe_params['tokenLabelOverrides'] = WC_Stripe_Payment_Tokens::get_token_label_overrides_for_checkout();
		}

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

		foreach ( $enabled_payment_methods as $payment_method_id ) {
			$payment_method = $this->payment_methods[ $payment_method_id ];

			$settings[ $payment_method_id ] = [
				'isReusable'             => $payment_method->is_reusable(),
				'title'                  => $payment_method->get_title(),
				'description'            => $payment_method->get_description(),
				'testingInstructions'    => $payment_method->get_testing_instructions(),
				'showSaveOption'         => $this->should_upe_payment_method_show_save_option( $payment_method ),
				'supportsDeferredIntent' => $payment_method->supports_deferred_intent(),
				'countries'              => $payment_method->get_available_billing_countries(),
			];
		}

		return $settings;
	}

	/**
	 * Returns UPE enabled payment method IDs.
	 *
	 * @return string[]
	 */
	public function get_upe_enabled_payment_method_ids( $force_refresh = false ) {
		return WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids( $force_refresh );
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
		$account_domestic_currency    = WC_Stripe::get_instance()->account->get_account_default_currency();
		foreach ( $this->get_upe_enabled_payment_method_ids() as $payment_method_id ) {
			if ( ! isset( $this->payment_methods[ $payment_method_id ] ) ) {
				continue;
			}

			$method = $this->payment_methods[ $payment_method_id ];
			if ( $method->is_enabled_at_checkout( $order_id, $account_domestic_currency ) === false ) {
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
	 * See https://docs.stripe.com/payments/accept-a-payment?platform=web&ui=elements#web-create-intent for a complete list.
	 *
	 * @return string[]
	 */
	public function get_upe_available_payment_methods() {
		// If the payment method configurations API is not enabled, fall back to determining available payment methods
		// based on the plugin's internal logic.
		if ( ! WC_Stripe_Payment_Method_Configurations::is_enabled() ) {
			$available_payment_methods = [];

			foreach ( $this->payment_methods as $payment_method ) {
				if ( is_callable( [ $payment_method, 'is_available_for_account_country' ] ) && ! $payment_method->is_available_for_account_country() ) {
					continue;
				}
				$available_payment_methods[] = $payment_method->get_id();
			}
			return $available_payment_methods;
		}

		return WC_Stripe_Payment_Method_Configurations::get_upe_available_payment_method_ids();
	}

	/**
	 * Updates the enabled payment methods.
	 *
	 * @param string[] $payment_method_ids_to_enable
	 */
	public function update_enabled_payment_methods( $payment_method_ids_to_enable ) {
		// If the payment method configurations API is not enabled, we fallback to store the enabled payment methods in the DB.
		if ( ! WC_Stripe_Payment_Method_Configurations::is_enabled() ) {
			$currently_enabled_payment_method_ids      = (array) $this->get_option( 'upe_checkout_experience_accepted_payments' );
			$upe_checkout_experience_accepted_payments = [];

			foreach ( self::UPE_AVAILABLE_METHODS as $gateway ) {
				if ( in_array( $gateway::STRIPE_ID, $payment_method_ids_to_enable, true ) ) {
					$upe_checkout_experience_accepted_payments[] = $gateway::STRIPE_ID;
				}
			}
			$this->update_option( 'upe_checkout_experience_accepted_payments', $upe_checkout_experience_accepted_payments );

			// After updating payment methods record tracks events.
			$newly_enabled_methods  = array_diff( $upe_checkout_experience_accepted_payments, $currently_enabled_payment_method_ids );
			$newly_disabled_methods = array_diff( $currently_enabled_payment_method_ids, $payment_method_ids_to_enable );
			WC_Stripe_Payment_Method_Configurations::record_payment_method_settings_event( $newly_enabled_methods, $newly_disabled_methods );

			return;
		}

		$payment_method_ids_to_update = array_merge(
			$this->get_stripe_supported_payment_methods(),
			[ WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ]
		);

		WC_Stripe_Payment_Method_Configurations::update_payment_method_configuration(
			$payment_method_ids_to_enable,
			$payment_method_ids_to_update
		);
	}

	/**
	 * Returns the list of supported payment method types for Stripe.
	 *
	 * @return string[]
	 */
	private function get_stripe_supported_payment_methods() {
		$supported_stripe_ids         = [];
		$available_payment_method_ids = $this->get_upe_available_payment_methods();

		// Return the list if the payment method configurations API is enabled.
		// We don't need any additional filtering as the list is already fetched from the payment method configurations API..
		if ( WC_Stripe_Payment_Method_Configurations::is_enabled() ) {
			return $available_payment_method_ids;
		}

		foreach ( self::UPE_AVAILABLE_METHODS as $gateway_class ) {
			$gateway = new $gateway_class();

			if (
				! in_array( $gateway->get_id(), $available_payment_method_ids, true ) ||
				( $gateway->get_supported_currencies() && ! in_array( get_woocommerce_currency(), $gateway->get_supported_currencies(), true ) )
			) {
				continue;
			}

			$supported_stripe_ids[] = $gateway::STRIPE_ID;
		}

		return $supported_stripe_ids;
	}

	/**
	 * Renders the UPE input fields needed to get the user's payment information on the checkout page
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards;

			// Output the form HTML.
			?>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>

			<?php
			if ( $this->testmode ) :
				if ( $this->spe_enabled ) :
					echo wp_kses_post( self::get_testing_instructions_for_optimized_checkout() );
				else :
					?>
				<p class="testmode-info">
					<?php
					printf(
					/* translators: 1) HTML strong open tag 2) HTML strong closing tag 3) HTML anchor open tag 2) HTML anchor closing tag */
						esc_html__( '%1$sTest mode:%2$s use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed %3$shere%4$s.', 'woocommerce-gateway-stripe' ),
						'<strong>',
						'</strong>',
						'<a href="https://docs.stripe.com/testing" target="_blank">',
						'</a>'
					);
					?>
				</p>
					<?php
				endif;
			endif;
			?>

			<?php
			if ( $display_tokenization ) {
				$this->tokenization_script();
				$this->saved_payment_methods();
			}
			?>

			<fieldset id="wc-stripe-upe-form" class="wc-upe-form wc-payment-form">
				<div class="wc-stripe-upe-element" data-payment-method-type="<?php echo esc_attr( WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID ); ?>"></div>
				<div id="wc-stripe-upe-errors" role="alert"></div>
				<input id="wc-stripe-payment-method-upe" type="hidden" name="wc-stripe-payment-method-upe" />
				<input id="wc_stripe_selected_upe_payment_type" type="hidden" name="wc_stripe_selected_upe_payment_type" />
				<input type="hidden" class="wc-stripe-is-deferred-intent" name="wc-stripe-is-deferred-intent" value="1" />
			</fieldset>
			<?php
			$methods_enabled_for_saved_payments = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_for_saved_payments' ] );
			if ( $this->is_saved_cards_enabled() && ! empty( $methods_enabled_for_saved_payments ) ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
				$this->save_payment_method_checkbox( $force_save_payment );
			}

			do_action( 'wc_stripe_payment_fields_' . $this->id, $this->id );
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
	 * @param int   $order_id Reference.
	 * @param bool  $retry Should we retry on fail.
	 * @param bool  $force_save_source Force save the payment source.
	 * @param mixed $previous_error Any error message from previous request.
	 * @param bool  $use_order_source Whether to use the source, which should already be attached to the order.
	 *
	 * @return array|null An array with result of payment and redirect URL, or nothing.
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		$payment_intent_id     = isset( $_POST['wc_payment_intent_id'] ) ? wc_clean( wp_unslash( $_POST['wc_payment_intent_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order                 = wc_get_order( $order_id );
		$selected_payment_type = $this->get_selected_payment_method_type_from_request();
		$save_payment_method   = $this->should_save_payment_method_from_request( $order_id, $selected_payment_type );

		if ( $payment_intent_id && ! $this->payment_methods[ $selected_payment_type ]->supports_deferred_intent() ) {
			// Adds customer and metadata to PaymentIntent.
			// These parameters cannot be added upon updating the intent via the `/confirm` API.
			$this->intent_controller->update_intent( $payment_intent_id, $order_id, $save_payment_method, $selected_payment_type );
		}

		// Flag for using a deferred intent. To be removed.
		// https://github.com/woocommerce/woocommerce-gateway-stripe/issues/3868
		if ( ! empty( $_POST['wc-stripe-is-deferred-intent'] ) ) {
			return $this->process_payment_with_deferred_intent( $order_id );
		}

		if ( $this->maybe_change_subscription_payment_method( $order_id ) ) {
			return $this->process_change_subscription_payment_method( $order_id );
		}

		if ( $this->is_using_saved_payment_method() ) {
			return $this->process_payment_with_saved_payment_method( $order_id );
		}

		$payment_needed            = $this->is_payment_needed( $order_id );
		$selected_upe_payment_type = ! empty( $_POST['wc_stripe_selected_upe_payment_type'] ) ? wc_clean( wp_unslash( $_POST['wc_stripe_selected_upe_payment_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$is_short_statement_descriptor_enabled = ! empty( $this->get_option( 'is_short_statement_descriptor_enabled' ) ) && 'yes' === $this->get_option( 'is_short_statement_descriptor_enabled' );

		if ( $payment_intent_id ) {
			if ( $payment_needed ) {
				$amount           = $order->get_total();
				$currency         = $order->get_currency();
				$converted_amount = WC_Stripe_Helper::get_stripe_amount( $amount, $currency );

				$request = [
					'amount'      => $converted_amount,
					'currency'    => $currency,
					/* translators: 1) blog name 2) order number */
					'description' => sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() ),
				];

				// Use the dynamic + short statement descriptor if enabled and it's a card payment.
				if ( WC_Stripe_Payment_Methods::CARD === $selected_upe_payment_type && $is_short_statement_descriptor_enabled ) {
					$request['statement_descriptor_suffix'] = WC_Stripe_Helper::get_dynamic_statement_descriptor_suffix( $order );
				}

				$customer = $this->get_stripe_customer_from_order( $order );

				// Update customer or create customer if customer does not exist.
				if ( ! $customer->get_id() ) {
					$request['customer'] = $customer->create_customer();
				} else {
					$request['customer'] = $customer->update_customer();
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

				// If order requires shipping, add the shipping address details to the payment intent request.
				if ( method_exists( $order, 'get_shipping_postcode' ) && ! empty( $order->get_shipping_postcode() ) ) {
					$request['shipping'] = $this->get_address_data_for_payment_request( $order );
				}

				// Run the necessary filter to make sure mandate information is added when it's required.
				$request = apply_filters(
					'wc_stripe_generate_create_intent_request',
					$request,
					$order,
					null // $prepared_source parameter is not necessary for adding mandate information.
				);

				WC_Stripe_Helper::add_payment_intent_to_order( $payment_intent_id, $order );
				$order->update_status( OrderStatus::PENDING, __( 'Awaiting payment.', 'woocommerce-gateway-stripe' ) );
				$order->update_meta_data( '_stripe_upe_payment_type', $selected_upe_payment_type );

				// TODO: This is a stop-gap to fix a critical issue, see
				// https://github.com/woocommerce/woocommerce-gateway-stripe/issues/2536. It would
				// be better if we removed the need for additional meta data in favor of refactoring
				// this part of the payment processing.
				$order->update_meta_data( '_stripe_upe_waiting_for_redirect', true );

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
	 * Process the payment for an order using a deferred intent.
	 *
	 * @param int $order_id WC Order ID to be paid for.
	 *
	 * @return array An array with the result of the payment processing, and a redirect URL on success.
	 */
	private function process_payment_with_deferred_intent( int $order_id ) {
		if ( ! empty( $_POST['wc-stripe-confirmation-token'] ) ) {
			return $this->process_payment_with_confirmation_token( $order_id );
		}

		return $this->process_payment_with_payment_method( $order_id );
	}

	/**
	 * Process the payment for an order that has a payment method attached.
	 *
	 * @param int $order_id ID of order to be processed.
	 *
	 * @return array An array with the result of the payment processing, and a redirect URL on success.
	 */
	private function process_payment_with_payment_method( int $order_id ) {
		if ( $this->is_changing_payment_method_for_subscription() ) {
			return $this->process_change_subscription_payment_with_deferred_intent( $order_id );
		}

		$order = wc_get_order( $order_id );

		try {
			$payment_information = $this->prepare_payment_information_from_request( $order );

			$this->validate_selected_payment_method_type( $payment_information, $order->get_billing_country() );

			$payment_needed                = $this->is_payment_needed( $order->get_id() );
			$payment_method_id             = $payment_information['payment_method'];
			$payment_method_details        = $payment_information['payment_method_details'];
			$selected_payment_type         = $payment_information['selected_payment_type'];
			$is_using_saved_payment_method = $payment_information['is_using_saved_payment_method'];
			$upe_payment_method            = $this->payment_methods[ $selected_payment_type ] ?? null;
			$response_args                 = [];

			if ( $this->oc_enabled && isset( $payment_method_details->type ) ) {
				foreach ( self::UPE_AVAILABLE_METHODS as $payment_method_class ) {
					$payment_method = new $payment_method_class();
					if ( $payment_method->get_id() === $payment_method_details->type ) {
						$upe_payment_method = $payment_method;
					}
				}
			}

			// Make sure that we attach the payment method and the customer ID to the order meta data.
			$this->set_payment_method_id_for_order( $order, $payment_method_id );
			$this->set_customer_id_for_order( $order, $payment_information['customer'] );

			// Only update the payment_type if we have a reference to the payment type the customer selected.
			if ( '' !== $selected_payment_type ) {
				$this->set_selected_payment_type_for_order( $order, $selected_payment_type );
			}

			// Retrieve the payment method object from Stripe.
			$payment_method = $this->stripe_request( 'payment_methods/' . $payment_method_id );

			// Throw an exception when the payment method is a prepaid card and it's disallowed.
			$this->maybe_disallow_prepaid_card( $payment_method );

			// Until we know other payment methods need this, let's just set for BLIK.
			if ( WC_Stripe_Payment_Methods::BLIK === $selected_payment_type ) {
				$payment_information['payment_method_options'] = $this->get_payment_method_options(
					$selected_payment_type,
					$order,
					$payment_method_details
				);
			}

			// Update saved payment method to include billing details.
			if ( $is_using_saved_payment_method ) {
				$this->update_saved_payment_method( $payment_method_id, $order );
			}

			// Lock the order before we create and confirm the payment/setup intents to prevent Stripe sending the success webhook before this request is completed.
			$this->lock_order_payment( $order );

			if ( $payment_needed ) {
				// Throw an exception if the minimum order amount isn't met.
				$this->validate_minimum_order_amount( $order );

				// Create a payment intent, or update an existing one associated with the order.
				$payment_intent = $this->process_payment_intent_for_order( $order, $payment_information );
			} elseif ( $is_using_saved_payment_method && WC_Stripe_Payment_Methods::CASHAPP_PAY === $selected_payment_type ) {
				// If the payment method is Cash App Pay, the order has no cost, and a saved payment method is used, mark the order as paid.
				$this->maybe_update_source_on_subscription_order(
					$order,
					(object) [
						'payment_method' => $payment_information['payment_method'],
						'customer'       => $payment_information['customer'],
					],
					$this->get_upe_gateway_id_for_order( $upe_payment_method )
				);
				$order->payment_complete();

				return [
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				];
			} else {
				// Create a setup intent, or update an existing one associated with the order.
				$payment_intent = $this->process_setup_intent_for_order( $order, $payment_information );
			}

			// Handle saving the payment method in the store.
			// It's already attached to the Stripe customer at this point.
			if ( $payment_information['save_payment_method_to_store'] && $upe_payment_method && $upe_payment_method->get_id() === $upe_payment_method->get_retrievable_type() ) {
				$this->handle_saving_payment_method(
					$order,
					$payment_method_details,
					$selected_payment_type
				);
			} elseif ( $is_using_saved_payment_method ) {
				$this->maybe_update_source_on_subscription_order(
					$order,
					(object) [
						'payment_method' => $payment_information['payment_method'],
						'customer'       => $payment_information['customer'],
					],
					$this->get_upe_gateway_id_for_order( $upe_payment_method )
				);
			}

			// Set the selected UPE payment method type title in the WC order.
			$this->set_payment_method_title_for_order( $order, $selected_payment_type, $payment_method );

			// Save the preferred card brand on the order.
			$this->maybe_set_preferred_card_brand_for_order( $order, $payment_method );

			// Updates the redirect URL and add extra meta data to the order if the payment intent requires confirmation or action.
			// Note: BLIK falls into this condition, but we want to skip this logic for it because from this point on,
			// the confirming action is done by the customer and the confirmation comes through webhooks.
			if ( in_array( $payment_intent->status, WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION_OR_ACTION_STATUSES, true )
				&& WC_Stripe_Payment_Methods::BLIK !== $selected_payment_type ) {
				$wallet_and_voucher_methods        = array_merge( WC_Stripe_Payment_Methods::VOUCHER_PAYMENT_METHODS, WC_Stripe_Payment_Methods::WALLET_PAYMENT_METHODS );
				$contains_wallet_or_voucher_method = isset( $payment_intent->payment_method_types ) && count( array_intersect( $wallet_and_voucher_methods, $payment_intent->payment_method_types ) ) !== 0;
				$contains_redirect_next_action     = isset( $payment_intent->next_action->type ) && in_array( $payment_intent->next_action->type, [ 'redirect_to_url', 'alipay_handle_redirect' ], true )
					&& ! empty( $payment_intent->next_action->{$payment_intent->next_action->type}->url );
				if ( ! $contains_wallet_or_voucher_method && ! $contains_redirect_next_action ) {
					// Return the payment method used to process the payment so the block checkout can save the payment method.
					$response_args['payment_method'] = $payment_information['payment_method'];
				}

				// If the order requires some action from the customer, add meta to the order to prevent it from being cancelled by WooCommerce's hold stock settings.
				WC_Stripe_Helper::set_payment_awaiting_action( $order, false );

				// Prevent processing the payment intent webhooks while also processing the redirect payment (also prevents duplicate Stripe meta stored on the order).
				$order->update_meta_data( '_stripe_upe_waiting_for_redirect', true );
				$order->save();

				$redirect = $this->get_redirect_url( $this->get_return_url( $order ), $payment_intent, $payment_information, $order, $payment_needed );
			} else {
				if ( $payment_needed ) {
					// Use the last charge within the intent to proceed.
					$charge = $this->get_latest_charge_from_intent( $payment_intent );

					// Only process the response if it contains a charge object. Intents with no charge require further action like 3DS and will be processed later.
					if ( $charge ) {
						$this->process_response( $charge, $order );
					}
				} elseif ( in_array( $payment_intent->status, WC_Stripe_Intent_Status::SUCCESSFUL_STATUSES, true ) ) {
					if ( ! $this->has_pre_order( $order ) ) {
						$order->payment_complete();
					} elseif ( $this->maybe_process_pre_orders( $order ) ) {
						$this->mark_order_as_pre_ordered( $order );
					}
				}
				$redirect = $this->get_return_url( $order );
			}

			$this->unlock_order_payment( $order );

			return array_merge(
				[
					'result'   => 'success',
					'redirect' => $redirect,
				],
				$response_args
			);
		} catch ( WC_Stripe_Exception $e ) {
			return $this->handle_process_payment_error( $e, $order );
		}
	}

	/**
	 * Process the payment for an order that has a confirmation token attached.
	 *
	 * @param int $order_id ID of order to be processed.
	 *
	 * @return array An array with the result of the payment processing, and a redirect URL on success.
	 */
	private function process_payment_with_confirmation_token( int $order_id ) {
		$order = wc_get_order( $order_id );

		try {
			$payment_information = $this->prepare_payment_information_from_request( $order );

			$this->validate_selected_payment_method_type( $payment_information, $order->get_billing_country() );

			$this->set_customer_id_for_order( $order, $payment_information['customer'] );

			$payment_needed = $this->is_payment_needed( $order->get_id() );

			if ( $payment_needed ) {
				$payment_intent = $this->process_payment_intent_for_order( $order, $payment_information );
			} else {
				// TODO: add confirmation token support, if possible.
				$payment_intent = $this->process_setup_intent_for_order( $order, $payment_information );
			}

			$payment_method_id = $payment_intent->payment_method;
			$this->set_payment_method_id_for_order( $order, $payment_method_id );

			$selected_payment_type = $payment_information['selected_payment_type'];

			// Retrieve the payment method object from Stripe.
			$payment_method = $this->stripe_request( 'payment_methods/' . $payment_method_id );

			$this->set_payment_method_title_for_order( $order, $selected_payment_type, $payment_method );

			if ( $payment_needed ) {
				// Use the last charge within the intent to proceed.
				$charge = $this->get_latest_charge_from_intent( $payment_intent );

				if ( $charge ) {
					$this->process_response( $charge, $order );
				}
			}

			if ( $payment_information['save_payment_method_to_store'] ) {
				$this->handle_saving_payment_method(
					$order,
					$payment_method,
					$selected_payment_type
				);
			}

			$return_url = $this->get_return_url( $order );

			return [
				'result'   => 'success',
				'redirect' => $return_url,
			];
		} catch ( WC_Stripe_Exception $e ) {
			return $this->handle_process_payment_error( $e, $order );
		}
	}

	/**
	 * Handle errors that occur during the payment processing.
	 *
	 * @param WC_Stripe_Exception $e    The exception that was thrown.
	 * @param WC_Order            $order The order that was being processed.
	 *
	 * @return array
	 */
	private function handle_process_payment_error( WC_Stripe_Exception $e, $order ) {
		$error_message = sprintf(
			/* translators: localized exception message */
			__( 'There was an error processing the payment: %s', 'woocommerce-gateway-stripe' ),
			$e->getLocalizedMessage()
		);

		wc_add_notice( $error_message, 'error' );

		WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

		do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

		$order->update_status(
			OrderStatus::FAILED,
			/* translators: localized exception message */
			sprintf( __( 'Payment failed: %s', 'woocommerce-gateway-stripe' ), $e->getLocalizedMessage() )
		);

		return [
			'result'   => 'failure',
			'redirect' => '',
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

				// If order requires shipping, add the shipping address details to the payment intent request.
				if ( method_exists( $order, 'get_shipping_postcode' ) && ! empty( $order->get_shipping_postcode() ) ) {
					$request['shipping'] = $this->get_address_data_for_payment_request( $order );
				}

				if ( $this->has_subscription( $order_id ) ) {
					$request['setup_future_usage'] = 'off_session';
				}

				// Run the necessary filter to make sure mandate information is added when it's required.
				$request = apply_filters(
					'wc_stripe_generate_create_intent_request',
					$request,
					$order,
					null // $prepared_source parameter is not necessary for adding mandate information.
				);

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
					if ( in_array( WC_Stripe_Payment_Methods::SEPA_DEBIT, array_values( $enabled_payment_methods ), true ) ) {
						$request = WC_Stripe_Helper::add_mandate_data( $request );
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

			if ( WC_Stripe_Intent_Status::REQUIRES_ACTION === $intent->status || WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION === $intent->status ) {
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
				$this->process_response( $this->get_latest_charge_from_intent( $intent ), $order );
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
			$order->update_status( OrderStatus::FAILED );

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
		if ( $this->is_payment_methods_page() || $this->is_changing_payment_method_for_subscription() ) {
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
						$token           = $this->create_token_from_setup_intent( $setup_intent_id, wp_get_current_user() );

						$customer_data         = WC_Stripe_Customer::map_customer_data( null, new WC_Customer( $user_id ) );
						$payment_method_object = $this->stripe_request(
							'payment_methods/' . $token->get_token(),
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
						wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
					} catch ( Exception $e ) {
						WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
					}
				} else {
					wc_add_notice( __( 'Failed to add payment method.', 'woocommerce-gateway-stripe' ), 'error', [ 'icon' => 'error' ] );
				}
			}
			return;
		}

		if ( ! parent::is_valid_order_received_endpoint() ) {
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

		$order_id            = isset( $_GET['order_id'] ) ? absint( wc_clean( wp_unslash( $_GET['order_id'] ) ) ) : '';
		$save_payment_method = isset( $_GET['save_payment_method'] ) ? 'yes' === wc_clean( wp_unslash( $_GET['save_payment_method'] ) ) : false;

		if ( ! empty( $_GET['payment_intent_client_secret'] ) ) {
			$intent_id = isset( $_GET['payment_intent'] ) ? wc_clean( wp_unslash( $_GET['payment_intent'] ) ) : '';
			if ( ! $this->is_order_associated_to_payment_intent( $order_id, $intent_id ) ) {
				return;
			}
		} elseif ( ! empty( $_GET['setup_intent_client_secret'] ) ) {
			$intent_id = isset( $_GET['setup_intent'] ) ? wc_clean( wp_unslash( $_GET['setup_intent'] ) ) : '';
			if ( ! $this->is_order_associated_to_setup_intent( $order_id, $intent_id ) ) {
				return;
			}
		} else {
			return;
		}

		if ( empty( $intent_id ) ) {
			return;
		}

		$this->process_upe_redirect_payment(
			$order_id,
			$intent_id,
			$save_payment_method,
			isset( $_GET['pay_for_order'] ) && 'yes' === $_GET['pay_for_order']
		);
	}

	/**
	 * Ensure the order is associated to the payment intent.
	 *
	 * @param int $order_id The order ID.
	 * @param string $intent_id The payment intent ID.
	 * @return bool
	 */
	private function is_order_associated_to_payment_intent( int $order_id, string $intent_id ): bool {
		$order_from_payment_intent = WC_Stripe_Helper::get_order_by_intent_id( $intent_id );
		return $order_from_payment_intent && $order_from_payment_intent->get_id() === $order_id;
	}

	/**
	 * Ensure the order is associated to the setup intent.
	 *
	 * @param int $order_id The order ID.
	 * @param string $intent_id The setup intent ID.
	 * @return bool
	 */
	private function is_order_associated_to_setup_intent( int $order_id, string $intent_id ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$intent = $this->stripe_request( 'setup_intents/' . $intent_id . '?expand[]=payment_method.billing_details' );
		if ( ! $intent ) {
			return false;
		}

		if ( ! isset( $intent->payment_method ) || ! isset( $intent->payment_method->billing_details ) ) {
			return false;
		}

		if ( $order->get_billing_email() !== $intent->payment_method->billing_details->email ) {
			return false;
		}

		return true;
	}

	/**
	 * Processes UPE redirect payments.
	 *
	 * @param int    $order_id The order ID being processed.
	 * @param string $intent_id The Stripe setup/payment intent ID for the order payment.
	 * @param bool   $save_payment_method Boolean representing whether payment method for order should be saved.
	 * @param bool $is_pay_for_order True if processing payment from Pay for Order page. Optional.
	 *
	 * @since 5.5.0
	 * @version 5.5.0
	 */
	public function process_upe_redirect_payment( $order_id, $intent_id, $save_payment_method, $is_pay_for_order = false ) {
		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) ) {
			return;
		}

		if ( $order->has_status( [ OrderStatus::PROCESSING, OrderStatus::COMPLETED, OrderStatus::ON_HOLD ] ) ) {
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
			$order->update_status( OrderStatus::FAILED, sprintf( __( 'UPE payment failed: %s', 'woocommerce-gateway-stripe' ), $e->getMessage() ) );

			wc_add_notice( $e->getMessage(), 'error' );

			$redirect_url = '';
			if ( $is_pay_for_order ) {
				$redirect_url = $order->get_checkout_payment_url();
			} else {
				$redirect_url = wc_get_checkout_url();
			}
			wp_safe_redirect( wp_sanitize_redirect( $redirect_url ) );

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

		$is_pre_order = false;
		if ( $this->has_pre_order( $order->get_id() ) ) {
			// If this is a pre-order, simply mark the order as pre-ordered and allow
			// the subsequent logic to save the payment method and proceed to complete the order.
			$this->mark_order_as_pre_ordered( $order->get_id() );

			// We require to save the payment method if the pre-order is charged upon release.
			$save_payment_method = $save_payment_method || $this->has_pre_order_charged_upon_release( $order );
			$is_pre_order        = true;
		}

		if ( $save_payment_method && $payment_method->is_reusable() ) {
			$payment_method_object = null;
			if ( $payment_method->get_id() !== $payment_method->get_retrievable_type() ) {
				$generated_payment_method_id = $payment_method_details[ $payment_method_type ]->generated_sepa_debit;
				$payment_method_object       = $this->stripe_request( "payment_methods/$generated_payment_method_id", [], null, 'GET' );

				// This is our first opportunity to save the payment method for payment methods that have a different retrievable type. Save it now.
				$payment_method->create_payment_token_for_user( $order->get_customer_id(), $payment_method_object );
			} else {
				$payment_method_object = $intent->payment_method;
			}

			$customer                = $this->get_stripe_customer_from_order( $order );
			$prepared_payment_method = $this->prepare_payment_method( $payment_method_object );

			$customer->clear_cache();
			$this->save_payment_method_to_order( $order, $prepared_payment_method );
			do_action( 'woocommerce_stripe_add_payment_method', $customer->get_user_id(), $payment_method_object );
		}

		if ( ! $is_pre_order ) {
			if ( $payment_needed ) {
				// Use the last charge within the intent to proceed.
				$this->process_response( $this->get_latest_charge_from_intent( $intent ), $order );
			} else {
				$order->payment_complete();
			}
		}

		$this->save_intent_to_order( $order, $intent );
		$this->set_payment_method_title_for_order( $order, $payment_method_type );
		$order->update_meta_data( '_stripe_upe_redirect_processed', true );

		// TODO: This is a stop-gap to fix a critical issue, see
		// https://github.com/woocommerce/woocommerce-gateway-stripe/issues/2536. It would
		// be better if we removed the need for additional meta data in favor of refactoring
		// this part of the payment processing.
		$order->delete_meta_data( '_stripe_upe_waiting_for_redirect' );

		/**
		 * This meta is to prevent stores with short hold stock settings from cancelling orders while waiting for payment to be finalised by Stripe or the customer (i.e. completing 3DS or payment redirects).
		 * Now that payment is confirmed, we can remove this meta.
		 */
		WC_Stripe_Helper::remove_payment_awaiting_action( $order, false );

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

		// Fetch the payment method ID from the payment method object.
		if ( isset( $this->payment_methods[ $payment_method->payment_method_object->type ] ) ) {
			$payment_method_id = $this->get_upe_gateway_id_for_order( $this->payment_methods[ $payment_method->payment_method_object->type ] );
		}

		$this->maybe_update_source_on_subscription_order( $order, $payment_method, $payment_method_id ?? $this->id );
	}

	/**
	 * Retries the payment process once an error occured.
	 *
	 * @param object   $response          The response from the Stripe API.
	 * @param WC_Order $order             An order that is being paid for.
	 * @param bool     $retry             A flag that indicates whether another retry should be attempted.
	 * @param bool     $force_save_source Force save the payment source.
	 * @param mixed    $previous_error    Any error message from previous request.
	 * @param bool     $use_order_source  Whether to use the source, which should already be attached to the order.
	 * @throws WC_Stripe_Exception If the payment is not accepted.
	 * @return array|void
	 */
	public function retry_after_error( $response, $order, $retry, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		if ( ! $retry ) {
			$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-gateway-stripe' );
			$order->add_order_note( $localized_message );
			throw new WC_Stripe_Exception( print_r( $intent, true ), $localized_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.
		}

		// Don't do anymore retries after this.
		if ( 5 <= $this->retry_interval ) {
			return $this->process_payment( $order->get_id(), false, $force_save_source, $response->error, $previous_error );
		}

		sleep( $this->retry_interval );
		++$this->retry_interval;

		return $this->process_payment( $order->get_id(), true, $force_save_source, $response->error, $previous_error );
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
		$is_pay_for_order_page = parent::is_valid_pay_for_order_endpoint();

		// Check if the cart contains a pre-order product. Ignore the cart if we're on the Pay for Order page.
		if ( $this->is_pre_order_item_in_cart() && ! $is_pay_for_order_page ) {
			$pre_order_product = $this->get_pre_order_product_from_cart();

			// Only one pre-order product is allowed per cart,
			// so we can return if it's charged upfront.
			return $this->is_pre_order_product_charged_upfront( $pre_order_product );
		}

		if ( ! empty( $order_id ) && $this->has_pre_order( $order_id ) ) {
			$pre_order_product  = $this->get_pre_order_product_from_order( $order_id );
			$is_charged_upfront = $this->is_pre_order_product_charged_upfront( $pre_order_product );

			// If the pre-order is set to charge upon release and we're on the pay for order page and the pre-order has completed status, payment is needed.
			if ( ! $is_charged_upfront && $is_pay_for_order_page && $this->is_pre_order_completed( $order_id ) ) {
				return true;
			}

			return $is_charged_upfront;
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
			&& ( WC_Stripe_Payment_Methods::CARD === $payment_method->type )
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
	 * Get WC Stripe Customer from WC Order.
	 *
	 * @param WC_Order $order
	 *
	 * @return WC_Stripe_Customer
	 */
	public function get_stripe_customer_from_order( $order ) {
		$user     = $this->get_user_from_order( $order );
		$customer = new WC_Stripe_Customer( $user->ID );

		return $customer;
	}

	/**
	 * Checks if gateway should be available to use.
	 *
	 * @since 5.6.0
	 */
	public function is_available() {
		// The main UPE gateway represents the card payment method. So it's only available if the card payment method is enabled and available.
		if ( isset( $this->payment_methods['card'] ) && ( ! $this->payment_methods['card']->is_enabled() || ! $this->payment_methods['card']->is_available() ) ) {
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

		$account_domestic_currency = WC_Stripe::get_instance()->account->get_account_default_currency();

		return $this->payment_methods[ $payment_method_id ]->is_enabled_at_checkout( null, $account_domestic_currency );
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
	 * Checks if the setting to allow the saving of SEPA tokens for other payment methods (iDEAL and Bancontact) is enabled.
	 *
	 * @return bool Whether the setting to allow SEPA tokens for other payment methods is enabled.
	 */
	public function is_sepa_tokens_for_other_methods_enabled() {
		return $this->sepa_tokens_for_other_methods;
	}

	/**
	 * Checks if the Single Payment Element setting is enabled.
	 *
	 * @return bool Whether the Single Payment Element setting is enabled.
	 *
	 * @deprecated 9.5.0 Use is_oc_enabled() instead.
	 */
	public function is_spe_enabled() {
		return $this->spe_enabled;
	}

	/**
	 * Checks if the Optimized Checkout (previously known as SPE) setting is enabled.
	 *
	 * @return bool Whether the Optimized Checkout setting is enabled.
	 */
	public function is_oc_enabled() {
		return $this->oc_enabled;
	}

	/**
	 * Set formatted readable payment method title for order,
	 * using payment method details from accompanying charge.
	 *
	 * @param WC_Order      $order WC Order being processed.
	 * @param string        $payment_method_type Stripe payment method key.
	 * @param stdClass|bool $stripe_payment_method Stripe payment method object.
	 *
	 * @since 5.5.0
	 * @version 5.5.0
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_type, $stripe_payment_method = false ) {
		if ( ! isset( $this->payment_methods[ $payment_method_type ] ) ) {
			return;
		}

		$payment_method    = $this->payment_methods[ $payment_method_type ];
		$payment_method_id = $payment_method instanceof WC_Stripe_UPE_Payment_Method_CC ? $this->id : $payment_method->id;
		$is_stripe_link    = WC_Stripe_Payment_Methods::LINK === $payment_method_type ||
			( isset( $stripe_payment_method->type ) && WC_Stripe_Payment_Methods::LINK === $stripe_payment_method->type );

		// Stripe Link uses the main gateway to process payments, however Link payments should use the title of the Link payment method.
		if ( $is_stripe_link && isset( $this->payment_methods[ WC_Stripe_Payment_Methods::LINK ] ) ) {
			$payment_method_id    = $this->id;
			$payment_method_title = $this->payment_methods[ WC_Stripe_Payment_Methods::LINK ]->get_title( $stripe_payment_method );
		} else {
			$payment_method_title = $payment_method->get_title( $stripe_payment_method );
		}

		$order->set_payment_method( $payment_method_id );
		$order->set_payment_method_title( $payment_method_title );
		$order->save();

		// Update the subscription's purchased in this order with the payment method ID.
		$this->update_subscription_payment_method_from_order( $order, $this->get_upe_gateway_id_for_order( $payment_method ) );
	}

	/**
	 * This is overloading the upe checkout experience type on the settings page.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_upe_checkout_experience_accepted_payments_html( $key, $data ) {
		try {
			$stripe_account = $this->stripe_request( 'account' );
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
		}

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
			$capability_id        = WC_Stripe_Helper::get_payment_method_capability_id( $method_id );
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
	 * Extacts the Stripe intent's payment_method_type and payment_method_details values.
	 *
	 * @param $intent   Stripe's intent response.
	 * @return string[] List with 2 values: payment_method_type and payment_method_details.
	 */
	private function get_payment_method_data_from_intent( $intent ) {
		$payment_method_type    = '';
		$payment_method_details = false;

		if ( 'payment_intent' === $intent->object ) {
			$charge = $this->get_latest_charge_from_intent( $intent );
			if ( ! empty( $charge ) ) {
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

		$metadata = [
			'customer_name'  => $name,
			'customer_email' => $email,
			'site_url'       => esc_url( get_site_url() ),
			'order_id'       => $order->get_order_number(),
			'order_key'      => $order->get_order_key(),
			'payment_type'   => $payment_type,
			'signature'      => $this->get_order_signature( $order ),
			'tax_amount'     => WC_Stripe_Helper::get_stripe_amount( $order->get_total_tax(), strtolower( $order->get_currency() ) ),
		];

		return apply_filters( 'wc_stripe_intent_metadata', $metadata, $order );
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
	 * @return WC_Payment_Token The added token.
	 *
	 * @since 5.8.0
	 * @version 5.8.0
	 */
	public function create_token_from_setup_intent( $setup_intent_id, $user ) {
		try {
			$setup_intent = $this->stripe_request( 'setup_intents/' . $setup_intent_id . '?&expand[]=latest_attempt' );
			if ( ! empty( $setup_intent->last_payment_error ) ) {
				throw new WC_Stripe_Exception( __( "We're not able to add this payment method. Please try again later.", 'woocommerce-gateway-stripe' ) );
			}

			list( $payment_method_type, $payment_method_details ) = $this->get_payment_method_data_from_intent( $setup_intent );

			$payment_method_id = $setup_intent->payment_method;

			$payment_method = null;
			if ( $this->oc_enabled ) {
				$payment_method_type = $payment_method_details['type'] ?? $payment_method_details->type ?? null;
				if ( ! empty( $payment_method_type ) ) {
					foreach ( self::UPE_AVAILABLE_METHODS as $payment_method_class ) {
						$payment_method_instance = new $payment_method_class();
						if ( $payment_method_instance->get_id() === $payment_method_type ) {
							$payment_method = $payment_method_instance;
						}
					}
				}
			} else {
				$payment_method = $this->payment_methods[ $payment_method_type ] ?? null;
			}

			if ( ! $payment_method ) {
				throw new WC_Stripe_Exception( __( "We're not able to add this payment method. Please try again later.", 'woocommerce-gateway-stripe' ) );
			}

			if ( $payment_method->get_id() !== $payment_method->get_retrievable_type() ) {
				$payment_method_id = $payment_method_details[ $payment_method_type ]->generated_sepa_debit;
			}

			$payment_method_object = $this->stripe_request( 'payment_methods/' . $payment_method_id );

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

	/**
	 * Returns an array of address data to be used in a Stripe /payment_intents API request.
	 *
	 * Stripe docs: https://docs.stripe.com/api/payment_intents/create#create_payment_intent-shipping
	 *
	 * @since 7.7.0
	 *
	 * @param WC_Order $order Order to fetch address data from.
	 *
	 * @return array
	 */
	private function get_address_data_for_payment_request( $order ) {
		return [
			'name'    => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
			'address' => [
				'line1'       => $order->get_shipping_address_1(),
				'line2'       => $order->get_shipping_address_2(),
				'city'        => $order->get_shipping_city(),
				'country'     => $order->get_shipping_country(),
				'postal_code' => $order->get_shipping_postcode(),
				'state'       => $order->get_shipping_state(),
			],
		];
	}

	/**
	 * Create a payment intent for the order, or update the existing one.
	 *
	 * @param WC_Order $order The WC Order for which we're handling a payment intent.
	 * @param array    $payment_information The payment information to be used for the payment intent.
	 * @param bool     $retry Whether we should retry if this processing fails.
	 *
	 * @throws WC_Stripe_Exception When there's an error creating or updating the payment intent, and can't be retried.
	 *
	 * @return stdClass
	 */
	private function process_payment_intent_for_order( WC_Order $order, array $payment_information, $retry = true ) {
		// Check if order already has a successful payment intent
		$existing_intent = $this->get_intent_from_order( $order );
		if ( $existing_intent && isset( $existing_intent->id ) && 'pi_' === substr( $existing_intent->id, 0, 3 ) ) {
			// Fetch the latest intent data from Stripe
			$intent = $this->stripe_request( 'payment_intents/' . $existing_intent->id );

			// If the intent is already successful, return it to prevent duplicate charges
			if ( isset( $intent->status ) && in_array( $intent->status, self::SUCCESSFUL_INTENT_STATUS, true ) ) {
				return $intent;
			}
		}

		// Check if the order has a payment intent that is compatible with the current payment method types.
		$payment_intent = $this->get_existing_compatible_payment_intent( $order, $payment_information['payment_method_types'] );

		// If the payment intent is not compatible, we need to create a new one. Throws an exception on error.
		if ( $payment_intent ) {
			// Update the existing payment intent if one exists.
			$payment_intent = $this->intent_controller->update_and_confirm_payment_intent( $payment_intent, $payment_information );
		} else {
			// Create (and confirm) a new payment intent if one doesn't exist.
			$payment_intent = $this->intent_controller->create_and_confirm_payment_intent( $payment_information );
		}

		// Handle an error in the payment intent.
		if ( ! empty( $payment_intent->error ) ) {

			// Add the payment intent information to the order meta
			// if we were able to create one despite the error.
			if ( ! empty( $payment_intent->error->payment_intent ) ) {
				$this->save_intent_to_order( $order, $payment_intent->error->payment_intent );
			}

			$has_removed_customer = $this->maybe_remove_non_existent_customer( $payment_intent->error, $order );

			if ( ! $this->is_retryable_error( $payment_intent->error ) || ! $retry ) {
				throw new WC_Stripe_Exception(
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					print_r( $payment_intent, true ),
					$this->get_payment_intent_error_message( $payment_intent )
				);
			}

			// If the non existent customer was removed, we need to recreate a customer.
			if ( $has_removed_customer ) {
				$payment_information['customer'] = $this->get_customer_id_for_order( $order );
			}

			// Don't do anymore retries after this.
			if ( 5 <= $this->retry_interval ) {
				return $this->process_payment_intent_for_order( $order, $payment_information, false );
			}

			sleep( $this->retry_interval );
			++$this->retry_interval;

			return $this->process_payment_intent_for_order( $order, $payment_information, true );
		}

		// Add the payment intent information to the order meta.
		$this->save_intent_to_order( $order, $payment_intent );

		return $payment_intent;
	}

	/**
	 * Return specific error messages for payment intent errors.
	 *
	 * @param stdClass $payment_intent The payment intent object.
	 * @return string The error message.
	 */
	private function get_payment_intent_error_message( $payment_intent ) {
		if ( isset( $payment_intent->error->payment_intent->payment_method_types[0] ) &&
			'amazon_pay' === $payment_intent->error->payment_intent->payment_method_types[0] &&
			isset( $payment_intent->error->decline_code ) &&
			'generic_decline' === $payment_intent->error->decline_code
		) {
			return __(
				'Amazon Pay is not compatible for this order. Please try a different payment method.',
				'woocommerce-gateway-stripe'
			);
		}

		return $payment_intent->error->message;
	}

	/**
	 * Create a setup intent for the order.
	 *
	 * @param WC_Order $order               The WC Order for which we're handling a setup intent.
	 * @param array    $payment_information The payment information to be used for the setup intent.
	 *
	 * @throws WC_Stripe_Exception When there's an error creating the setup intent.
	 *
	 * @return stdClass
	 */
	protected function process_setup_intent_for_order( WC_Order $order, array $payment_information ) {
		$setup_intent = $this->intent_controller->create_and_confirm_setup_intent( $payment_information );

		if ( ! empty( $setup_intent->error ) ) {

			// Add the setup intent information to the order meta, if one was created despite the error.
			if ( ! empty( $setup_intent->error->payment_intent ) ) {
				$this->save_intent_to_order( $order, $setup_intent->error->payment_intent );
			}

			$this->maybe_remove_non_existent_customer( $setup_intent->error, $order );

			throw new WC_Stripe_Exception(
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				print_r( $setup_intent, true ),
				__( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-gateway-stripe' )
			);
		}

		// Add the payment intent information to the order meta.
		$this->save_intent_to_order( $order, $setup_intent );

		return $setup_intent;
	}

	/**
	 * Collects the payment information needed for processing a payment intent.
	 *
	 * @param WC_Order $order The WC Order to be paid for.
	 * @return array An array containing the payment information for processing a payment intent.
	 * @throws WC_Stripe_Exception When there's an error retrieving the payment information.
	 */
	protected function prepare_payment_information_from_request( WC_Order $order ) {
		$selected_payment_type = $this->get_selected_payment_method_type_from_request();
		$capture_method        = $this->is_automatic_capture_enabled() ? 'automatic' : 'manual'; // automatic | manual.
		$currency              = strtolower( $order->get_currency() );
		$amount                = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency );
		$shipping_details      = null;
		$token                 = false;

		$save_payment_method_to_store  = $this->should_save_payment_method_from_request( $order->get_id(), $selected_payment_type );
		$is_using_saved_payment_method = $this->is_using_saved_payment_method();

		// If order requires shipping, add the shipping address details to the payment intent request.
		if ( method_exists( $order, 'get_shipping_postcode' ) && ! empty( $order->get_shipping_postcode() ) ) {
			$shipping_details = $this->get_address_data_for_payment_request( $order );
		}

		if ( $is_using_saved_payment_method ) {
			// Use the saved payment method.
			$token = WC_Stripe_Payment_Tokens::get_token_from_request( $_POST );

			// A valid token couldn't be retrieved from the request.
			if ( null === $token ) {
				throw new WC_Stripe_Exception(
					'A valid payment method token could not be retrieved from the request.',
					__( "The selected payment method isn't valid.", 'woocommerce-gateway-stripe' )
				);
			}

			$payment_method_id = $token->get_token();

			if ( is_a( $token, 'WC_Payment_Token_SEPA' ) ) {
				$selected_payment_type = WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID;
			} elseif ( is_a( $token, 'WC_Payment_Token_Amazon_Pay' ) ) {
				$selected_payment_type = WC_Stripe_UPE_Payment_Method_Amazon_Pay::STRIPE_ID;
			}
		} else {
			$payment_method_id = sanitize_text_field( wp_unslash( $_POST['wc-stripe-payment-method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$payment_method_details = ! empty( $payment_method_id ) ? WC_Stripe_API::get_payment_method( $payment_method_id ) : (object) [];

		$payment_method_types = $this->get_payment_method_types_for_intent_creation(
			$selected_payment_type,
			$order->get_id(),
			$this->get_express_payment_type_from_request(),
			( $payment_method_details->type ?? null )
		);

		$payment_information = [
			'amount'                        => $amount,
			'currency'                      => $currency,
			'customer'                      => $this->get_customer_id_for_order( $order ),
			'is_using_saved_payment_method' => $is_using_saved_payment_method,
			'level3'                        => $this->get_level3_data_from_order( $order ),
			'metadata'                      => $this->get_metadata_from_order( $order ),
			'order'                         => $order,
			'payment_initiated_by'          => 'initiated_by_customer', // initiated_by_merchant | initiated_by_customer.
			'selected_payment_type'         => $selected_payment_type,
			'payment_method_types'          => $payment_method_types,
			'shipping'                      => $shipping_details,
			'token'                         => $token,
			'return_url'                    => $this->get_return_url_for_redirect( $order, $save_payment_method_to_store ),
			'use_stripe_sdk'                => 'true', // We want to use the SDK to handle next actions via the client payment elements. See https://docs.stripe.com/api/setup_intents/create#create_setup_intent-use_stripe_sdk
			'has_subscription'              => $this->has_subscription( $order->get_id() ),
			'payment_method'                => $payment_method_id,
			'payment_method_details'        => $payment_method_details,
			'payment_type'                  => 'single', // single | recurring.
			'save_payment_method_to_store'  => $save_payment_method_to_store,
			'capture_method'                => $capture_method,
		];

		if ( WC_Stripe_Payment_Methods::ACH === $selected_payment_type ) {
			WC_Stripe_API::attach_payment_method_to_customer( $payment_information['customer'], $payment_method_id );
		}

		// Use the dynamic + short statement descriptor if enabled and it's a card payment.
		$is_short_statement_descriptor_enabled = 'yes' === $this->get_option( 'is_short_statement_descriptor_enabled', 'no' );
		if ( WC_Stripe_Payment_Methods::CARD === $selected_payment_type && $is_short_statement_descriptor_enabled ) {
			$payment_information['statement_descriptor_suffix'] = WC_Stripe_Helper::get_dynamic_statement_descriptor_suffix( $order );
		}

		if ( empty( $payment_method_id ) && ! empty( $_POST['wc-stripe-confirmation-token'] ) ) {
			// Add fields that are only set when using the confirmation token flow.
			$payment_information = $this->prepare_payment_information_for_confirmation_token(
				$payment_information,
				$selected_payment_type,
				$capture_method,
			);
		} else {
			// Add fields that are only set when using the payment method flow.
			$payment_information = $this->prepare_payment_information_for_payment_method( $payment_information, $selected_payment_type, $order );
		}

		return $payment_information;
	}

	/**
	 * Add or remove payment information fields for the confirmation token flow.
	 *
	 * @param array $payment_information The base payment information.
	 * @param string $selected_payment_type The selected payment type.
	 * @param string $capture_method The capture method to be used.
	 * @return array The customized payment information for the confirmation token flow.
	 */
	private function prepare_payment_information_for_confirmation_token( $payment_information, $selected_payment_type, $capture_method ) {
		// These fields should not be set when using confirmation tokens to create a payment intent.
		unset( $payment_information['payment_method'] );
		unset( $payment_information['payment_method_details'] );

		$confirmation_token_id                     = sanitize_text_field( wp_unslash( $_POST['wc-stripe-confirmation-token'] ?? '' ) );
		$payment_information['confirmation_token'] = $confirmation_token_id;

		// Some payment methods such as Amazon Pay will only accept a capture_method of 'manual'
		// under payment_method_options instead of at the top level.
		if ( 'manual' === $capture_method ) {
			unset( $payment_information['capture_method'] );
			$payment_information['payment_method_options'][ $selected_payment_type ]['capture_method'] = 'manual';
		}

		if ( $payment_information['has_subscription'] ) {
			$payment_information['payment_method_options'][ $selected_payment_type ]['setup_future_usage'] = 'off_session';
		}

		return $payment_information;
	}

	/**
	 * Add or remove payment information fields for the payment method flow.
	 *
	 * @param array $payment_information The base payment information.
	 * @param string $selected_payment_type The selected payment type.
	 * @param WC_Order $order The WC Order being processed.
	 * @return array The customized payment information for the payment method flow.
	 */
	private function prepare_payment_information_for_payment_method( $payment_information, $selected_payment_type, $order ) {
		$payment_information['payment_method_options'] = $this->get_payment_method_options(
			$selected_payment_type,
			$order,
			$payment_information['payment_method_details']
		);

		return $payment_information;
	}

	/**
	 * Returns the payment method options for the selected payment type.
	 *
	 * @param string   $selected_payment_type  The selected payment type, e.g. 'klarna'
	 * @param WC_Order $order                  The WC Order we are processing a payment for.
	 * @param stdClass $payment_method_details The payment method details.
	 */
	private function get_payment_method_options( $selected_payment_type, $order, $payment_method_details ) {
		$payment_method_options = [];

		// Specify the client in payment_method_options (currently, Checkout only supports a client value of "web")
		if ( WC_Stripe_Payment_Methods::WECHAT_PAY === $selected_payment_type ) {
			$payment_method_options = [
				WC_Stripe_Payment_Methods::WECHAT_PAY => [
					'client' => 'web',
				],
			];
		} elseif ( WC_Stripe_Payment_Methods::KLARNA === $selected_payment_type ) {
			$preferred_locale = WC_Stripe_Helper::get_klarna_preferred_locale(
				get_locale(),
				$order->get_billing_country()
			);

			if ( ! empty( $preferred_locale ) ) {
				$payment_method_options = [
					WC_Stripe_Payment_Methods::KLARNA => [
						'preferred_locale' => $preferred_locale,
					],
				];
			}
		} elseif ( WC_Stripe_Payment_Methods::BLIK === $selected_payment_type ) {
			$payment_method_options = [
				WC_Stripe_Payment_Methods::BLIK => [
					'code' => sanitize_text_field( wp_unslash( $_POST['wc-stripe-blik-code'] ?? '' ) ),
				],
			];
		}

		// Add the updated preferred credit card brand when defined
		$preferred_brand = $payment_method_details->card->networks->preferred ?? null;
		if ( isset( $preferred_brand ) ) {
			$payment_method_options = [
				'card' => [
					'network' => $preferred_brand,
				],
			];
		}

		return $payment_method_options;
	}

	/**
	 * Conditionally stores the card brand to the order meta.
	 *
	 * @param WC_Order $order          The WC Order for which we're processing a payment.
	 * @param stdClass $payment_method The payment method object.
	 */
	private function maybe_set_preferred_card_brand_for_order( WC_Order $order, $payment_method ) {
		// Retrieve the preferred card brand for the payment method.
		$preferred_brand = $payment_method->card->networks->preferred ?? null;
		if ( WC_Stripe_Co_Branded_CC_Compatibility::is_wc_supported() && $preferred_brand ) {

			$order->update_meta_data( '_stripe_card_brand', $preferred_brand );
			$order->save_meta_data();

			if ( function_exists( 'wc_admin_record_tracks_event' ) ) {
				wc_admin_record_tracks_event( 'wcstripe_co_branded_cc_preferred_brand_selected', [ 'brand' => $preferred_brand ] );
			}
		}
	}

	/**
	 * Returns whether the selected payment method should be saved.
	 *
	 * We want to save it for subscriptions and when the shopper chooses to,
	 * as long as the selected payment method type is reusable.
	 *
	 * @param int    $order_id            The ID of the order we're processing.
	 * @param string $payment_method_type
	 *
	 * @return boolean
	 */
	private function should_save_payment_method_from_request( $order_id, $payment_method_type ) {
		// Don't save it when the type is unknown or not reusable.
		if (
			! isset( $this->payment_methods[ $payment_method_type ] ) ||
			! $this->payment_methods[ $payment_method_type ]->is_reusable()
		) {
			return false;
		}

		// Don't save it if we're using a saved payment method.
		if ( $this->is_using_saved_payment_method() ) {
			return false;
		}

		// Save it when paying for a subscription.
		if ( $this->has_subscription( $order_id ) ) {
			return true;
		}

		// Unless it's paying for a subscription, don't save it when saving payment methods is disabled.
		if ( ! $this->is_saved_cards_enabled() ) {
			return false;
		}

		// For card/stripe, the request arg is `wc-stripe-new-payment-method` and for our reusable APMs (i.e. bancontact) it's `wc-stripe_bancontact-new-payment-method`.
		$save_payment_method_request_arg = sprintf( 'wc-stripe%s-new-payment-method', WC_Stripe_Payment_Methods::CARD !== $payment_method_type ? '_' . $payment_method_type : '' );

		// Don't save it if we don't have the data from the checkout checkbox for saving a payment method.
		if ( ! isset( $_POST[ $save_payment_method_request_arg ] ) ) {
			return false;
		}

		// Save it when the checkout checkbox for saving a payment method was checked off.
		$save_payment_method = wc_clean( wp_unslash( $_POST[ $save_payment_method_request_arg ] ) );

		// Its value is 'true' for classic and '1' for block.
		if ( in_array( $save_payment_method, [ 'true', '1' ], true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Gets the selected payment method type from the request and normalizes its slug for internal use.
	 *
	 * @return string
	 */
	private function get_selected_payment_method_type_from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['payment_method'] ) ) {
			return '';
		}

		$payment_method_type = sanitize_text_field( wp_unslash( $_POST['payment_method'] ) );
		if ( substr( $payment_method_type, 0, 6 ) !== 'stripe' ) {
			return '';
		}

		// Amazon Pay is available as an express checkout method only, for now.
		// To prevent WooCommerce from rendering it as a standard payment method in checkout, we make
		// WC_Stripe_UPE_Payment_Method_Amazon_Pay::is_available() return false.
		// We set the payment method to 'amazon_pay' here, instead of earlier (i.e. passing
		// 'stripe_amazon_pay' in the POST request) to avoid WooCommerce rejecting the order for
		// having an "unavailable" payment method type.
		if ( WC_Stripe_Payment_Methods::AMAZON_PAY === $this->get_express_payment_type_from_request() ) {
			return WC_Stripe_Payment_Methods::AMAZON_PAY;
		}

		return substr( $payment_method_type, 0, 7 ) === 'stripe_' ? substr( $payment_method_type, 7 ) : 'card';
	}

	/**
	 * Gets the express payment type, e.g. google_pay, apple_pay, from the request,
	 *   if applicable.
	 *
	 * @return string|null
	 */
	private function get_express_payment_type_from_request() {
		if ( ! isset( $_POST['express_payment_type'] ) ) {
			return null;
		}

		return sanitize_text_field( wp_unslash( $_POST['express_payment_type'] ) );
	}

	/**
	 * Save the selected payment method information to the order and as a payment token for the user.
	 *
	 * @param WC_Order $order                  The WC order for which we're saving the payment method.
	 * @param stdClass $payment_method_object  The payment method object retrieved from Stripe.
	 * @param string   $payment_method_type    The payment method type, like `card`, `sepa_debit`, etc.
	 */
	protected function handle_saving_payment_method( WC_Order $order, $payment_method_object, string $payment_method_type ) {
		$user     = $this->get_user_from_order( $order );
		$customer = new WC_Stripe_Customer( $user->ID );
		$customer->clear_cache();

		// If the payment method object is a Link payment method, use Link as the payment method type.
		if ( isset( $payment_method_object->type ) && WC_Stripe_Payment_Methods::LINK === $payment_method_object->type ) {
			$payment_method_type     = WC_Stripe_Payment_Methods::LINK;
			$payment_method_instance = $this->get_payment_method_instance( $payment_method_type );
		} elseif ( $this->oc_enabled && isset( $payment_method_object->type ) ) {
			// When OC is enabled, use the payment method type from the payment method object
			$payment_method_type     = $payment_method_object->type;
			$payment_method_instance = $this->get_payment_method_instance( $payment_method_type );
		} else {
			$payment_method_instance = $this->payment_methods[ $payment_method_type ];
		}

		// Searches for an existing duplicate token to update.
		$found_token = WC_Stripe_Payment_Tokens::get_duplicate_token( $payment_method_object, $customer->get_user_id(), $this->id );

		if ( $found_token ) {
			// Update the token with the new payment method ID.
			$payment_method_instance->update_payment_token( $found_token, $payment_method_object->id );
		} else {
			// Create a payment token for the user in the store.
			$payment_method_instance->create_payment_token_for_user( $user->ID, $payment_method_object );
		}

		// Add the payment method information to the order.
		$prepared_payment_method_object = $this->prepare_payment_method( $payment_method_object );

		// If the customer ID is missing from the Payment Method, Stripe haven't attached it to the customer yet. This occurs for Cash App for example.
		// Fallback to the order's customer ID.
		if ( empty( $prepared_payment_method_object->customer ) ) {
			$prepared_payment_method_object->customer = $this->get_stripe_customer_id( $order );
		}

		$this->maybe_update_source_on_subscription_order( $order, $prepared_payment_method_object, $this->get_upe_gateway_id_for_order( $payment_method_instance ) );

		do_action( 'woocommerce_stripe_add_payment_method', $user->ID, $payment_method_object );
	}

	/**
	 * Set the payment metadata for payment method id.
	 *
	 * @param WC_Order $order The order.
	 * @param string   $payment_method_id The value to be set.
	 */
	public function set_payment_method_id_for_order( WC_Order $order, string $payment_method_id ) {
		// Save the payment method id as `source_id`, because we use both `sources` and `payment_methods` APIs.
		$order->update_meta_data( '_stripe_source_id', $payment_method_id );
		$order->save_meta_data();
	}

	/**
	 * Set the payment metadata for payment method id for subscription.
	 *
	 * @param WC_Subscription $order The order.
	 * @param string   $payment_method_id The value to be set.
	 */
	public function set_payment_method_id_for_subscription( $subscription, string $payment_method_id ) {
		$subscription->update_meta_data( '_stripe_source_id', $payment_method_id );
		$subscription->save_meta_data();
	}

	/**
	 * Set the payment metadata for customer id.
	 *
	 * Set to public so it can be called from confirm_change_payment_from_setup_intent_ajax()
	 *
	 * @param WC_Order $order The order.
	 * @param string   $customer_id The value to be set.
	 */
	public function set_customer_id_for_order( WC_Order $order, string $customer_id ) {
		$order->update_meta_data( '_stripe_customer_id', $customer_id );
		$order->save_meta_data();
	}

	/**
	 * Set the payment metadata for customer id for subscription.
	 *
	 * Set to public so it can be called from confirm_change_payment_from_setup_intent_ajax()
	 *
	 * @param WC_Stripe_Order $order The order.
	 * @param string   $customer_id The value to be set.
	 */
	public function set_customer_id_for_subscription( $subscription, string $customer_id ) {
		$subscription->update_meta_data( '_stripe_customer_id', $customer_id );
		$subscription->save_meta_data();
	}

	/**
	 * Set the payment metadata for the selected payment type.
	 *
	 * @param WC_Order $order                 The order for which we're setting the selected payment type.
	 * @param string   $selected_payment_type The selected payment type.
	 */
	private function set_selected_payment_type_for_order( WC_Order $order, string $selected_payment_type ) {
		$order->update_meta_data( '_stripe_upe_payment_type', $selected_payment_type );
		$order->save_meta_data();
	}
	/**
	 * Gets the Stripe customer ID associated with an order, creates one if none is associated.
	 *
	 * @param WC_Order $order The WC order from which to get the Stripe customer.
	 * @return string The Stripe customer ID.
	 */
	private function get_customer_id_for_order( WC_Order $order ): string {

		// Get the user/customer from the order.
		$customer_id = $this->get_stripe_customer_id( $order );
		if ( ! empty( $customer_id ) ) {
			return $customer_id;
		}

		// Update customer or create customer if one does not exist.
		$user     = $this->get_user_from_order( $order );
		$customer = new WC_Stripe_Customer( $user->ID );

		// Pass the order object so we can retrieve billing details
		// in payment flows where it is not present in the request.
		$args = [ 'order' => $order ];
		return $customer->update_or_create_customer( $args );
	}

	/**
	 * Throws an exception when the given payment method type is not valid.
	 *
	 * @param array  $payment_information Payment information to process the payment.
	 * @param string $billing_country     Order billing country.
	 *
	 * @throws WC_Stripe_Exception When the payment method type is not allowed in the given country.
	 */
	protected function validate_selected_payment_method_type( $payment_information, $billing_country ) {
		$invalid_method_message = __( 'The selected payment method type is invalid.', 'woocommerce-gateway-stripe' );

		// No payment method type was provided.
		if ( empty( $payment_information['selected_payment_type'] ) ) {
			throw new WC_Stripe_Exception( 'No payment method type selected.', $invalid_method_message );
		}

		$payment_method_type = $payment_information['selected_payment_type'];

		// The provided payment method type is not among the available payment method types.
		if ( ! isset( $this->payment_methods[ $payment_method_type ] ) ) {
			throw new WC_Stripe_Exception(
				sprintf(
					'The selected payment method type is not within the available payment methods.%1$sSelected payment method type: %2$s. Available payment methods: %3$s',
					PHP_EOL,
					$payment_method_type,
					implode( ', ', array_keys( $this->payment_methods ) )
				),
				$invalid_method_message
			);
		}

		// The selected payment method is allowed in the billing country.
		if ( ! $this->payment_methods[ $payment_method_type ]->is_allowed_on_country( $billing_country ) ) {
			throw new WC_Stripe_Exception(
				sprintf( 'The payment method type "%1$s" is not available in %2$s.', $payment_method_type, $billing_country ),
				__( 'This payment method type is not available in the selected country.', 'woocommerce-gateway-stripe' )
			);
		}
	}

	/**
	 * Add a new Stripe payment method via the My Account > Payment methods page.
	 *
	 * This function is called by @see WC_Form_Handler::add_payment_method_action().
	 *
	 * @return array
	 */
	public function add_payment_method() {
		try {
			if ( ! is_user_logged_in() ) {
				throw new WC_Stripe_Exception( 'No logged-in user found.' );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! isset( $_POST['wc-stripe-setup-intent'] ) ) {
				throw new WC_Stripe_Exception( 'Stripe setup intent is missing.' );
			}

			$user            = wp_get_current_user();
			$setup_intent_id = wc_clean( wp_unslash( $_POST['wc-stripe-setup-intent'] ) );
			$setup_intent    = $this->stripe_request( 'setup_intents/' . $setup_intent_id );

			if ( ! empty( $setup_intent->last_payment_error ) ) {
				throw new WC_Stripe_Exception( sprintf( 'Error fetching the setup intent (ID %s) from Stripe: %s.', $setup_intent_id, ! empty( $setup_intent->last_payment_error->message ) ? $setup_intent->last_payment_error->message : 'Unknown error' ) );
			}

			$payment_method_id     = $setup_intent->payment_method;
			$payment_method_object = $this->stripe_request( 'payment_methods/' . $payment_method_id );

			$payment_method = $this->payment_methods[ $payment_method_object->type ];

			$customer = new WC_Stripe_Customer( $user->ID );
			$customer->clear_cache();

			// Check if a token with the same payment method details exist. If so, just updates the payment method ID and return.
			$found_token = WC_Stripe_Payment_Tokens::get_duplicate_token( $payment_method_object, $user->ID, $this->id );

			// If we have a token found, update it and return.
			if ( $found_token ) {
				$token = $payment_method->update_payment_token( $found_token, $payment_method_object->id );
			} else {
				// Create a new token if not.
				$token = $payment_method->create_payment_token_for_user( $user->ID, $payment_method_object );
			}

			if ( ! is_a( $token, 'WC_Payment_Token' ) ) {
				throw new WC_Stripe_Exception( sprintf( 'New payment token is not an instance of WC_Payment_Token. Token: %s.', print_r( $token, true ) ) );
			}

			do_action( 'woocommerce_stripe_add_payment_method', $user->ID, $payment_method_object );

			return [
				'result'   => 'success',
				'redirect' => wc_get_endpoint_url( 'payment-methods' ),
			];
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( sprintf( 'Add payment method error: %s', $e->getMessage() ) );
			return [ 'result' => 'failure' ];
		}
	}

	/**
	 * Returns a URL to process UPE redirect payments.
	 *
	 * @param WC_Order $order               The WC Order to be paid for.
	 * @param bool     $save_payment_method Whether to save the payment method for future use.
	 *
	 * @return string
	 */
	private function get_return_url_for_redirect( $order, $save_payment_method ) {
		return wp_sanitize_redirect(
			esc_url_raw(
				add_query_arg(
					[
						'order_id'            => $order->get_id(),
						'wc_payment_method'   => self::ID,
						'_wpnonce'            => wp_create_nonce( 'wc_stripe_process_redirect_order_nonce' ),
						'save_payment_method' => $save_payment_method ? 'yes' : 'no',
						'pay_for_order'       => parent::is_valid_pay_for_order_endpoint() ? 'yes' : 'no',
					],
					$this->get_return_url( $order )
				)
			)
		);
	}

	/**
	 * Retrieves the (possible) existing payment intent for an order and payment method types.
	 *
	 * @param WC_Order $order The order.
	 * @param array    $payment_method_types The payment method types.
	 *
	 * @return object|null
	 *
	 * @throws WC_Stripe_Exception
	 */
	private function get_existing_compatible_payment_intent( $order, $payment_method_types ) {
		// Reload the order to make sure we have the latest data.
		$order  = wc_get_order( $order->get_id() );
		$intent = $this->get_intent_from_order( $order );
		if ( ! $intent ) {
			return null;
		}

		// If the payment method types match, we can reuse the payment intent.
		if ( count( array_intersect( $intent->payment_method_types, $payment_method_types ) ) !== count( $payment_method_types ) ) {
			return null;
		}

		// Check if the order total matches the existing intent amount.
		$order_total = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $order->get_currency() );
		if ( $order_total !== $intent->amount ) {
			return null;
		}

		// Check if the status of the intent still allows update.
		if ( in_array( $intent->status, [ WC_Stripe_Intent_Status::CANCELED, WC_Stripe_Intent_Status::SUCCEEDED ], true ) ) {
			return null;
		}

		// If the intent requires confirmation to show voucher on checkout (i.e. Boleto or oxxo or multibanco ) or requires action (i.e. need to show a 3DS confirmation card or handle the UPE redirect), don't reuse the intent
		if ( in_array( $intent->status, [ WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION, WC_Stripe_Intent_Status::REQUIRES_ACTION ], true ) ) {
			return null;
		}

		// Cash App Pay intents with a "requires payment method" status cannot be reused. See https://docs.stripe.com/payments/cash-app-pay/accept-a-payment?web-or-mobile=web&payments-ui-type=direct-api#failed-payments
		if ( in_array( WC_Stripe_Payment_Methods::CASHAPP_PAY, $intent->payment_method_types ) && WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD === $intent->status ) {
			return null;
		}

		return $intent;
	}

	/**
	 * Returns the payment method types for the intent creation request, given the selected payment type.
	 *
	 * @param string $selected_payment_type The payment type the shopper selected, if any.
	 * @param int    $order_id              ID of the WC order we're handling.
	 * @param string|null $express_payment_type  The express payment type, if any.
	 *
	 * @return array
	 */
	private function get_payment_method_types_for_intent_creation(
		string $selected_payment_type,
		int $order_id,
		?string $express_payment_type = null,
		?string $payment_method_type = null
	): array {
		// If Single Payment Element is enabled, return only the payment method type.
		if ( $this->oc_enabled && ! empty( $payment_method_type ) ) {
			return [ $payment_method_type ];
		}

		// If the shopper didn't select a payment type, return all the enabled ones.
		if ( '' === $selected_payment_type ) {
			return $this->get_upe_enabled_at_checkout_payment_method_ids( $order_id );
		}

		// Check if this is for an express payment
		if ( ! empty( $express_payment_type ) ) {
			switch ( $express_payment_type ) {
				case WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID:
					return [ WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID ];
				case WC_Stripe_Payment_Methods::AMAZON_PAY:
					return [ WC_Stripe_Payment_Methods::AMAZON_PAY ];
				case WC_Stripe_Payment_Methods::GOOGLE_PAY:
				case WC_Stripe_Payment_Methods::APPLE_PAY:
				default:
					return [ WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID ];
			}
		}

		// If the "card" type was selected and Link is enabled, include Link in the types,
		// to support paying with cards stored in Link.
		if (
			WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID === $selected_payment_type &&
			in_array(
				WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
				$this->get_upe_enabled_payment_method_ids(),
				true
			)
		) {
			return [
				WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
				WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
			];
		}

		// Otherwise, return the selected payment method type.
		return [ $selected_payment_type ];
	}

	/**
	 * Checks if the save option for a payment method should be displayed or not.
	 *
	 * @param WC_Stripe_UPE_Payment_Method $payment_method UPE Payment Method instance.
	 * @return bool - True if the payment method is reusable and the saved cards feature is enabled for the gateway and there is no subscription item in the cart, false otherwise.
	 */
	private function should_upe_payment_method_show_save_option( $payment_method ) {
		if ( $payment_method->is_reusable() ) {
			// If a subscription in the cart, it will be saved by default so no need to show the option.
			return $this->is_saved_cards_enabled() && ! $this->is_subscription_item_in_cart() && ! $this->is_pre_order_charged_upon_release_in_cart();
		}

		return false;
	}

	/**
	 * Determines the gateway ID to set as the subscription order's payment method.
	 *
	 * Some UPE payment methods use different gateway IDs to process their payments. eg Bancontact uses SEPA tokens, cards use 'stripe' etc.
	 * This function will return the correct gateway ID which should be recorded on the subscription so that the correct payment method is used to process future payments.
	 *
	 * @param WC_Stripe_UPE_Payment_Method $payment_method The UPE payment method instance.
	 * @return string The gateway ID to set on the subscription/order.
	 */
	protected function get_upe_gateway_id_for_order( $payment_method ) {
		$token_gateway_type = $payment_method->get_retrievable_type();

		if ( WC_Stripe_Payment_Methods::CARD === $token_gateway_type ||
			WC_Stripe_Payment_Methods::LINK === $token_gateway_type ) {
			return $this->id;
		}

		return $this->payment_methods[ $token_gateway_type ]->id;
	}

	/**
	 * Fetches the appearance settings for Stripe Elements from the transient cache.
	 *
	 * Include the theme name in the transient key because getAppearance (found in client/styles/upe/index.js) will generate different
	 * appearance rules based on the active theme, so we need to cache the appearance settings per theme.
	 *
	 * @param bool $is_block_checkout Whether the appearance settings are for the block checkout.
	 *
	 * @return array|null The appearance settings.
	 */
	private function get_appearance_transient_key( $is_block_checkout = false ) {
		return ( $is_block_checkout ? self::BLOCKS_APPEARANCE_TRANSIENT : self::APPEARANCE_TRANSIENT ) . '_' . get_option( 'stylesheet' );
	}

	/**
	 * Checks if the current page is the order details page.
	 *
	 * @return bool Whether the current page is the order details page.
	 */
	private function is_order_details_page() {
		$query_params = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( WC_Stripe_Woo_Compat_Utils::is_custom_orders_table_enabled() ) { // If custom order tables are enabled, we need to check the page query param.
			return isset( $query_params['page'] ) && 'wc-orders' === $query_params['page'] && isset( $query_params['id'] );
		}

		// If custom order tables are not enabled, we need to check the post type and action query params.
		$is_shop_order_post_type = isset( $query_params['post'] ) && 'shop_order' === get_post_type( $query_params['post'] );
		return isset( $query_params['action'] ) && 'edit' === $query_params['action'] && $is_shop_order_post_type;
	}

	/**
	 * Checks if this is a refund request.
	 *
	 * @return bool Whether this is a refund request.
	 */
	private function is_refund_request() {
		return isset( $_POST['action'] ) && 'woocommerce_refund_line_items' === $_POST['action']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Depending on the payment method used to process the payment, we may need to redirect the user to a URL for further processing.
	 *
	 * - Voucher payments (Boleto or Oxxo or Multibanco) respond with a hash URL so the client JS code can recognize the response, pull out the necessary args and handle the displaying of the voucher.
	 * - Wallet payments (CashApp or WeChat) respond with a hash URL so the client JS code can recognize the response, pull out the necessary args and handle the displaying of the modal.
	 * - Other payment methods like Giropay, iDEAL, Alipay etc require a redirect to a URL provided by Stripe.
	 * - 3DS Card payments return a hash URL so the client JS code can recognize the response, pull out the necessary PI args and display the 3DS confirmation modal.
	 *
	 * @param $return_url string The return URL.
	 * @param $payment_intent object The payment intent object.
	 * @param $payment_information array The payment information.
	 * @param $order WC_Order The order.
	 * @param $payment_needed bool Whether payment is needed.
	 * @return string The redirect URL.
	 */
	protected function get_redirect_url( $return_url, $payment_intent, $payment_information, $order, $payment_needed ) {
		$selected_payment_type = $this->oc_enabled ? $payment_information['payment_method_details']->type : $payment_information['selected_payment_type'];
		if ( isset( $payment_intent->payment_method_types ) && count( array_intersect( WC_Stripe_Payment_Methods::VOUCHER_PAYMENT_METHODS, $payment_intent->payment_method_types ) ) !== 0 ) {
			// For Voucher payment method types (Boleto/Oxxo/Multibanco), redirect the customer to a URL hash formatted #wc-stripe-voucher-{order_id}:{payment_method_type}:{client_secret}:{redirect_url} to confirm the intent which also displays the voucher.
			return sprintf(
				'#wc-stripe-voucher-%s:%s:%s:%s',
				$order->get_id(),
				$selected_payment_type,
				$payment_intent->client_secret,
				rawurlencode( $return_url )
			);
		} elseif ( isset( $payment_intent->payment_method_types ) && count( array_intersect( WC_Stripe_Payment_Methods::WALLET_PAYMENT_METHODS, $payment_intent->payment_method_types ) ) !== 0 ) {
			// For Wallet payment method types (CashApp/WeChat Pay), redirect the customer to a URL hash formatted #wc-stripe-wallet-{order_id}:{payment_method_type}:{payment_intent_type}:{client_secret}:{redirect_url} to confirm the intent which also displays the modal.
			return sprintf(
				'#wc-stripe-wallet-%s:%s:%s:%s:%s:%s',
				$order->get_id(),
				$selected_payment_type,
				$payment_intent->object,
				$payment_intent->client_secret,
				rawurlencode( $return_url ),
				wp_create_nonce( 'wc_stripe_update_order_status_nonce' )
			);
		} elseif ( isset( $payment_intent->next_action->type ) && in_array( $payment_intent->next_action->type, [ 'redirect_to_url', 'alipay_handle_redirect' ], true ) && ! empty( $payment_intent->next_action->{$payment_intent->next_action->type}->url ) ) {
			return $payment_intent->next_action->{$payment_intent->next_action->type}->url;
		}

		return sprintf(
			'#wc-stripe-confirm-%s:%s:%s:%s',
			$payment_needed ? 'pi' : 'si',
			$order->get_id(),
			$payment_intent->client_secret,
			wp_create_nonce( 'wc_stripe_update_order_status_nonce' )
		);
	}

	/**
	 * Saves the default appearance settings to a transient cache.
	 *
	 * Individual appearance settings are saved for both block and shortcode checkout and also against each theme so that changing the theme will use different transient setting.
	 */
	public function save_appearance_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_save_appearance_nonce', false, false );

			if ( ! $is_nonce_valid ) {
				throw new WC_Stripe_Exception( 'Invalid nonce saving appearance.', __( 'Unable to update Stripe Elements appearance values at this time.', 'woocommerce-gateway-stripe' ) );
			}

			$is_block_checkout = isset( $_POST['is_block_checkout'] ) ? wc_string_to_bool( wc_clean( wp_unslash( $_POST['is_block_checkout'] ) ) ) : false;
			$appearance        = isset( $_POST['appearance'] ) ? json_decode( wc_clean( wp_unslash( $_POST['appearance'] ) ) ) : null;

			if ( null !== $appearance ) {
				set_transient( $this->get_appearance_transient_key( $is_block_checkout ), $appearance, DAY_IN_SECONDS );
			}

			wp_send_json_success( $appearance, 200 );
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
			wp_send_json_error(
				[
					'error' => [
						'message' => $e->getLocalizedMessage(),
					],
				]
			);
		}
	}

	/**
	 * Clears the appearance transients when a Block theme is updated or customized.
	 * This ensures the UPE appearance is regenerated with the new theme colors.
	 */
	public function clear_appearance_transients_block_theme( $post_id, $post ) {
		if ( in_array( $post->post_type, [ 'wp_global_styles' ], true ) ) {
			delete_transient( $this->get_appearance_transient_key( true ) );
		}
	}

	/**
	 * Clears the appearance transients when a classic theme is updated or customized.
	 * This ensures the UPE appearance is regenerated with the new theme colors.
	 */
	public function clear_appearance_transients() {
		delete_transient( $this->get_appearance_transient_key() );
	}

	/**
	 * Hide "Pay" and "Cancel" action buttons for pending orders if they take a while to be confirmed.
	 *
	 * @param $actions array An array with the default actions.
	 * @param $order WC_Order The order.
	 * @return array
	 */
	public function filter_my_account_my_orders_actions( $actions, $order ) {
		$methods_with_delayed_confirmation = [
			WC_Stripe_Payment_Methods::BACS_DEBIT_LABEL,
		];
		if ( is_order_received_page() && in_array( $order->get_payment_method_title(), $methods_with_delayed_confirmation, true ) && $order->has_status( OrderStatus::PENDING ) ) {
			unset( $actions['pay'], $actions['cancel'] );
		}
		return $actions;
	}

	/**
	 * Checks if Google Pay and Apple Pay (ECE) are enabled.
	 *
	 * Overrides WC_Gateway_Stripe::is_payment_request_enabled().
	 *
	 * @return bool
	 */
	public function is_payment_request_enabled() {
		// If the payment method configurations API is not enabled, we fallback to the enabled payment methods stored in the DB.
		if ( ! WC_Stripe_Payment_Method_Configurations::is_enabled() ) {
			return parent::is_payment_request_enabled();
		}

		$enabled_payment_method_ids = $this->get_upe_enabled_payment_method_ids();

		// Apple Pay and Google Pay settings are currently unified in wp-admin.
		// However, they are managed separately within the Stripe dashboard.
		// Until we move to separate settings in wp-admin, both payment methods will be
		// considered enabled if either is enabled in Stripe.
		return in_array( WC_Stripe_Payment_Methods::APPLE_PAY, $enabled_payment_method_ids, true ) ||
			in_array( WC_Stripe_Payment_Methods::GOOGLE_PAY, $enabled_payment_method_ids, true );
	}
}
