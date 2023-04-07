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

	const ID = 'stripe';

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
	 * Do we accept Payment Request?
	 *
	 * @var bool
	 */
	public $payment_request;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

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
		$this->id           = self::ID;
		$this->method_title = __( 'Stripe', 'woocommerce-gateway-stripe' );
		/* translators: 1) link to Stripe register page 2) link to Stripe api keys page */
		$this->method_description = __( 'Stripe works by adding payment fields on the checkout and then sending the details to Stripe for verification.', 'woocommerce-gateway-stripe' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// Check if pre-orders are enabled and add support for them.
		$this->maybe_init_pre_orders();

		// Get setting values.
		$this->title                = $this->get_validated_option( 'title' );
		$this->description          = $this->get_validated_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = 'yes' === $this->get_option( 'testmode' );
		$this->inline_cc_form       = 'yes' === $this->get_option( 'inline_cc_form' );
		$this->capture              = 'yes' === $this->get_option( 'capture', 'yes' );
		$this->statement_descriptor = WC_Stripe_Helper::clean_statement_descriptor( $this->get_validated_option( 'statement_descriptor' ) );
		$this->saved_cards          = 'yes' === $this->get_option( 'saved_cards' );
		$this->secret_key           = $this->testmode ? $this->get_validated_option( 'test_secret_key' ) : $this->get_validated_option( 'secret_key' );
		$this->publishable_key      = $this->testmode ? $this->get_validated_option( 'test_publishable_key' ) : $this->get_validated_option( 'publishable_key' );
		$this->payment_request      = 'yes' === $this->get_option( 'payment_request', 'yes' );

		WC_Stripe_API::set_secret_key( $this->secret_key );

		// Hooks.
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_fee' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_payout' ], 20 );
		add_action( 'woocommerce_customer_save_address', [ $this, 'show_update_card_notice' ], 10, 2 );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'prepare_order_pay_page' ] );
		add_action( 'woocommerce_account_view-order_endpoint', [ $this, 'check_intent_status_on_order_page' ], 1 );
		add_filter( 'woocommerce_payment_successful_result', [ $this, 'modify_successful_payment_result' ], 99999, 2 );
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );
		add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'get_checkout_payment_url' ], 10, 2 );
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, [ $this, 'settings_api_sanitized_fields' ] );
		add_filter( 'woocommerce_gateway_' . $this->id . '_settings_values', [ $this, 'update_onboarding_settings' ] );

		// Note: display error is in the parent class.
		add_action( 'admin_notices', [ $this, 'display_errors' ], 9999 );
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
	 * Adds a notice for customer when they update their billing address.
	 *
	 * @since 4.1.0
	 * @param int    $user_id      The ID of the current user.
	 * @param string $load_address The address to load.
	 */
	public function show_update_card_notice( $user_id, $load_address ) {
		if ( ! $this->saved_cards || ! WC_Stripe_Payment_Tokens::customer_has_saved_methods( $user_id ) || 'billing' !== $load_address ) {
			return;
		}

		/* translators: 1) Opening anchor tag 2) closing anchor tag */
		wc_add_notice( sprintf( __( 'If your billing address has been changed for saved payment methods, be sure to remove any %1$ssaved payment methods%2$s on file and re-add them.', 'woocommerce-gateway-stripe' ), '<a href="' . esc_url( wc_get_endpoint_url( 'payment-methods' ) ) . '" class="wc-stripe-update-card-notice" style="text-decoration:underline;">', '</a>' ), 'notice' );
	}

	/**
	 * Get_icon function.
	 *
	 * @since 1.0.0
	 * @version 5.6.2
	 * @return string|null
	 */
	public function get_icon() {
		return apply_filters( 'woocommerce_gateway_icon', null, $this->id );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = require dirname( __FILE__ ) . '/admin/stripe-settings.php';
		unset( $this->form_fields['title_upe'] );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		global $wp;
		$user                 = wp_get_current_user();
		$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards;
		$total                = WC()->cart->total;
		$user_email           = '';
		$description          = $this->get_description();
		$description          = ! empty( $description ) ? $description : '';
		$firstname            = '';
		$lastname             = '';

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) { // wpcs: csrf ok.
			$order      = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) ); // wpcs: csrf ok, sanitization ok.
			$total      = $order->get_total();
			$user_email = $order->get_billing_email();
		} else {
			if ( $user->ID ) {
				$user_email = get_user_meta( $user->ID, 'billing_email', true );
				$user_email = $user_email ? $user_email : $user->user_email;
			}
		}

		if ( is_add_payment_method_page() ) {
			$firstname = $user->user_firstname;
			$lastname  = $user->user_lastname;
		}

		ob_start();

		echo '<div
			id="stripe-payment-data"
			data-email="' . esc_attr( $user_email ) . '"
			data-full-name="' . esc_attr( $firstname . ' ' . $lastname ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '"
		>';

		if ( $this->testmode ) {
			/* translators: link to Stripe testing page */
			$description .= ' ' . sprintf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the <a href="%s" target="_blank">Testing Stripe documentation</a> for more card numbers.', 'woocommerce-gateway-stripe' ), 'https://stripe.com/docs/testing' );
		}

		$description = trim( $description );

		echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $description ) ), $this->id ); // wpcs: xss ok.

		if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		$this->elements_form();

		if ( apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) { // wpcs: csrf ok.

			$this->save_payment_method_checkbox();
		}

		do_action( 'wc_stripe_payment_fields_stripe', $this->id );

		echo '</div>';

		ob_end_flush();
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

				<div id="stripe-card-element" class="wc-stripe-elements-field">
				<!-- a Stripe Element will be inserted here. -->
				</div>
			<?php } else { ?>
				<div class="form-row form-row-wide">
					<label for="stripe-card-element"><?php esc_html_e( 'Card Number', 'woocommerce-gateway-stripe' ); ?> <span class="required">*</span></label>
					<div class="stripe-card-group">
						<div id="stripe-card-element" class="wc-stripe-elements-field">
						<!-- a Stripe Element will be inserted here. -->
						</div>

						<i class="stripe-credit-card-brand stripe-card-brand" alt="Credit Card"></i>
					</div>
				</div>

				<div class="form-row form-row-first">
					<label for="stripe-exp-element"><?php esc_html_e( 'Expiry Date', 'woocommerce-gateway-stripe' ); ?> <span class="required">*</span></label>

					<div id="stripe-exp-element" class="wc-stripe-elements-field">
					<!-- a Stripe Element will be inserted here. -->
					</div>
				</div>

				<div class="form-row form-row-last">
					<label for="stripe-cvc-element"><?php esc_html_e( 'Card Code (CVC)', 'woocommerce-gateway-stripe' ); ?> <span class="required">*</span></label>
				<div id="stripe-cvc-element" class="wc-stripe-elements-field">
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
	 * Override the parent admin_options method.
	 */
	public function admin_options() {
		do_action( 'wc_stripe_gateway_admin_options_wrapper', $this );
	}

	/**
	 * Completes an order without a positive value.
	 *
	 * @since 4.2.0
	 * @param WC_Order $order             The order to complete.
	 * @param WC_Order $prepared_source   Payment source and customer data.
	 * @param boolean  $force_save_source Whether the payment source must be saved, like when dealing with a Subscription setup.
	 * @return array                      Redirection data for `process_payment`.
	 */
	public function complete_free_order( $order, $prepared_source, $force_save_source ) {
		if ( $force_save_source ) {
			$intent_secret = $this->setup_intent( $order, $prepared_source );

			if ( ! empty( $intent_secret ) ) {
				// `get_return_url()` must be called immediately before returning a value.
				return [
					'result'              => 'success',
					'redirect'            => $this->get_return_url( $order ),
					'setup_intent_secret' => $intent_secret,
				];
			}
		}

		// Remove cart.
		WC()->cart->empty_cart();

		$order->payment_complete();

		// Return thank you page redirect.
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Process the payment
	 *
	 * @since 1.0.0
	 * @since 4.1.0 Add 4th parameter to track previous error.
	 * @version 5.6.0
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force save the payment source.
	 * @param mix  $previous_error Any error message from previous request.
	 * @param bool $use_order_source Whether to use the source, which should already be attached to the order.
	 *
	 * @throws Exception If payment will not be accepted.
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		try {
			$order = wc_get_order( $order_id );

			if ( $this->has_subscription( $order_id ) ) {
				$force_save_source = true;
			}

			if ( $this->maybe_change_subscription_payment_method( $order_id ) ) {
				return $this->process_change_subscription_payment_method( $order_id );
			}

			if ( $this->maybe_process_pre_orders( $order_id ) ) {
				return $this->process_pre_order( $order_id );
			}

			// Check whether there is an existing intent.
			$intent = $this->get_intent_from_order( $order );
			if ( isset( $intent->object ) && 'setup_intent' === $intent->object ) {
				$intent = false; // This function can only deal with *payment* intents
			}

			$stripe_customer_id = null;
			if ( $intent && ! empty( $intent->customer ) ) {
				$stripe_customer_id = $intent->customer;
			}

			// For some payments the source should already be present in the order.
			if ( $use_order_source ) {
				$prepared_source = $this->prepare_order_source( $order );
			} else {
				$prepared_source = $this->prepare_source( get_current_user_id(), $force_save_source, $stripe_customer_id );
			}

			$this->maybe_disallow_prepaid_card( $prepared_source->source_object );
			$this->check_source( $prepared_source );
			$this->save_source_to_order( $order, $prepared_source );

			if ( 0 >= $order->get_total() ) {
				return $this->complete_free_order( $order, $prepared_source, $force_save_source );
			}

			// This will throw exception if not valid.
			$this->validate_minimum_order_amount( $order );

			WC_Stripe_Logger::log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

			if ( $intent ) {
				$intent = $this->update_existing_intent( $intent, $order, $prepared_source );
			} else {
				$intent = $this->create_intent( $order, $prepared_source );
			}

			// Confirm the intent after locking the order to make sure webhooks will not interfere.
			if ( empty( $intent->error ) ) {
				$this->lock_order_payment( $order, $intent );
				$intent = $this->confirm_intent( $intent, $order, $prepared_source );
			}

			$force_save_source_value = apply_filters( 'wc_stripe_force_save_source', $force_save_source, $prepared_source->source );

			if ( ! empty( $intent->error ) ) {
				$this->maybe_remove_non_existent_customer( $intent->error, $order );

				// We want to retry.
				if ( $this->is_retryable_error( $intent->error ) ) {
					return $this->retry_after_error( $intent, $order, $retry, $force_save_source, $previous_error, $use_order_source );
				}

				$this->unlock_order_payment( $order );
				$this->throw_localized_message( $intent, $order );
			}

			if ( 'succeeded' === $intent->status && ! $this->is_using_saved_payment_method() && ( $this->save_payment_method_requested() || $force_save_source_value ) ) {
				$this->save_payment_method( $prepared_source->source_object );
			}

			if ( ! empty( $intent ) ) {
				// Use the last charge within the intent to proceed.
				$response = end( $intent->charges->data );

				// If the intent requires a 3DS flow, redirect to it.
				if ( 'requires_action' === $intent->status ) {
					$this->unlock_order_payment( $order );

					if ( is_wc_endpoint_url( 'order-pay' ) ) {
						$redirect_url = add_query_arg( 'wc-stripe-confirmation', 1, $order->get_checkout_payment_url( false ) );

						return [
							'result'   => 'success',
							'redirect' => $redirect_url,
						];
					} else {
						/**
						 * This URL contains only a hash, which will be sent to `checkout.js` where it will be set like this:
						 * `window.location = result.redirect`
						 * Once this redirect is sent to JS, the `onHashChange` function will execute `handleCardPayment`.
						 */

						return [
							'result'                => 'success',
							'redirect'              => $this->get_return_url( $order ),
							'payment_intent_secret' => $intent->client_secret,
							'save_payment_method'   => $this->save_payment_method_requested(),
						];
					}
				}
			}

			// Process valid response.
			$this->process_response( $response, $order );

			// Remove cart.
			if ( isset( WC()->cart ) ) {
				WC()->cart->empty_cart();
			}

			// Unlock the order.
			$this->unlock_order_payment( $order );

			// Return thank you page redirect.
			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];

		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			/* translators: error message */
			$order->update_status( 'failed' );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}

	/**
	 * Saves payment method
	 *
	 * @param object $source_object
	 * @throws WC_Stripe_Exception
	 */
	public function save_payment_method( $source_object ) {
		$user_id  = get_current_user_id();
		$customer = new WC_Stripe_Customer( $user_id );

		if ( ( $user_id && WC_Stripe_Helper::is_reusable_payment_method( $source_object ) ) ) {
			$response = $customer->add_source( $source_object->id );

			if ( ! empty( $response->error ) ) {
				throw new WC_Stripe_Exception( print_r( $response, true ), $this->get_localized_error_message_from_response( $response ) );
			}
			if ( is_wp_error( $response ) ) {
				throw new WC_Stripe_Exception( $response->get_error_message(), $response->get_error_message() );
			}
		}
	}

	/**
	 * Displays the Stripe fee
	 *
	 * @since 4.1.0
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function display_order_fee( $order_id ) {
		if ( apply_filters( 'wc_stripe_hide_display_order_fee', false, $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		$fee      = WC_Stripe_Helper::get_stripe_fee( $order );
		$currency = WC_Stripe_Helper::get_stripe_currency( $order );

		if ( ! $fee || ! $currency ) {
			return;
		}

		?>

		<tr>
			<td class="label stripe-fee">
				<?php echo wc_help_tip( __( 'This represents the fee Stripe collects for the transaction.', 'woocommerce-gateway-stripe' ) ); // wpcs: xss ok. ?>
				<?php esc_html_e( 'Stripe Fee:', 'woocommerce-gateway-stripe' ); ?>
			</td>
			<td width="1%"></td>
			<td class="total">
				-<?php echo wc_price( $fee, [ 'currency' => $currency ] ); // wpcs: xss ok. ?>
			</td>
		</tr>

		<?php
	}

	/**
	 * Displays the net total of the transaction without the charges of Stripe.
	 *
	 * @since 4.1.0
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function display_order_payout( $order_id ) {
		if ( apply_filters( 'wc_stripe_hide_display_order_payout', false, $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		$net      = WC_Stripe_Helper::get_stripe_net( $order );
		$currency = WC_Stripe_Helper::get_stripe_currency( $order );

		if ( ! $net || ! $currency ) {
			return;
		}

		?>

		<tr>
			<td class="label stripe-payout">
				<?php echo wc_help_tip( __( 'This represents the net total that will be credited to your Stripe bank account. This may be in the currency that is set in your Stripe account.', 'woocommerce-gateway-stripe' ) ); // wpcs: xss ok. ?>
				<?php esc_html_e( 'Stripe Payout:', 'woocommerce-gateway-stripe' ); ?>
			</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wc_price( $net, [ 'currency' => $currency ] ); // wpcs: xss ok. ?>
			</td>
		</tr>

		<?php
	}

	/**
	 * Retries the payment process once an error occured.
	 *
	 * @since 4.2.0
	 * @param object   $response          The response from the Stripe API.
	 * @param WC_Order $order             An order that is being paid for.
	 * @param bool     $retry             A flag that indicates whether another retry should be attempted.
	 * @param bool     $force_save_source Force save the payment source.
	 * @param mixed    $previous_error    Any error message from previous request.
	 * @param bool     $use_order_source  Whether to use the source, which should already be attached to the order.
	 * @throws WC_Stripe_Exception        If the payment is not accepted.
	 * @return array|void
	 */
	public function retry_after_error( $response, $order, $retry, $force_save_source, $previous_error, $use_order_source ) {
		if ( ! $retry ) {
			$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-gateway-stripe' );
			$order->add_order_note( $localized_message );
			throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.
		}

		// Don't do anymore retries after this.
		if ( 5 <= $this->retry_interval ) {
			return $this->process_payment( $order->get_id(), false, $force_save_source, $response->error, $previous_error );
		}

		sleep( $this->retry_interval );
		$this->retry_interval++;

		return $this->process_payment( $order->get_id(), true, $force_save_source, $response->error, $previous_error, $use_order_source );
	}

	/**
	 * Adds the necessary hooks to modify the "Pay for order" page in order to clean
	 * it up and prepare it for the Stripe PaymentIntents modal to confirm a payment.
	 *
	 * @since 4.2
	 * @param WC_Payment_Gateway[] $gateways A list of all available gateways.
	 * @return WC_Payment_Gateway[]          Either the same list or an empty one in the right conditions.
	 */
	public function prepare_order_pay_page( $gateways ) {
		if ( ! is_wc_endpoint_url( 'order-pay' ) || ! isset( $_GET['wc-stripe-confirmation'] ) ) { // wpcs: csrf ok.
			return $gateways;
		}

		try {
			$this->prepare_intent_for_order_pay_page();
		} catch ( WC_Stripe_Exception $e ) {
			// Just show the full order pay page if there was a problem preparing the Payment Intent
			return $gateways;
		}

		add_filter( 'woocommerce_checkout_show_terms', '__return_false' );
		add_filter( 'woocommerce_pay_order_button_html', '__return_false' );
		add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array' );
		add_filter( 'woocommerce_no_available_payment_methods_message', [ $this, 'change_no_available_methods_message' ] );
		add_action( 'woocommerce_pay_order_after_submit', [ $this, 'render_payment_intent_inputs' ] );

		return [];
	}

	/**
	 * Changes the text of the "No available methods" message to one that indicates
	 * the need for a PaymentIntent to be confirmed.
	 *
	 * @since 4.2
	 * @return string the new message.
	 */
	public function change_no_available_methods_message() {
		return wpautop( __( "Almost there!\n\nYour order has already been created, the only thing that still needs to be done is for you to authorize the payment with your bank.", 'woocommerce-gateway-stripe' ) );
	}

	/**
	 * Prepares the Payment Intent for it to be completed in the "Pay for Order" page.
	 *
	 * @param WC_Order|null $order Order object, or null to get the order from the "order-pay" URL parameter
	 *
	 * @throws WC_Stripe_Exception
	 * @since 4.3
	 */
	public function prepare_intent_for_order_pay_page( $order = null ) {
		if ( ! isset( $order ) || empty( $order ) ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		}
		$intent = $this->get_intent_from_order( $order );

		if ( ! $intent ) {
			throw new WC_Stripe_Exception(
				'Payment Intent not found',
				sprintf(
					/* translators: %s is the order Id */
					__( 'Payment Intent not found for order #%s', 'woocommerce-gateway-stripe' ),
					$order->get_id()
				)
			);
		}

		if ( 'requires_payment_method' === $intent->status && isset( $intent->last_payment_error )
			 && 'authentication_required' === $intent->last_payment_error->code ) {
			$level3_data = $this->get_level3_data_from_order( $order );
			$intent      = WC_Stripe_API::request_with_level3_data(
				[
					'payment_method' => $intent->last_payment_error->source->id,
				],
				'payment_intents/' . $intent->id . '/confirm',
				$level3_data,
				$order
			);

			if ( isset( $intent->error ) ) {
				throw new WC_Stripe_Exception( print_r( $intent, true ), $intent->error->message );
			}
		}

		$this->order_pay_intent = $intent;
	}

	/**
	 * Renders hidden inputs on the "Pay for Order" page in order to let Stripe handle PaymentIntents.
	 *
	 * @param WC_Order|null $order Order object, or null to get the order from the "order-pay" URL parameter
	 *
	 * @throws WC_Stripe_Exception
	 * @since 4.2
	 */
	public function render_payment_intent_inputs( $order = null ) {
		if ( ! isset( $order ) || empty( $order ) ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		}
		if ( ! isset( $this->order_pay_intent ) ) {
			$this->prepare_intent_for_order_pay_page( $order );
		}

		$verification_url = add_query_arg(
			[
				'order'            => $order->get_id(),
				'nonce'            => wp_create_nonce( 'wc_stripe_confirm_pi' ),
				'redirect_to'      => rawurlencode( $this->get_return_url( $order ) ),
				'is_pay_for_order' => true,
			],
			WC_AJAX::get_endpoint( 'wc_stripe_verify_intent' )
		);

		echo '<input type="hidden" id="stripe-intent-id" value="' . esc_attr( $this->order_pay_intent->client_secret ) . '" />';
		echo '<input type="hidden" id="stripe-intent-return" value="' . esc_attr( $verification_url ) . '" />';
	}

	/**
	 * Adds an error message wrapper to each saved method.
	 *
	 * @since 4.2.0
	 * @param WC_Payment_Token $token Payment Token.
	 * @return string                 Generated payment method HTML
	 */
	public function get_saved_payment_method_option_html( $token ) {
		$html          = parent::get_saved_payment_method_option_html( $token );
		$error_wrapper = '<div class="stripe-source-errors" role="alert"></div>';

		return preg_replace( '~</(\w+)>\s*$~', "$error_wrapper</$1>", $html );
	}

	/**
	 * Attempt to manually complete the payment process for orders, which are still pending
	 * before displaying the View Order page. This is useful in case webhooks have not been set up.
	 *
	 * @since 4.2.0
	 * @param int $order_id The ID that will be used for the thank you page.
	 */
	public function check_intent_status_on_order_page( $order_id ) {
		if ( empty( $order_id ) || absint( $order_id ) <= 0 ) {
			return;
		}

		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order ) {
			return;
		}

		$this->verify_intent_after_checkout( $order );
	}

	/**
	 * Attached to `woocommerce_payment_successful_result` with a late priority,
	 * this method will combine the "naturally" generated redirect URL from
	 * WooCommerce and a payment/setup intent secret into a hash, which contains both
	 * the secret, and a proper URL, which will confirm whether the intent succeeded.
	 *
	 * @since 4.2.0
	 * @param array $result   The result from `process_payment`.
	 * @param int   $order_id The ID of the order which is being paid for.
	 * @return array
	 */
	public function modify_successful_payment_result( $result, $order_id ) {
		if ( ! isset( $result['payment_intent_secret'] ) && ! isset( $result['setup_intent_secret'] ) ) {
			// Only redirects with intents need to be modified.
			return $result;
		}

		// Put the final thank you page redirect into the verification URL.
		$query_params = [
			'order'       => $order_id,
			'nonce'       => wp_create_nonce( 'wc_stripe_confirm_pi' ),
			'redirect_to' => rawurlencode( $result['redirect'] ),
		];

		$force_save_source_value = apply_filters( 'wc_stripe_force_save_source', false );

		if ( $this->save_payment_method_requested() || $force_save_source_value ) {
			$query_params['save_payment_method'] = true;
		}

		$verification_url = add_query_arg( $query_params, WC_AJAX::get_endpoint( 'wc_stripe_verify_intent' ) );

		if ( isset( $result['payment_intent_secret'] ) ) {
			$redirect = sprintf( '#confirm-pi-%s:%s', $result['payment_intent_secret'], rawurlencode( $verification_url ) );
		} elseif ( isset( $result['setup_intent_secret'] ) ) {
			$redirect = sprintf( '#confirm-si-%s:%s', $result['setup_intent_secret'], rawurlencode( $verification_url ) );
		}

		return [
			'result'   => 'success',
			'redirect' => $redirect,
		];
	}

	/**
	 * Proceed with current request using new login session (to ensure consistent nonce).
	 */
	public function set_cookie_on_current_request( $cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	}

	/**
	 * Executed between the "Checkout" and "Thank you" pages, this
	 * method updates orders based on the status of associated PaymentIntents.
	 *
	 * @since 4.2.0
	 * @param WC_Order $order The order which is in a transitional state.
	 */
	public function verify_intent_after_checkout( $order ) {
		$payment_method = $order->get_payment_method();
		if ( $payment_method !== $this->id ) {
			// If this is not the payment method, an intent would not be available.
			return;
		}

		$intent = $this->get_intent_from_order( $order );
		if ( ! $intent ) {
			// No intent, redirect to the order received page for further actions.
			return;
		}

		// A webhook might have modified or locked the order while the intent was retreived. This ensures we are reading the right status.
		clean_post_cache( $order->get_id() );
		$order = wc_get_order( $order->get_id() );

		if ( ! $order->has_status(
			apply_filters(
				'wc_stripe_allowed_payment_processing_statuses',
				[ 'pending', 'failed' ],
				$order
			)
		) ) {
			// If payment has already been completed, this function is redundant.
			return;
		}

		if ( $this->lock_order_payment( $order, $intent ) ) {
			return;
		}

		if ( 'setup_intent' === $intent->object && 'succeeded' === $intent->status ) {
			WC()->cart->empty_cart();
			if ( $this->has_pre_order( $order ) ) {
				$this->mark_order_as_pre_ordered( $order );
			} else {
				$order->payment_complete();
			}
		} elseif ( 'succeeded' === $intent->status || 'requires_capture' === $intent->status ) {
			// Proceed with the payment completion.
			$this->handle_intent_verification_success( $order, $intent );
		} elseif ( 'requires_payment_method' === $intent->status ) {
			// `requires_payment_method` means that SCA got denied for the current payment method.
			$this->handle_intent_verification_failure( $order, $intent );
		}

		$this->unlock_order_payment( $order );
	}

	/**
	 * Called after an intent verification succeeds, this allows
	 * specific APNs or children of this class to modify its behavior.
	 *
	 * @param WC_Order $order The order whose verification succeeded.
	 * @param stdClass $intent The Payment Intent object.
	 */
	protected function handle_intent_verification_success( $order, $intent ) {
		$this->process_response( end( $intent->charges->data ), $order );
		$this->maybe_process_subscription_early_renewal_success( $order, $intent );
	}

	/**
	 * Called after an intent verification fails, this allows
	 * specific APNs or children of this class to modify its behavior.
	 *
	 * @param WC_Order $order The order whose verification failed.
	 * @param stdClass $intent The Payment Intent object.
	 */
	protected function handle_intent_verification_failure( $order, $intent ) {
		$this->failed_sca_auth( $order, $intent );
		$this->maybe_process_subscription_early_renewal_failure( $order, $intent );
	}

	/**
	 * Checks if the payment intent associated with an order failed and records the event.
	 *
	 * @since 4.2.0
	 * @param WC_Order $order  The order which should be checked.
	 * @param object   $intent The intent, associated with the order.
	 */
	public function failed_sca_auth( $order, $intent ) {
		// If the order has already failed, do not repeat the same message.
		if ( $order->has_status( 'failed' ) ) {
			return;
		}

		// Load the right message and update the status.
		$status_message = isset( $intent->last_payment_error )
			/* translators: 1) The error message that was received from Stripe. */
			? sprintf( __( 'Stripe SCA authentication failed. Reason: %s', 'woocommerce-gateway-stripe' ), $intent->last_payment_error->message )
			: __( 'Stripe SCA authentication failed.', 'woocommerce-gateway-stripe' );
		$order->update_status( 'failed', $status_message );
	}

	/**
	 * Preserves the "wc-stripe-confirmation" URL parameter so the user can complete the SCA authentication after logging in.
	 *
	 * @param string   $pay_url Current computed checkout URL for the given order.
	 * @param WC_Order $order Order object.
	 *
	 * @return string Checkout URL for the given order.
	 */
	public function get_checkout_payment_url( $pay_url, $order ) {
		global $wp;
		if ( isset( $_GET['wc-stripe-confirmation'] ) && isset( $wp->query_vars['order-pay'] ) && $wp->query_vars['order-pay'] == $order->get_id() ) {
			$pay_url = add_query_arg( 'wc-stripe-confirmation', 1, $pay_url );
		}
		return $pay_url;
	}

	/**
	 * Checks whether new keys are being entered when saving options.
	 */
	public function process_admin_options() {
		// Load all old values before the new settings get saved.
		$old_publishable_key      = $this->get_option( 'publishable_key' );
		$old_secret_key           = $this->get_option( 'secret_key' );
		$old_test_publishable_key = $this->get_option( 'test_publishable_key' );
		$old_test_secret_key      = $this->get_option( 'test_secret_key' );

		parent::process_admin_options();

		// Load all old values after the new settings have been saved.
		$new_publishable_key      = $this->get_option( 'publishable_key' );
		$new_secret_key           = $this->get_option( 'secret_key' );
		$new_test_publishable_key = $this->get_option( 'test_publishable_key' );
		$new_test_secret_key      = $this->get_option( 'test_secret_key' );

		// Checks whether a value has transitioned from a non-empty value to a new one.
		$has_changed = function( $old_value, $new_value ) {
			return ! empty( $old_value ) && ( $old_value !== $new_value );
		};

		// Look for updates.
		if (
			$has_changed( $old_publishable_key, $new_publishable_key )
			|| $has_changed( $old_secret_key, $new_secret_key )
			|| $has_changed( $old_test_publishable_key, $new_test_publishable_key )
			|| $has_changed( $old_test_secret_key, $new_test_secret_key )
		) {
			update_option( 'wc_stripe_show_changed_keys_notice', 'yes' );
		}
	}

	public function validate_publishable_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pk_live_/', $value ) ) {
			return '';
		}
		return $value;
	}

	public function validate_secret_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^[rs]k_live_/', $value ) ) {
			return '';
		}
		return $value;
	}

	public function validate_test_publishable_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pk_test_/', $value ) ) {
			return '';
		}
		return $value;
	}

	public function validate_test_secret_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^[rs]k_test_/', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Ensures the statement descriptor about to be saved to options does not contain any invalid characters.
	 *
	 * @since 4.8.0
	 * @param $settings WC_Settings_API settings to be filtered
	 * @return Filtered settings
	 */
	public function settings_api_sanitized_fields( $settings ) {
		if ( is_array( $settings ) ) {
			if ( array_key_exists( 'statement_descriptor', $settings ) ) {
				$settings['statement_descriptor'] = WC_Stripe_Helper::clean_statement_descriptor( $settings['statement_descriptor'] );
			}
		}
		return $settings;
	}

	/**
	 * Checks whether the gateway is enabled.
	 *
	 * @return bool The result.
	 */
	public function is_enabled() {
		return 'yes' === $this->get_option( 'enabled' );
	}

	/**
	 * Disables gateway.
	 */
	public function disable() {
		$this->update_option( 'enabled', 'no' );
	}

	/**
	 * Enables gateway.
	 */
	public function enable() {
		$this->update_option( 'enabled', 'yes' );
	}

	/**
	 * Returns whether test_mode is active for the gateway.
	 *
	 * @return boolean Test mode enabled if true, disabled if false.
	 */
	public function is_in_test_mode() {
		return 'yes' === $this->get_option( 'testmode' );
	}

	/**
	 * Determines whether the "automatic" or "manual" capture setting is enabled.
	 *
	 * @return bool
	 */
	public function is_automatic_capture_enabled() {
		return empty( $this->get_option( 'capture' ) ) || $this->get_option( 'capture' ) === 'yes';
	}

	/**
	 * Validates statement descriptor value
	 *
	 * @param string $param Param name.
	 * @param string $value Posted Value.
	 * @param int    $max_length Maximum statement length.
	 *
	 * @return string                   Sanitized statement descriptor.
	 * @throws InvalidArgumentException When statement descriptor is invalid.
	 */
	public function validate_account_statement_descriptor_field( $param, $value, $max_length ) {
		// Since the value is escaped, and we are saving in a place that does not require escaping, apply stripslashes.
		$value = trim( stripslashes( $value ) );
		$field = __( 'customer bank statement', 'woocommerce-gateway-stripe' );

		if ( 'short_statement_descriptor' === $param ) {
			$field = __( 'shortened customer bank statement', 'woocommerce-gateway-stripe' );
		}

		// Validation can be done with a single regex but splitting into multiple for better readability.
		$valid_length   = '/^.{5,' . $max_length . '}$/';
		$has_one_letter = '/^.*[a-zA-Z]+/';
		$no_specials    = '/^[^*"\'<>]*$/';

		if (
			! preg_match( $valid_length, $value ) ||
			! preg_match( $has_one_letter, $value ) ||
			! preg_match( $no_specials, $value )
		) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %1 field name, %2 Number of the maximum characters allowed */
					__( 'The %1$s is invalid. The bank statement must contain only Latin characters, be between 5 and %2$u characters, contain at least one letter, and not contain any of the special characters: \' " * &lt; &gt;', 'woocommerce-gateway-stripe' ),
					$field,
					$max_length
				)
			);
		}

		return $value;
	}

	/**
	 * Get required setting keys for setup.
	 *
	 * @return array Array of setting keys used for setup.
	 */
	public function get_required_settings_keys() {
		return [ 'publishable_key', 'secret_key' ];
	}

	/**
	 * Get the connection URL.
	 *
	 * @return string Connection URL.
	 */
	public function get_connection_url( $return_url = '' ) {
		$api     = new WC_Stripe_Connect_API();
		$connect = new WC_Stripe_Connect( $api );

		$url = $connect->get_oauth_url( $return_url );

		return is_wp_error( $url ) ? null : $url;
	}

	/**
	 * Get help text to display during quick setup.
	 *
	 * @return string
	 */
	public function get_setup_help_text() {
		return sprintf(
			/* translators: %1$s Link to Stripe API details, %2$s Link to register a Stripe account */
			__( 'Your API details can be obtained from your <a href="%1$s">Stripe account</a>. Don’t have a Stripe account? <a href="%2$s">Create one.</a>', 'woocommerce-gateway-stripe' ),
			'https://dashboard.stripe.com/apikeys',
			'https://dashboard.stripe.com/register'
		);
	}

	/**
	 * Determine if the gateway still requires setup.
	 *
	 * @return bool
	 */
	public function needs_setup() {
		return ! $this->get_option( 'publishable_key' ) || ! $this->get_option( 'secret_key' );
	}

	/**
	 * Updates the test mode based on keys provided when setting up the gateway via onboarding.
	 *
	 * @return array
	 */
	public function update_onboarding_settings( $settings ) {
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) ) {
			return;
		}

		parse_str( wp_parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_QUERY ), $queries ); // phpcs:ignore sanitization ok.

		// Determine if merchant is onboarding (page='wc-admin' and task='payments').
		if (
			! isset( $queries ) ||
			! isset( $queries['page'] ) ||
			! isset( $queries['task'] ) ||
			'wc-admin' !== $queries['page'] ||
			'payments' !== $queries['task']
		) {
			return;
		}

		if ( ! empty( $settings['publishable_key'] ) && ! empty( $settings['secret_key'] ) ) {
			if ( strpos( $settings['publishable_key'], 'pk_test_' ) === 0 || strpos( $settings['secret_key'], 'sk_test_' ) === 0 ) {
				$settings['test_publishable_key'] = $settings['publishable_key'];
				$settings['test_secret_key']      = $settings['secret_key'];
				unset( $settings['publishable_key'] );
				unset( $settings['secret_key'] );
				$settings['testmode'] = 'yes';
			} else {
				$settings['testmode'] = 'no';
			}
		}

		return $settings;
	}

	/**
	 * Validates a field value before updating.
	 *
	 * @param string $field_key the form field key.
	 * @param string $field_value the form field value.
	 *
	 * @return bool True if the value was updated, false otherwise.
	 */
	public function update_validated_option( $field_key, $field_value ) {
		$validated_field_value = $this->validate_field( $field_key, $field_value );
		return $this->update_option( $field_key, $validated_field_value );
	}

	/**
	 * Retrieves validated field value.
	 *
	 * @param string $field_key the form field key.
	 * @param mixed $empty_value fallback value.
	 *
	 * @return string validated field value.
	 */
	public function get_validated_option( $field_key, $empty_value = null ) {
		$value = parent::get_option( $field_key, $empty_value );
		return $this->validate_field( $field_key, $value );
	}

	/**
	 * Ensures validated field values.
	 *
	 * @param string $field_key the form field key.
	 * @param string $field_value the form field value.
	 *
	 * @return string validated field value.
	 */
	private function validate_field( $field_key, $field_value ) {
		if ( is_callable( [ $this, 'validate_' . $field_key . '_field' ] ) ) {
			return $this->{'validate_' . $field_key . '_field'}( $field_key, $field_value );
		}

		if ( empty( $this->form_fields ) ) {
			$this->init_form_fields();
		}
		if ( key_exists( $field_key, $this->form_fields ) ) {
			$field_type = $this->form_fields[ $field_key ]['type'];

			if ( is_callable( [ $this, 'validate_' . $field_type . '_field' ] ) ) {
				return $this->{'validate_' . $field_type . '_field'}( $field_key, $field_value );
			}
		}

		return $this->validate_text_field( $field_key, $field_value );
	}
}
