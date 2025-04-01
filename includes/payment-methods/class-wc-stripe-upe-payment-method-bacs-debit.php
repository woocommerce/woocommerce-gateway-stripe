<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Bacs Direct Debit Payment Method class extending UPE base class.
 *
 * @since 9.3.0
 */
class WC_Stripe_UPE_Payment_Method_Bacs_Debit extends WC_Stripe_UPE_Payment_Method {
	use WC_Stripe_Subscriptions_Trait;

	/**
	 * The Stripe ID for the payment method.
	 */
	const STRIPE_ID = WC_Stripe_Payment_Methods::BACS_DEBIT;

	/**
	 * Constructor for Bacs Direct Debit payment method.
	 */
	public function __construct() {
		parent::__construct();

		$this->stripe_id                    = self::STRIPE_ID;
		$this->title                        = __( 'Bacs Direct Debit', 'woocommerce-gateway-stripe' );
		$this->is_reusable                  = true;
		$this->supported_currencies         = [ WC_Stripe_Currency_Code::POUND_STERLING ];
		$this->supported_countries          = [ 'GB' ];
		$this->accept_only_domestic_payment = true;
		$this->label                        = __( 'Bacs Direct Debit', 'woocommerce-gateway-stripe' );
		$this->description                  = __( 'Bacs Direct Debit enables customers in the UK to pay by providing their bank account details.', 'woocommerce-gateway-stripe' );
		$this->supports[]                   = 'tokenization';

		// Check if subscriptions are enabled and add support for them.
		$this->maybe_init_subscriptions();

		// Add support for pre-orders.
		$this->maybe_init_pre_orders();

		$this->maybe_hide_bacs_payment_gateway();

		// Add endpoints
		$this->add_bacs_ajax_endpoints();
	}

	/**
	 * Determines if the Stripe Account country supports Bacs Direct Debit.
	 *
	 * @return bool
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}

	/**
	 * Returns true if Bacs Direct Debit is available for processing payments.
	 *
	 * @return bool
	 */
	public function is_enabled_at_checkout( $order_id = null, $account_domestic_currency = null ) {
		if ( ! WC_Stripe_Feature_Flags::is_bacs_lpm_enabled() ) {
			return false;
		}

		return parent::is_enabled_at_checkout( $order_id, $account_domestic_currency );
	}

	/**
	 * Returns a string representing payment method type to query for when retrieving saved payment methods from Stripe.
	 *
	 * @return string The payment method type.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}

	/**
	 * Creates a Bacs Direct Debit payment token for the customer.
	 *
	 * @param int      $user_id        The customer ID the payment token is associated with.
	 * @param stdClass $payment_method The payment method object.
	 *
	 * @return WC_Payment_Token The payment token created.
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_Payment_Token_Bacs_Debit();
		$token->set_token( $payment_method->id );
		$token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ self::STRIPE_ID ] );
		$token->set_last4( $payment_method->bacs_debit->last4 );
		$token->set_fingerprint( $payment_method->bacs_debit->fingerprint );
		$token->set_payment_method_type( $this->get_id() );
		$token->set_user_id( $user_id );
		$token->save();
		return $token;
	}

	/**
	 * Conditionally hides the Bacs payment gateway for specific scenarios.
	 */
	public function maybe_hide_bacs_payment_gateway() {
		add_filter(
			'woocommerce_available_payment_gateways',
			function ( $available_gateways ) {
				if (
					$this->should_hide_bacs_for_pre_orders_charge_upon_release() ||
					$this->should_hide_bacs_on_add_payment_method_page()
				) {
					unset( $available_gateways['stripe_bacs_debit'] );
				}
				return $available_gateways;
			}
		);
	}

