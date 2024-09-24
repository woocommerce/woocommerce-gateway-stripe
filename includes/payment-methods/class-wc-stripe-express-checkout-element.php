<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Express_Checkout_Element class.
 */
class WC_Stripe_Express_Checkout_Element {
	/**
	 * Stripe settings.
	 *
	 * @var
	 */
	public $stripe_settings;

	/**
	 * This Instance.
	 *
	 * @var
	 */
	private static $_this;

	/**
	 * WC_Stripe_Express_Checkout_Ajax_Handler instance.
	 *
	 * @var WC_Stripe_Express_Checkout_Ajax_Handler
	 */
	private $express_checkout_ajax_handler;

	/**
	 * WC_Stripe_Express_Checkout_Helper instance.
	 *
	 * @var WC_Stripe_Express_Checkout_Helper
	 */
	public $express_checkout_helper;

	/**
	 * Constructor.
	 */
	public function __construct( WC_Stripe_Express_Checkout_Ajax_Handler $express_checkout_ajax_handler, WC_Stripe_Express_Checkout_Helper $express_checkout_helper ) {
		self::$_this           = $this;
		$this->stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		$this->express_checkout_helper       = $express_checkout_helper;
		$this->express_checkout_ajax_handler = $express_checkout_ajax_handler;
		$this->express_checkout_ajax_handler->init();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return  void
	 */
	public function init() {
		// Check if ECE feature flag is enabled.
		if ( ! WC_Stripe_Feature_Flags::is_stripe_ece_enabled() ) {
			return;
		}

		// Checks if Stripe Gateway is enabled.
		if ( empty( $this->stripe_settings ) || ( isset( $this->stripe_settings['enabled'] ) && 'yes' !== $this->stripe_settings['enabled'] ) ) {
			return;
		}

		// Don't initiate this class if express checkout element is disabled.
		if ( ! $this->express_checkout_helper->is_express_checkout_enabled() ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'set_session' ] );
		add_action( 'template_redirect', [ $this, 'handle_express_checkout_redirect' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_express_checkout_button_html' ], 1 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_express_checkout_button_html' ], 25 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_express_checkout_button_html' ], 1 );

		add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'add_order_meta' ], 10, 2 );
		add_filter( 'woocommerce_login_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );
		add_filter( 'woocommerce_registration_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );
	}

	/**
	 * Get this instance.
	 *
	 * @return class
	 */
	public static function instance() {
		return self::$_this;
	}

	/**
	 * Sets the WC customer session if one is not set.
	 * This is needed so nonces can be verified by AJAX Request.
	 *
	 * @return void
	 */
	public function set_session() {
		if ( ! $this->express_checkout_helper->is_product() || ( isset( WC()->session ) && WC()->session->has_session() ) ) {
			return;
		}

		WC()->session->set_customer_session_cookie( true );
	}

	/**
	 * Handles express checkout redirect when the redirect dialog "Continue" button is clicked.
	 */
	public function handle_express_checkout_redirect() {
		if (
			! empty( $_GET['wc_stripe_express_checkout_redirect_url'] )
			&& ! empty( $_GET['_wpnonce'] )
			&& wp_verify_nonce( $_GET['_wpnonce'], 'wc-stripe-set-redirect-url' ) // @codingStandardsIgnoreLine
		) {
			$url = rawurldecode( esc_url_raw( wp_unslash( $_GET['wc_stripe_express_checkout_redirect_url'] ) ) );
			// Sets a redirect URL cookie for 10 minutes, which we will redirect to after authentication.
			// Users will have a 10 minute timeout to login/create account, otherwise redirect URL expires.
			wc_setcookie( 'wc_stripe_express_checkout_redirect_url', $url, time() + MINUTE_IN_SECONDS * 10 );
			// Redirects to "my-account" page.
			wp_safe_redirect( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) );
			exit;
		}
	}

	/**
	 * Returns the login redirect URL.
	 *
	 * @param string $redirect Default redirect URL.
	 * @return string Redirect URL.
	 */
	public function get_login_redirect_url( $redirect ) {
		$url = esc_url_raw( wp_unslash( isset( $_COOKIE['wc_stripe_express_checkout_redirect_url'] ) ? $_COOKIE['wc_stripe_express_checkout_redirect_url'] : '' ) );

		if ( empty( $url ) ) {
			return $redirect;
		}
		wc_setcookie( 'wc_stripe_express_checkout_redirect_url', null );

		return $url;
	}

