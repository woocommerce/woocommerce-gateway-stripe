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
	 * Statement Description
	 *
	 * @var
	 */
	public $statement_descriptor;

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

		$this->statement_descriptor = ! empty( $this->_gateway_settings['statement_descriptor'] ) ? $this->_gateway_settings['statement_descriptor'] : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

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
		add_action( 'wp_enqueue_scripts', array( $this, 'cart_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'single_scripts' ) );

		/**
		 * In order to display the Apple Pay button in the correct position,
		 * a new hook was added to WooCommerce 2.7. In older versions of WooCommerce,
		 * CSS is used to position the button.
		 */
		if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'display_apple_pay_button' ), 1 );
		} else {
			add_action( 'woocommerce_after_add_to_cart_quantity', array( $this, 'display_apple_pay_button' ), 1 );
		}

		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_apple_pay_button' ), 1 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_apple_pay_separator_html' ), 2 );
		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'display_apple_pay_button' ), 1 );
		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'display_apple_pay_separator_html' ), 2 );
		add_action( 'wc_ajax_wc_stripe_apple_pay', array( $this, 'process_apple_pay' ) );
		add_action( 'wc_ajax_wc_stripe_generate_apple_pay_cart', array( $this, 'generate_apple_pay_cart' ) );
		add_action( 'wc_ajax_wc_stripe_generate_apple_pay_single', array( $this, 'generate_apple_pay_single' ) );
		add_action( 'wc_ajax_wc_stripe_apple_pay_get_shipping_methods', array( $this, 'get_shipping_methods' ) );
		add_action( 'wc_ajax_wc_stripe_apple_pay_update_shipping_method', array( $this, 'update_shipping_method' ) );
		add_filter( 'woocommerce_gateway_title', array( $this, 'filter_gateway_title' ), 10, 2 );
	}

	/**
	 * Filters the gateway title to reflect Apple Pay.
	 *
	 */
	public function filter_gateway_title( $title, $id ) {
		global $post;

		if ( ! is_object( $post ) ) {
			return $title;
		}

		$method_title = get_post_meta( $post->ID, '_payment_method_title', true );

		if ( 'stripe' === $id && ! empty( $method_title ) ) {
			return $method_title;
		}

		return $title;
	}

	/**
	 * Enqueue JS scripts and styles for single product page.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function single_scripts() {
		if ( ! is_single() ) {
			return;
		}

		global $post;

		$product = wc_get_product( $post->ID );

		if ( ! is_object( $product ) || ! in_array( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->product_type : $product->get_type() ), $this->supported_product_types() ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'stripe_apple_pay', plugins_url( 'assets/css/stripe-apple-pay.css', WC_STRIPE_MAIN_FILE ), array(), WC_STRIPE_VERSION );

		wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', '', '1.0', true );
		wp_enqueue_script( 'woocommerce_stripe_apple_pay_single', plugins_url( 'assets/js/stripe-apple-pay-single' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), array( 'stripe' ), WC_STRIPE_VERSION, true );

		$publishable_key = 'yes' === $this->_gateway_settings['testmode'] ? $this->_gateway_settings['test_publishable_key'] : $this->_gateway_settings['publishable_key'];

		$stripe_params = array(
			'key'                                           => $publishable_key,
			'currency_code'                                 => get_woocommerce_currency(),
			'country_code'                                  => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			'label'                                         => $this->statement_descriptor,
			'ajaxurl'                                       => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe_apple_pay_nonce'                        => wp_create_nonce( '_wc_stripe_apple_pay_nonce' ),
			'stripe_apple_pay_cart_nonce'                   => wp_create_nonce( '_wc_stripe_apple_pay_cart_nonce' ),
			'stripe_apple_pay_get_shipping_methods_nonce'   => wp_create_nonce( '_wc_stripe_apple_pay_get_shipping_methods_nonce' ),
			'stripe_apple_pay_update_shipping_method_nonce' => wp_create_nonce( '_wc_stripe_apple_pay_update_shipping_method_nonce' ),
			'needs_shipping'                                => WC()->cart->needs_shipping() ? 'yes' : 'no',
		);

		wp_localize_script( 'woocommerce_stripe_apple_pay_single', 'wc_stripe_apple_pay_single_params', apply_filters( 'wc_stripe_apple_pay_single_params', $stripe_params ) );
	}

	/**
	 * Enqueue JS scripts and styles for the cart/checkout.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function cart_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'stripe_apple_pay', plugins_url( 'assets/css/stripe-apple-pay.css', WC_STRIPE_MAIN_FILE ), array(), WC_STRIPE_VERSION );

		wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', '', '1.0', true );
		wp_enqueue_script( 'woocommerce_stripe_apple_pay', plugins_url( 'assets/js/stripe-apple-pay' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), array( 'stripe' ), WC_STRIPE_VERSION, true );

		$publishable_key = 'yes' === $this->_gateway_settings['testmode'] ? $this->_gateway_settings['test_publishable_key'] : $this->_gateway_settings['publishable_key'];

		$stripe_params = array(
			'key'                                           => $publishable_key,
			'currency_code'                                 => get_woocommerce_currency(),
			'country_code'                                  => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			'label'                                         => $this->statement_descriptor,
			'ajaxurl'                                       => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe_apple_pay_nonce'                        => wp_create_nonce( '_wc_stripe_apple_pay_nonce' ),
			'stripe_apple_pay_cart_nonce'                   => wp_create_nonce( '_wc_stripe_apple_pay_cart_nonce' ),
			'stripe_apple_pay_get_shipping_methods_nonce'   => wp_create_nonce( '_wc_stripe_apple_pay_get_shipping_methods_nonce' ),
			'stripe_apple_pay_update_shipping_method_nonce' => wp_create_nonce( '_wc_stripe_apple_pay_update_shipping_method_nonce' ),
			'needs_shipping'                                => WC()->cart->needs_shipping() ? 'yes' : 'no',
			'is_cart_page'                                  => is_cart() ? 'yes' : 'no',
		);

		wp_localize_script( 'woocommerce_stripe_apple_pay', 'wc_stripe_apple_pay_params', apply_filters( 'wc_stripe_apple_pay_params', $stripe_params ) );
	}

	/**
	 * Checks to make sure product type is supported by Apple Pay.
	 *
	 */
	public function supported_product_types() {
		return array(
			'simple',
			'variable',
		);
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

		if ( is_single() ) {
			global $post;

			$product = wc_get_product( $post->ID );

			if ( ! in_array( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->product_type : $product->get_type() ), $this->supported_product_types() ) ) {
				return;
			}
		}

		$apple_pay_button = ! empty( $this->_gateway_settings['apple_pay_button'] ) ? $this->_gateway_settings['apple_pay_button'] : 'black';
		$button_lang      = ! empty( $this->_gateway_settings['apple_pay_button_lang'] ) ? strtolower( $this->_gateway_settings['apple_pay_button_lang'] ) : 'en';
		?>
		<div class="apple-pay-button-wrapper">
			<button class="apple-pay-button" lang="<?php echo esc_attr( $button_lang ); ?>" style="-webkit-appearance: -apple-pay-button; -apple-pay-button-type: buy; -apple-pay-button-style: <?php echo esc_attr( $apple_pay_button ); ?>;"></button>
		</div>
		<?php
	}

	/**
	 * Display Apple Pay button on the cart page
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function display_apple_pay_separator_html() {
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

		if ( is_single() ) {
			global $post;

			$product = wc_get_product( $post->ID );

			if ( ! in_array( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->product_type : $product->get_type() ), $this->supported_product_types() ) ) {
				return;
			}
		}
		?>
		<p class="apple-pay-button-checkout-separator">- <?php esc_html_e( 'Or', 'woocommerce-gateway-stripe' ); ?> -</p>
		<?php
	}

	/**
	 * Generates the Apple Pay single cart.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function generate_apple_pay_single() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_apple_pay_cart_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		global $post;

		$product = wc_get_product( $post->ID );
		$qty     = absint( $_POST['qty'] );

		/**
		 * If this page is single product page, we need to simulate
		 * adding the product to the cart taken account if it is a
		 * simple or variable product.
		 */
		if ( is_single() ) {
			// First empty the cart to prevent wrong calculation.
			WC()->cart->empty_cart();

			if ( 'variable' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->product_type : $product->get_type() ) && isset( $_POST['attributes'] ) ) {
				$attributes = array_map( 'wc_clean', $_POST['attributes'] );

				$variation_id = $product->get_matching_variation( $attributes );

				WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
			}

			if ( 'simple' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->product_type : $product->get_type() ) ) {
				WC()->cart->add_to_cart( $product->get_id(), $qty );
			}
		}

		WC()->cart->calculate_totals();

		wp_send_json( array( 'line_items' => $this->build_line_items(), 'total' => WC()->cart->total ) );
	}

	/**
	 * Generates the Apple Pay cart.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function generate_apple_pay_cart() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_apple_pay_cart_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
		}

		wp_send_json( array( 'line_items' => $this->build_line_items(), 'total' => WC()->cart->total ) );
	}

	/**
	 * Calculate and set shipping method.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param array $address
	 */
	public function calculate_shipping( $address = array() ) {
		$country  = strtoupper( $address['countryCode'] );
		$state    = strtoupper( $address['administrativeArea'] );
		$postcode = $address['postalCode'];
		$city     = $address['locality'];

		WC()->shipping->reset_shipping();

		if ( $postcode && ! WC_Validation::is_postcode( $postcode, $country ) ) {
			throw new Exception( __( 'Please enter a valid postcode/ZIP.', 'woocommerce-gateway-stripe' ) );
		} elseif ( $postcode ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_to_base();
			WC()->customer->set_shipping_to_base();
		}

		WC()->customer->calculated_shipping( true );

		/**
		 * Set the shipping package.
		 *
		 * Note that address lines are not provided at this point
		 * because Apple Pay does not supply that until after
		 * authentication via passcode or Touch ID. We will need to
		 * capture this information when we process the payment.
		 */

		$packages = array();

		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;

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
	 * Gets shipping for Apple Pay Payment sheet.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function get_shipping_methods() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_apple_pay_get_shipping_methods_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		try {
			$address = array_map( 'wc_clean', $_POST['address'] );

			$this->calculate_shipping( $address );

			// Set the shipping options.
			$currency = get_woocommerce_currency();
			$data     = array();

			$packages = WC()->shipping->get_packages();

			if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
				foreach ( $packages as $package_key => $package ) {
					if ( empty( $package['rates'] ) ) {
						throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
					}

					foreach ( $package['rates'] as $key => $rate ) {
						$data[] = array(
							'id'       => $rate->id,
							'label'    => $rate->label,
							'amount'   => array(
								'currency' => $currency,
								'value'    => $rate->cost,
							),
							'selected' => false,
						);
					}
				}

				// Auto select the first shipping method.
				WC()->session->set( 'chosen_shipping_methods', array( $data[0]['id'] ) );

				WC()->cart->calculate_totals();

				wp_send_json( array( 'success' => 'true', 'shipping_methods' => $this->build_shipping_methods( $data ), 'line_items' => $this->build_line_items(), 'total' => WC()->cart->total ) );
			} else {
				throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
			}
		} catch ( Exception $e ) {
			wp_send_json( array( 'success' => 'false', 'shipping_methods' => array(), 'line_items' => $this->build_line_items(), 'total' => WC()->cart->total ) );
		}
	}

	/**
	 * Updates shipping method on cart session.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function update_shipping_method() {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_apple_pay_update_shipping_method_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
		}

		$selected_shipping_method = array_map( 'wc_clean', $_POST['selected_shipping_method'] );

		WC()->session->set( 'chosen_shipping_methods', array( $selected_shipping_method['identifier'] ) );

		WC()->cart->calculate_totals();

		// Send back the new cart total.
		$currency  = get_woocommerce_currency();
		$tax_total = max( 0, round( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp ) );
		$data      = array(
			'total' => WC()->cart->total,
		);

		// Include fees and taxes as displayItems.
		foreach ( WC()->cart->fees as $key => $fee ) {
			$data['items'][] = array(
				'label'  => $fee->name,
				'amount' => array(
					'currency' => $currency,
					'value'    => $fee->amount,
				),
			);
		}
		if ( 0 < $tax_total ) {
			$data['items'][] = array(
				'label'  => __( 'Tax', 'woocommerce-gateway-stripe' ),
				'amount' => array(
					'currency' => $currency,
					'value'    => $tax_total,
				),
			);
		}

		wp_send_json( array( 'success' => 'true', 'line_items' => $this->build_line_items(), 'total' => WC()->cart->total ) );
	}

	/**
	 * Handles the Apple Pay processing via AJAX
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function process_apple_pay() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_apple_pay_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
		}

		try {
			$result = array_map( 'wc_clean', $_POST['result'] );

			$order = $this->create_order( $result );

			$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < WC_Stripe::get_minimum_amount() ) {
					return new WP_Error( 'stripe_error', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe::get_minimum_amount() / 100 ) ) );
				}

				WC_Stripe::log( "Info: Begin processing payment for order {$order_id} for the amount of {$order->get_total()}" );

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

			update_post_meta( $order_id, '_customer_user', get_current_user_id() );
			update_post_meta( $order_id, '_payment_method_title', __( 'Apple Pay (Stripe)', 'woocommerce-gateway-stripe' ) );

			// Return thank you page redirect.
			wp_send_json( array(
				'success'  => 'true',
				'redirect' => $this->get_return_url( $order ),
			) );

		} catch ( Exception $e ) {
			WC()->session->set( 'refresh_totals', true );
			WC_Stripe::log( sprintf( __( 'Error: %s', 'woocommerce-gateway-stripe' ), $e->getMessage() ) );

			if ( is_object( $order ) && isset( $order_id ) && $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );
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
		$post_data['currency']    = strtolower( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency() );
		$post_data['amount']      = $this->get_stripe_amount( $order->get_total(), $post_data['currency'] );
		$post_data['description'] = sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), $this->statement_descriptor, $order->get_order_number() );
		$post_data['capture']     = 'yes' === $this->_gateway_settings['capture'] ? 'true' : 'false';

		$billing_email      = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_email : $order->get_billing_email();

		if ( ! empty( $billing_email ) && apply_filters( 'wc_stripe_send_stripe_receipt', false ) ) {
			$post_data['receipt_email'] = $billing_email;
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
	 * Builds the shippings methods to pass to Apple Pay.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function build_shipping_methods( $shipping_methods ) {
		if ( empty( $shipping_methods ) ) {
			return array();
		}

		$shipping = array();

		foreach ( $shipping_methods as $method ) {
			$shipping[] = array(
				'label'      => $method['label'],
				'detail'     => '',
				'amount'     => $method['amount']['value'],
				'identifier' => $method['id'],
			);
		}

		return $shipping;
	}

	/**
	 * Builds the line items to pass to Apple Pay.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function build_line_items() {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$decimals = apply_filters( 'wc_stripe_apple_pay_decimals', 2 );
		
		$items    = array();
		$subtotal = 0;

		foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
			$amount         = wc_format_decimal( $values['line_subtotal'], $decimals );
			$subtotal       += $values['line_subtotal'];
			$quantity_label = 1 < $values['quantity'] ? ' (x' . $values['quantity'] . ')' : '';

			$item = array(
				'type'   => 'final',
				'label'  => $values['data']->post->post_title . $quantity_label,
				'amount' => wc_format_decimal( $amount, $decimals ),
			);

			$items[] = $item;
		}

		// Default show only subtotal instead of itemization.
		if ( apply_filters( 'wc_stripe_apple_pay_disable_itemization', true ) ) {
			$items = array();
			$items[] = array(
				'type'   => 'final',
				'label'  => __( 'Sub-Total', 'woocommerce-gateway-stripe' ),
				'amount' => wc_format_decimal( $subtotal, $decimals ),
			);
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
				'amount' => '-' . $discounts,
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
		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

		if ( is_wp_error( $order ) ) {
			throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 520 ) );
		} elseif ( false === $order ) {
			throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 521 ) );
		} else {
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
						'tax_data'     => $values['line_tax_data'], // Since 2.2
					),
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
			$shipping_address['address_2']  = $data['shippingContact']['addressLines'][1];
			$shipping_address['city']       = $data['shippingContact']['locality'];
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
				foreach ( WC()->session->get( 'chosen_shipping_methods' ) as $method ) {
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
