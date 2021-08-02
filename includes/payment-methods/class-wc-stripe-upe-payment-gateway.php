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
		$this->id           = 'stripe';
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
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$base_stripe_fields = require WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-settings.php';
		unset( $base_stripe_fields['inline_cc_form'] );
		$this->form_fields = array_merge_recursive( $base_stripe_fields, require WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-upe-settings.php' );
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
	 * Returns the list of enabled payment method types for UPE.
	 *
	 * @return string[]
	 */
	public function get_upe_enabled_payment_method_ids() {
		return $this->get_option(
			'upe_checkout_experience_accepted_payments',
			[
				'card',
			]
		);
	}

	/**
	 * Returns the list of available payment method types for UPE.
	 * See https://stripe.com/docs/stripe-js/payment-element#web-create-payment-intent for a complete list.
	 *
	 * @return string[]
	 */
	public function get_upe_available_payment_methods() {
		return [
			'card',
		];
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

	// TODO: Actually validate.
	public function validate_upe_checkout_experience_accepted_payments_field( $key, $value ) {
		return $value;
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
		$sepa_enabled = in_array( 'sepa', $this->get_upe_enabled_payment_method_ids(), true ) ? 'enabled' : 'disabled';
		$card_enabled = in_array( 'card', $this->get_upe_enabled_payment_method_ids(), true ) ? 'enabled' : 'disabled';

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
				<tr data-upe_method_id="card">
					<td class="name" width=""><a href="#" class="wc-payment-gateway-method-title">Credit card / debit card</a><span class="wc-payment-gateway-method-name">&nbsp;–&nbsp;Cards</span></td>
					<td class="status" width="1%"><a class="wc-payment-upe-method-toggle-' . $card_enabled . '" href="#"><span class="woocommerce-input-toggle woocommerce-input-toggle--' . $card_enabled . '" aria-label="The &quot;Stripe&quot; payment method is currently enabled">Yes</span></a></td>
					<td class="description" width="">Offer checkout with major credit and debit cards without leaving your store.</td>
				</tr>
				<tr data-upe_method_id="sepa">
					<td class="name" width=""><a href="#" class="wc-payment-gateway-method-title">SEPA Direct Debit</a><span class="wc-payment-gateway-method-name">&nbsp;–&nbsp;SEPA Direct Debit</span></td>
					<td class="status" width="1%"><a class="wc-payment-upe-method-toggle-' . $sepa_enabled . '" href="#"><span class="woocommerce-input-toggle woocommerce-input-toggle--' . $sepa_enabled . '" aria-label="The &quot;Stripe&quot; payment method is currently enabled">Yes</span></a></td>
					<td class="description" width="">Reach 500 million customers and over 20 million businesses across the European Union.</td>
				</tr>
			</tbody>
			</table>
			<span id="wc_stripe_upe_change_notice" class="hidden">You must save your changes.</span>';
		return $this->generate_title_html( $key, $data );
	}

	/**
	 * This is overloading the title type so the oauth url is only fetched if we are on the settings page.
	 *
	 * TODO: This is duplicate code from WC_Gateway_Stripe.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_stripe_account_keys_html( $key, $data ) {
		if ( woocommerce_gateway_stripe()->connect->is_connected() ) {
			$reset_link = add_query_arg(
				[
					'_wpnonce'                     => wp_create_nonce( 'reset_stripe_api_credentials' ),
					'reset_stripe_api_credentials' => true,
				],
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' )
			);

			$api_credentials_text = sprintf(
			/* translators: %1, %2, %3, and %4 are all HTML markup tags */
				__( '%1$sClear all Stripe account keys.%2$s %3$sThis will disable any connection to Stripe.%4$s', 'woocommerce-gateway-stripe' ),
				'<a id="wc_stripe_connect_button" href="' . $reset_link . '" class="button button-secondary">',
				'</a>',
				'<span style="color:red;">',
				'</span>'
			);
		} else {
			$oauth_url = woocommerce_gateway_stripe()->connect->get_oauth_url();

			if ( ! is_wp_error( $oauth_url ) ) {
				$api_credentials_text = sprintf(
				/* translators: %1, %2 and %3 are all HTML markup tags */
					__( '%1$sSetup or link an existing Stripe account.%2$s By clicking this button you agree to the %3$sTerms of Service%2$s. Or, manually enter Stripe account keys below.', 'woocommerce-gateway-stripe' ),
					'<a id="wc_stripe_connect_button" href="' . $oauth_url . '" class="button button-primary">',
					'</a>',
					'<a href="https://wordpress.com/tos">'
				);
			} else {
				$api_credentials_text = __( 'Manually enter Stripe keys below.', 'woocommerce-gateway-stripe' );
			}
		}
		$data['description'] = $api_credentials_text;
		return $this->generate_title_html( $key, $data );
	}
}