	/**
	 * Returns the JavaScript configuration object used for any pages with express checkout element.
	 *
	 * @return array  The settings used for the Stripe express checkout element in JavaScript.
	 */
	public function javascript_params() {
		$needs_shipping = 'no';
		if ( ! is_null( WC()->cart ) && WC()->cart->needs_shipping() ) {
			$needs_shipping = 'yes';
		}

		return [
			'ajax_url'           => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe'             => [
				'allow_prepaid_card'          => apply_filters( 'wc_stripe_allow_prepaid_card', true ) ? 'yes' : 'no',
				'locale'                      => WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( get_locale() ),
				'is_link_enabled'             => WC_Stripe_UPE_Payment_Method_Link::is_link_enabled(),
				'is_express_checkout_enabled' => $this->express_checkout_helper->is_express_checkout_enabled(),
			],
			'nonce'              => [
				'payment'                   => wp_create_nonce( 'wc-stripe-express-checkout' ),
				'shipping'                  => wp_create_nonce( 'wc-stripe-express-checkout-shipping' ),
				'update_shipping'           => wp_create_nonce( 'wc-stripe-update-shipping-method' ),
				'checkout'                  => wp_create_nonce( 'woocommerce-process_checkout' ),
				'add_to_cart'               => wp_create_nonce( 'wc-stripe-add-to-cart' ),
				'get_selected_product_data' => wp_create_nonce( 'wc-stripe-get-selected-product-data' ),
				'log_errors'                => wp_create_nonce( 'wc-stripe-log-errors' ),
				'clear_cart'                => wp_create_nonce( 'wc-stripe-clear-cart' ),
			],
			'i18n'               => [
				'no_prepaid_card'  => __( 'Sorry, we\'re not accepting prepaid cards at this time.', 'woocommerce-gateway-stripe' ),
				/* translators: Do not translate the [option] placeholder */
				'unknown_shipping' => __( 'Unknown shipping option "[option]".', 'woocommerce-gateway-stripe' ),
			],
			'checkout'           => [
				'url'               => wc_get_checkout_url(),
				'currency_code'     => strtolower( get_woocommerce_currency() ),
				'country_code'      => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'needs_shipping'    => $needs_shipping,
				// Defaults to 'required' to match how core initializes this option.
				'needs_payer_phone' => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			],
			'button'             => $this->express_checkout_helper->get_button_settings(),
			'has_block'          => has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ),
			'login_confirmation' => $this->express_checkout_helper->get_login_confirmation_settings(),
			'is_product_page'    => $this->express_checkout_helper->is_product(),
			'product'            => $this->express_checkout_helper->get_product_data(),
		];
	}

	/**
	 * Load scripts and styles.
	 */
	public function scripts() {
		// If page is not supported, bail.
		if ( ! $this->express_checkout_helper->is_page_supported() ) {
			return;
		}

		if ( ! $this->express_checkout_helper->should_show_express_checkout_button() ) {
			return;
		}

		$asset_path   = WC_STRIPE_PLUGIN_PATH . '/build/express_checkout.asset.php';
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

		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script(
			'wc_stripe_express_checkout',
			WC_STRIPE_PLUGIN_URL . '/build/express_checkout.js',
			array_merge( [ 'jquery', 'stripe' ], $dependencies ),
			$version,
			true
		);

		wp_enqueue_style(
			'wc_stripe_express_checkout_style',
			WC_STRIPE_PLUGIN_URL . '/build/express_checkout.css',
			[],
			$version
		);

		wp_localize_script(
			'wc_stripe_express_checkout',
			'wc_stripe_express_checkout_params',
			apply_filters(
				'wc_stripe_express_checkout_params',
				$this->javascript_params()
			)
		);

		wp_enqueue_script( 'wc_stripe_express_checkout' );
	}

	/**
	 * Add needed order meta
	 *
	 * @param integer $order_id    The order ID.
	 * @param array   $posted_data The posted data from checkout form.
	 *
	 * @return  void
	 */
	public function add_order_meta( $order_id, $posted_data ) {
		if ( empty( $_POST['express_checkout_type'] ) || ! isset( $_POST['payment_method'] ) || 'stripe' !== $_POST['payment_method'] ) {
			return;
		}

		$order = wc_get_order( $order_id );

		$express_checkout_type = wc_clean( wp_unslash( $_POST['express_checkout_type'] ) );

		if ( 'apple_pay' === $express_checkout_type ) {
			$order->set_payment_method_title( 'Apple Pay (Stripe)' );
			$order->save();
		} elseif ( 'google_pay' === $express_checkout_type ) {
			$order->set_payment_method_title( 'Google Pay (Stripe)' );
			$order->save();
		}
	}

	/**
	 * Filters the gateway title to reflect express checkout type
	 */
	public function filter_gateway_title( $title, $id ) {
		global $theorder;

		// If $theorder is empty (i.e. non-HPOS), fallback to using the global post object.
		if ( empty( $theorder ) && ! empty( $GLOBALS['post']->ID ) ) {
			$theorder = wc_get_order( $GLOBALS['post']->ID );
		}

		if ( ! is_object( $theorder ) ) {
			return $title;
		}

		$method_title = $theorder->get_payment_method_title();

		if ( 'stripe' === $id && ! empty( $method_title ) ) {
			if ( 'Apple Pay (Stripe)' === $method_title
				|| 'Google Pay (Stripe)' === $method_title
			) {
				return $method_title;
			}
		}

		return $title;
	}

	/**
	 * Display the express checkout button.
	 */
	public function display_express_checkout_button_html() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['stripe'] ) ) {
			return;
		}

		if ( ! $this->express_checkout_helper->is_page_supported() ) {
			return;
		}

		if ( ! $this->express_checkout_helper->should_show_express_checkout_button() ) {
			return;
		}

		?>
		<div id="wc-stripe-express-checkout-wrapper" style="margin-top: 1em;clear:both;display:none;">
			<div id="wc-stripe-express-checkout-button">
				<!-- A Stripe Element will be inserted here. -->
			</div>
		</div>
		<?php
		$this->display_express_checkout_button_separator_html();
	}

	/**
	 * Display express checkout button separator.
	 */
	public function display_express_checkout_button_separator_html() {
		if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		if ( is_checkout() && ! in_array( 'checkout', $this->express_checkout_helper->get_button_locations(), true ) ) {
			return;
		}

		?>
		<p id="wc-stripe-express-checkout-button-separator" style="margin-top:1.5em;text-align:center;display:none;">&mdash; <?php esc_html_e( 'OR', 'woocommerce-gateway-stripe' ); ?> &mdash;</p>
		<?php
	}
}
