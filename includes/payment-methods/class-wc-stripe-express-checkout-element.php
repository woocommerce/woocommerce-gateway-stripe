<?php
/**
 * Class that handles checkout with Stripe Express Checkout Element.
 * Utilizes the Stripe Express Checkout Element to support checkout with Google Pay and Apple pay from the product detail, cart and checkout pages.
 *
 * @since 8.8.0
 */

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

		// ECE is only available when UPE checkout is enabled.
		if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
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

		// Don't load for switch subscription page.
		if ( isset( $_GET['switch-subscription'] ) ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'set_session' ] );
		add_action( 'template_redirect', [ $this, 'handle_express_checkout_redirect' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_express_checkout_button_html' ], 1 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_express_checkout_button_html' ], 20 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_express_checkout_button_html' ], 1 );
		add_action( 'woocommerce_pay_order_before_payment', [ $this, 'display_express_checkout_button_html' ], 1 );

		add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'add_order_meta' ], 10, 2 );
		add_filter( 'woocommerce_login_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );
		add_filter( 'woocommerce_registration_redirect', [ $this, 'get_login_redirect_url' ], 10, 3 );
		add_filter( 'woocommerce_cart_needs_shipping_address', [ $this, 'filter_cart_needs_shipping_address' ], 11, 1 );

		add_action( 'before_woocommerce_pay_form', [ $this, 'localize_pay_for_order_page_scripts' ] );
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
		return [
			'ajax_url'               => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe'                 => [
				'publishable_key'             => WC_Stripe_Mode::is_test() ? $this->stripe_settings['test_publishable_key'] : $this->stripe_settings['publishable_key'],
				'allow_prepaid_card'          => apply_filters( 'wc_stripe_allow_prepaid_card', true ) ? 'yes' : 'no',
				'locale'                      => WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( get_locale() ),
				'is_link_enabled'             => WC_Stripe_UPE_Payment_Method_Link::is_link_enabled(),
				'is_express_checkout_enabled' => $this->express_checkout_helper->is_express_checkout_enabled(),
			],
			'nonce'                  => [
				'payment'                   => wp_create_nonce( 'wc-stripe-express-checkout' ),
				'shipping'                  => wp_create_nonce( 'wc-stripe-express-checkout-shipping' ),
				'get_cart_details'          => wp_create_nonce( 'wc-stripe-get-cart-details' ),
				'update_shipping'           => wp_create_nonce( 'wc-stripe-update-shipping-method' ),
				'checkout'                  => wp_create_nonce( 'woocommerce-process_checkout' ),
				'add_to_cart'               => wp_create_nonce( 'wc-stripe-add-to-cart' ),
				'get_selected_product_data' => wp_create_nonce( 'wc-stripe-get-selected-product-data' ),
				'log_errors'                => wp_create_nonce( 'wc-stripe-log-errors' ),
				'clear_cart'                => wp_create_nonce( 'wc-stripe-clear-cart' ),
				'pay_for_order'             => wp_create_nonce( 'wc-stripe-pay-for-order' ),
			],
			'i18n'                   => [
				'no_prepaid_card'  => __( 'Sorry, we\'re not accepting prepaid cards at this time.', 'woocommerce-gateway-stripe' ),
				/* translators: Do not translate the [option] placeholder */
				'unknown_shipping' => __( 'Unknown shipping option "[option]".', 'woocommerce-gateway-stripe' ),
			],
			'checkout'               => $this->express_checkout_helper->get_checkout_data(),
			'button'                 => $this->express_checkout_helper->get_button_settings(),
			'is_pay_for_order'       => $this->express_checkout_helper->is_pay_for_order_page(),
			'has_block'              => has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ),
			'login_confirmation'     => $this->express_checkout_helper->get_login_confirmation_settings(),
			'is_product_page'        => $this->express_checkout_helper->is_product(),
			'is_checkout_page'       => $this->express_checkout_helper->is_checkout(),
			'product'                => $this->express_checkout_helper->get_product_data(),
			'is_cart_page'           => is_cart(),
			'taxes_based_on_billing' => wc_tax_enabled() && get_option( 'woocommerce_tax_based_on' ) === 'billing',
		];
	}

	/**
	 * Localizes additional parameters necessary for the Pay for Order page.
	 *
	 * @param WC_Order $order The order that needs payment.
	 */
	public function localize_pay_for_order_page_scripts( $order ) {
		$currency = get_woocommerce_currency();
		$data     = [];
		$items    = [];

		// Allow third-party plugins to show itemization on the payment request button.
		if ( apply_filters( 'wc_stripe_payment_request_hide_itemization', true ) ) {
			$items[] = [
				'label'  => __( 'Subtotal', 'woocommerce-gateway-stripe' ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $order->get_subtotal(), $currency ),
			];
		} else {
			foreach ( $order->get_items() as $item ) {
				$quantity       = $item->get_quantity();
				$quantity_label = 1 < $quantity ? ' (x' . $quantity . ')' : '';

				if ( method_exists( $item, 'get_total' ) ) {
					$items[] = [
						'label'  => $item->get_name() . $quantity_label,
						'amount' => WC_Stripe_Helper::get_stripe_amount( $item->get_total(), $currency ),
					];
				}
			}
		}

		if ( $order->get_total_tax() ) {
			$items[] = [
				'label'  => __( 'Tax', 'woocommerce-gateway-stripe' ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $order->get_total_tax(), $currency ),
			];
		}

		if ( $order->get_total_discount() ) {
			$items[] = [
				'label'  => __( 'Discount', 'woocommerce-gateway-stripe' ),
				'amount' => - WC_Stripe_Helper::get_stripe_amount( $order->get_total_discount(), $currency ),
			];
		}

		if ( $order->get_shipping_total() ) {
			$shipping_label = sprintf(
			// Translators: %s is the name of the shipping method.
				__( 'Shipping (%s)', 'woocommerce-gateway-stripe' ),
				$order->get_shipping_method()
			);

			$items[] = [
				'label'  => $shipping_label,
				'amount' => WC_Stripe_Helper::get_stripe_amount( $order->get_shipping_total(), $currency ),
			];
		}

		foreach ( $order->get_fees() as $fee ) {
			$items[] = [
				'label'  => $fee->get_name(),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $fee->get_amount(), $currency ),
			];
		}

		$data['order']          = $order->get_id();
		$data['displayItems']   = $items;
		$data['needs_shipping'] = false; // This should be already entered/prepared.
		$data['total']          = [
			'label'   => $this->express_checkout_helper->get_total_label(),
			'amount'  => WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency ),
			'pending' => true,
		];

		wp_localize_script( 'wc_stripe_express_checkout', 'wcStripeExpressCheckoutPayForOrderParams', $data );
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
		<div id="wc-stripe-express-checkout-element" style="margin-top: 1em;clear:both;display:none;">
			<!-- A Stripe Element will be inserted here. -->
		</div>
		<?php

		if ( is_cart() ) {
			add_action( 'woocommerce_after_cart', [ $this, 'add_order_attribution_inputs' ], 1 );
		} else {
			$this->add_order_attribution_inputs();
		}

		$this->display_express_checkout_button_separator_html();
	}

	/**
	 * Add order attribution inputs to the page.
	 *
	 * @return void
	 */
	public function add_order_attribution_inputs() {
		echo '<wc-order-attribution-inputs id="wc-stripe-express-checkout__order-attribution-inputs"></wc-order-attribution-inputs>';
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

	/**
	 * Determine whether to filter the cart needs shipping address.
	 *
	 * @param boolean $needs_shipping_address Whether the cart needs a shipping address.
	 */
	public function filter_cart_needs_shipping_address( $needs_shipping_address ) {
		if ( $this->express_checkout_helper->has_subscription_product() && wc_get_shipping_method_count( true, true ) === 0 ) {
			return false;
		}

		return $needs_shipping_address;
	}
}
