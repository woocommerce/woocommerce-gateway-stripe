<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Apple_Pay class.
 *
 * @extends WC_Gateway_Stripe
 */
class WC_Stripe_Apple_Pay extends WC_Gateway_Stripe {
	/**
	 * This Instance.
	 *
	 * @var
	 */
	private static $_this;

	/**
	 * Gateway.
	 *
	 * @var
	 */
	private $_gateway;

	/**
	 * Gateway settings.
	 *
	 * @var
	 */
	private $_gateway_settings;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function __construct() {
		self::$_this = $this;

		$this->_gateway_settings = get_option( 'woocommerce_stripe_settings', '' );

		$this->init();
	}

	public function instance() {
		return self::$_this;
	}

	/**
	 * Initialize.
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_apple_pay_button' ), 20 );
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'display_apple_pay_button' ) );
		
		if ( is_admin() ) {
			add_action( 'wp_ajax_wc_stripe_apple_pay', array( $this, 'process_apple_pay' ) );
			add_action( 'wp_ajax_nopriv_wc_stripe_apple_pay', array( $this, 'process_apple_pay' ) );
			add_action( 'wp_ajax_wc_stripe_generate_apple_pay_cart', array( $this, 'generate_apple_pay_cart' ) );
			add_action( 'wp_ajax_nopriv_wc_stripe_generate_apple_pay_cart', array( $this, 'generate_apple_pay_cart' ) );
		}
	}

	/**
	 * Enqueue JS scripts and styles.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( ! $this->is_supported_product_type() ) {
			return;
		}
		
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		
		wp_enqueue_style( 'stripe_apple_pay', plugins_url( 'assets/css/stripe-apple-pay.css', WC_STRIPE_MAIN_FILE ), array(), WC_STRIPE_VERSION );

		wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', '', '1.0', true );
		wp_enqueue_script( 'woocommerce_stripe_apple_pay', plugins_url( 'assets/js/stripe-apple-pay' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), array( 'stripe' ), WC_STRIPE_VERSION, true );

		$publishable_key = 'yes' === $this->_gateway_settings['testmode'] ? $this->_gateway_settings['test_publishable_key'] : $this->_gateway_settings['publishable_key'];

		$stripe_params = array(
			'key'                         => $publishable_key,
			'currency_code'               => get_woocommerce_currency(),
			'country_code'                => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			'label'                       => get_bloginfo( 'name', 'display' ),
			'ajaxurl'                     => admin_url( 'admin-ajax.php' ),
			'stripe_apple_pay_nonce'      => wp_create_nonce( '_wc_stripe_apple_pay_nonce' ),
			'stripe_apple_pay_cart_nonce' => wp_create_nonce( '_wc_stripe_apple_pay_cart_nonce' ),
			'needs_shipping'              => WC()->cart->needs_shipping() ? 'yes' : 'no',
			'needs_shipping_msg'          => __( 'Please first calculate your shipping.', 'woocommerce-gateway-stripe' ),
			'is_cart_page'                => is_cart() ? 'yes' : 'no',
			'chosen_shipping'             => wc_get_chosen_shipping_method_ids(),
		);

		wp_localize_script( 'woocommerce_stripe_apple_pay', 'wc_stripe_apple_pay_params', apply_filters( 'wc_stripe_apple_pay_params', $stripe_params ) );
	}
		
	/**
	 * Checks to make sure product type is supported by Apple Pay.
	 *
	 */
	public function is_supported_product_type() {
		foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
			if ( 'subscription' === $values['data']->product_type ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Display Apple Pay button on the cart page
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function display_apple_pay_button() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		/**
		 * In order for the Apple Pay button to show on cart page,
		 * Apple Pay must be enabled and Stripe gateway must be enabled.
		 */
		if ( 
			'yes' !== $this->_gateway_settings['apple_pay']
			|| ! isset( $gateways['stripe'] ) 
		) {
			return;
		}

		if ( ! $this->is_supported_product_type() ) {
			return;
		}
		?>
		<button id="apple-pay-button"></button>
		<?php
	}

	/**
	 * Generates the Apple Pay cart.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function generate_apple_pay_cart() {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_apple_pay_cart_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
		}

		wp_send_json( array( 'line_items' => $this->build_line_items(), 'total' => WC()->cart->total, 'chosen_shipping' => wc_get_chosen_shipping_method_ids() ) );
	}

	/**
	 * Handles the Apple Pay processing via AJAX
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function process_apple_pay() {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_apple_pay_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
		}

		try {
			$result = array_map( 'wc_clean', $_POST['result'] );

			$order = $this->create_order( $result );

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < 50 ) {
					throw new Exception( __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', 'woocommerce-gateway-stripe' ) );
				}

				WC_Stripe::log( "Info: Begin processing payment for order $order->id for the amount of {$order->get_total()}" );

				// Make the request.
				$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $result['token']['id'] ) );

				if ( is_wp_error( $response ) ) {
					$localized_messages = $this->get_localized_messages();

					throw new Exception( ( isset( $localized_messages[ $response->get_error_code() ] ) ? $localized_messages[ $response->get_error_code() ] : $response->get_error_message() ) );
				}

				// Process valid response.
				$this->process_response( $response, $order );
			} else {
				$order->payment_complete();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			update_post_meta( $order->id, '_customer_user', get_current_user_id() );

			// Return thank you page redirect.
			wp_send_json( array(
				'success'  => 'true',
				'redirect' => $this->get_return_url( $order ),
			) );

		} catch ( Exception $e ) {
			WC()->session->set( 'refresh_totals', true );
			WC_Stripe::log( sprintf( __( 'Error: %s', 'woocommerce-gateway-stripe' ), $e->getMessage() ) );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order->id );
			}

			wp_send_json( array( 'success' => 'false', 'msg' => $e->getMessage() ) );
		}
	}

	/**
	 * Generate the request for the payment.
	 * @param  WC_Order $order
	 * @param string $source token
	 * @return array()
	 */
	protected function generate_payment_request( $order, $source ) {
		$post_data                = array();
		$post_data['currency']    = strtolower( $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() );
		$post_data['amount']      = $this->get_stripe_amount( $order->get_total(), $post_data['currency'] );
		$post_data['description'] = sprintf( __( '%s - Order %s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		$post_data['capture']     = 'yes' === $this->_gateway_settings['capture'] ? 'true' : 'false';

		if ( ! empty( $order->billing_email ) && apply_filters( 'wc_stripe_send_stripe_receipt', false ) ) {
			$post_data['receipt_email'] = $order->billing_email;
		}

		$post_data['expand[]']    = 'balance_transaction';
		$post_data['source']      = $source;

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
		 *
		 * @since 3.1.0
		 * @param array $post_data
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_stripe_generate_payment_request', $post_data, $order );
	}

	/**
	 * Builds the line items to pass to Apple Pay
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */	
	public function build_line_items() {
		$decimals = apply_filters( 'wc_stripe_apple_pay_decimals', 2 );

		$items = array();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
			$amount         = wc_format_decimal( $values['line_subtotal'], $decimals );
			$quantity_label = 1 < $values['quantity'] ? ' (x' . $values['quantity'] . ')' : '';

			$item = array(
				'type'   => 'final',
				'label'  => $values['data']->post->post_title . $quantity_label,
				'amount' => wc_format_decimal( $amount, $decimals ),
			);

			$items[] = $item;
		}

		$discounts   = wc_format_decimal( WC()->cart->get_cart_discount_total(), $decimals );
		$tax         = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, $decimals );
		$shipping    = wc_format_decimal( WC()->cart->shipping_total, $decimals );
		$item_total  = wc_format_decimal( WC()->cart->cart_contents_total, $decimals ) + $discounts;
		$order_total = wc_format_decimal( $item_total + $tax + $shipping, $decimals );

		if ( wc_tax_enabled() ) {
			$items[] = array(
				'type'   => 'final',
				'label'  => __( 'Tax', 'woocommerce-gateway-stripe' ),
				'amount' => $tax,
			);
		}

		if ( WC()->cart->needs_shipping() ) {
			$items[] = array(
				'type'   => 'final',
				'label'  => __( 'Shipping', 'woocommerce-gateway-stripe' ),
				'amount' => $shipping,
			);
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = array(
				'type'   => 'final',
				'label'  => __( 'Discount', 'woocommerce-gateway-stripe' ),
				'amount' => $discounts,
			);
		}

		return $items;
	}

	/**
	 * Create order programatically.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param array $data
	 * @return object $order
	 */
	public function create_order( $data = array() ) {
		if ( empty( $data ) ) {
			throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 520 ) );
		}

