<?php
/**
 * Stripe Payment Request API
 * Adds support for Apple Pay and Chrome Payment Request API buttons.
 * Utilizes the Stripe Payment Request Button to support checkout from the product detail and cart pages.
 *
 * @package WooCommerce_Stripe/Classes/Payment_Request
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Payment_Request class.
 */
class WC_Stripe_Payment_Request {
	/**
	 * Enabled.
	 *
	 * @var
	 */
	public $stripe_settings;

	/**
	 * Total label
	 *
	 * @var
	 */
	public $total_label;

	/**
	 * Key
	 *
	 * @var
	 */
	public $publishable_key;

	/**
	 * Key
	 *
	 * @var
	 */
	public $secret_key;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * This Instance.
	 *
	 * @var
	 */
	private static $_this;

	/**
	 * Initialize class actions.
	 *
	 * @since   3.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		self::$_this           = $this;
		$this->stripe_settings = get_option( 'woocommerce_stripe_settings', [] );
		$this->testmode        = ( ! empty( $this->stripe_settings['testmode'] ) && 'yes' === $this->stripe_settings['testmode'] ) ? true : false;
		$this->publishable_key = ! empty( $this->stripe_settings['publishable_key'] ) ? $this->stripe_settings['publishable_key'] : '';
		$this->secret_key      = ! empty( $this->stripe_settings['secret_key'] ) ? $this->stripe_settings['secret_key'] : '';
		$this->total_label     = ! empty( $this->stripe_settings['statement_descriptor'] ) ? WC_Stripe_Helper::clean_statement_descriptor( $this->stripe_settings['statement_descriptor'] ) : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $this->stripe_settings['test_publishable_key'] ) ? $this->stripe_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $this->stripe_settings['test_secret_key'] ) ? $this->stripe_settings['test_secret_key'] : '';
		}

		$this->total_label = str_replace( "'", '', $this->total_label ) . apply_filters( 'wc_stripe_payment_request_total_label_suffix', ' (via WooCommerce)' );

		// Checks if Stripe Gateway is enabled.
		if ( empty( $this->stripe_settings ) || ( isset( $this->stripe_settings['enabled'] ) && 'yes' !== $this->stripe_settings['enabled'] ) ) {
			return;
		}

		// Checks if Payment Request is enabled.
		if ( ! isset( $this->stripe_settings['payment_request'] ) || 'yes' !== $this->stripe_settings['payment_request'] ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'set_session' ] );
		$this->init();
	}

	/**
	 * Checks whether authentication is required for checkout.
	 *
	 * @since 5.1.0
	 *
	 * @return bool
	 */
	public function is_authentication_required() {
		// If guest checkout is disabled, authentication might be required.
		if ( 'no' === get_option( 'woocommerce_enable_guest_checkout', 'yes' ) ) {
			// If account creation is not possible, authentication is required.
			return ! $this->is_account_creation_possible();
		}

		return false;
	}

