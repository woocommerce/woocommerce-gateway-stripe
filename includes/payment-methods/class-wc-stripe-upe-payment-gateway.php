<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Class that handles UPE payment method.
*
* @extends WC_Stripe_Payment_Gateway
*
* @since x.x.x
*/
class WC_Stripe_UPE_Payment_Gateway extends WC_Stripe_Payment_Gateway {

	const UPE_APPEARANCE_TRANSIENT = 'wc_stripe_upe_appearance';

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
	 * Array mapping payment method string IDs to classes
	 *
	 * @var array
	 */
	protected $payment_methods = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id           = 'stripe_upe';
		$this->method_title = __( 'Stripe UPE', 'woocommerce-gateway-stripe' );
		/* translators: link */
		$this->method_description = __( 'Accept debit and credit cards in 135+ currencies, methods such as Alipay, and one-touch checkout with Apple Pay.', 'woocommerce-gateway-stripe' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = get_option( 'woocommerce_stripe_settings' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'];
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		$this->payment_methods  = [];
		$payment_method_classes = [
			WC_Stripe_UPE_Payment_Method_CC::class,
		];
		foreach ( $payment_method_classes as $payment_method_class ) {
			$payment_method                                     = new $payment_method_class( null );
			$this->payment_methods[ $payment_method->get_id() ] = $payment_method;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-upe-settings.php';
	}

	/**
	 * Outputs scripts used for stripe payment
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script( 'woocommerce_stripe_upe', plugins_url( 'assets/js/stripe-upe' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), [ 'jquery-payment', 'stripe' ], WC_STRIPE_VERSION, true );

		wp_localize_script(
			'woocommerce_stripe_upe',
			'wc_stripe_upe_params',
			apply_filters( 'wc_stripe_upe_params', $this->javascript_params() )
		);

		wp_enqueue_script( 'woocommerce_stripe_upe' );
	}

	/**
	 * Returns the JavaScript configuration object used on the product, cart, and checkout pages.
	 *
	 * @return array  The configuration object to be loaded to JS.
	 */
	public function javascript_params() {
		global $wp;

		$stripe_params = [
			'publishableKey' => $this->publishable_key,
			'isUPEEnabled'   => true,
			'locale'         => WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( get_locale() ),
		];

		// If we're on the pay page we need to pass stripe.js the address of the order.
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) { // wpcs: csrf ok.
			$order_id                 = wc_clean( $wp->query_vars['order-pay'] ); // wpcs: csrf ok, sanitization ok, xss ok.
			$stripe_params['orderId'] = $order_id;
		}

		$stripe_params['isCheckout']               = ( is_checkout() && empty( $_GET['pay_for_order'] ) ) ? 'yes' : 'no'; // wpcs: csrf ok.
		$stripe_params['isOrderPay']               = is_wc_endpoint_url( 'order-pay' ) ? 'yes' : 'no';
		$stripe_params['return_url']               = $this->get_stripe_return_url();
		$stripe_params['ajaxurl']                  = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$stripe_params['createPaymentIntentNonce'] = wp_create_nonce( '_wc_stripe_nonce' );
		$stripe_params['upeAppeareance']           = get_transient( self::UPE_APPEARANCE_TRANSIENT );
		$stripe_params['paymentMethodsConfig']     = $this->get_enabled_payment_method_config();

		return $stripe_params;
	}

	/**
	 * Gets payment method settings to pass to client scripts
	 *
	 * @return array
	 */
	private function get_enabled_payment_method_config() {
		$settings                = [];
		$enabled_payment_methods = $this->get_upe_enabled_payment_method_ids(); //array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_at_checkout' ] );

		foreach ( $enabled_payment_methods as $payment_method ) {
			$settings[ $payment_method ] = [
				'isReusable' => $this->payment_methods[ $payment_method ]->is_reusable(),
			];
		}

		return $settings;
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		?>
		<form id="payment-form">
			  <div id="wc-stripe-upe-element">
				<!-- Elements will create form elements here -->
			  </div>
		</form>
		<?php
	}

	/**
	 * Function to be used with array_filter
	 * to filter UPE payment methods supported with current checkout
	 *
	 * @param string $payment_method_id Stripe payment method.
	 *
	 * @return bool
	 */
	private function is_enabled_at_checkout( $payment_method_id ) {
		if ( ! isset( $this->payment_methods[ $payment_method_id ] ) ) {
			return false;
		}
		return $this->payment_methods[ $payment_method_id ]->is_enabled_at_checkout();
	}

	/**
	 * Function to be used with array_filter
	 * to filter UPE payment methods that support saved payments
	 *
	 * @param string $payment_method_id Stripe payment method.
	 *
	 * @return bool
	 */
	private function is_enabled_for_saved_payments( $payment_method_id ) {
		if ( ! isset( $this->payment_methods[ $payment_method_id ] ) ) {
			return false;
		}
		return $this->payment_methods[ $payment_method_id ]->is_reusable();
	}

	/**
	 * This is overloading the upe checkout experience type on the settings page.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_upe_checkout_experience_accepted_payments_html( $key, $data ) {
		// TODO: This is just a placeholder for now
		$data['description'] = '<p><strong>Payments accepted on checkout</strong></p>
			<table class="wc_gateways widefat" cellspacing="0" aria-describedby="payment_gateways_options-description">
			<thead>
				<tr>
					<th class="name">Method</th>
					<th class="status">Enabled</th>
					<th class="description">Description</th>
				</tr>
			</thead>
			<tbody>
				<tr data-gateway_id="stripe">
					<td class="name" width=""><a href="#" class="wc-payment-gateway-method-title">Credit card / debit card</a><span class="wc-payment-gateway-method-name">&nbsp;–&nbsp;Cards</span></td>
					<td class="status" width="1%"><a class="wc-payment-gateway-method-toggle-enabled" href="#"><span class="woocommerce-input-toggle woocommerce-input-toggle--enabled" aria-label="The &quot;Stripe&quot; payment method is currently enabled">Yes</span></a></td>
					<td class="description" width="">Offer checkout with major credit and debit cards without leaving your store.</td>
				</tr>
				<tr data-gateway_id="stripe_sepa">
					<td class="name" width=""><a href="#" class="wc-payment-gateway-method-title">SEPA Direct Debit</a><span class="wc-payment-gateway-method-name">&nbsp;–&nbsp;SEPA Direct Debit</span></td>
					<td class="status" width="1%"><a class="wc-payment-gateway-method-toggle-enabled" href="#"><span class="woocommerce-input-toggle woocommerce-input-toggle--enabled" aria-label="The &quot;Stripe SEPA Direct Debit&quot; payment method is currently enabled">Yes</span></a></td>
					<td class="description" width="">Reach 500 million customers and over 20 million businesses across the European Union.</td>
				</tr>
			</tbody>
		</table>';
		return $this->generate_title_html( $key, $data );
	}
}