		$order = wc_create_order();

		if ( is_wp_error( $order ) ) {
			throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 520 ) );
		} elseif ( false === $order ) {
			throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 521 ) );
		} else {
			$order_id = $order->id;
			do_action( 'woocommerce_new_order', $order_id );
		}

		// Store the line items to the new/resumed order
		foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
			$item_id = $order->add_product(
				$values['data'],
				$values['quantity'],
				array(
					'variation' => $values['variation'],
					'totals'    => array(
						'subtotal'     => $values['line_subtotal'],
						'subtotal_tax' => $values['line_subtotal_tax'],
						'total'        => $values['line_total'],
						'tax'          => $values['line_tax'],
						'tax_data'     => $values['line_tax_data'] // Since 2.2
					)
				)
			);

			if ( ! $item_id ) {
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 525 ) );
			}

			// Allow plugins to add order item meta
			do_action( 'woocommerce_add_order_item_meta', $item_id, $values, $cart_item_key );
		}

		// Store fees
		foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
			$item_id = $order->add_fee( $fee );

			if ( ! $item_id ) {
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 526 ) );
			}

			// Allow plugins to add order item meta to fees
			do_action( 'woocommerce_add_order_fee_meta', $order_id, $item_id, $fee, $fee_key );
		}

		// Store tax rows
		foreach ( array_keys( WC()->cart->taxes + WC()->cart->shipping_taxes ) as $tax_rate_id ) {
			if ( $tax_rate_id && ! $order->add_tax( $tax_rate_id, WC()->cart->get_tax_amount( $tax_rate_id ), WC()->cart->get_shipping_tax_amount( $tax_rate_id ) ) && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 528 ) );
			}
		}

		// Store coupons
		foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
			if ( ! $order->add_coupon( $code, WC()->cart->get_coupon_discount_amount( $code ), WC()->cart->get_coupon_discount_tax_amount( $code ) ) ) {
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 529 ) );
			}
		}

		// Billing address
		$billing_address = array();
		if ( ! empty( $data['token']['card'] ) ) {
			// Name from Stripe is a full name string.
			$name                          = explode( ' ', $data['token']['card']['name'] );
			$lastname                      = array_pop( $name );
			$firstname                     = implode( ' ', $name );
			$billing_address['first_name'] = $firstname;
			$billing_address['last_name']  = $lastname;
			$billing_address['email']      = $data['shippingContact']['emailAddress'];
			$billing_address['phone']      = $data['shippingContact']['phoneNumber'];
			$billing_address['country']    = $data['token']['card']['country'];
			$billing_address['address_1']  = $data['token']['card']['address_line1'];
			$billing_address['address_2']  = $data['token']['card']['address_line2'];
			$billing_address['city']       = $data['token']['card']['address_city'];
			$billing_address['state']      = $data['token']['card']['address_state'];
			$billing_address['postcode']   = $data['token']['card']['address_zip'];
		}

		// Shipping address.
		$shipping_address = array();
		if ( WC()->cart->needs_shipping() && ! empty( $data['shippingContact'] ) ) {
			$shipping_address['first_name'] = $data['shippingContact']['givenName'];
			$shipping_address['last_name']  = $data['shippingContact']['familyName'];
			$shipping_address['email']      = $data['shippingContact']['emailAddress'];
			$shipping_address['phone']      = $data['shippingContact']['phoneNumber'];
			$shipping_address['country']    = $data['shippingContact']['countryCode'];
			$shipping_address['address_1']  = $data['shippingContact']['addressLines'][0];
			$shipping_address['state']      = $data['shippingContact']['locality'];
			$shipping_address['state']      = $data['shippingContact']['administrativeArea'];
			$shipping_address['postcode']   = $data['shippingContact']['postalCode'];
		} elseif ( ! empty( $data['shippingContact'] ) ) {
			$shipping_address['first_name'] = $firstname;
			$shipping_address['last_name']  = $lastname;
			$shipping_address['email']      = $data['shippingContact']['emailAddress'];
			$shipping_address['phone']      = $data['shippingContact']['phoneNumber'];
			$shipping_address['country']    = $data['token']['card']['country'];
			$shipping_address['address_1']  = $data['token']['card']['address_line1'];
			$shipping_address['address_2']  = $data['token']['card']['address_line2'];
			$shipping_address['city']       = $data['token']['card']['address_city'];
			$shipping_address['state']      = $data['token']['card']['address_state'];
			$shipping_address['postcode']   = $data['token']['card']['address_zip'];
		}

		$order->set_address( $billing_address, 'billing' );
		$order->set_address( $shipping_address, 'shipping' );

		WC()->shipping->calculate_shipping( WC()->cart->get_shipping_packages() );
		
		// Get the rate object selected by user.
		foreach ( WC()->shipping->get_packages() as $package_key => $package ) {
			foreach ( $package['rates'] as $key => $rate ) {
				// Loop through user chosen shipping methods.
				foreach( WC()->session->get( 'chosen_shipping_methods' ) as $method ) {
					if ( $method === $key ) {
						$order->add_shipping( $rate );
					}
				}
			}
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$order->set_payment_method( $available_gateways['stripe'] );
		$order->set_total( WC()->cart->shipping_total, 'shipping' );
		$order->set_total( WC()->cart->get_cart_discount_total(), 'cart_discount' );
		$order->set_total( WC()->cart->get_cart_discount_tax_total(), 'cart_discount_tax' );
		$order->set_total( WC()->cart->tax_total, 'tax' );
		$order->set_total( WC()->cart->shipping_tax_total, 'shipping_tax' );
		$order->set_total( WC()->cart->total );

		// If we got here, the order was created without problems!
		wc_transaction_query( 'commit' );

		return $order;
	}
}

new WC_Stripe_Apple_Pay();

