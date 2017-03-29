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
	 * Statement Description
	 *
	 * @var
	 */
	public $statement_descriptor;

	/**
	 * Check if we capture the transaction immediately.
	 *
	 * @var bool
	 */
	public $capture;

	/**
	 * Do we accept Apple Pay?
	 *
	 * @var bool
	 */
	public $apple_pay;

	/**
	 * Apple Pay button style.
	 *
	 * @var bool
	 */
	public $apple_pay_button;

	/**
	 * Apple Pay button language.
	 *
	 * @var bool
	 */
	public $apple_pay_button_lang;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Logging enabled?
	 *
	 * @var bool
	 */
	public $logging;

	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * Publishable key credentials.
	 *
	 * @var bool
	 */
	public $publishable_key;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function __construct() {
		self::$_this = $this;

		$gateway_settings = get_option( 'woocommerce_stripe_settings', '' );

		$this->statement_descriptor = ! empty( $gateway_settings['statement_descriptor'] ) ? $gateway_settings['statement_descriptor'] : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		// If both site title and statement descriptor is not set. Fallback.
		if ( empty( $this->statement_descriptor ) ) {
			$this->statement_descriptor = $_SERVER['SERVER_NAME'];
		}

		$this->enabled               = ( ! empty( $gateway_settings['enabled'] ) && 'yes' === $gateway_settings['enabled'] ) ? true : false;
		$this->testmode              = ( ! empty( $gateway_settings['testmode'] ) && 'yes' === $gateway_settings['testmode'] ) ? true : false;
		$this->capture               = ( ! empty( $gateway_settings['capture'] ) && 'yes' === $gateway_settings['capture'] ) ? true : false;
		$this->saved_cards           = ( ! empty( $gateway_settings['saved_cards'] ) && 'yes' === $gateway_settings['saved_cards'] ) ? true : false;
		$this->apple_pay             = ( ! empty( $gateway_settings['apple_pay'] ) && 'yes' === $gateway_settings['apple_pay'] ) ? true : false;
		$this->apple_pay_button      = ! empty( $gateway_settings['apple_pay_button'] ) ? $gateway_settings['apple_pay_button'] : 'black';
		$this->apple_pay_button_lang = ! empty( $gateway_settings['apple_pay_button_lang'] ) ? $gateway_settings['apple_pay_button_lang'] : 'en';
		$this->logging               = ( ! empty( $gateway_settings['logging'] ) && 'yes' === $gateway_settings['logging'] ) ? true : false;
		$this->publishable_key       = ! empty( $gateway_settings['publishable_key'] ) ? $gateway_settings['publishable_key'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $gateway_settings['test_publishable_key'] ) ? $gateway_settings['test_publishable_key'] : '';
		}

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
	 * @version 3.1.4
	 */
	public function init() {
		// If Apple Pay is not enabled no need to proceed further.
		if ( ! $this->apple_pay ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'cart_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'single_scripts' ) );

		/**
		 * In order to display the Apple Pay button in the correct position,
		 * a new hook was added to WooCommerce 3.0. In older versions of WooCommerce,
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
		add_action( 'wc_ajax_wc_stripe_log_apple_pay_errors', array( $this, 'log_apple_pay_errors' ) );
		add_action( 'wc_ajax_wc_stripe_apple_pay', array( $this, 'process_apple_pay' ) );
		add_action( 'wc_ajax_wc_stripe_generate_apple_pay_cart', array( $this, 'generate_apple_pay_cart' ) );
		add_action( 'wc_ajax_wc_stripe_apple_pay_clear_cart', array( $this, 'clear_cart' ) );
		add_action( 'wc_ajax_wc_stripe_generate_apple_pay_single', array( $this, 'generate_apple_pay_single' ) );
		add_action( 'wc_ajax_wc_stripe_apple_pay_get_shipping_methods', array( $this, 'get_shipping_methods' ) );
		add_action( 'wc_ajax_wc_stripe_apple_pay_update_shipping_method', array( $this, 'update_shipping_method' ) );
		add_filter( 'woocommerce_gateway_title', array( $this, 'filter_gateway_title' ), 10, 2 );
		add_filter( 'woocommerce_validate_postcode', array( $this, 'postal_code_validation' ), 10, 3 );
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

		if ( 'stripe' === $id && ! empty( $method_title ) && 'Apple Pay (Stripe)' === $method_title ) {
			return $method_title;
		}

		return $title;
	}

	/**
	 * Log errors coming from Apple Pay.
	 *
	 * @since 3.1.4
	 * @version 3.1.4
	 */
	public function log_apple_pay_errors() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_apple_pay_nonce' ) && ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_apple_pay_cart_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
		}

		$errors = wc_clean( stripslashes( $_POST['errors'] ) );

		$this->log( $errors );

		exit;
	}

	/**
	 * Removes postal code validation from WC.
	 *
	 * @since 3.1.4
	 * @version 3.1.4
	 */
	public function postal_code_validation( $valid, $postcode, $country ) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! $this->apple_pay || ! isset( $gateways['stripe'] ) ) {
			return $valid;
		}

		/**
		 * Currently Apple Pay truncates postal codes from UK and Canada to first 3 characters
		 * when passing it back from the shippingcontactselected object. This causes WC to invalidate
		 * the order and not let it go through. The remedy for now is just to remove this validation.
		 */
		if ( 'GB' === $country || 'CA' === $country ) {
			return true;
		}

		return $valid;
	}

	/**
	 * Enqueue JS scripts and styles for single product page.
	 *
	 * @since 3.1.0
	 * @version 3.1.4
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

		$stripe_params = array(
			'key'                                           => $this->publishable_key,
			'currency_code'                                 => get_woocommerce_currency(),
			'country_code'                                  => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			'label'                                         => $this->statement_descriptor,
			'ajaxurl'                                       => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'stripe_apple_pay_nonce'                        => wp_create_nonce( '_wc_stripe_apple_pay_nonce' ),
			'stripe_apple_pay_cart_nonce'                   => wp_create_nonce( '_wc_stripe_apple_pay_cart_nonce' ),
			'stripe_apple_pay_get_shipping_methods_nonce'   => wp_create_nonce( '_wc_stripe_apple_pay_get_shipping_methods_nonce' ),
			'stripe_apple_pay_update_shipping_method_nonce' => wp_create_nonce( '_wc_stripe_apple_pay_update_shipping_method_nonce' ),
			'needs_shipping'                                => $product->needs_shipping() ? 'yes' : 'no',
			'i18n'                                          => array(
				'sub_total' => __( 'Sub-Total', 'woocommerce-gateway-stripe' ),
			),
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

		$stripe_params = array(
			'key'                                           => $this->publishable_key,
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
	 * @since 3.1.0
	 * @version 3.1.0
	 * @return array
	 */
	public function supported_product_types() {
		return array(
			'simple',
			'variable',
		);
	}

	/**
	 * Checks the cart to see if all items are allowed to use
	 * Apple Pay.
	 *
	 * @since 3.1.4
	 * @version 3.1.4
	 * @return bool
	 */
	public function allowed_items_in_cart() {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( ! in_array( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $_product->product_type : $_product->get_type() ), $this->supported_product_types() ) ) {
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
		 * In order for the Apple Pay button to show on product detail page,
		 * Apple Pay must be enabled and Stripe gateway must be enabled.
		 */
		if ( ! $this->apple_pay || ! isset( $gateways['stripe'] ) ) {
			$this->log( 'Apple Pay not enabled or Stripe is not an available gateway ( Apple Pay button disabled )' );
			return;
		}

		if ( is_single() ) {
			global $post;

			$product = wc_get_product( $post->ID );

			if ( ! is_object( $product ) || ! in_array( ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->product_type : $product->get_type() ), $this->supported_product_types() ) ) {
				return;
			}
		} else {
			if ( ! $this->allowed_items_in_cart() ) {
				$this->log( 'Items in the cart has unsupported product type ( Apple Pay button disabled )' );
				return;
			}
		}

		?>
		<div class="apple-pay-button-wrapper">
			<button class="apple-pay-button" lang="<?php echo esc_attr( $this->apple_pay_button_lang ); ?>" style="-webkit-appearance: -apple-pay-button; -apple-pay-button-type: buy; -apple-pay-button-style: <?php echo esc_attr( $this->apple_pay_button ); ?>;"></button>
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
		if ( ! $this->apple_pay || ! isset( $gateways['stripe'] ) ) {
			$this->log( 'Apple Pay not enabled or Stripe is not an available gateway ( Apple Pay button disabled )' );
			return;
		}

		if ( ! $this->allowed_items_in_cart() ) {
			$this->log( 'Items in the cart has unsupported product type ( Apple Pay button disabled )' );
			return;
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
		$qty     = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );

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

				if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
					$variation_id = $product->get_matching_variation( $attributes );
				} else {
					$data_store = WC_Data_Store::load( 'product' );
					$variation_id = $data_store->find_matching_product_variation( $product, $attributes );
				}

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
	 * Clears Apple Pay cart.
	 *
	 * @since 3.1.4
	 * @version 3.1.4
	 */
	public function clear_cart() {
		WC()->cart->empty_cart();
		exit;
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

				$this->log( "Info: Begin processing payment for order {$order_id} for the amount of {$order->get_total()}" );

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
			update_post_meta( $order_id, '_payment_method_title', 'Apple Pay (Stripe)' );

			// Return thank you page redirect.
			wp_send_json( array(
				'success'  => 'true',
				'redirect' => $this->get_return_url( $order ),
			) );

		} catch ( Exception $e ) {
			WC()->session->set( 'refresh_totals', true );
			$this->log( sprintf( __( 'Error: %s', 'woocommerce-gateway-stripe' ), $e->getMessage() ) );

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
		$post_data['capture']     = $this->capture ? 'true' : 'false';

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

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$amount         = wc_format_decimal( $cart_item['line_subtotal'], $decimals );
			$subtotal       += $cart_item['line_subtotal'];
			$quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';

			$item = array(
				'type'   => 'final',
				'label'  => $cart_item['data']->post->post_title . $quantity_label,
				'amount' => wc_format_decimal( $amount, $decimals ),
			);

			$items[] = $item;
		}

		// Default show only subtotal instead of itemization.
		if ( apply_filters( 'wc_stripe_apple_pay_disable_itemization', true ) ) {
			$items = array();
			$items[] = array(
				'type'   => 'final',
				'label'  => esc_html( __( 'Sub-Total', 'woocommerce-gateway-stripe' ) ),
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
				'label'  => esc_html( __( 'Tax', 'woocommerce-gateway-stripe' ) ),
				'amount' => $tax,
			);
		}

		if ( WC()->cart->needs_shipping() ) {
			$items[] = array(
				'type'   => 'final',
				'label'  => esc_html( __( 'Shipping', 'woocommerce-gateway-stripe' ) ),
				'amount' => $shipping,
			);
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = array(
				'type'   => 'final',
				'label'  => esc_html( __( 'Discount', 'woocommerce-gateway-stripe' ) ),
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
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				do_action( 'woocommerce_add_order_item_meta', $item_id, $values, $cart_item_key );
			} else {
				do_action( 'woocommerce_new_order_item', $item_id, wc_get_product( $item_id ), $order->get_id() );
			}
		}

		// Store fees
		foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$item_id = $order->add_fee( $fee );
			} else {
				$item = new WC_Order_Item_Fee();
				$item->set_props( array(
					'name'      => $fee->name,
					'tax_class' => $fee->taxable ? $fee->tax_class : 0,
					'total'     => $fee->amount,
					'total_tax' => $fee->tax,
					'taxes'     => array(
						'total' => $fee->tax_data,
					),
					'order_id'  => $order->get_id(),
				) );
				$item_id = $item->save();
				$order->add_item( $item );
			}

			if ( ! $item_id ) {
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 526 ) );
			}

			// Allow plugins to add order item meta to fees
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				do_action( 'woocommerce_add_order_fee_meta', $order_id, $item_id, $fee, $fee_key );
			} else {
				do_action( 'woocommerce_new_order_item', $item_id, $fee, $order->get_id() );
			}
		}

		// Store tax rows
		foreach ( array_keys( WC()->cart->taxes + WC()->cart->shipping_taxes ) as $tax_rate_id ) {
			$tax_amount = WC()->cart->get_tax_amount( $tax_rate_id );
			$shipping_tax_amount = WC()->cart->get_shipping_tax_amount( $tax_rate_id );

			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$item_id = $order->add_tax( $tax_rate_id, $tax_amount, $shipping_tax_amount );
			} else {
				$item = new WC_Order_Item_Tax();
				$item->set_props( array(
					'rate_id'            => $tax_rate_id,
					'tax_total'          => $tax_amount,
					'shipping_tax_total' => $shipping_tax_amount,
				) );
				$item->set_rate( $tax_rate_id );
				$item->set_order_id( $order->get_id() );
				$item_id = $item->save();
				$order->add_item( $item );
			}

			if ( $tax_rate_id && ! $item_id && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce-gateway-stripe' ), 528 ) );
			}
		}

		// Store coupons
		$discount = WC()->cart->get_coupon_discount_amount( $code );
		$discount_tax = WC()->cart->get_coupon_discount_tax_amount( $code );

		foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$coupon_id = $order->add_coupon( $code, $discount, $discount_tax );
			} else {
				$item = new WC_Order_Item_Coupon();
				$item->set_props( array(
					'code'         => $code,
					'discount'     => $discount,
					'discount_tax' => $discount_tax,
					'order_id'     => $order->get_id(),
				) );
				$coupon_id = $item->save();
				$order->add_item( $item );
			}

			if ( ! $coupon_id ) {
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
						if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
							$order->add_shipping( $rate );
						} else {
							$item = new WC_Order_Item_Shipping();
							$item->set_props( array(
								'method_title' => $rate->label,
								'method_id'    => $rate->id,
								'total'        => wc_format_decimal( $rate->cost ),
								'taxes'        => $rate->taxes,
								'order_id'     => $order->get_id(),
							) );
							foreach ( $rate->get_meta_data() as $key => $value ) {
								$item->add_meta_data( $key, $value, true );
							}
							$item->save();
							$order->add_item( $item );
						}
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

	/**
	 * Logs
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 *
	 * @param string $message
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WC_Stripe::log( 'Apple Pay: ' . $message );
		}
	}
}

new WC_Stripe_Apple_Pay();
