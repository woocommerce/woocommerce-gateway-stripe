<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles SEPA payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 4.0.0
 */
class WC_Gateway_Stripe_Sepa extends WC_Stripe_Payment_Gateway {
	/**
	 * Notices (array)
	 * @var array
	 */
	public $notices = array();

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
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'stripe_sepa';
		$this->method_title         = __( 'Stripe SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		/* translators: link */
		$this->method_description   = sprintf( __( 'All other general Stripe settings can be adjusted <a href="%s">here</a>.', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) );
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

		$main_settings              = get_option( 'woocommerce_stripe_settings' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = ( ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'] ) ? true : false;
		$this->saved_cards          = ( ! empty( $main_settings['saved_cards'] ) && 'yes' === $main_settings['saved_cards'] ) ? true : false;
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_notices', array( $this, 'check_environment' ) );
		add_action( 'admin_head', array( $this, 'remove_admin_notice' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}

	/**
	 * Checks to make sure environment is setup correctly to use this payment method.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function check_environment() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$environment_warning = $this->get_environment_warning();

		if ( $environment_warning ) {
			$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
		}

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo '</p></div>';
		}
	}

	/**
	 * Checks the environment for compatibility problems. Returns a string with the first incompatibility
	 * found or false if the environment has no problems.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_environment_warning() {
		if ( 'yes' === $this->enabled && ! in_array( get_woocommerce_currency(), $this->get_supported_currency() ) ) {
			$message = __( 'SEPA is enabled - it requires store currency to be set to Euros.', 'woocommerce-gateway-stripe' );

			return $message;
		}

		return false;
	}

	/**
	 * Returns all supported currencies for this payment method.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return array
	 */
	public function get_supported_currency() {
		return apply_filters( 'wc_stripe_sepa_supported_currencies', array(
			'EUR',
		) );
	}

	/**
	 * Checks to see if all criteria is met before showing payment method.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return bool
	 */
	public function is_available() {
		if ( ! in_array( get_woocommerce_currency(), $this->get_supported_currency() ) ) {
			return false;
		}

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

		$icons_str .= $icons['sepa'];

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		wp_enqueue_style( 'stripe_paymentfonts' );
		wp_enqueue_script( 'woocommerce_stripe' );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require( WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-sepa-settings.php' );
	}

	/**
	 * Displays the mandate acceptance notice to customer.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function mandate_display() {
		/* translators: statement descriptor */
		printf( __( 'By providing your IBAN and confirming this payment, you are authorizing %s and Stripe, our payment service provider, to send instructions to your bank to debit your account and your bank to debit your account in accordance with those instructions. You are entitled to a refund from your bank under the terms and conditions of your agreement with your bank. A refund must be claimed within 8 weeks starting from the date on which your account was debited.', 'woocommerce-gateway-stripe' ), WC_Stripe_Helper::clean_statement_descriptor( $this->statement_descriptor ) );
	}

	/**
	 * Renders the Stripe elements form.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function form() {
		?>
		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="wc-payment-form">
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
			<p class="wc-stripe-sepa-mandate" style="margin-bottom:40px;"><?php $this->mandate_display(); ?></p>
			<p class="form-row form-row-wide validate-required">
				<label for="stripe-sepa-owner">
					<?php esc_html_e( 'IBAN Account Name.', 'woocommerce-gateway-stripe' ); ?>
				</label>
				<input id="stripe-sepa-owner" name="stripe_sepa_owner" value="" style="border:1px solid #ddd;margin:5px 0;padding:10px 5px;background-color:#fff;outline:0;" />
			</p>
			<p class="form-row form-row-wide validate-required">
				<label for="stripe-sepa-iban">
					<?php esc_html_e( 'IBAN Account Number.', 'woocommerce-gateway-stripe' ); ?>
				</label>
				<input id="stripe-sepa-iban" name="stripe_sepa_iban" value="" style="border:1px solid #ddd;margin:5px 0;padding:10px 5px;background-color:#fff;outline:0;" />
			</p>
			<!-- Used to display form errors -->
			<div class="stripe-source-errors" role="alert"></div>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$user                 = wp_get_current_user();
		$total                = WC()->cart->total;
		$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards;

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
			$total = $order->get_total();
		}

		if ( is_add_payment_method_page() ) {
			$pay_button_text = __( 'Add Payment', 'woocommerce-gateway-stripe' );
			$total        = '';
		} else {
			$pay_button_text = '';
		}

		echo '<div
			id="stripe-sepa_debit-payment-data"
			data-amount="' . esc_attr( WC_Stripe_Helper::get_stripe_amount( $total ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';

		if ( $this->description ) {
			if ( $this->testmode ) {
				$this->description .= ' ' . __( 'TEST MODE ENABLED. In test mode, you can use IBAN number DE89370400440532013000.', 'woocommerce-gateway-stripe' );
				$this->description  = trim( $this->description );
			}
			echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		$this->form();

		if ( apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
			$this->save_payment_method_checkbox();
		}

		echo '</div>';
	}

	/**
	 * Process the payment
	 *
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
			$order = wc_get_order( $order_id );

			// This comes from the create account checkbox in the checkout page.
			$create_account = ! empty( $_POST['createaccount'] ) ? true : false;

			if ( $create_account ) {
				$new_customer_id     = WC_Stripe_Helper::is_pre_30() ? $order->customer_user : $order->get_customer_id();
				$new_stripe_customer = new WC_Stripe_Customer( $new_customer_id );
				$new_stripe_customer->create_customer();
			}

			$prepared_source = $this->prepare_source( $this->create_source_object(), get_current_user_id(), $force_save_source );

			// Store source to order meta.
			$this->save_source( $order, $prepared_source );

			// Result from Stripe API request.
			$response = null;

			if ( $order->get_total() > 0 ) {
				// This will throw exception if not valid.
				$this->validate_minimum_order_amount( $order );

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
