<?php
/**
 * Stripe Payment Request API
 *
 * @package WooCommerce_Stripe/Classes/Payment_Request
 * @since   3.1.0
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
	 * Stripe Checkout enabled.
	 *
	 * @var
	 */
	public $stripe_checkout_enabled;

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
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Initialize class actions.
	 *
	 * @since 3.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		$this->stripe_settings         = get_option( 'woocommerce_stripe_settings', array() );
		$this->testmode                = ( ! empty( $this->stripe_settings['testmode'] ) && 'yes' === $this->stripe_settings['testmode'] ) ? true : false;
		$this->publishable_key         = ! empty( $this->stripe_settings['publishable_key'] ) ? $this->stripe_settings['publishable_key'] : '';
		$this->stripe_checkout_enabled = isset( $this->stripe_settings['stripe_checkout'] ) && 'yes' === $this->stripe_settings['stripe_checkout'];
		$this->total_label             = ! empty( $this->stripe_settings['statement_descriptor'] ) ? WC_Stripe_Helper::clean_statement_descriptor( $this->stripe_settings['statement_descriptor'] ) : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $this->stripe_settings['test_publishable_key'] ) ? $this->stripe_settings['test_publishable_key'] : '';
		}

		// If both site title and statement descriptor is not set. Fallback.
		if ( empty( $this->total_label ) ) {
			$this->total_label = $_SERVER['SERVER_NAME'];
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

		$this->init();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	protected function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'wp', array( $this, 'set_session' ) );

		/*
		 * In order to display the Payment Request button in the correct position,
		 * a new hook was added to WooCommerce 3.0. In older versions of WooCommerce,
		 * CSS is used to position the button.
		 */
		if ( WC_Stripe_Helper::is_pre_30() ) {
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'display_payment_request_button_html' ), 1 );
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'display_payment_request_button_separator_html' ), 2 );
		} else {
			add_action( 'woocommerce_after_add_to_cart_quantity', array( $this, 'display_payment_request_button_html' ), 1 );
			add_action( 'woocommerce_after_add_to_cart_quantity', array( $this, 'display_payment_request_button_separator_html' ), 2 );
		}

		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_payment_request_button_html' ), 1 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_payment_request_button_separator_html' ), 2 );

		if ( apply_filters( 'wc_stripe_show_payment_request_on_checkout', false ) ) {
			add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'display_payment_request_button_html' ), 1 );
			add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'display_payment_request_button_separator_html' ), 2 );
		}

		add_action( 'wc_ajax_wc_stripe_get_cart_details', array( $this, 'ajax_get_cart_details' ) );
		add_action( 'wc_ajax_wc_stripe_get_shipping_options', array( $this, 'ajax_get_shipping_options' ) );
		add_action( 'wc_ajax_wc_stripe_update_shipping_method', array( $this, 'ajax_update_shipping_method' ) );
		add_action( 'wc_ajax_wc_stripe_create_order', array( $this, 'ajax_create_order' ) );
		add_action( 'wc_ajax_wc_stripe_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wc_ajax_wc_stripe_get_selected_product_data', array( $this, 'ajax_get_selected_product_data' ) );
		add_action( 'wc_ajax_wc_stripe_clear_cart', array( $this, 'ajax_clear_cart' ) );
		add_action( 'wc_ajax_wc_stripe_log_errors', array( $this, 'ajax_log_errors' ) );

		add_filter( 'woocommerce_gateway_title', array( $this, 'filter_gateway_title' ), 10, 2 );
		add_filter( 'woocommerce_validate_postcode', array( $this, 'postal_code_validation' ), 10, 3 );

		add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_order_meta' ), 10, 3 );
	}

	/**
	 * Sets the WC customer session if one is not set.
	 * This is needed so nonces can be verified.
	 *
	 * @since 4.0.0
	 */
	public function set_session() {
		if ( ! is_user_logged_in() ) {
			$wc_session = new WC_Session_Handler();

			if ( ! $wc_session->has_session() ) {
				$wc_session->set_customer_session_cookie( true );
			}
		}
	}

	/**
	 * Gets the button type.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_button_type() {
		return isset( $this->stripe_settings['payment_request_button_type'] ) ? $this->stripe_settings['payment_request_button_type'] : 'default';
	}

	/**
	 * Gets the button theme.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_button_theme() {
		return isset( $this->stripe_settings['payment_request_button_theme'] ) ? $this->stripe_settings['payment_request_button_theme'] : 'dark';
	}

	/**
	 * Gets the button height.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_button_height() {
		return isset( $this->stripe_settings['payment_request_button_height'] ) ? str_replace( 'px', '', $this->stripe_settings['payment_request_button_height'] ) : '64';
	}

	/**
	 * Gets the product data for the currently viewed page
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_product_data() {
		if ( ! is_product() ) {
			return false;
		}

		global $post;

		$product = wc_get_product( $post->ID );

		$data  = array();
		$items = array();

		$items[] = array(
			'label'  => WC_Stripe_Helper::is_pre_30() ? $product->name : $product->get_name(),
			'amount' => WC_Stripe_Helper::get_stripe_amount( WC_Stripe_Helper::is_pre_30() ? $product->price : $product->get_price() ),
		);

		if ( wc_tax_enabled() ) {
			$items[] = array(
				'label'   => __( 'Tax', 'woocommerce-gateway-stripe' ),
				'amount'  => 0,
				'pending' => true,
			);
		}

		if ( wc_shipping_enabled() && $product->needs_shipping() ) {
			$items[] = array(
				'label'   => __( 'Shipping', 'woocommerce-gateway-stripe' ),
				'amount'  => 0,
				'pending' => true,
			);

			$data['shippingOptions']  = array(
				'id'     => 'pending',
				'label'  => __( 'Pending', 'woocommerce-gateway-stripe' ),
				'detail' => '',
				'amount' => 0,
			);
		}

		$data['displayItems'] = $items;
		$data['total'] = array(
			'label'   => $this->total_label,
			'amount'  => WC_Stripe_Helper::get_stripe_amount( WC_Stripe_Helper::is_pre_30() ? $product->price : $product->get_price() ),
			'pending' => true,
		);

		$data['requestShipping'] = ( wc_shipping_enabled() && $product->needs_shipping() );
		$data['currency']        = strtolower( get_woocommerce_currency() );
		$data['country_code']    = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

		return $data;
	}

	/**
	 * Filters the gateway title to reflect Payment Request type
	 *
	 */
	public function filter_gateway_title( $title, $id ) {
		global $post;

		if ( ! is_object( $post ) ) {
			return $title;
		}

		if ( WC_Stripe_Helper::is_pre_30() ) {
			$method_title = get_post_meta( $post->ID, '_payment_method_title', true );
		} else {
			$order        = wc_get_order( $post->ID );
			$method_title = is_object( $order ) ? $order->get_payment_method_title() : '';
		}

		if ( 'stripe' === $id && ! empty( $method_title ) && 'Apple Pay (Stripe)' === $method_title ) {
			return $method_title;
		}

		if ( 'stripe' === $id && ! empty( $method_title ) && 'Chrome Payment Request (Stripe)' === $method_title ) {
			return $method_title;
		}

		return $title;
	}

	/**
	 * Removes postal code validation from WC.
	 *
	 * @since 3.1.4
	 * @version 4.0.0
	 */
	public function postal_code_validation( $valid, $postcode, $country ) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['stripe'] ) ) {
			return $valid;
		}

		/**
		 * Currently Apple Pay truncates postal codes from UK and Canada to first 3 characters
		 * when passing it back from the shippingcontactselected object. This causes WC to invalidate
		 * the order and not let it go through. The remedy for now is just to remove this validation.
		 * Note that this only works with shipping providers that don't validate full postal codes.
		 */
		if ( 'GB' === $country || 'CA' === $country ) {
			return true;
		}

		return $valid;
	}

	/**
	 * Add needed order meta
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param int $order_id
	 * @param array $posted_data The posted data from checkout form.
	 * @param object $order
	 */
	public function add_order_meta( $order_id, $posted_data, $order ) {
		if ( empty( $_POST['payment_request_type'] ) ) {
			return;
		}

		$payment_request_type = wc_clean( $_POST['payment_request_type'] );

		if ( 'apple_pay' === $payment_request_type ) {
			if ( WC_Stripe_Helper::is_pre_30() ) {
				update_post_meta( $order_id, '_payment_method_title', 'Apple Pay (Stripe)' );
			} else {
				$order->set_payment_method_title( 'Apple Pay (Stripe)' );
				$order->save();
			}
		}

		if ( 'payment_request_api' === $payment_request_type ) {
			if ( WC_Stripe_Helper::is_pre_30() ) {
				update_post_meta( $order_id, '_payment_method_title', 'Chrome Payment Request (Stripe)' );
			} else {
				$order->set_payment_method_title( 'Chrome Payment Request (Stripe)' );
				$order->save();
			}
		}
	}

	/**
	 * Checks to make sure product type is supported.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @return array
	 */
	public function supported_product_types() {
		return apply_filters( 'wc_stripe_payment_request_supported_types', array(
			'simple',
			'variable',
			'variation',
		) );
	}

	/**
	 * Checks the cart to see if all items are allowed to used.
	 *
	 * @since 3.1.4
	 * @version 4.0.0
	 * @return bool
	 */
	public function allowed_items_in_cart() {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( ! in_array( ( WC_Stripe_Helper::is_pre_30() ? $_product->product_type : $_product->get_type() ), $this->supported_product_types() ) ) {
				return false;
			}

			// Pre Orders compatbility where we don't support charge upon release.
			if ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Cart::cart_contains_pre_order() && WC_Pre_Orders_Product::product_is_charged_upon_release( WC_Pre_Orders_Cart::get_pre_order_product() ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Load public scripts and styles.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function scripts() {
		if ( ! is_product() && ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( is_product() ) {
			global $post;

			$product = wc_get_product( $post->ID );

			if ( ! is_object( $product ) || ! in_array( ( WC_Stripe_Helper::is_pre_30() ? $product->product_type : $product->get_type() ), $this->supported_product_types() ) ) {
				return;
			}

			if ( apply_filters( 'wc_stripe_hide_payment_request_on_product_page', false ) ) {
				return;
			}
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script( 'wc_stripe_payment_request', plugins_url( 'assets/js/stripe-payment-request' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), array( 'jquery', 'stripe' ), WC_STRIPE_VERSION, true );

		wp_localize_script(
			'wc_stripe_payment_request',
			'wc_stripe_payment_request_params',
			array(
				'ajax_url' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'stripe'   => array(
					'key'                => $this->publishable_key,
					'allow_prepaid_card' => apply_filters( 'wc_stripe_allow_prepaid_card', true ) ? 'yes' : 'no',
				),
				'nonce'    => array(
					'payment'                        => wp_create_nonce( 'wc-stripe-payment-request' ),
					'shipping'                       => wp_create_nonce( 'wc-stripe-payment-request-shipping' ),
					'update_shipping'                => wp_create_nonce( 'wc-stripe-update-shipping-method' ),
					'checkout'                       => wp_create_nonce( 'woocommerce-process_checkout' ),
					'add_to_cart'                    => wp_create_nonce( 'wc-stripe-add-to-cart' ),
					'get_selected_product_data'      => wp_create_nonce( 'wc-stripe-get-selected-product-data' ),
					'log_errors'                     => wp_create_nonce( 'wc-stripe-log-errors' ),
					'clear_cart'                     => wp_create_nonce( 'wc-stripe-clear-cart' ),
				),
				'i18n'     => array(
					'no_prepaid_card'  => __( 'Sorry, we\'re not accepting prepaid cards at this time.', 'woocommerce-gateway-stripe' ),
					/* translators: Do not translate the [option] placeholder */
					'unknown_shipping' => __( 'Unknown shipping option "[option]".', 'woocommerce-gateway-stripe' ),
				),
				'checkout' => array(
					'url'            => wc_get_checkout_url(),
					'currency_code'  => strtolower( get_woocommerce_currency() ),
					'country_code'   => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
					'needs_shipping' => WC()->cart->needs_shipping() ? 'yes' : 'no',
				),
				'button' => array(
					'type'   => $this->get_button_type(),
					'theme'  => $this->get_button_theme(),
					'height' => $this->get_button_height(),
					'locale' => substr( get_locale(), 0, 2 ), // Default format is en_US.
				),
				'is_product_page' => is_product(),
				'product'         => $this->get_product_data(),
			)
		);

		wp_enqueue_script( 'wc_stripe_payment_request' );
	}

	/**
	 * Display the payment request button.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function display_payment_request_button_html() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['stripe'] ) ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() && ! is_product() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( is_product() && apply_filters( 'wc_stripe_hide_payment_request_on_product_page', false ) ) {
			return;
		}

		if ( is_product() ) {
			global $post;

			$product = wc_get_product( $post->ID );

			if ( ! is_object( $product ) || ! in_array( ( WC_Stripe_Helper::is_pre_30() ? $product->product_type : $product->get_type() ), $this->supported_product_types() ) ) {
				return;
			}

			// Pre Orders charge upon release not supported.
			if ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) {
				WC_Stripe_Logger::log( 'Pre Order charge upon release is not supported. ( Payment Request button disabled )' );
				return;
			}
		} else {
			if ( ! $this->allowed_items_in_cart() ) {
				WC_Stripe_Logger::log( 'Items in the cart has unsupported product type ( Payment Request button disabled )' );
				return;
			}
		}
		?>
		<div id="wc-stripe-payment-request-wrapper" style="clear:both;padding-top:1.5em;">
			<div id="wc-stripe-payment-request-button">
				<!-- A Stripe Element will be inserted here. -->
			</div>
		</div>
		<?php
	}

	/**
	 * Display payment request button separator.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function display_payment_request_button_separator_html() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['stripe'] ) ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() && ! is_product() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( is_product() && apply_filters( 'wc_stripe_hide_payment_request_on_product_page', false ) ) {
			return;
		}

		if ( is_product() ) {
			global $post;

			$product = wc_get_product( $post->ID );

			if ( ! is_object( $product ) || ! in_array( ( WC_Stripe_Helper::is_pre_30() ? $product->product_type : $product->get_type() ), $this->supported_product_types() ) ) {
				return;
			}

			// Pre Orders charge upon release not supported.
			if ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) {
				WC_Stripe_Logger::log( 'Pre Order charge upon release is not supported. ( Payment Request button disabled )' );
				return;
			}
		} else {
			if ( ! $this->allowed_items_in_cart() ) {
				WC_Stripe_Logger::log( 'Items in the cart has unsupported product type ( Payment Request button disabled )' );
				return;
			}
		}
		?>
		<p id="wc-stripe-payment-request-button-separator" style="margin-top:1.5em;text-align:center;display:none;">- <?php esc_html_e( 'OR', 'woocommerce-gateway-stripe' ); ?> -</p>
		<?php
	}

	/**
	 * Log errors coming from Payment Request
	 *
	 * @since 3.1.4
	 * @version 4.0.0
	 */
	public function ajax_log_errors() {
		check_ajax_referer( 'wc-stripe-log-errors', 'security' );

		$errors = wc_clean( stripslashes( $_POST['errors'] ) );

		WC_Stripe_Logger::log( $errors );

		exit;
	}

	/**
	 * Clears cart.
	 *
	 * @since 3.1.4
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

		WC()->cart->calculate_totals();

		$currency = get_woocommerce_currency();

		// Set mandatory payment details.
		$data = array(
			'shipping_required' => WC()->cart->needs_shipping(),
			'order_data'        => array(
				'currency'        => strtolower( $currency ),
				'country_code'    => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			),
		);

		$data['order_data'] += $this->build_display_items();

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

		try {
			// Set the shipping package.
			$posted = filter_input_array( INPUT_POST, array(
				'country'   => FILTER_SANITIZE_STRING,
				'state'     => FILTER_SANITIZE_STRING,
				'postcode'  => FILTER_SANITIZE_STRING,
				'city'      => FILTER_SANITIZE_STRING,
				'address'   => FILTER_SANITIZE_STRING,
				'address_2' => FILTER_SANITIZE_STRING,
			) );

			$this->calculate_shipping( $posted );

			// Set the shipping options.
			$data     = array();
			$packages = WC()->shipping->get_packages();

			if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
				foreach ( $packages as $package_key => $package ) {
					if ( empty( $package['rates'] ) ) {
						throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
					}

					foreach ( $package['rates'] as $key => $rate ) {
						$data['shipping_options'][] = array(
							'id'       => $rate->id,
							'label'    => $rate->label,
							'detail'   => '',
							'amount'   => WC_Stripe_Helper::get_stripe_amount( $rate->cost ),
						);
					}
				}
			} else {
				throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
			}

			if ( isset( $data[0] ) ) {
				// Auto select the first shipping method.
				WC()->session->set( 'chosen_shipping_methods', array( $data[0]['id'] ) );
			}

			WC()->cart->calculate_totals();

			$data += $this->build_display_items();
			$data['result'] = 'success';

			wp_send_json( $data );
		} catch ( Exception $e ) {
			$data += $this->build_display_items();
			$data['result'] = 'invalid_shipping_address';

			wp_send_json( $data );
		}
	}

	/**
	 * Update shipping method.
	 */
	public function ajax_update_shipping_method() {
		check_ajax_referer( 'wc-stripe-update-shipping-method', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		$shipping_method         = filter_input( INPUT_POST, 'shipping_method', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		if ( is_array( $shipping_method ) ) {
			foreach ( $shipping_method as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

		WC()->cart->calculate_totals();

		$data = array();
		$data += $this->build_display_items();
		$data['result'] = 'success';

		wp_send_json( $data );
	}

	/**
	 * Gets the selected product data.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return array $data
	 */
	public function ajax_get_selected_product_data() {
		check_ajax_referer( 'wc-stripe-get-selected-product-data', 'security' );

		$product_id = absint( $_POST['product_id'] );
		$qty = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );

		$product = wc_get_product( $product_id );

		if ( 'variable' === ( WC_Stripe_Helper::is_pre_30() ? $product->product_type : $product->get_type() ) && isset( $_POST['attributes'] ) ) {
			$attributes = array_map( 'wc_clean', $_POST['attributes'] );

			if ( WC_Stripe_Helper::is_pre_30() ) {
				$variation_id = $product->get_matching_variation( $attributes );
			} else {
				$data_store = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $product, $attributes );
			}

			if ( ! empty( $variation_id ) ) {
				$product = wc_get_product( $variation_id );
			}
		} elseif ( 'simple' === ( WC_Stripe_Helper::is_pre_30() ? $product->product_type : $product->get_type() ) ) {
			$product = wc_get_product( $product_id );
		}

		$total = $qty * ( WC_Stripe_Helper::is_pre_30() ? $product->price : $product->get_price() );

		$quantity_label = 1 < $qty ? ' (x' . $qty . ')' : '';

		$data  = array();
		$items = array();

		$items[] = array(
			'label'  => ( WC_Stripe_Helper::is_pre_30() ? $product->name : $product->get_name() ) . $quantity_label,
			'amount' => WC_Stripe_Helper::get_stripe_amount( $total ),
		);

		if ( wc_tax_enabled() ) {
			$items[] = array(
				'label'   => __( 'Tax', 'woocommerce-gateway-stripe' ),
				'amount'  => 0,
				'pending' => true,
			);
		}

		if ( wc_shipping_enabled() && $product->needs_shipping() ) {
			$items[] = array(
				'label'   => __( 'Shipping', 'woocommerce-gateway-stripe' ),
				'amount'  => 0,
				'pending' => true,
			);

			$data['shippingOptions']  = array(
				'id'     => 'pending',
				'label'  => __( 'Pending', 'woocommerce-gateway-stripe' ),
				'detail' => '',
				'amount' => 0,
			);
		}

		$data['displayItems'] = $items;
		$data['total'] = array(
			'label'   => $this->total_label,
			'amount'  => WC_Stripe_Helper::get_stripe_amount( $total ),
			'pending' => true,
		);

		$data['requestShipping'] = ( wc_shipping_enabled() && $product->needs_shipping() );
		$data['currency']        = strtolower( get_woocommerce_currency() );
		$data['country_code']    = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

		wp_send_json( $data );
	}

	/**
	 * Adds the current product to the cart. Used on product detail page.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return array $data
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'wc-stripe-add-to-cart', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		$product_id = absint( $_POST['product_id'] );
		$qty = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );

		$product = wc_get_product( $product_id );

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		if ( 'variable' === ( WC_Stripe_Helper::is_pre_30() ? $product->product_type : $product->get_type() ) && isset( $_POST['attributes'] ) ) {
			$attributes = array_map( 'wc_clean', $_POST['attributes'] );

			if ( WC_Stripe_Helper::is_pre_30() ) {
				$variation_id = $product->get_matching_variation( $attributes );
			} else {
				$data_store = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $product, $attributes );
			}

			WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
		}

		if ( 'simple' === ( WC_Stripe_Helper::is_pre_30() ? $product->product_type : $product->get_type() ) ) {
			WC()->cart->add_to_cart( $product->get_id(), $qty );
		}

		WC()->cart->calculate_totals();

		$data = array();
		$data += $this->build_display_items();
		$data['result'] = 'success';

		wp_send_json( $data );
	}

	/**
	 * Normalizes the state/county field because in some
	 * cases, the state/county field is formatted differently from
	 * what WC is expecting and throws an error. An example
	 * for Ireland the county dropdown in Chrome shows "Co. Clare" format
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function normalize_state() {
		$billing_country  = ! empty( $_POST['billing_country'] ) ? wc_clean( $_POST['billing_country'] ) : '';
		$shipping_country = ! empty( $_POST['shipping_country'] ) ? wc_clean( $_POST['shipping_country'] ) : '';
		$billing_state    = ! empty( $_POST['billing_state'] ) ? wc_clean( $_POST['billing_state'] ) : '';
		$shipping_state   = ! empty( $_POST['shipping_state'] ) ? wc_clean( $_POST['shipping_state'] ) : '';

		if ( $billing_state && $billing_country ) {
			$valid_states = WC()->countries->get_states( $billing_country );

			// Valid states found for country.
			if ( ! empty( $valid_states ) && is_array( $valid_states ) && sizeof( $valid_states ) > 0 ) {
				foreach ( $valid_states as $state_abbr => $state ) {
					if ( preg_match( '/' . preg_quote( $state ) . '/i', $billing_state ) ) {
						$_POST['billing_state'] = $state_abbr;
					}
				}
			}
		}

		if ( $shipping_state && $shipping_country ) {
			$valid_states = WC()->countries->get_states( $shipping_country );

			// Valid states found for country.
			if ( ! empty( $valid_states ) && is_array( $valid_states ) && sizeof( $valid_states ) > 0 ) {
				foreach ( $valid_states as $state_abbr => $state ) {
					if ( preg_match( '/' . preg_quote( $state ) . '/i', $shipping_state ) ) {
						$_POST['shipping_state'] = $state_abbr;
					}
				}
			}
		}
	}

	/**
	 * Create order. Security is handled by WC.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function ajax_create_order() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'woocommerce-gateway-stripe' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		$this->normalize_state();

		WC()->checkout()->process_checkout();

		die( 0 );
	}

	/**
	 * Calculate and set shipping method.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param array $address
	 */
	protected function calculate_shipping( $address = array() ) {
		global $states;

		$country   = $address['country'];
		$state     = $address['state'];
		$postcode  = $address['postcode'];
		$city      = $address['city'];
		$address_1 = $address['address'];
		$address_2 = $address['address_2'];

		$country_class = new WC_Countries();
		$country_class->load_country_states();

		/**
		 * In some versions of Chrome, state can be a full name. So we need
		 * to convert that to abbreviation as WC is expecting that.
		 */
		if ( 2 < strlen( $state ) ) {
			$state = array_search( ucfirst( strtolower( $state ) ), $states[ $country ] );
		}

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC_Stripe_Helper::is_pre_30() ? WC()->customer->set_to_base() : WC()->customer->set_billing_address_to_base();
			WC_Stripe_Helper::is_pre_30() ? WC()->customer->set_shipping_to_base() : WC()->customer->set_shipping_address_to_base();
		}

		if ( WC_Stripe_Helper::is_pre_30() ) {
			WC()->customer->calculated_shipping( true );
		} else {
			WC()->customer->set_calculated_shipping( true );
			WC()->customer->save();
		}

		$packages = array();

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
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	protected function build_shipping_methods( $shipping_methods ) {
		if ( empty( $shipping_methods ) ) {
			return array();
		}

		$shipping = array();

		foreach ( $shipping_methods as $method ) {
			$shipping[] = array(
				'id'         => $method['id'],
				'label'      => $method['label'],
				'detail'     => '',
				'amount'     => WC_Stripe_Helper::get_stripe_amount( $method['amount']['value'] ),
			);
		}

		return $shipping;
	}

	/**
	 * Builds the line items to pass to Payment Request
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	protected function build_display_items() {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$items    = array();
		$subtotal = 0;

		// Default show only subtotal instead of itemization.
		if ( ! apply_filters( 'wc_stripe_payment_request_hide_itemization', true ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$amount         = $cart_item['line_subtotal'];
				$subtotal       += $cart_item['line_subtotal'];
				$quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';

				$product_name = WC_Stripe_Helper::is_pre_30() ? $cart_item['data']->post->post_title : $cart_item['data']->get_name();

				$item = array(
					'label'  => $product_name . $quantity_label,
					'amount' => WC_Stripe_Helper::get_stripe_amount( $amount ),
				);

				$items[] = $item;
			}
		}

		$discounts   = wc_format_decimal( WC()->cart->get_cart_discount_total(), WC()->cart->dp );
		$tax         = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp );
		$shipping    = wc_format_decimal( WC()->cart->shipping_total, WC()->cart->dp );
		$items_total = wc_format_decimal( WC()->cart->cart_contents_total, WC()->cart->dp ) + $discounts;
		$order_total = wc_format_decimal( $items_total + $tax + $shipping - $discounts, WC()->cart->dp );

		if ( wc_tax_enabled() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Tax', 'woocommerce-gateway-stripe' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $tax ),
			);
		}

		if ( WC()->cart->needs_shipping() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Shipping', 'woocommerce-gateway-stripe' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $shipping ),
			);
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Discount', 'woocommerce-gateway-stripe' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $discounts ),
			);
		}

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$cart_fees = WC()->cart->fees;
		} else {
			$cart_fees = WC()->cart->get_fees();
		}

		// Include fees and taxes as display items.
		foreach ( $cart_fees as $key => $fee ) {
			$items[] = array(
				'label'  => $fee->name,
				'amount' => WC_Stripe_Helper::get_stripe_amount( $fee->amount ),
			);
		}

		return array(
			'displayItems' => $items,
			'total'      => array(
				'label'   => $this->total_label,
				'amount'  => max( 0, apply_filters( 'woocommerce_stripe_calculated_total', WC_Stripe_Helper::get_stripe_amount( $order_total ), $order_total, WC()->cart ) ),
				'pending' => false,
			),
		);
	}
}

new WC_Stripe_Payment_Request();
