<?php
/**
 * Stripe Payment Request API
 *
 * @package WooCommerce_Stripe/Classes/Payment_Request
 * @since   3.1.0
 * @version 3.1.0
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
	public $enabled;

	/**
	 * Stripe enabled.
	 *
	 * @var
	 */
	public $stripe_enabled;

	/**
	 * Stripe Checkout enabled.
	 *
	 * @var
	 */
	public $stripe_checkout_enabled;

	/**
	 * Initialize class actions.
	 *
	 * @since 3.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		$gateway_settings              = get_option( 'woocommerce_stripe_settings', '' );
		$this->enabled                 = isset( $gateway_settings['request_payment_api'] ) && 'yes' === $gateway_settings['request_payment_api'];
		$this->stripe_enabled          = isset( $gateway_settings['enabled'] ) && 'yes' === $gateway_settings['enabled'];
		$this->stripe_checkout_enabled = isset( $gateway_settings['stripe_checkout'] ) && 'yes' === $gateway_settings['stripe_checkout'];

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_request_pay_button' ), 1 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_request_pay_separator_html' ), 2 );
		add_action( 'wc_ajax_wc_stripe_get_cart_details', array( $this, 'ajax_get_cart_details' ) );
		add_action( 'wc_ajax_wc_stripe_get_shipping_options', array( $this, 'ajax_get_shipping_options' ) );
		add_action( 'wc_ajax_wc_stripe_update_shipping_method', array( $this, 'ajax_update_shipping_method' ) );
		add_action( 'wc_ajax_wc_stripe_create_order', array( $this, 'ajax_create_order' ) );
	}

	/**
	 * Check if Stripe gateway is enabled.
	 *
	 * @since 3.0.0
	 * @version 4.0.0
	 * @return bool
	 */
	protected function is_activated() {
		return $this->enabled && $this->stripe_enabled && ! $this->stripe_checkout_enabled && is_ssl();
	}

	/**
	 * Get publishable key.
	 *
	 * @return string
	 */
	protected function get_publishable_key() {
		$options = get_option( 'woocommerce_stripe_settings', array() );

		if ( empty( $options ) ) {
			return '';
		}

		return 'yes' === $options['testmode'] ? $options['test_publishable_key'] : $options['publishable_key'];
	}

	/**
	 * Load public scripts.
	 */
	public function scripts() {
		// Load PaymentRequest only on cart for now.
		if ( ! is_cart() ) {
			return;
		}

		if ( ! $this->is_activated() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'google-payment-request-shim', 'https://storage.googleapis.com/prshim/v1/payment-shim.js', '', '1.0', false );
		wp_register_script( 'wc-stripe-payment-request', plugins_url( 'assets/js/payment-request' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), array( 'jquery', 'stripe', 'google-payment-request-shim' ), WC_STRIPE_VERSION, true );

		wp_localize_script(
			'wc-stripe-payment-request',
			'wcStripePaymentRequestParams',
			array(
				'ajax_url' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'stripe'   => array(
					'key'                => $this->get_publishable_key(),
					'allow_prepaid_card' => apply_filters( 'wc_stripe_allow_prepaid_card', true ) ? 'yes' : 'no',
				),
				'nonce'    => array(
					'payment'         => wp_create_nonce( 'wc-stripe-payment-request' ),
					'shipping'        => wp_create_nonce( 'wc-stripe-payment-request-shipping' ),
					'update_shipping' => wp_create_nonce( 'wc-stripe-update-shipping-method' ),
					'checkout'        => wp_create_nonce( 'woocommerce-process_checkout' ),
				),
				'i18n'     => array(
					'no_prepaid_card'  => __( 'Sorry, we\'re not accepting prepaid cards at this time.', 'woocommerce-gateway-stripe' ),
					/* translators: Do not translate the [option] placeholder */
					'unknown_shipping' => __( 'Unknown shipping option "[option]".', 'woocommerce-gateway-stripe' ),
				),
				'checkout_url' => wc_get_checkout_url(),
			)
		);

		wp_enqueue_script( 'wc-stripe-payment-request' );
	}

	/**
	 * Display Request Pay button on the cart page.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function display_request_pay_button() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! $this->enabled || ! isset( $gateways['stripe'] ) || ! is_cart() ) {
			return;
		}

		?>
		<a href="#" class="wc-stripe-request-pay-button button checkout-button" alt="<?php esc_attr_e( 'Pay with Chrome', 'woocommerce-gateway-stripe' ); ?>" style="display:none;"><?php esc_html_e( 'Pay with Chrome', 'woocommerce-gateway-stripe' ); ?></a>
		<?php
	}

	/**
	 * Display Request Pay button separator on the cart page.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function display_request_pay_separator_html() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! $this->enabled || ! isset( $gateways['stripe'] ) || ! is_cart() ) {
			return;
		}
		?>
		<p class="wc-stripe-request-pay-button-separator" style="text-align:center;display:none;">- <?php esc_html_e( 'Or', 'woocommerce-gateway-stripe' ); ?> -</p>
		<?php
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
				'total' => array(
					'label'  => __( 'Total', 'woocommerce-gateway-stripe' ),
					'amount' => array(
						'value'    => max( 0, apply_filters( 'woocommerce_calculated_total', round( WC()->cart->cart_contents_total + WC()->cart->fee_total + WC()->cart->tax_total, WC()->cart->dp ), WC()->cart ) ),
						'currency' => $currency,
					),
				),
				// Include line items such as subtotal, fees and taxes. No shipping option is provided here because it is not chosen yet.
				'displayItems' => $this->compute_display_items( null ),
			),
		);

		wp_send_json( $data );
	}

	/**
	 * Calculate and set shipping method.
	 *
	 * @since 3.1.0
	 * @version 3.2.0
	 * @param array $address
	 */
	public function calculate_shipping( $address = array() ) {
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
			WC()->customer->set_to_base();
			WC()->customer->set_shipping_to_base();
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
	 * Get shipping options.
	 *
	 * @see WC_Cart::get_shipping_packages().
	 * @see WC_Shipping::calculate_shipping().
	 * @see WC_Shipping::get_packages().
	 */
	public function ajax_get_shipping_options() {
		check_ajax_referer( 'wc-stripe-payment-request-shipping', 'security' );

		// Set the shipping package.
		$posted   = filter_input_array( INPUT_POST, array(
			'country'   => FILTER_SANITIZE_STRING,
			'state'     => FILTER_SANITIZE_STRING,
			'postcode'  => FILTER_SANITIZE_STRING,
			'city'      => FILTER_SANITIZE_STRING,
			'address'   => FILTER_SANITIZE_STRING,
			'address_2' => FILTER_SANITIZE_STRING,
		) );

		$this->calculate_shipping( $posted );

		// Set the shipping options.
		$currency = get_woocommerce_currency();
		$data     = array();

		$packages = WC()->shipping->get_packages();

		if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
			foreach ( $packages as $package_key => $package ) {
				if ( empty( $package['rates'] ) ) {
					break;
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
		}

		// Auto select when have only one shipping method available.
		if ( 1 === count( $data ) ) {
			$data[0]['selected'] = true;
		}

		wp_send_json( $data );
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

		// Send back the new cart total and line items to be displayed, such as subtotal, shipping rate(s), fees and taxes.
		$data      = array(
			'total' => WC()->cart->total,
			'items' => $this->compute_display_items( $shipping_method[0] ),
		);

		wp_send_json( $data );
	}

	/**
	 * Create order.
	 */
	public function ajax_create_order() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'woocommerce-gateway-stripe' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		$_POST['terms'] = 1;
		$_POST['ship_to_different_address'] = 1;

		WC()->checkout()->process_checkout();

		die( 0 );
	}

	/**
	 * Compute display items to be included in the 'displayItems' key of the PaymentDetails.
	 *
	 * @param string shipping_method_id If shipping method ID is provided, will include display items about shipping.
	 */
	protected function compute_display_items( $shipping_method_id ) {
		$currency = get_woocommerce_currency();
		$items = array(
			// Subtotal excluding tax, because taxes is a separate item, below.
			array(
				'label' => __( 'Subtotal', 'woocommerce-gateway-stripe' ),
				'amount' => array(
					'value'    => max( 0, round( WC()->cart->subtotal_ex_tax, WC()->cart->dp ) ),
					'currency' => $currency,
				),
			),
		);
		// If a chosen shipping option was provided, add line item(s) for it and include the shipping tax.
		$tax_total = max( 0, round( WC()->cart->tax_total, WC()->cart->dp ) );
		if ( $shipping_method_id ) {
			$tax_total = max( 0, round( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp ) );
			// Look through the package rates for $shipping_method_id, and when found, add a line item.
			foreach ( WC()->shipping->get_packages() as $package_key => $package ) {
				foreach ( $package['rates'] as $key => $rate ) {
					if ( $rate->id == $shipping_method_id ) {
						$items[] = array(
							'label' => $rate->label,
							'amount' => array(
								'value' => $rate->cost,
								'currency' => $currency,
							),
						);
						break;
					}
				}
			}
		}
		// Include fees and taxes as display items.
		foreach ( WC()->cart->fees as $key => $fee ) {
			$items[] = array(
				'label'  => $fee->name,
				'amount' => array(
					'currency' => $currency,
					'value'    => $fee->amount,
				),
			);
		}
		// The tax total may include the shipping taxes if a shipping option is provided.
		if ( 0 < $tax_total ) {
			$items[] = array(
				'label'  => __( 'Tax', 'woocommerce-gateway-stripe' ),
				'amount' => array(
					'currency' => $currency,
					'value'    => $tax_total,
				),
			);
		}
		return $items;
	}
}

new WC_Stripe_Payment_Request();
