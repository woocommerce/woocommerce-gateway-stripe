<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Stripe class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Stripe extends WC_Stripe_Payment_Gateway {
	/**
	 * Should we capture Credit cards
	 *
	 * @var bool
	 */
	public $capture;

	/**
	 * Alternate credit card statement name
	 *
	 * @var bool
	 */
	public $statement_descriptor;

	/**
	 * Checkout enabled
	 *
	 * @var bool
	 */
	public $stripe_checkout;

	/**
	 * Require 3D Secure enabled
	 *
	 * @var bool
	 */
	public $three_d_secure;

	/**
	 * Checkout Locale
	 *
	 * @var string
	 */
	public $stripe_checkout_locale;

	/**
	 * Credit card image
	 *
	 * @var string
	 */
	public $stripe_checkout_image;

	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

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
	 * Do we accept bitcoin?
	 *
	 * @var bool
	 */
	public $bitcoin;

	/**
	 * Do we accept Payment Request?
	 *
	 * @var bool
	 */
	public $payment_request;

	/**
	 * Apple Pay Domain Set.
	 *
	 * @var bool
	 */
	public $apple_pay_domain_set;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Stores Apple Pay domain verification issues.
	 *
	 * @var string
	 */
	public $apple_pay_verify_notice;

	/**
	 * Inline CC form styling
	 *
	 * @var string
	 */
	public $inline_cc_form;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'stripe';
		$this->method_title         = __( 'Stripe', 'woocommerce-gateway-stripe' );
		/* translators: 1) link to Stripe register page 2) link to Stripe api keys page */
		$this->method_description   = sprintf( __( 'Stripe works by adding payment fields on the checkout and then sending the details to Stripe for verification. <a href="%1$s" target="_blank">Sign up</a> for a Stripe account, and <a href="%2$s" target="_blank">get your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), 'https://dashboard.stripe.com/register', 'https://dashboard.stripe.com/account/apikeys' );
		$this->has_fields           = true;
		$this->supports             = array(
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
			'pre-orders',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                   = $this->get_option( 'title' );
		$this->description             = $this->get_option( 'description' );
		$this->enabled                 = $this->get_option( 'enabled' );
		$this->testmode                = 'yes' === $this->get_option( 'testmode' );
		$this->inline_cc_form          = 'yes' === $this->get_option( 'inline_cc_form' );
		$this->capture                 = 'yes' === $this->get_option( 'capture', 'yes' );
		$this->statement_descriptor    = WC_Stripe_Helper::clean_statement_descriptor( $this->get_option( 'statement_descriptor' ) );
		$this->three_d_secure          = 'yes' === $this->get_option( 'three_d_secure' );
		$this->stripe_checkout         = 'yes' === $this->get_option( 'stripe_checkout' );
		$this->stripe_checkout_locale  = $this->get_option( 'stripe_checkout_locale' );
		$this->stripe_checkout_image   = $this->get_option( 'stripe_checkout_image', '' );
		$this->saved_cards             = 'yes' === $this->get_option( 'saved_cards' );
		$this->secret_key              = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->publishable_key         = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
		$this->bitcoin                 = 'USD' === strtoupper( get_woocommerce_currency() ) && 'yes' === $this->get_option( 'stripe_bitcoin' );
		$this->payment_request         = 'yes' === $this->get_option( 'payment_request', 'yes' );
		$this->apple_pay_domain_set    = 'yes' === $this->get_option( 'apple_pay_domain_set', 'no' );
		$this->apple_pay_verify_notice = '';

		if ( $this->stripe_checkout ) {
			$this->order_button_text = __( 'Continue to payment', 'woocommerce-gateway-stripe' );
		}

		WC_Stripe_API::set_secret_key( $this->secret_key );

		$this->init_apple_pay();

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Checks if gateway should be available to use.
	 *
	 * @since 4.0.2
	 */
	public function is_available() {
		if ( is_add_payment_method_page() && ! $this->saved_cards ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Get_icon function.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_icon() {
		$icons = $this->payment_icons();

		$icons_str = '';

		$icons_str .= $icons['visa'];
		$icons_str .= $icons['amex'];
		$icons_str .= $icons['mastercard'];

		if ( 'USD' === get_woocommerce_currency() ) {
			$icons_str .= $icons['discover'];
			$icons_str .= $icons['jcb'];
			$icons_str .= $icons['diners'];
		}

		if ( $this->bitcoin && $this->stripe_checkout ) {
			$icons_str .= $icons['bitcoin'];
		}

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * Initializes Apple Pay process on settings page.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function init_apple_pay() {
		if (
			is_admin() &&
			isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] &&
			isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] &&
			isset( $_GET['section'] ) && 'stripe' === $_GET['section'] &&
			$this->payment_request
		) {
			$this->process_apple_pay_verification();
		}
	}

	/**
	 * Registers the domain with Stripe/Apple Pay
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param string $secret_key
	 */
	private function register_apple_pay_domain( $secret_key = '' ) {
		if ( empty( $secret_key ) ) {
			throw new Exception( __( 'Unable to verify domain - missing secret key.', 'woocommerce-gateway-stripe' ) );
		}

		$endpoint = 'https://api.stripe.com/v1/apple_pay/domains';

		$data = array(
			'domain_name' => $_SERVER['HTTP_HOST'],
		);

		$headers = array(
			'User-Agent'    => 'WooCommerce Stripe Apple Pay',
			'Authorization' => 'Bearer ' . $secret_key,
		);

		$response = wp_remote_post( $endpoint, array(
			'headers' => $headers,
			'body'    => http_build_query( $data ),
		) );

		if ( is_wp_error( $response ) ) {
			/* translators: error message */
			throw new Exception( sprintf( __( 'Unable to verify domain - %s', 'woocommerce-gateway-stripe' ), $response->get_error_message() ) );
		}

		if ( 200 !== $response['response']['code'] ) {
			$parsed_response = json_decode( $response['body'] );

			$this->apple_pay_verify_notice = $parsed_response->error->message;

			/* translators: error message */
			throw new Exception( sprintf( __( 'Unable to verify domain - %s', 'woocommerce-gateway-stripe' ), $parsed_response->error->message ) );
		}
	}

	/**
	 * Processes the Apple Pay domain verification.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function process_apple_pay_verification() {
		$gateway_settings = get_option( 'woocommerce_stripe_settings', array() );

		try {
			$path     = untrailingslashit( $_SERVER['DOCUMENT_ROOT'] );
			$dir      = '.well-known';
			$file     = 'apple-developer-merchantid-domain-association';
			$fullpath = $path . '/' . $dir . '/' . $file;

			if ( ! empty( $gateway_settings['apple_pay_domain_set'] ) && 'yes' === $gateway_settings['apple_pay_domain_set'] && file_exists( $fullpath ) ) {
				return;
			}

			if ( ! file_exists( $path . '/' . $dir ) ) {
				if ( ! @mkdir( $path . '/' . $dir, 0755 ) ) {
					throw new Exception( __( 'Unable to create domain association folder to domain root.', 'woocommerce-gateway-stripe' ) );
				}
			}

			if ( ! file_exists( $fullpath ) ) {
				if ( ! @copy( WC_STRIPE_PLUGIN_PATH . '/' . $file, $fullpath ) ) {
					throw new Exception( __( 'Unable to copy domain association file to domain root.', 'woocommerce-gateway-stripe' ) );
				}
			}

			// At this point then the domain association folder and file should be available.
			// Proceed to verify/and or verify again.
			$this->register_apple_pay_domain( $this->secret_key );

			// No errors to this point, verification success!
			$gateway_settings['apple_pay_domain_set'] = 'yes';
			$this->apple_pay_domain_set = true;

			update_option( 'woocommerce_stripe_settings', $gateway_settings );

			WC_Stripe_Logger::log( 'Your domain has been verified with Apple Pay!' );

		} catch ( Exception $e ) {
			$gateway_settings['apple_pay_domain_set'] = 'no';

			update_option( 'woocommerce_stripe_settings', $gateway_settings );

			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( 'no' === $this->enabled ) {
			return;
		}

		if ( $this->payment_request && ! empty( $this->apple_pay_verify_notice ) ) {
			$allowed_html = array(
				'a' => array(
					'href' => array(),
					'title' => array(),
				),
			);

			echo '<div class="error stripe-apple-pay-message"><p>' . wp_kses( make_clickable( $this->apple_pay_verify_notice ), $allowed_html ) . '</p></div>';
		}

		/**
		 * Apple pay is enabled by default and domain verification initializes
		 * when setting screen is displayed. So if domain verification is not set,
		 * something went wrong so lets notify user.
		 */
		if ( ! empty( $this->secret_key ) && $this->payment_request && ! $this->apple_pay_domain_set ) {
			/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			echo '<div class="error stripe-apple-pay-message"><p>' . sprintf( __( 'Apple Pay domain verification failed. Please check the %1$slog%2$s to see the issue. (Logging must be enabled to see recorded logs)', 'woocommerce-gateway-stripe' ), '<a href="' . admin_url( 'admin.php?page=wc-status&tab=logs' ) . '">', '</a>' ) . '</p></div>';
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = require( dirname( __FILE__ ) . '/admin/stripe-settings.php' );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$user                 = wp_get_current_user();
		$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards;
		$total                = WC()->cart->total;
		$user_email           = '';

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order      = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
			$total      = $order->get_total();
			$user_email = WC_Stripe_Helper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
		} else {
			if ( $user->ID ) {
				$user_email = get_user_meta( $user->ID, 'billing_email', true );
				$user_email = $user_email ? $user_email : $user->user_email;
			}
		}

		if ( is_add_payment_method_page() ) {
			$pay_button_text = __( 'Add Card', 'woocommerce-gateway-stripe' );
			$total        = '';
		} else {
			$pay_button_text = '';
		}

		echo '<div
			id="stripe-payment-data"
			data-panel-label="' . esc_attr( $pay_button_text ) . '"
			data-description=""
			data-email="' . esc_attr( $user_email ) . '"
			data-amount="' . esc_attr( WC_Stripe_Helper::get_stripe_amount( $total ) ) . '"
			data-name="' . esc_attr( $this->statement_descriptor ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '"
			data-image="' . esc_attr( $this->stripe_checkout_image ) . '"
			data-bitcoin="' . esc_attr( ( $this->bitcoin && $this->capture ) ? 'true' : 'false' ) . '"
			data-locale="' . esc_attr( $this->stripe_checkout_locale ? $this->stripe_checkout_locale : 'en' ) . '"
			data-three-d-secure="' . esc_attr( $this->three_d_secure ? 'true' : 'false' ) . '"
			data-allow-remember-me="' . esc_attr( $this->saved_cards ? 'true' : 'false' ) . '">';

		if ( $this->description ) {
			if ( $this->testmode ) {
				/* translators: link to Stripe testing page */
				$this->description .= ' ' . sprintf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the documentation "<a href="%s" target="_blank">Testing Stripe</a>" for more card numbers.', 'woocommerce-gateway-stripe' ), 'https://stripe.com/docs/testing' );
				$this->description  = trim( $this->description );
			}
			echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		if ( ! $this->stripe_checkout ) {
			if ( apply_filters( 'wc_stripe_use_elements_checkout_form', true ) ) {
				$this->elements_form();
			} else {
				$this->form();
				echo '<div class="stripe-source-errors" role="alert"></div>';
			}
		}

		if ( apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
			$this->save_payment_method_checkbox();
		}

		echo '</div>';
	}

	/**
	 * Renders the Stripe elements form.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function elements_form() {
		?>
		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>

			<?php if ( $this->inline_cc_form ) { ?>
				<label for="card-element">
					<?php esc_html_e( 'Credit or debit card', 'woocommerce-gateway-stripe' ); ?>
				</label>

				<div id="stripe-card-element" style="background:#fff;padding:0 1em;border:1px solid #ddd;margin:5px 0;padding:10px 5px;">
				<!-- a Stripe Element will be inserted here. -->
				</div>
			<?php } else { ?>
				<div class="form-row form-row-wide">
					<label><?php _e( 'Card Number', 'woocommerce-gateway-stripe' ); ?> <span class="required">*</span></label>

					<div id="stripe-card-element" style="background:#fff;padding:0 1em;border:1px solid #ddd;margin:5px 0;padding:10px 5px;">
					<!-- a Stripe Element will be inserted here. -->
					</div>
				</div>

				<div class="form-row form-row-first">
					<label><?php _e( 'Expiry Date', 'woocommerce-gateway-stripe' ); ?> <span class="required">*</span></label>

					<div id="stripe-exp-element" style="background:#fff;padding:0 1em;border:1px solid #ddd;margin:5px 0;padding:10px 5px;">
					<!-- a Stripe Element will be inserted here. -->
					</div>
				</div>

				<div class="form-row form-row-last">
					<label><?php _e( 'Card Code (CVC)', 'woocommerce-gateway-stripe' ); ?> <span class="required">*</span></label>
				<div id="stripe-cvc-element" style="background:#fff;padding:0 1em;border:1px solid #ddd;margin:5px 0;padding:10px 5px;">
				<!-- a Stripe Element will be inserted here. -->
				</div>
				</div>
				<div class="clear"></div>
			<?php } ?>

			<!-- Used to display form errors -->
			<div class="stripe-source-errors" role="alert"></div>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	/**
	 * Load admin scripts.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function admin_scripts() {
		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'woocommerce_stripe_admin', plugins_url( 'assets/js/stripe-admin' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), array(), WC_STRIPE_VERSION, true );
	}

	/**
	 * Payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style( 'stripe_paymentfonts', plugins_url( 'assets/css/stripe-paymentfonts.css', WC_STRIPE_MAIN_FILE ), array(), '1.2.5' );
		wp_enqueue_style( 'stripe_paymentfonts' );
		wp_register_script( 'stripe_checkout', 'https://checkout.stripe.com/checkout.js', '', WC_STRIPE_VERSION, true );
		wp_register_script( 'stripev2', 'https://js.stripe.com/v2/', '', '2.0', true );
		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script( 'woocommerce_stripe', plugins_url( 'assets/js/stripe' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), array( 'jquery-payment', 'stripev2', 'stripe' ), WC_STRIPE_VERSION, true );

		$stripe_params = array(
			'key'                  => $this->publishable_key,
			'i18n_terms'           => __( 'Please accept the terms and conditions first', 'woocommerce-gateway-stripe' ),
			'i18n_required_fields' => __( 'Please fill in required checkout fields first', 'woocommerce-gateway-stripe' ),
		);

		// If we're on the pay page we need to pass stripe.js the address of the order.
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {
			$order_id = wc_get_order_id_by_order_key( urldecode( $_GET['key'] ) );
			$order    = wc_get_order( $order_id );

			$stripe_params['billing_first_name'] = WC_Stripe_Helper::is_pre_30() ? $order->billing_first_name : $order->get_billing_first_name();
			$stripe_params['billing_last_name']  = WC_Stripe_Helper::is_pre_30() ? $order->billing_last_name : $order->get_billing_last_name();
			$stripe_params['billing_address_1']  = WC_Stripe_Helper::is_pre_30() ? $order->billing_address_1 : $order->get_billing_address_1();
			$stripe_params['billing_address_2']  = WC_Stripe_Helper::is_pre_30() ? $order->billing_address_2 : $order->get_billing_address_2();
			$stripe_params['billing_state']      = WC_Stripe_Helper::is_pre_30() ? $order->billing_state : $order->get_billing_state();
			$stripe_params['billing_city']       = WC_Stripe_Helper::is_pre_30() ? $order->billing_city : $order->get_billing_city();
			$stripe_params['billing_postcode']   = WC_Stripe_Helper::is_pre_30() ? $order->billing_postcode : $order->get_billing_postcode();
			$stripe_params['billing_country']    = WC_Stripe_Helper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
		}

		$stripe_params['no_prepaid_card_msg']                     = __( 'Sorry, we\'re not accepting prepaid cards at this time. Your credit card has not been charge. Please try with alternative payment method.', 'woocommerce-gateway-stripe' );
		$stripe_params['no_bank_country_msg']                     = __( 'Please select a country for your bank.', 'woocommerce-gateway-stripe' );
		$stripe_params['no_sepa_owner_msg']                       = __( 'Please enter your IBAN account name.', 'woocommerce-gateway-stripe' );
		$stripe_params['no_sepa_iban_msg']                        = __( 'Please enter your IBAN account number.', 'woocommerce-gateway-stripe' );
		$stripe_params['allow_prepaid_card']                      = apply_filters( 'wc_stripe_allow_prepaid_card', true ) ? 'yes' : 'no';
		$stripe_params['inline_cc_form']                          = $this->inline_cc_form ? 'yes' : 'no';
		$stripe_params['stripe_checkout_require_billing_address'] = apply_filters( 'wc_stripe_checkout_require_billing_address', false ) ? 'yes' : 'no';
		$stripe_params['is_checkout']                             = ( is_checkout() && empty( $_GET['pay_for_order'] ) );
		$stripe_params['return_url']                              = $this->get_stripe_return_url();
		$stripe_params['ajaxurl']                                 = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$stripe_params['stripe_nonce']                            = wp_create_nonce( '_wc_stripe_nonce' );
		$stripe_params['statement_descriptor']                    = $this->statement_descriptor;
		$stripe_params['use_elements']                            = apply_filters( 'wc_stripe_use_elements_checkout_form', true ) ? 'yes' : 'no';
		$stripe_params['is_stripe_checkout']                      = $this->stripe_checkout ? 'yes' : 'no';
		$stripe_params['is_change_payment_page']                  = ( isset( $_GET['pay_for_order'] ) || isset( $_GET['change_payment_method'] ) ) ? 'yes' : 'no';
		$stripe_params['is_add_payment_method_page']              = is_add_payment_method_page() ? 'yes' : 'no';
		$stripe_params['elements_styling']                        = apply_filters( 'wc_stripe_elements_styling', false );
		$stripe_params['elements_classes']                        = apply_filters( 'wc_stripe_elements_classes', false );

		// merge localized messages to be use in JS
		$stripe_params = array_merge( $stripe_params, WC_Stripe_Helper::get_localized_messages() );

		wp_localize_script( 'woocommerce_stripe', 'wc_stripe_params', apply_filters( 'wc_stripe_params', $stripe_params ) );
		wp_localize_script( 'woocommerce_stripe_checkout', 'wc_stripe_params', apply_filters( 'wc_stripe_params', $stripe_params ) );

		if ( $this->stripe_checkout ) {
			wp_enqueue_script( 'stripe_checkout' );
		}

		$this->tokenization_script();
		wp_enqueue_script( 'woocommerce_stripe' );
	}

	/**
	 * Creates the 3DS source for charge.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order
	 * @param object $source_object
	 * @return mixed
	 */
	public function create_3ds_source( $order, $source_object ) {
		$currency                    = WC_Stripe_Helper::is_pre_30() ? $order->get_order_currency() : $order->get_currency();
		$order_id                    = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();
		$return_url                  = $this->get_stripe_return_url( $order );

		$post_data                   = array();
		$post_data['amount']         = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency );
		$post_data['currency']       = strtolower( $currency );
		$post_data['type']           = 'three_d_secure';
		$post_data['owner']          = $this->get_owner_details( $order );
		$post_data['three_d_secure'] = array( 'card' => $source_object->id );
		$post_data['redirect']       = array( 'return_url' => $return_url );

		WC_Stripe_Logger::log( 'Info: Begin creating 3DS source' );

		return WC_Stripe_API::request( $post_data, 'sources' );
	}

	/**
	 * Process the payment
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force save the payment source.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false ) {
		try {
			$order   = wc_get_order( $order_id );

			// This comes from the create account checkbox in the checkout page.
			$create_account = ! empty( $_POST['createaccount'] ) ? true : false;

			if ( $create_account ) {
				$new_customer_id     = WC_Stripe_Helper::is_pre_30() ? $order->customer_user : $order->get_customer_id();
				$new_stripe_customer = new WC_Stripe_Customer( $new_customer_id );
				$new_stripe_customer->create_customer();
			}

			$source_object = $this->create_source_object();

			$prepared_source = $this->prepare_source( $source_object, get_current_user_id(), $force_save_source );

			// Check if we don't allow prepaid credit cards.
			if ( ! apply_filters( 'wc_stripe_allow_prepaid_card', true ) ) {
				if ( $source_object && 'token' === $source_object->object && 'prepaid' === $source_object->card->funding ) {
					$localized_message = __( 'Sorry, we\'re not accepting prepaid cards at this time. Your credit card has not been charge. Please try with alternative payment method.', 'woocommerce-gateway-stripe' );
					throw new WC_Stripe_Exception( print_r( $source_object, true ), $localized_message );
				}
			}

			if ( empty( $prepared_source->source ) ) {
				$localized_message = __( 'Payment processing failed. Please retry.', 'woocommerce-gateway-stripe' );
				throw new WC_Stripe_Exception( print_r( $prepared_source, true ), $localized_message );
			}

			// Store source to order meta.
			$this->save_source( $order, $prepared_source );

			// Result from Stripe API request.
			$response = null;

			if ( $order->get_total() > 0 ) {
				// This will throw exception if not valid.
				$this->validate_minimum_order_amount( $order );

				/**
				 * Check if card 3DS is required or optional with 3DS setting.
				 * Will need to first create 3DS source and require redirection
				 * for customer to login to their credit card company.
				 * Note that if we need to save source, the original source must be first
				 * attached to a customer in Stripe before it can be charged.
				 */
				if ( ( $source_object && ! empty( $source_object->card ) ) && ( 'card' === $source_object->type && 'required' === $source_object->card->three_d_secure || ( $this->three_d_secure && 'optional' === $source_object->card->three_d_secure ) ) ) {

					$response = $this->create_3ds_source( $order, $source_object );

					if ( ! empty( $response->error ) ) {
						$localized_message = $response->error->message;

						$order->add_order_note( $localized_message );

						throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
					}

					// Update order meta with 3DS source.
					if ( WC_Stripe_Helper::is_pre_30() ) {
						update_post_meta( $order_id, '_stripe_source_id', $response->id );
					} else {
						$order->update_meta_data( '_stripe_source_id', $response->id );
						$order->save();
					}

					WC_Stripe_Logger::log( 'Info: Redirecting to 3DS...' );

					return array(
						'result'   => 'success',
						'redirect' => esc_url_raw( $response->redirect->url ),
					);
				}

				WC_Stripe_Logger::log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

				// Make the request.
				$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $prepared_source ) );

				if ( ! empty( $response->error ) ) {
					// If it is an API error such connection or server, let's retry.
					if ( 'api_connection_error' === $response->error->type || 'api_error' === $response->error->type ) {
						if ( $retry ) {
							sleep( 5 );
							return $this->process_payment( $order_id, false, $force_save_source );
						} else {
							$localized_message = 'API connection error and retries exhausted.';
							$order->add_order_note( $localized_message );
							throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
						}
					}

					// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
					if ( preg_match( '/No such customer/i', $response->error->message ) && $retry ) {
						delete_user_meta( WC_Stripe_Helper::is_pre_30() ? $order->customer_user : $order->get_customer_id(), '_stripe_customer_id' );

						return $this->process_payment( $order_id, false, $force_save_source );
					} elseif ( preg_match( '/No such token/i', $response->error->message ) && $prepared_source->token_id ) {
						// Source param wrong? The CARD may have been deleted on stripe's end. Remove token and show message.
						$wc_token = WC_Payment_Tokens::get( $prepared_source->token_id );
						$wc_token->delete();
						$localized_message = __( 'This card is no longer available and has been removed.', 'woocommerce-gateway-stripe' );
						$order->add_order_note( $localized_message );
						throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
					}

					$localized_messages = WC_Stripe_Helper::get_localized_messages();

					if ( 'card_error' === $response->error->type ) {
						$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
					} else {
						$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
					}

					$order->add_order_note( $localized_message );

					throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
				}

				do_action( 'wc_gateway_stripe_process_payment', $response, $order );

				// Process valid response.
				$this->process_response( $response, $order );
			} else {
				$order->payment_complete();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );
			}

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}
}