	/**
	 * Determines whether the Bacs payment gateway should be hidden on the "Add Payment Method" page.
	 *
	 * @return bool True if the Bacs payment gateway should be hidden, false otherwise.
	 */
	public function should_hide_bacs_on_add_payment_method_page() {
		if ( is_wc_endpoint_url( 'add-payment-method' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines whether the Bacs payment gateway should be hidden for pre-orders that are charged upon release.
	 *
	 * WooCommerce Pre-Orders allows merchants to choose when to charge customers.
	 * If a product is set to be charged upon release, Bacs can't be used for now as setup intents are not supported for Bacs.
	 *
	 * @return bool True if Bacs should be hidden, false otherwise.
	 */
	public function should_hide_bacs_for_pre_orders_charge_upon_release() {
		if ( is_checkout() && class_exists( 'WC_Pre_Orders_Cart' ) && WC_Pre_Orders_Cart::cart_contains_pre_order() ) {
			$cart = WC()->cart->get_cart();
			// Iteration is unnecessary since only one pre-order product can be in the cart.
			$product_id = reset( $cart )['product_id'];
			if ( class_exists( 'WC_Pre_Orders_Product' ) && WC_Pre_Orders_Product::product_is_charged_upon_release( $product_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Adds AJAX endpoints for BACS Direct Debit functionality.
	 *
	 * This method registers the AJAX actions required for creating a BACS Checkout Session,
	 * attaching a payment method to a customer, and retrieving payment method details.
	 *
	 * @return void
	 */
	public function add_bacs_ajax_endpoints() {
		add_action( 'wc_ajax_wc_stripe_create_bacs_checkout_session', [ $this, 'create_bacs_checkout_session_ajax' ] );
		add_action( 'wc_ajax_wc_stripe_attach_payment_method_to_customer', [ $this, 'attach_payment_method_to_customer_ajax' ] );
		add_action( 'wc_ajax_wc_stripe_get_payment_method_details', [ $this, 'get_payment_method_details_ajax' ] );
	}


	/**
	 * Handles the AJAX request to create a Bacs Direct Debit Checkout Session.
	 *
	 * This method sends a request to Stripe to create a Checkout Session for Bacs Direct Debit.
	 *
	 * @return void Outputs a JSON response indicating success or failure.
	 *              If an error occurs, a JSON response with an error message is sent.
	 */
	public function create_bacs_checkout_session_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_create_bacs_checkout_session_nonce', false, false );

			if ( ! $is_nonce_valid ) {
				throw new WC_Stripe_Exception( 'Invalid nonce', __( 'We couldn\'t create a Checkout Session for Bacs this time. Please refresh the page and try again.', 'woocommerce-gateway-stripe' ) );
			}

			$params   = [
				'success_url'            => wc_get_checkout_url() . '?checkout_session_id={CHECKOUT_SESSION_ID}',
				'payment_method_types[]' => WC_Stripe_Payment_Methods::BACS_DEBIT,
				'mode'                   => 'setup',
			];
			$response = WC_Stripe_API::request( $params, 'checkout/sessions', 'POST' );

			if ( is_wp_error( $response ) ) {
				throw new WC_Stripe_Exception( $response->get_error_message(), __( 'An unexpected error occurred while creating the Checkout Session.', 'woocommerce-gateway-stripe' ) );
			}

			if ( isset( $response->error ) ) {
				throw new WC_Stripe_Exception( $response->error->message, __( 'An unexpected error occurred while creating the Checkout Session.', 'woocommerce-gateway-stripe' ) );
			}

			// It might be a good idea to add the Setup Intent ID to save a request when associating the payment method with the customer.
			$redirect_url = $response->url;
			wp_send_json_success( [ 'checkout_session_url' => $redirect_url ] );
		} catch ( Error $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( $e->getMessage() );

			// Send a friendly error message to the frontend.
			wp_send_json_error( [ 'message' => $e->getLocalizedMessage() ] );
		}
	}

	/**
	 * Handles the AJAX request to retrieve payment method details for a Bacs Direct Debit.
	 *
	 * This method retrieves the `checkout_session_id` from the request, fetches the checkout session object then
	 * fetches the associated setup intent and payment method from Stripe, and returns the last 4 digits of the Bacs Direct Debit account.
	 *
	 * @return void Outputs a JSON response indicating success or failure.
	 */
	public function get_payment_method_details_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_get_payment_method_details_nonce', false, false );

			if ( ! $is_nonce_valid ) {
				throw new WC_Stripe_Exception( 'Invalid nonce', __( 'We couldn\'t get the payment method details this time. Please refresh the page and try again.', 'woocommerce-gateway-stripe' ) );
			}

			// Retrieve and sanitize the checkout session ID from the request.
			$checkout_session_id = filter_input( INPUT_GET, 'checkout_session_id', FILTER_SANITIZE_SPECIAL_CHARS );

			$friendly_error_message = __( 'An error occurred while getting the Bacs Direct Debit payment method details.', 'woocommerce-gateway-stripe' );

			// Get the Checkout Session data from Stripe.
			$response = WC_Stripe_API::get_checkout_session( $checkout_session_id );

			if ( is_wp_error( $response ) ) {
				throw new WC_Stripe_Exception( $response->get_error_message(), $friendly_error_message );
			}

			if ( isset( $response->error ) ) {
				throw new WC_Stripe_Exception( $response->error->message, $friendly_error_message );
			}

			$setup_intent_id = $response->setup_intent;

			// Get the Setup Intent data from Stripe.
			$response = WC_Stripe_API::get_setup_intent( $setup_intent_id );

			if ( is_wp_error( $response ) ) {
				throw new WC_Stripe_Exception( $response->get_error_message(), $friendly_error_message );
			}

			if ( isset( $response->error ) ) {
				throw new WC_Stripe_Exception( $response->error->message, $friendly_error_message );
			}

			$payment_method_id = $response->payment_method;

			// Get the Payment Method details from Stripe.
			$response = WC_Stripe_API::get_payment_method( $payment_method_id );

			if ( is_wp_error( $response ) ) {
				throw new WC_Stripe_Exception( $response->get_error_message(), $friendly_error_message );
			}

			if ( isset( $response->error ) ) {
				throw new WC_Stripe_Exception( $response->error->message, $friendly_error_message );
			}

			$last_4 = $response->bacs_debit->last4;

			// Return the last 4 digits in the response.
			wp_send_json_success( [ 'last4' => $last_4 ] );
		} catch ( Error $e ) {
			// Handle generic PHP errors.
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		} catch ( WC_Stripe_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getLocalizedMessage() ] );
		}
	}

	/**
	 * Handles the AJAX request to attach a payment method to a customer using a Checkout Session ID.
	 *
	 * This method retrieves the payment method details from Stripe using the Checkout Session ID
	 * and attaches the payment method to the currently logged-in customer.
	 *
	 * @return void Outputs a JSON response indicating success or failure.
	 *              If an error occurs, a JSON response with an error message is sent.
	 */
	public function attach_payment_method_to_customer_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_attach_payment_method_to_customer_nonce', false, false );

			if ( ! $is_nonce_valid ) {
				throw new WC_Stripe_Exception( 'Invalid nonce', __( 'Unable to attach the payment method to the customer at this time.', 'woocommerce-gateway-stripe' ) );
			}

			$checkout_session_id    = filter_input( INPUT_POST, 'checkout_session_id', FILTER_SANITIZE_SPECIAL_CHARS );
			$friendly_error_message = __( 'An error occurred while attaching the Bacs Direct Debit payment method to the customer.', 'woocommerce-gateway-stripe' );

			// Get the Checkout Session data.
			$response = WC_Stripe_API::request( [], "checkout/sessions/$checkout_session_id", 'GET' );
			if ( isset( $response->error ) ) {
				WC_Stripe_Logger::log( $response->error->message );
				throw new WC_Stripe_Exception( $response->error->message, $friendly_error_message );
			}
			$setup_intent_id = $response->setup_intent;

			// Get the paymen method ID via the setup intent.
			$response = WC_Stripe_API::request( [], "setup_intents/$setup_intent_id", 'GET' );
			if ( isset( $response->error ) ) {
				WC_Stripe_Logger::log( $response->error->message );
				throw new WC_Stripe_Exception( $response->error->message, $friendly_error_message );
			}
			$payment_method_id = $response->payment_method;

			// Attach payment method to the user.
			$user_id     = get_current_user_id();
			$stripe_user = new WC_Stripe_Customer( $user_id );
			$response    = WC_Stripe_API::request( [ 'customer' => $stripe_user->get_id() ], "payment_methods/$payment_method_id/attach", 'POST' );
			if ( isset( $response->error ) ) {
				WC_Stripe_Logger::log( $response->error->message );
				throw new WC_Stripe_Exception( $response->error->message, $friendly_error_message );
			}

			// It's necessary to save the payment method here in order to get the token ID.
			$pament_token = WC_Stripe_API::request( [], "payment_methods/$payment_method_id", 'GET' );
			$bacs_token   = $this->create_payment_token_for_user( $user_id, $pament_token );

			// Clear the cache so that in the next request, we can fetch the payment methods from Stripe to keep local saved payment methods in sync with Stripe.
			delete_transient( WC_Stripe_Customer::PAYMENT_METHODS_TRANSIENT_KEY . WC_Stripe_Payment_Methods::BACS_DEBIT . $stripe_user->get_id() );

			wp_send_json_success( [ 'bacs_token_id' => $bacs_token->get_id() ] );
		} catch ( Error $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( $e->getMessage() );

			// Send a friendly error message to the frontend.
			wp_send_json_error( [ 'message' => $e->getLocalizedMessage() ] );
		}
	}
}
