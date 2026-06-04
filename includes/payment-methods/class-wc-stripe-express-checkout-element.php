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

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

/**
 * WC_Stripe_Express_Checkout_Element class.
 */
class WC_Stripe_Express_Checkout_Element {
	/**
	 * Stripe settings.
	 *
	 * @var array
	 */
	public $stripe_settings;

	/**
	 * This Instance.
	 *
	 * @var WC_Stripe_Express_Checkout_Element
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
		// Checks if Stripe Gateway is enabled.
		if ( empty( $this->stripe_settings ) || ( isset( $this->stripe_settings['enabled'] ) && 'yes' !== $this->stripe_settings['enabled'] ) ) {
			return;
		}

		// Don't initiate this class if express checkout element is disabled.
		if ( ! $this->express_checkout_helper->is_express_checkout_enabled() ) {
			return;
		}

		// Apply the express checkout title after intent confirmation (e.g. 3DS) for the
		// subscription change-payment-method flow. Registered unconditionally because the
		// AJAX confirmation request doesn't run through the change-payment-method page check.
		add_action( 'wc_stripe_after_set_payment_method_title_for_confirmed_intent', [ $this, 'maybe_apply_express_title_after_confirmed_intent' ] );

		// Substitute the express checkout label into the WCS-written change-payment note.
		// Registered unconditionally so the filter is in place by the time WCS calls
		// `WC_Subscriptions_Change_Payment_Gateway::update_payment_method()` during the
		// form submission, regardless of which page hook bootstrapped this class.
		add_filter( 'woocommerce_subscription_note_new_payment_method_title', [ $this, 'filter_change_payment_method_note_title' ], 10, 3 );

		// Add network and preload hints for express checkout resources.
		// The hooks check whether express checkout will be shown, and are no-ops in other contexts.
		add_filter( 'wp_resource_hints', [ $this, 'add_resource_hints' ], 10, 2 );
		add_filter( 'wp_preload_resources', [ $this, 'add_preload_resources' ] );

		// Change-payment uses WC Subscriptions' own template, so ride
		// `before_woocommerce_pay` (the only action the gateway fires before it).
		if ( $this->express_checkout_helper->is_change_payment_method_page() ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );
			add_action( 'before_woocommerce_pay', [ $this, 'display_express_checkout_button_html' ], 1 );
			add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
			add_action( 'wc_stripe_change_subs_payment_method_success', [ $this, 'update_subscription_payment_method_title' ] );
			return;
		}

		// Don't load for switch subscription page.
		if ( isset( $_GET['switch-subscription'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

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

		/**
		 * Determines whether express checkout orders should process or ignore
		 * custom, classic checkout fields. Disabled by default.
		 *
		 * @since 9.7.0
		 */
		if ( apply_filters( 'wc_stripe_express_checkout_enable_classic_checkout_custom_fields', false ) ) {
			$custom_checkout_fields_support = new WC_Stripe_Express_Checkout_Custom_Fields();
			$custom_checkout_fields_support->init();
		}
	}

	/**
	 * Get this instance.
	 *
	 * @return WC_Stripe_Express_Checkout_Element
	 */
	public static function instance() {
		return self::$_this;
	}

	/**
	 * Sets the WC customer session if one is not set.
	 * This is needed so nonces can be verified by AJAX Request.
	 * DEPRECATED: We now defer creation of sessions until the user actually interacts with the Express Checkout Element.
	 *
	 * @deprecated 10.5.0
	 * @return void
	 */
	public function set_session() {
		wc_deprecated_function( __FUNCTION__, '10.5.0' );
		// Don't set session cookies on product pages to allow for caching when payment request
		// buttons are disabled. But keep cookies if there is already an active WC session in place.
		if (
			! ( $this->express_checkout_helper->is_product() && $this->express_checkout_helper->should_show_express_checkout_button() )
			|| ( isset( WC()->session ) && WC()->session->has_session() )
		) {
			return;
		}

		WC()->session->set_customer_session_cookie( true );
	}

	/**
	 * Handles express checkout redirect when the redirect dialog "Continue" button is clicked.
	 *
	 * @return void
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
		$publishable_key = WC_Stripe_Mode::is_test()
			? ( $this->stripe_settings['test_publishable_key'] ?? '' )
			: ( $this->stripe_settings['publishable_key'] ?? '' );

		return [
			'ajax_url'                   => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe'                     => [
				'publishable_key'             => $publishable_key,
				'allow_prepaid_card'          => apply_filters( 'wc_stripe_allow_prepaid_card', true ) ? 'yes' : 'no',
				'locale'                      => WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( get_locale() ),
				'is_link_enabled'             => $this->express_checkout_helper->is_link_enabled(),
				'is_express_checkout_enabled' => $this->express_checkout_helper->is_express_checkout_enabled(),
				'is_amazon_pay_enabled'       => $this->express_checkout_helper->is_amazon_pay_enabled(),
			],
			'nonce'                      => [
				'payment'                       => wp_create_nonce( 'wc-stripe-express-checkout' ),
				'shipping'                      => wp_create_nonce( 'wc-stripe-express-checkout-shipping' ),
				'normalize_address'             => wp_create_nonce( 'wc-stripe-express-checkout-normalize-address' ),
				'get_cart_details'              => wp_create_nonce( 'wc-stripe-get-cart-details' ),
				'update_shipping'               => wp_create_nonce( 'wc-stripe-update-shipping-method' ),
				'checkout'                      => wp_create_nonce( 'woocommerce-process_checkout' ),
				'add_to_cart'                   => wp_create_nonce( 'wc-stripe-add-to-cart' ),
				'get_selected_product_data'     => wp_create_nonce( 'wc-stripe-get-selected-product-data' ),
				'log_errors'                    => wp_create_nonce( 'wc-stripe-log-errors' ),
				'clear_cart'                    => wp_create_nonce( 'wc-stripe-clear-cart' ),
				'pay_for_order'                 => wp_create_nonce( 'wc-stripe-pay-for-order' ),
				'wc_store_api'                  => wp_create_nonce( 'wc_store_api' ),
				'wc_store_api_express_checkout' => wp_create_nonce( 'wc_store_api_express_checkout' ),
			],
			'i18n'                       => [
				'no_prepaid_card'  => __( 'Sorry, we\'re not accepting prepaid cards at this time.', 'woocommerce-gateway-stripe' ),
				/* translators: Do not translate the [option] placeholder */
				'unknown_shipping' => __( 'Unknown shipping option "[option]".', 'woocommerce-gateway-stripe' ),
			],
			'checkout'                   => $this->express_checkout_helper->get_checkout_data(),
			'button'                     => $this->express_checkout_helper->get_button_settings(),
			'is_pay_for_order'           => $this->express_checkout_helper->is_pay_for_order_page(),
			'has_block'                  => has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ),
			'login_confirmation'         => $this->express_checkout_helper->get_login_confirmation_settings(),
			'is_product_page'            => $this->is_product_page_for_ece(),
			'is_checkout_page'           => $this->express_checkout_helper->is_checkout(),
			'product'                    => $this->express_checkout_helper->get_product_data(),
			'is_cart_page'               => $this->express_checkout_helper->is_cart(),
			'taxes_based_on_billing'     => wc_tax_enabled() && get_option( 'woocommerce_tax_based_on' ) === 'billing',
			'allowed_shipping_countries' => $this->express_checkout_helper->get_allowed_shipping_countries(),
			'custom_checkout_fields'     => ( new WC_Stripe_Express_Checkout_Custom_Fields() )->get_custom_checkout_fields(),
			'has_free_trial'             => $this->express_checkout_helper->has_free_trial(),
			'is_change_payment_method'   => $this->express_checkout_helper->is_change_payment_method_page(),
		];
	}

	/**
	 * Should ECE use product pricing (vs. cart pricing) in the current context.
	 *
	 * For One Page Checkout (OPC), when checkout buttons are enabled, always use cart
	 * context so discounts/coupons are reflected.
	 *
	 * @return bool True to use product pricing; false to use cart totals.
	 */
	public function is_product_page_for_ece() {
		if ( ! $this->express_checkout_helper->is_product() ) {
			return false;
		}

		// OPC renders checkout on product pages; if ECE is shown on checkout, use cart pricing.
		if (
			$this->express_checkout_helper->is_one_page_checkout()
			&& $this->express_checkout_helper->should_show_ece_on_checkout_page()
		) {
			return false;
		}

		// Otherwise, product context is valid for ECE.
		return true;
	}


	/**
	 * Localizes additional parameters necessary for the Pay for Order page.
	 *
	 * @param WC_Order $order The order that needs payment.
	 * @return void
	 */
	public function localize_pay_for_order_page_scripts( $order ) {
		// Ensure the script is registered before localizing
		if ( ! wp_script_is( 'wc_stripe_express_checkout', 'registered' ) ) {
			$this->register_express_checkout_script();
		}
		$currency = get_woocommerce_currency();
		$data     = [];
		$items    = [];

		// Allow third-party plugins to show itemization on express checkout (keep legacy hook for BC).
		$hide_itemization = apply_filters_deprecated(
			'wc_stripe_payment_request_hide_itemization',
			[ true ],
			'10.6.0',
			'wc_stripe_express_checkout_hide_itemization'
		);
		$hide_itemization = apply_filters( 'wc_stripe_express_checkout_hide_itemization', $hide_itemization );
		if ( $hide_itemization ) {
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
		$data['orderDetails']   = [
			'orderKey'        => $order->get_order_key(),
			'billingEmail'    => $order->get_billing_email(),
			'shippingAddress' => [
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
				'phone'      => $order->get_shipping_phone(),
			],
		];
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
	 * Get asset file data (version and dependencies).
	 *
	 * @return array Array containing 'version' and 'dependencies' keys.
	 */
	private function get_asset_data() {
		$asset_path   = WC_STRIPE_PLUGIN_PATH . '/build/express-checkout.asset.php';
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

		return [
			'version'      => $version,
			'dependencies' => $dependencies,
		];
	}

	/**
	 * Register the express checkout script without enqueuing it.
	 *
	 * @return void
	 */
	private function register_express_checkout_script() {
		$asset_data = $this->get_asset_data();

		wp_register_script( 'stripe', 'https://js.stripe.com/clover/stripe.js', '', null, true );
		wp_register_script(
			'wc_stripe_express_checkout',
			WC_STRIPE_PLUGIN_URL . '/build/express-checkout.js',
			array_merge( [ 'jquery', 'stripe' ], $asset_data['dependencies'] ),
			$asset_data['version'],
			true
		);
	}

	/**
	 * Append preconnect URLs for Stripe-owned hosts when the current page will render ECE.
	 *
	 * @param array  $urls          URLs that core has gathered for the relation type.
	 * @param string $relation_type Resource hint relation ('preconnect', 'dns-prefetch', etc.).
	 *
	 * @return array
	 */
	public function add_resource_hints( $urls, $relation_type ) {
		if ( 'preconnect' !== $relation_type ) {
			return $urls;
		}

		if ( ! $this->express_checkout_helper->is_page_supported() ) {
			return $urls;
		}

		if ( ! $this->express_checkout_helper->should_show_express_checkout_button() ) {
			return $urls;
		}

		// `js.stripe.com` and `m.stripe.network` host cross-origin scripts/iframes, so the
		// crossorigin attribute is required for the preconnected connection to be reused
		// by the subsequent `<script>` and iframe fetches.
		$urls[] = [
			'href'        => 'https://js.stripe.com',
			'crossorigin' => 'anonymous',
		];
		$urls[] = [
			'href'        => 'https://m.stripe.network',
			'crossorigin' => 'anonymous',
		];
		$urls[] = [
			'href' => 'https://q.stripe.com',
		];
		$urls[] = [
			'href'        => 'https://b.stripecdn.com',
			'crossorigin' => 'anonymous',
		];

		return $urls;
	}

	/**
	 * Append a preload entry for the Express Checkout bundle when ECE will render.
	 *
	 * Routed through the `wp_preload_resources` filter (WP 6.1+) so core handles the
	 * `<link>` emission, attribute escaping, and deduplication. The hint lands in
	 * `<head>` ahead of the footer enqueue, letting the browser's preload scanner
	 * overlap the bundle fetch with the rest of `<head>` parsing.
	 *
	 * @param array $preload_resources Preload entries gathered by core.
	 *
	 * @return array
	 */
	public function add_preload_resources( $preload_resources ) {
		if ( ! $this->express_checkout_helper->is_page_supported() ) {
			return $preload_resources;
		}

		if ( ! $this->express_checkout_helper->should_show_express_checkout_button() ) {
			return $preload_resources;
		}

		$asset_data = $this->get_asset_data();

		if ( is_array( $asset_data ) && isset( $asset_data['version'] ) ) {
			$preload_resources[] = [
				'href' => add_query_arg(
					'ver',
					$asset_data['version'],
					WC_STRIPE_PLUGIN_URL . '/build/express-checkout.js'
				),
				'as'   => 'script',
			];
		}

		return $preload_resources;
	}

	/**
	 * Load scripts and styles.
	 *
	 * @return void
	 */
	public function scripts() {
		// If page is not supported, bail.
		if ( ! $this->express_checkout_helper->is_page_supported() ) {
			return;
		}

		if ( ! $this->express_checkout_helper->should_show_express_checkout_button() ) {
			return;
		}

		// Register the script if not already registered
		if ( ! wp_script_is( 'wc_stripe_express_checkout', 'registered' ) ) {
			$this->register_express_checkout_script();
		}

		$asset_data = $this->get_asset_data();

		wp_enqueue_style(
			'wc_stripe_express_checkout_style',
			WC_STRIPE_PLUGIN_URL . '/build/express-checkout.css',
			[],
			$asset_data['version']
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
		$payment_method_title  = '';
		if ( WC_Stripe_Payment_Methods::APPLE_PAY === $express_checkout_type ) {
			$payment_method_title = WC_Stripe_Payment_Methods::APPLE_PAY_LABEL;
		} elseif ( WC_Stripe_Payment_Methods::GOOGLE_PAY === $express_checkout_type ) {
			$payment_method_title = WC_Stripe_Payment_Methods::GOOGLE_PAY_LABEL;
		}

		if ( $payment_method_title ) {
			$payment_method_suffix = WC_Stripe_Express_Checkout_Helper::get_payment_method_title_suffix();
			$order->set_payment_method_title( $payment_method_title . $payment_method_suffix );
			$order->save();
		}

		// Save custom checkout fields to the order.
		$checkout_fields = Package::container()->get( CheckoutFields::class );
		$field_names     = array_keys( $checkout_fields->get_additional_fields() );
		foreach ( $field_names as $name ) {
			if ( isset( $_POST[ $name ] ) ) {
				$order->update_meta_data( $name, wc_clean( wp_unslash( $_POST[ $name ] ) ) );
				$order->save_meta_data();
			}
		}
	}

	/**
	 * Updates the subscription's payment method title to reflect the express checkout type.
	 *
	 * Mirrors `add_order_meta()` for the subscription change-payment-method flow, which
	 * doesn't fire `woocommerce_checkout_order_processed`. Hooked on
	 * `wc_stripe_change_subs_payment_method_success` (success path with $_POST data).
	 *
	 * @return void
	 */
	public function update_subscription_payment_method_title() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
		if ( empty( $_POST['express_checkout_type'] ) || ! isset( $_GET['change_payment_method'] ) ) {
			return;
		}

		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}

		$subscription = wcs_get_subscription( absint( $_GET['change_payment_method'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $subscription ) {
			return;
		}

		$express_checkout_type = is_array( $_POST['express_checkout_type'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['express_checkout_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->apply_express_checkout_title_to_order( $subscription, $express_checkout_type );
	}

	/**
	 * After intent confirmation (e.g. post-3DS), re-apply the express checkout title to
	 * the subscription using the type persisted to subscription meta during the change-
	 * payment-method redirect. This compensates for `set_payment_method_title_for_order()`
	 * resetting the title to the underlying card type during 3DS confirmation.
	 *
	 * Hooked on `wc_stripe_after_set_payment_method_title_for_confirmed_intent`.
	 *
	 * @param WC_Order $order The order or subscription that was just confirmed.
	 * @return void
	 */
	public function maybe_apply_express_title_after_confirmed_intent( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$express_checkout_type = $order->get_meta( '_wc_stripe_express_checkout_type' );
		if ( empty( $express_checkout_type ) ) {
			return;
		}

		$this->apply_express_checkout_title_to_order( $order, $express_checkout_type );

		// Mirror the no-3DS path: associate the new WC payment token with the
		// subscription so the shopper-facing My Account list reflects it.
		$payment_method_id = $order->get_meta( '_wc_stripe_express_checkout_payment_method_id' );
		if ( ! empty( $payment_method_id ) ) {
			WC_Stripe_Express_Checkout_Helper::replace_subscription_payment_token( $order, $payment_method_id );
		}

		// Always remove the markers — the values are only meaningful for the
		// single confirmation that follows the 3DS redirect, and leaving them
		// behind would let a later confirmation on the same subscription
		// re-apply a stale title or token. The cleanup runs unconditionally
		// because that's also true when apply_express_checkout_title_to_order
		// returned false: junk should not linger if we couldn't handle it.
		$order->delete_meta_data( '_wc_stripe_express_checkout_type' );
		$order->delete_meta_data( '_wc_stripe_express_checkout_payment_method_id' );
		$order->save();
	}

	/**
	 * Applies an express checkout title (Apple Pay / Google Pay) to the given order or
	 * subscription. Returns true when the title was applied.
	 *
	 * @param WC_Order $order                 Order or subscription to update.
	 * @param string   $express_checkout_type The express checkout type (apple_pay, google_pay, link).
	 * @return bool    Whether the title was applied.
	 */
	private function apply_express_checkout_title_to_order( $order, $express_checkout_type ) {
		$payment_method_title = $this->get_express_checkout_method_title( $express_checkout_type );

		if ( '' === $payment_method_title ) {
			return false;
		}

		$order->set_payment_method_title( $payment_method_title );
		$order->save();
		return true;
	}

	/**
	 * Returns the order-storage title for an express checkout type, including the
	 * "(Stripe)" suffix for Apple Pay / Google Pay / Amazon Pay and the bare "Link"
	 * label for Stripe Link.
	 *
	 * @param string $express_checkout_type One of WC_Stripe_Payment_Methods::APPLE_PAY/GOOGLE_PAY/AMAZON_PAY/LINK, or empty.
	 * @return string The express label, or empty string if the type is not an express method.
	 */
	private function get_express_checkout_method_title( $express_checkout_type ) {
		$suffix = WC_Stripe_Express_Checkout_Helper::get_payment_method_title_suffix();

		if ( WC_Stripe_Payment_Methods::APPLE_PAY === $express_checkout_type ) {
			return WC_Stripe_Payment_Methods::APPLE_PAY_LABEL . $suffix;
		}
		if ( WC_Stripe_Payment_Methods::GOOGLE_PAY === $express_checkout_type ) {
			return WC_Stripe_Payment_Methods::GOOGLE_PAY_LABEL . $suffix;
		}
		if ( WC_Stripe_Payment_Methods::AMAZON_PAY === $express_checkout_type ) {
			return WC_Stripe_Payment_Methods::AMAZON_PAY_LABEL . $suffix;
		}
		if ( WC_Stripe_Payment_Methods::LINK === $express_checkout_type ) {
			// Match the title produced by the standard Link path (set_payment_method_title_for_order)
			// and expected by WC_Stripe_UPE_Payment_Method_Link::filter_gateway_title — bare "Link", no suffix.
			return WC_Stripe_Payment_Methods::LINK_LABEL;
		}

		return '';
	}

	/**
	 * Substitutes the express checkout label into the change-payment-method order note
	 * that `WC_Subscriptions_Change_Payment_Gateway::update_payment_method()` writes.
	 *
	 * Without this filter, WCS records "Payment method changed from X to Credit Card" even
	 * when the shopper paid via Apple Pay / Google Pay / Link, because WCS computes the
	 * "to" label from `$gateway->get_title()` (which is "Credit Card" for the `stripe`
	 * gateway). Our own title override on `wc_stripe_change_subs_payment_method_success`
	 * runs after the note has already been written, so it can correct the visible
	 * subscription title but not the note copy.
	 *
	 * @param string          $title              The WCS-computed new payment method title.
	 * @param string          $new_payment_method The new payment gateway ID.
	 * @param WC_Subscription $subscription       The subscription being updated.
	 * @return string
	 */
	public function filter_change_payment_method_note_title( $title, $new_payment_method, $subscription ) {
		if ( 'stripe' !== $new_payment_method ) {
			return $title;
		}

		$express_checkout_type = '';

		// No-3DS path: the form submission is in progress, so $_POST is authoritative.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['express_checkout_type'] ) && ! is_array( $_POST['express_checkout_type'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$express_checkout_type = sanitize_text_field( wp_unslash( $_POST['express_checkout_type'] ) );
		} elseif ( $subscription instanceof WC_Subscription ) {
			// 3DS-redirect path: WCS may re-run update_payment_method after intent confirmation,
			// when $_POST has been lost. The express type was persisted to subscription meta
			// before the redirect and is read here as a fallback.
			$express_checkout_type = (string) $subscription->get_meta( '_wc_stripe_express_checkout_type' );
		}

		$express_title = $this->get_express_checkout_method_title( $express_checkout_type );

		return '' !== $express_title ? $express_title : $title;
	}

	/**
	 * Filters the gateway title to reflect express checkout type
	 *
	 * @param string $title The gateway title.
	 * @param string $id    The gateway ID.
	 * @return string
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
			$express_method_titles = WC_Stripe_Payment_Methods::EXPRESS_METHODS_LABELS;
			$suffix                = WC_Stripe_Express_Checkout_Helper::get_payment_method_title_suffix();
			array_walk(
				$express_method_titles,
				function ( &$value, $key ) use ( $suffix ) {
					$value .= $suffix;
				}
			);
			if ( in_array( $method_title, $express_method_titles, true ) ) {
				return $method_title;
			}
		}

		return $title;
	}

	/**
	 * Display the express checkout button.
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	public function display_express_checkout_button_separator_html() {
		if ( ! $this->express_checkout_helper->is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		if ( $this->express_checkout_helper->is_checkout() && ! $this->express_checkout_helper->should_show_ece_on_checkout_page() ) {
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
	 * @return bool
	 */
	public function filter_cart_needs_shipping_address( $needs_shipping_address ) {
		if ( $this->express_checkout_helper->has_subscription_product() && wc_get_shipping_method_count( true, true ) === 0 ) {
			return false;
		}

		return $needs_shipping_address;
	}
}