	/**
	 * Checks whether account creation is possible during checkout.
	 *
	 * @since 5.1.0
	 *
	 * @return bool
	 */
	public function is_account_creation_possible() {
		// If automatically generate username/password are disabled, the Payment Request API
		// can't include any of those fields, so account creation is not possible.
		return (
			'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' ) &&
			'yes' === get_option( 'woocommerce_registration_generate_username', 'yes' ) &&
			'yes' === get_option( 'woocommerce_registration_generate_password', 'yes' )
		);
	}

	/**
	 * Checks if keys are set and valid.
	 *
	 * @since  4.0.6
	 * @return boolean True if the keys are set *and* valid, false otherwise (for example, if keys are empty or the secret key was pasted as publishable key).
	 */
	public function are_keys_set() {
		// NOTE: updates to this function should be added to are_keys_set()
		// in includes/abstracts/abstract-wc-stripe-payment-gateway.php
		if ( $this->testmode ) {
			return preg_match( '/^pk_test_/', $this->publishable_key )
				&& preg_match( '/^[rs]k_test_/', $this->secret_key );
		} else {
			return preg_match( '/^pk_live_/', $this->publishable_key )
				&& preg_match( '/^[rs]k_live_/', $this->secret_key );
		}
	}

	/**
	 * Get this instance.
	 *
	 * @since  4.0.6
	 * @return class
	 */
	public static function instance() {
		return self::$_this;
	}

	/**
	 * Sets the WC customer session if one is not set.
	 * This is needed so nonces can be verified by AJAX Request.
	 *
	 * @since  4.0.0
	 * @version 5.2.0
	 * @return void
	 */
	public function set_session() {
		if ( ! $this->is_product() || ( isset( WC()->session ) && WC()->session->has_session() ) ) {
			return;
		}

		WC()->session->set_customer_session_cookie( true );
	}

	/**
	 * Initialize hooks.
	 *
	 * @since   4.0.0
	 * @version 4.0.0
	 * @return  void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );

		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_payment_request_button_html' ], 1 );
		add_action( 'woocommerce_after_add_to_cart_quantity', [ $this, 'display_payment_request_button_separator_html' ], 2 );

		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_payment_request_button_html' ], 1 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_payment_request_button_separator_html' ], 2 );

		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_payment_request_button_html' ], 1 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_payment_request_button_separator_html' ], 2 );

		add_action( 'wc_ajax_wc_stripe_get_cart_details', [ $this, 'ajax_get_cart_details' ] );
		add_action( 'wc_ajax_wc_stripe_get_shipping_options', [ $this, 'ajax_get_shipping_options' ] );
		add_action( 'wc_ajax_wc_stripe_update_shipping_method', [ $this, 'ajax_update_shipping_method' ] );
		add_action( 'wc_ajax_wc_stripe_create_order', [ $this, 'ajax_create_order' ] );
		add_action( 'wc_ajax_wc_stripe_add_to_cart', [ $this, 'ajax_add_to_cart' ] );
		add_action( 'wc_ajax_wc_stripe_get_selected_product_data', [ $this, 'ajax_get_selected_product_data' ] );
		add_action( 'wc_ajax_wc_stripe_clear_cart', [ $this, 'ajax_clear_cart' ] );
		add_action( 'wc_ajax_wc_stripe_log_errors', [ $this, 'ajax_log_errors' ] );

		add_filter( 'woocommerce_gateway_title', [ $this, 'filter_gateway_title' ], 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'add_order_meta' ], 10, 2 );
	}

	/**
	 * Gets the button type.
	 *
	 * @since   4.0.0
	 * @version 4.0.0
	 * @return  string
	 */
	public function get_button_type() {
		return isset( $this->stripe_settings['payment_request_button_type'] ) ? $this->stripe_settings['payment_request_button_type'] : 'default';
	}

	/**
	 * Gets the button theme.
	 *
	 * @since   4.0.0
	 * @version 4.0.0
	 * @return  string
	 */
	public function get_button_theme() {
		return isset( $this->stripe_settings['payment_request_button_theme'] ) ? $this->stripe_settings['payment_request_button_theme'] : 'dark';
	}

	/**
	 * Gets the button height.
	 *
	 * @since   4.0.0
	 * @version 4.0.0
	 * @return  string
	 */
	public function get_button_height() {
		return isset( $this->stripe_settings['payment_request_button_height'] ) ? str_replace( 'px', '', $this->stripe_settings['payment_request_button_height'] ) : '64';
	}

	/**
	 * Checks if the button is branded.
	 *
	 * @since   4.4.0
	 * @version 4.4.0
	 * @return  boolean
	 */
	public function is_branded_button() {
		return 'branded' === $this->get_button_type();
	}

	/**
	 * Gets the branded button type.
	 *
	 * @since   4.4.0
	 * @version 4.4.0
	 * @return  string
	 */
	public function get_button_branded_type() {
		return isset( $this->stripe_settings['payment_request_button_branded_type'] ) ? $this->stripe_settings['payment_request_button_branded_type'] : 'default';
	}

	/**
	 * Checks if the button is custom.
	 *
	 * @since   4.4.0
	 * @version 4.4.0
	 * @return  boolean
	 */
	public function is_custom_button() {
		return 'custom' === $this->get_button_type();
	}

	/**
	 * Returns custom button css selector.
	 *
	 * @since   4.4.0
	 * @version 4.4.0
	 * @return  string
	 */
	public function custom_button_selector() {
		return $this->is_custom_button() ? '#wc-stripe-custom-button' : '';
	}

	/**
	 * Gets the custom button label.
	 *
	 * @since   4.4.0
	 * @version 4.4.0
	 * @return  string
	 */
	public function get_button_label() {
		return isset( $this->stripe_settings['payment_request_button_label'] ) ? $this->stripe_settings['payment_request_button_label'] : 'Buy now';
	}

	/**
	 * Gets the product total price.
	 *
	 * @since 5.2.0
	 *
	 * @param object $product WC_Product_* object.
	 * @return integer Total price.
	 */
	public function get_product_price( $product ) {
		$product_price = $product->get_price();
		// Add subscription sign-up fees to product price.
		if ( 'subscription' === $product->get_type() && class_exists( 'WC_Subscriptions_Product' ) ) {
			$product_price = $product->get_price() + WC_Subscriptions_Product::get_sign_up_fee( $product );
		}

		return $product_price;
	}

	/**
	 * Gets the product data for the currently viewed page
	 *
	 * @since   4.0.0
	 * @version 5.2.0
	 * @return  mixed Returns false if not on a product page, the product information otherwise.
	 */
	public function get_product_data() {
		if ( ! $this->is_product() ) {
			return false;
		}

		$product = $this->get_product();

		if ( 'variable' === $product->get_type() ) {
			$variation_attributes = $product->get_variation_attributes();
			$attributes           = [];

			foreach ( $variation_attributes as $attribute_name => $attribute_values ) {
				$attribute_key = 'attribute_' . sanitize_title( $attribute_name );

				// Passed value via GET takes precedence. Otherwise get the default value for given attribute
				$attributes[ $attribute_key ] = isset( $_GET[ $attribute_key ] )
					? wc_clean( wp_unslash( $_GET[ $attribute_key ] ) )
					: $product->get_variation_default_attribute( $attribute_name );
			}

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			if ( ! empty( $variation_id ) ) {
				$product = wc_get_product( $variation_id );
			}
		}

		$data  = [];
		$items = [];

		$items[] = [
			'label'  => $product->get_name(),
			'amount' => WC_Stripe_Helper::get_stripe_amount( $this->get_product_price( $product ) ),
		];

		if ( wc_tax_enabled() ) {
			$items[] = [
				'label'   => __( 'Tax', 'woocommerce-gateway-stripe' ),
				'amount'  => 0,
				'pending' => true,
			];
		}

		if ( wc_shipping_enabled() && $product->needs_shipping() ) {
			$items[] = [
				'label'   => __( 'Shipping', 'woocommerce-gateway-stripe' ),
				'amount'  => 0,
				'pending' => true,
			];

			// TODO: Confirm this is still needed, doesn't seems to be used anywhere
			$data['shippingOptions'] = [
				'id'     => 'pending',
				'label'  => __( 'Pending', 'woocommerce-gateway-stripe' ),
				'detail' => '',
				'amount' => 0,
			];
		}

		$data['total']        = [
			'label'   => apply_filters( 'wc_stripe_payment_request_total_label', $this->total_label ),
			'amount'  => WC_Stripe_Helper::get_stripe_amount( $this->get_product_price( $product ) ),
			'pending' => true,
		];

		$data['currency']        = strtolower( get_woocommerce_currency() );
		$data['country_code']    = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

		return apply_filters( 'wc_stripe_payment_request_product_data', $data, $product );
	}

	/**
	 * Filters the gateway title to reflect Payment Request type
	 */
	public function filter_gateway_title( $title, $id ) {
		global $post;

		if ( ! is_object( $post ) ) {
			return $title;
		}

		$order        = wc_get_order( $post->ID );
		$method_title = is_object( $order ) ? $order->get_payment_method_title() : '';

		if ( 'stripe' === $id && ! empty( $method_title ) ) {
			if ( 'Apple Pay (Stripe)' === $method_title
				|| 'Google Pay (Stripe)' === $method_title
				|| 'Payment Request (Stripe)' === $method_title
			) {
				return $method_title;
			}

			// We renamed 'Chrome Payment Request' to just 'Payment Request' since Payment Requests
			// are supported by other browsers besides Chrome. As such, we need to check for the
			// old title to make sure older orders still reflect that they were paid via Payment
			// Request Buttons.
			if ( 'Chrome Payment Request (Stripe)' === $method_title ) {
				return 'Payment Request (Stripe)';
			}
		}

		return $title;
	}

	/**
	 * Normalizes postal code in case of redacted data from Apple Pay.
	 *
	 * @since 5.2.0
	 *
	 * @param string $postcode Postal code.
	 * @param string $country Country.
	 */
	public function get_normalized_postal_code( $postcode, $country ) {
		/**
		 * Currently, Apple Pay truncates the UK and Canadian postal codes to the first 4 and 3 characters respectively
		 * when passing it back from the shippingcontactselected object. This causes WC to invalidate
		 * the postal code and not calculate shipping zones correctly.
		 */
		if ( 'GB' === $country ) {
			// Replaces a redacted string with something like LN10***.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 7, '*' );
		}
		if ( 'CA' === $country ) {
			// Replaces a redacted string with something like L4Y***.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 6, '*' );
		}

		return $postcode;
	}

	/**
	 * Add needed order meta
	 *
	 * @param integer $order_id    The order ID.
	 * @param array   $posted_data The posted data from checkout form.
	 *
	 * @since   4.0.0
	 * @version 4.0.0
	 * @return  void
	 */
	public function add_order_meta( $order_id, $posted_data ) {
		if ( empty( $_POST['payment_request_type'] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		$payment_request_type = wc_clean( wp_unslash( $_POST['payment_request_type'] ) );

		if ( 'apple_pay' === $payment_request_type ) {
			$order->set_payment_method_title( 'Apple Pay (Stripe)' );
			$order->save();
		} elseif ( 'google_pay' === $payment_request_type ) {
			$order->set_payment_method_title( 'Google Pay (Stripe)' );
			$order->save();
		} elseif ( 'payment_request_api' === $payment_request_type ) {
			$order->set_payment_method_title( 'Payment Request (Stripe)' );
			$order->save();
		}
	}

	/**
	 * Checks to make sure product type is supported.
	 *
	 * @since   3.1.0
	 * @version 4.0.0
	 * @return  array
	 */
	public function supported_product_types() {
		return apply_filters(
			'wc_stripe_payment_request_supported_types',
			[
				'simple',
				'variable',
				'variation',
				'subscription',
				'variable-subscription',
				'subscription_variation',
				'booking',
				'bundle',
				'composite',
			]
		);
	}

	/**
	 * Checks the cart to see if all items are allowed to be used.
	 *
	 * @since   3.1.4
	 * @version 4.0.0
	 * @return  boolean
	 */
	public function allowed_items_in_cart() {
		// Pre Orders compatibility where we don't support charge upon release.
		if ( class_exists( 'WC_Pre_Orders_Cart' ) && WC_Pre_Orders_Cart::cart_contains_pre_order() && class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( WC_Pre_Orders_Cart::get_pre_order_product() ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( ! in_array( $_product->get_type(), $this->supported_product_types() ) ) {
				return false;
			}

			// Not supported for subscription products when user is not authenticated and account creation is not possible.
			if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $_product ) && ! is_user_logged_in() && ! $this->is_account_creation_possible() ) {
				return false;
			}

			// Trial subscriptions with shipping are not supported.
			if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $_product ) && $_product->needs_shipping() && WC_Subscriptions_Product::get_trial_length( $_product ) > 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks if this page contains a cart or checkout block.
	 *
	 * @since 5.2.0
	 * @return boolean
	 */
	public function is_block() {
		return has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' );
	}

	/**
	 * Checks if this is a product page or content contains a product_page shortcode.
	 *
	 * @since 5.2.0
	 * @return boolean
	 */
	public function is_product() {
		return is_product() || wc_post_content_has_shortcode( 'product_page' );
	}

	/**
	 * Get product from product page or product_page shortcode.
	 *
	 * @since 5.2.0
	 * @return WC_Product Product object.
	 */
	public function get_product() {
		global $post;

		if ( is_product() ) {
			return wc_get_product( $post->ID );
		} elseif ( wc_post_content_has_shortcode( 'product_page' ) ) {
			// Get id from product_page shortcode.
			preg_match( '/\[product_page id="(?<id>\d+)"\]/', $post->post_content, $shortcode_match );

			if ( ! isset( $shortcode_match['id'] ) ) {
				return false;
			}

			return wc_get_product( $shortcode_match['id'] );
		}

		return false;
	}

	/**
	 * Load public scripts and styles.
	 *
	 * @since   3.1.0
	 * @version 5.2.0
	 */
	public function scripts() {
		// If keys are not set bail.
		if ( ! $this->are_keys_set() ) {
			WC_Stripe_Logger::log( 'Keys are not set correctly.' );
			return;
		}

		// If no SSL bail.
		if ( ! $this->testmode && ! is_ssl() ) {
			WC_Stripe_Logger::log( 'Stripe Payment Request live mode requires SSL.' );
			return;
		}

		// If page is not supported, bail.
		if ( ! $this->is_block() && ! $this->is_product() && ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( $this->is_product() && ! $this->should_show_payment_button_on_product_page() ) {
			return;
		} elseif ( ! $this->should_show_payment_button_on_cart() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$stripe_params = [
			'ajax_url'        => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe'          => [
				'key'                => $this->publishable_key,
				'allow_prepaid_card' => apply_filters( 'wc_stripe_allow_prepaid_card', true ) ? 'yes' : 'no',
			],
			'nonce'           => [
				'payment'                   => wp_create_nonce( 'wc-stripe-payment-request' ),
				'shipping'                  => wp_create_nonce( 'wc-stripe-payment-request-shipping' ),
				'update_shipping'           => wp_create_nonce( 'wc-stripe-update-shipping-method' ),
				'checkout'                  => wp_create_nonce( 'woocommerce-process_checkout' ),
				'add_to_cart'               => wp_create_nonce( 'wc-stripe-add-to-cart' ),
				'get_selected_product_data' => wp_create_nonce( 'wc-stripe-get-selected-product-data' ),
				'log_errors'                => wp_create_nonce( 'wc-stripe-log-errors' ),
				'clear_cart'                => wp_create_nonce( 'wc-stripe-clear-cart' ),
			],
			'i18n'            => [
				'no_prepaid_card'  => __( 'Sorry, we\'re not accepting prepaid cards at this time.', 'woocommerce-gateway-stripe' ),
				/* translators: Do not translate the [option] placeholder */
				'unknown_shipping' => __( 'Unknown shipping option "[option]".', 'woocommerce-gateway-stripe' ),
			],
			'checkout'        => [
				'url'               => wc_get_checkout_url(),
				'currency_code'     => strtolower( get_woocommerce_currency() ),
				'country_code'      => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
				'needs_shipping'    => WC()->cart->needs_shipping() ? 'yes' : 'no',
				// Defaults to 'required' to match how core initializes this option.
				'needs_payer_phone' => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			],
			'button'          => [
				'type'         => $this->get_button_type(),
				'theme'        => $this->get_button_theme(),
				'height'       => $this->get_button_height(),
				'locale'       => apply_filters( 'wc_stripe_payment_request_button_locale', substr( get_locale(), 0, 2 ) ), // Default format is en_US.
				'is_custom'    => $this->is_custom_button(),
				'is_branded'   => $this->is_branded_button(),
				'css_selector' => $this->custom_button_selector(),
				'branded_type' => $this->get_button_branded_type(),
			],
			'is_product_page' => $this->is_product(),
			'product'         => $this->get_product_data(),
		];

		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script( 'wc_stripe_payment_request', plugins_url( 'assets/js/stripe-payment-request' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), [ 'jquery', 'stripe' ], WC_STRIPE_VERSION, true );

		wp_localize_script( 'wc_stripe_payment_request', 'wc_stripe_payment_request_params', apply_filters( 'wc_stripe_payment_request_params', $stripe_params ) );

		wp_enqueue_script( 'wc_stripe_payment_request' );

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['stripe'] ) ) {
			$gateways['stripe']->payment_scripts();
		}
	}

	/**
	 * Display the payment request button.
	 *
	 * @since   4.0.0
	 * @version 5.2.0
	 */
	public function display_payment_request_button_html() {
		global $post;

		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['stripe'] ) ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() && ! $this->is_product() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( is_checkout() && ! apply_filters( 'wc_stripe_show_payment_request_on_checkout', false, $post ) ) {
			return;
		}
		?>
		<div id="wc-stripe-payment-request-wrapper" style="clear:both;padding-top:1.5em;display:none;">
			<div id="wc-stripe-payment-request-button">
				<?php
				if ( $this->is_custom_button() ) {
					$label      = esc_html( $this->get_button_label() );
					$class_name = esc_attr( 'button ' . $this->get_button_theme() );
					$style      = esc_attr( 'height:' . $this->get_button_height() . 'px;' );
					echo "<button id=\"wc-stripe-custom-button\" class=\"$class_name\" style=\"$style\"> $label </button>";
				}
				?>
				<!-- A Stripe Element will be inserted here. -->
			</div>
		</div>
		<?php
	}

	/**
	 * Display payment request button separator.
	 *
	 * @since   4.0.0
	 * @version 5.2.0
	 */
	public function display_payment_request_button_separator_html() {
		global $post;

		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['stripe'] ) ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() && ! $this->is_product() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( is_checkout() && ! apply_filters( 'wc_stripe_show_payment_request_on_checkout', false, $post ) ) {
			return;
		}
		?>
		<p id="wc-stripe-payment-request-button-separator" style="margin-top:1.5em;text-align:center;display:none;">&mdash; <?php esc_html_e( 'OR', 'woocommerce-gateway-stripe' ); ?> &mdash;</p>
		<?php
	}

	/**
	 * Whether payment button html should be rendered on the Cart
	 *
	 * @since  4.4.1
	 * @return boolean
	 */
	private function should_show_payment_button_on_cart() {
		// Not supported when user isn't authenticated and authentication is required.
		if ( ! is_user_logged_in() && $this->is_authentication_required() ) {
			return false;
		}

		if ( ! apply_filters( 'wc_stripe_show_payment_request_on_cart', true ) ) {
			return false;
		}

		if ( ! $this->allowed_items_in_cart() ) {
			WC_Stripe_Logger::log( 'Items in the cart has unsupported product type ( Payment Request button disabled )' );
			return false;
		}
		return true;
	}

	/**
	 * Whether payment button html should be rendered
	 *
	 * @since  4.3.2
	 * @version 5.2.0
	 * @return boolean
	 */
	private function should_show_payment_button_on_product_page() {
		global $post;

		if ( apply_filters( 'wc_stripe_hide_payment_request_on_product_page', false, $post ) ) {
			return false;
		}

		$product = $this->get_product();

		if ( ! is_object( $product ) || ! in_array( $product->get_type(), $this->supported_product_types() ) ) {
			return false;
		}

		// Not supported when user isn't authenticated and authentication is required.
		if ( ! is_user_logged_in() && $this->is_authentication_required() ) {
			return false;
		}

		// Not supported for subscription products when user is not authenticated and account creation is not possible.
		if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) && ! is_user_logged_in() && ! $this->is_account_creation_possible() ) {
			return false;
		}

		// Trial subscriptions with shipping are not supported.
		if ( class_exists( 'WC_Subscriptions_Product' ) && $product->needs_shipping() && WC_Subscriptions_Product::get_trial_length( $product ) > 0 ) {
			return false;
		}

		// Pre Orders charge upon release not supported.
		if ( class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) {
			return false;
		}

		// Composite products are not supported on the product page.
		if ( class_exists( 'WC_Composite_Products' ) && function_exists( 'is_composite_product' ) && is_composite_product() ) {
			return false;
		}

		// File upload addon not supported
		if ( class_exists( 'WC_Product_Addons_Helper' ) ) {
			$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );
			foreach ( $product_addons as $addon ) {
				if ( 'file_upload' === $addon['type'] ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Log errors coming from Payment Request
	 *
	 * @since   3.1.4
	 * @version 4.0.0
	 */
	public function ajax_log_errors() {
		check_ajax_referer( 'wc-stripe-log-errors', 'security' );

		$errors = isset( $_POST['errors'] ) ? wc_clean( wp_unslash( $_POST['errors'] ) ) : '';

		WC_Stripe_Logger::log( $errors );

		exit;
	}

	/**
	 * Clears cart.
	 *
	 * @since   3.1.4
	 * @version 4.0.0
	 */
	public function ajax_clear_cart() {
		check_ajax_referer( 'wc-stripe-clear-cart', 'security' );

		WC()->cart->empty_cart();
		exit;
	}

	/**
	 * Get cart details.
	 */
	public function ajax_get_cart_details() {
		check_ajax_referer( 'wc-stripe-payment-request', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$page_options      = filter_input_array( INPUT_POST, [ 'shipping_pending' => FILTER_VALIDATE_BOOLEAN ] );

		WC()->cart->calculate_totals();

		$currency = get_woocommerce_currency();

		// Set mandatory payment details.
		$data = [
			'shipping_required' => WC()->cart->needs_shipping(),
			'needs_payer_phone' => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			'order_data'        => [
				'currency'     => strtolower( $currency ),
				'country_code' => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			],
		];

		$shipping_pending = $page_options['shipping_pending'];
		$data['order_data'] += $this->build_response($shipping_pending, 'cart_page');
		wp_send_json( $data );
	}

	/**
	 * Get shipping options.
	 *
	 * @see WC_Cart::get_shipping_packages().
	 * @see WC_Shipping::calculate_shipping().
	 * @see WC_Shipping::get_packages().
	 */
	public function ajax_get_shipping_options() {
		check_ajax_referer( 'wc-stripe-payment-request-shipping', 'security' );

		$shipping_address          = filter_input_array(
			INPUT_POST,
			[
				'country'   => FILTER_SANITIZE_STRING,
				'state'     => FILTER_SANITIZE_STRING,
				'postcode'  => FILTER_SANITIZE_STRING,
				'city'      => FILTER_SANITIZE_STRING,
				'address'   => FILTER_SANITIZE_STRING,
				'address_2' => FILTER_SANITIZE_STRING,
			]
		);
		$product_view_options      = filter_input_array(
			INPUT_POST,
			[
				'is_product_page'  => FILTER_VALIDATE_BOOLEAN,
				'shipping_pending' => FILTER_VALIDATE_BOOLEAN,
			]
		);

		if ($product_view_options['is_product_page']) {
			$page = 'product_page';
		} else {
			$page = 'cart_page';
		}

		WC_Stripe_Logger::log( '$page ' . print_r($page, true) );


		$shipping_pending       = $product_view_options['shipping_pending'];

		$data = $this->get_shipping_options($shipping_address, $shipping_pending, $page);
		wp_send_json( $data );
	}

	/**
	 * Gets shipping options available for specified shipping address
	 *
	 * @param array   $shipping_address       Shipping address.
	 * @param boolean $itemized_display_items Indicates whether to show subtotals or itemized views.
	 *
	 * @return array Shipping options data.
	 * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag
	 */
	public function get_shipping_options( $shipping_address, $shipping_pending, $page = 'product_page' ) {
		try {
			// Set the shipping options.
			$data = [];

			// Remember current shipping method before resetting.
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			$this->calculate_shipping( apply_filters( 'wc_stripe_payment_request_shipping_posted_values', $shipping_address ) );

			$packages = WC()->shipping->get_packages();

			if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
				foreach ( $packages as $package_key => $package ) {
					if ( empty( $package['rates'] ) ) {
						throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
					}

					foreach ( $package['rates'] as $key => $rate ) {
						$data['shipping_options'][] = [
							'id'     => $rate->id,
							'label'  => $rate->label,
							'detail' => '',
							'amount' => WC_Stripe_Helper::get_stripe_amount( $rate->cost ),
						];
					}
				}
			} else {
				throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
			}

			// The first shipping option is automatically applied on the client.
			// Keep chosen shipping method by sorting shipping options if the method still available for new address.
			// Fallback to the first available shipping method.
			if ( isset( $data['shipping_options'][0] ) ) {
				if ( isset( $chosen_shipping_methods[0] ) ) {
					$chosen_method_id         = $chosen_shipping_methods[0];
					$compare_shipping_options = function ( $a, $b ) use ( $chosen_method_id ) {
						if ( $a['id'] === $chosen_method_id ) {
							return -1;
						}

						if ( $b['id'] === $chosen_method_id ) {
							return 1;
						}

						return 0;
					};
					usort( $data['shipping_options'], $compare_shipping_options );
				}

				$first_shipping_method_id = $data['shipping_options'][0]['id'];
				$this->update_shipping_method( [ $first_shipping_method_id ] );
			}

			WC()->cart->calculate_totals();

			$data 				  += $this->build_response( $shipping_pending, $page );
			$data['result']  = 'success';
		} catch ( Exception $e ) {
			// TODO: Check this case
			$data          += $this->build_response( $shipping_pending, $page );
			$data['result'] = 'invalid_shipping_address';
		}

		return $data;
	}

	/**
	 * Update shipping method.
	 */
	public function ajax_update_shipping_method() {
		check_ajax_referer( 'wc-stripe-update-shipping-method', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$shipping_methods = filter_input( INPUT_POST, 'shipping_method', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$this->update_shipping_method( $shipping_methods );

		WC()->cart->calculate_totals();

		$product_view_options      = filter_input_array( INPUT_POST, [ 'is_product_page' => FILTER_SANITIZE_STRING ] );
		$should_show_itemized_view = ! isset( $product_view_options['is_product_page'] ) ? true : filter_var( $product_view_options['is_product_page'], FILTER_VALIDATE_BOOLEAN );

		$data           = [];
		$data          += $this->build_display_items( $should_show_itemized_view );
		$data['result'] = 'success';

		wp_send_json( $data );
	}

	/**
	 * Updates shipping method in WC session
	 *
	 * @param array $shipping_methods Array of selected shipping methods ids.
	 */
	public function update_shipping_method( $shipping_methods ) {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shipping_methods ) ) {
			foreach ( $shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}

	/**
	 * Gets the selected product data.
	 *
	 * @since   4.0.0
	 * @version 4.0.0
	 * @return  array $data
	 */
	public function ajax_get_selected_product_data() {
		check_ajax_referer( 'wc-stripe-get-selected-product-data', 'security' );

		try {
			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$product    = wc_get_product( $product_id );
			$qty        = ! isset( $_POST['qty'] ) ? 1 : apply_filters( 'woocommerce_add_to_cart_quantity', absint( $_POST['qty'] ), $product_id );

			if ( ! is_a( $product, 'WC_Product' ) ) {
				/* translators: %d is the product Id */
				throw new Exception( sprintf( __( 'Product with the ID (%d) cannot be found.', 'woocommerce-gateway-stripe' ), $product_id ) );
			}

			// TODO: Added the same check in `ajax_add_to_cart` just in case we remove this (`ajax_get_selected_product_data`) method
			if ( ! $product->has_enough_stock( $qty ) ) {
				/* translators: 1: product name 2: quantity in stock */
				throw new Exception( sprintf( __( 'You cannot add that amount of "%1$s"; to the cart because there is not enough stock (%2$s remaining).', 'woocommerce-gateway-stripe' ), $product->get_name(), wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ) );
			}

			$data = $this->build_response();
			wp_send_json( $data );
		} catch ( Exception $e ) {
			wp_send_json( [ 'error' => wp_strip_all_tags( $e->getMessage() ) ] );
		}
	}

	/**
	 * Adds the current product to the cart. Used on product detail page.
	 *
	 * @since   4.0.0
	 * @version 4.0.0
	 * @return  array $data
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'wc-stripe-add-to-cart', 'security' );

		try {
			if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
				define( 'WOOCOMMERCE_CART', true );
			}

			$product_id       = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$product          = wc_get_product( $product_id );
			$post_input       = filter_input_array( INPUT_POST, [ 'shipping_pending' => FILTER_VALIDATE_BOOLEAN ] );
			$shipping_pending = $post_input['shipping_pending'];

			if ( ! is_a( $product, 'WC_Product' ) ) {
				/* translators: %d is the product Id */
				throw new Exception( sprintf( __( 'Product with the ID (%d) cannot be found.', 'woocommerce-gateway-stripe' ), $product_id ) );
			}

			$qty          = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );
			$product_type = $product->get_type();

			if ( ! $product->has_enough_stock( $qty ) ) {
				/* translators: 1: product name 2: quantity in stock */
				throw new Exception( sprintf( __( 'You cannot add that amount of "%1$s"; to the cart because there is not enough stock (%2$s remaining).', 'woocommerce-gateway-stripe' ), $product->get_name(), wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ) );
			}

			// Do we need to do this before emptying the cart?
			WC()->shipping->reset_shipping();

			// First empty the cart to prevent wrong calculation.
			WC()->cart->empty_cart();

			if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
				$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

				WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
			}

			if ( 'simple' === $product_type || 'subscription' === $product_type ) {
				WC()->cart->add_to_cart( $product->get_id(), $qty );
			}

			$data = $this->build_response( $shipping_pending );
			wp_send_json( $data );

		} catch ( Exception $e ) {
			wp_send_json( [ 'error' => wp_strip_all_tags( $e->getMessage() ) ] );
		}

	}

	/**
	 * Normalizes billing and shipping state fields.
	 *
	 * @since 4.0.0
	 * @version 5.1.0
	 */
	public function normalize_state() {
		$billing_country  = ! empty( $_POST['billing_country'] ) ? wc_clean( wp_unslash( $_POST['billing_country'] ) ) : '';
		$shipping_country = ! empty( $_POST['shipping_country'] ) ? wc_clean( wp_unslash( $_POST['shipping_country'] ) ) : '';
		$billing_state    = ! empty( $_POST['billing_state'] ) ? wc_clean( wp_unslash( $_POST['billing_state'] ) ) : '';
		$shipping_state   = ! empty( $_POST['shipping_state'] ) ? wc_clean( wp_unslash( $_POST['shipping_state'] ) ) : '';

		if ( $billing_state && $billing_country ) {
			$_POST['billing_state'] = $this->get_normalized_state( $billing_state, $billing_country );
		}

		if ( $shipping_state && $shipping_country ) {
			$_POST['shipping_state'] = $this->get_normalized_state( $shipping_state, $shipping_country );
		}
	}

	/**
	 * Checks if given state is normalized.
	 *
	 * @since 5.1.0
	 *
	 * @param string $state State.
	 * @param string $country Two-letter country code.
	 *
	 * @return bool Whether state is normalized or not.
	 */
	public function is_normalized_state( $state, $country ) {
		$wc_states = WC()->countries->get_states( $country );
		return (
			is_array( $wc_states ) &&
			in_array( $state, array_keys( $wc_states ), true )
		);
	}

	/**
	 * Sanitize string for comparison.
	 *
	 * @since 5.1.0
	 *
	 * @param string $string String to be sanitized.
	 *
	 * @return string The sanitized string.
	 */
	public function sanitize_string( $string ) {
		return trim( wc_strtolower( remove_accents( $string ) ) );
	}

	/**
	 * Get normalized state from Payment Request API dropdown list of states.
	 *
	 * @since 5.1.0
	 *
	 * @param string $state   Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	public function get_normalized_state_from_pr_states( $state, $country ) {
		// Include Payment Request API State list for compatibility with WC countries/states.
		include_once WC_STRIPE_PLUGIN_PATH . '/includes/constants/class-wc-stripe-payment-request-button-states.php';
		$pr_states = WC_Stripe_Payment_Request_Button_States::STATES;

		if ( ! isset( $pr_states[ $country ] ) ) {
			return $state;
		}

		foreach ( $pr_states[ $country ] as $wc_state_abbr => $pr_state ) {
			$sanitized_state_string = $this->sanitize_string( $state );
			// Checks if input state matches with Payment Request state code (0), name (1) or localName (2).
			if (
				( ! empty( $pr_state[0] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[0] ) ) ||
				( ! empty( $pr_state[1] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[1] ) ) ||
				( ! empty( $pr_state[2] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[2] ) )
			) {
				return $wc_state_abbr;
			}
		}

		return $state;
	}

	/**
	 * Get normalized state from WooCommerce list of translated states.
	 *
	 * @since 5.1.0
	 *
	 * @param string $state   Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	public function get_normalized_state_from_wc_states( $state, $country ) {
		$wc_states = WC()->countries->get_states( $country );

		if ( is_array( $wc_states ) ) {
			foreach ( $wc_states as $wc_state_abbr => $wc_state_value ) {
				if ( preg_match( '/' . preg_quote( $wc_state_value, '/' ) . '/i', $state ) ) {
					return $wc_state_abbr;
				}
			}
		}

		return $state;
	}

	/**
	 * Gets the normalized state/county field because in some
	 * cases, the state/county field is formatted differently from
	 * what WC is expecting and throws an error. An example
	 * for Ireland, the county dropdown in Chrome shows "Co. Clare" format.
	 *
	 * @since 5.0.0
	 * @version 5.1.0
	 *
	 * @param string $state   Full state name or an already normalized abbreviation.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state abbreviation.
	 */
	public function get_normalized_state( $state, $country ) {
		// If it's empty or already normalized, skip.
		if ( ! $state || $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// Try to match state from the Payment Request API list of states.
		$state = $this->get_normalized_state_from_pr_states( $state, $country );

		// If it's normalized, return.
		if ( $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// If the above doesn't work, fallback to matching against the list of translated
		// states from WooCommerce.
		return $this->get_normalized_state_from_wc_states( $state, $country );
	}

	/**
	 * The Payment Request API provides its own validation for the address form.
	 * For some countries, it might not provide a state field, so we need to return a more descriptive
	 * error message, indicating that the Payment Request button is not supported for that country.
	 *
	 * @since 5.1.0
	 */
	public function validate_state() {
		$wc_checkout     = WC_Checkout::instance();
		$posted_data     = $wc_checkout->get_posted_data();
		$checkout_fields = $wc_checkout->get_checkout_fields();
		$countries       = WC()->countries->get_countries();

		$is_supported = true;
		// Checks if billing state is missing and is required.
		if ( ! empty( $checkout_fields['billing']['billing_state']['required'] ) && '' === $posted_data['billing_state'] ) {
			$is_supported = false;
		}

		// Checks if shipping state is missing and is required.
		if ( WC()->cart->needs_shipping_address() && ! empty( $checkout_fields['shipping']['shipping_state']['required'] ) && '' === $posted_data['shipping_state'] ) {
			$is_supported = false;
		}

		if ( ! $is_supported ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: country. */
					__( 'The Payment Request button is not supported in %s because some required fields couldn\'t be verified. Please proceed to the checkout page and try again.', 'woocommerce-gateway-stripe' ),
					isset( $countries[ $posted_data['billing_country'] ] ) ? $countries[ $posted_data['billing_country'] ] : $posted_data['billing_country']
				),
				'error'
			);
		}
	}

	/**
	 * Create order. Security is handled by WC.
	 *
	 * @since   3.1.0
	 * @version 5.1.0
	 */
	public function ajax_create_order() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'woocommerce-gateway-stripe' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		// In case the state is required, but is missing, add a more descriptive error notice.
		$this->validate_state();

		// Normalizes billing and shipping state values.
		$this->normalize_state();

		WC()->checkout()->process_checkout();

		die( 0 );
	}

	/**
	 * Calculate and set shipping method.
	 *
	 * @param array $address Shipping address.
	 *
	 * @since   3.1.0
	 * @version 5.0.0
	 */
	protected function calculate_shipping( $address = [] ) {
		$country   = $address['country'];
		$state     = $address['state'];
		$postcode  = $address['postcode'];
		$city      = $address['city'];
		$address_1 = $address['address'];
		$address_2 = $address['address_2'];

		// Normalizes state to calculate shipping zones.
		$state = $this->get_normalized_state( $state, $country );

		// Normalizes postal code in case of redacted data from Apple Pay.
		$postcode = $this->get_normalized_postal_code( $postcode, $country );

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		$packages = [];

		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;
		$packages[0]['destination']['address']   = $address_1;
		$packages[0]['destination']['address_2'] = $address_2;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $item['data']->needs_shipping() ) {
				if ( isset( $item['line_total'] ) ) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		WC()->shipping->calculate_shipping( $packages );
	}

	/**
	 * Builds the shippings methods to pass to Payment Request
	 *
	 * @since   3.1.0
	 * @version 4.0.0
	 */
	protected function build_shipping_methods( $shipping_methods ) {
		if ( empty( $shipping_methods ) ) {
			return [];
		}

		$shipping = [];

		foreach ( $shipping_methods as $method ) {
			$shipping[] = [
				'id'     => $method['id'],
				'label'  => $method['label'],
				'detail' => '',
				'amount' => WC_Stripe_Helper::get_stripe_amount( $method['amount']['value'] ),
			];
		}

		return $shipping;
	}


	/**
	 * Builds the response object to pass to Payment Request
	 *
	 * @since 5.3.0
	 *
	 * @param bool $shipping_pending True is the user hasn't defined yet a shipping address.
	 *
	 * @return array Data object used to update Payment Request button on the client.
	 */
	protected function build_response( $shipping_pending = true, $page = 'product_page' ) {
		if ($page == 'product_page') {
			$itemized_display_items = true;
		} elseif($page == 'cart_page') {
			$itemized_display_items = false;
		}

		$data                 = [];
		$data['currency']     = strtolower( get_woocommerce_currency() );
		$data['country_code'] = substr( get_option( 'woocommerce_default_country' ), 0, 2 );


		// Taxes
		$taxes            = WC()->cart->tax_total + WC()->cart->shipping_tax_total;
		$tax_to_substract = 0;

		if ( wc_tax_enabled() ) {
			if ( $shipping_pending ) {
				$tax_to_substract = $taxes;
				$tax              = 0;
			}
		}

		// Shipping
		$shipping           = wc_format_decimal( WC()->cart->get_shipping_total(), WC()->cart->dp );
		$shipping_to_remove = 0;

		if ( wc_shipping_enabled() && WC()->cart->needs_shipping() ) {
			$data['requestShipping'] = ( wc_shipping_enabled() && WC()->cart->needs_shipping() );

			if ( $shipping_pending ) {
				$shipping_to_remove = $shipping;
				$shipping           = 0;

				$data['shippingOptions'] = [
					'id'     => 'pending',
					'label'  => __( 'Pending', 'woocommerce-gateway-stripe' ),
					'detail' => '',
					'amount' => 0,
				];
			}
		}

		// Discounts
		$discounts = 0;

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$discounts = wc_format_decimal( WC()->cart->get_cart_discount_total(), WC()->cart->dp );
		} else {
			$applied_coupons = array_values( WC()->cart->get_coupon_discount_totals() );

			foreach ( $applied_coupons as $amount ) {
				$discounts += (float) $amount;
			}
		}

		$discounts = wc_format_decimal( $discounts, WC()->cart->dp );

		// Order total
		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$items_total = wc_format_decimal( WC()->cart->cart_contents_total, WC()->cart->dp ) + $discounts;
			$order_total = wc_format_decimal( $items_total + $tax + $shipping - $discounts, WC()->cart->dp );
		} else {
			// Getting the total amount from the cart automatically adds a shipping cost to it
			// We need to remove it if the user hasn't picked a shipping addres yet
			$order_total = WC()->cart->get_total( false ) - $tax_to_substract - $shipping_to_remove;
		}

		$data['total'] = [
			'label'   => $this->total_label,
			'amount'  => max( 0, apply_filters( 'woocommerce_stripe_calculated_total', WC_Stripe_Helper::get_stripe_amount( $order_total ), $order_total, WC()->cart ) ),
			'pending' => true, // TODO: Check when this should be false
		];

		$data['displayItems'] = $this->build_display_items( $itemized_display_items, $shipping_pending, $shipping, $discounts );

		return $data;
	}

	/**
	 * Builds the line items to pass to Payment Request
	 *
	 * @since   3.1.0
	 * @version 4.0.0
	 *
	 * @param  bool $itemized_display_items Wether to return an array of items with its details or not.
	 * @param  bool $shipping_pending       True is the user hasn't defined yet a shipping address. @since 5.3.0
	 * @param  bool $shipping               Total shipping cost. @since 5.3.0
	 */
	protected function build_display_items( $itemized_display_items = false, $shipping_pending = true, $shipping = 0, $discounts = 0 ) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$items    = [];
		$subtotal = 0;

		// Default show only subtotal instead of itemization.
		if ( ! apply_filters( 'wc_stripe_payment_request_hide_itemization', true ) || $itemized_display_items ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$amount         = $cart_item['line_subtotal'];
				$subtotal      += $cart_item['line_subtotal'];
				$quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';

				$product_name = $cart_item['data']->get_name();
				// $product_name = $product->get_name(); // which one should we use?

				$item = [
					'label'  => $product_name . $quantity_label,
					'amount' => WC_Stripe_Helper::get_stripe_amount( $amount ),
				];

				$items[] = $item;
			}
		}

		$tax = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp );

		// Taxes
		if ( wc_tax_enabled() ) {
			if ( $shipping_pending ) {
				$items[] = [
					'label'   => esc_html( __( 'Tax', 'woocommerce-gateway-stripe' ) ),
					'amount'  => 0,
					'pending' => true,
				];
			} else {
				$items[] = [
					'label'  => esc_html( __( 'Tax', 'woocommerce-gateway-stripe' ) ),
					'amount' => WC_Stripe_Helper::get_stripe_amount( $tax ),
				];
			}
		}

		// Shipping
		if ( wc_shipping_enabled() && WC()->cart->needs_shipping() ) {
			if ( $shipping_pending ) {
				$items[] = [
					'label'   => esc_html( __( 'Shipping', 'woocommerce-gateway-stripe' ) ),
					'amount'  => 0,
					'pending' => true,
				];
			} else {
				$items[] = [
					'label'  => esc_html( __( 'Shipping', 'woocommerce-gateway-stripe' ) ),
					'amount' => WC_Stripe_Helper::get_stripe_amount( $shipping ),
				];
			}
		}

		// Discounts
		if ( WC()->cart->has_discount() ) {
			$items[] = [
				'label'  => esc_html( __( 'Discount', 'woocommerce-gateway-stripe' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $discounts ),
			];
		}

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$cart_fees = WC()->cart->fees;
		} else {
			$cart_fees = WC()->cart->get_fees();
		}

		// Include fees and taxes as display items.
		foreach ( $cart_fees as $key => $fee ) {
			$items[] = [
				'label'  => $fee->name,
				'amount' => WC_Stripe_Helper::get_stripe_amount( $fee->amount ),
			];
		}

		return $items;
	}
}

new WC_Stripe_Payment_Request();
