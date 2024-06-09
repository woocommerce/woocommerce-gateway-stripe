<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Intent_Controller class.
 *
 * Handles in-checkout AJAX calls, related to Payment Intents.
 */
class WC_Stripe_Intent_Controller {
	/**
	 * Holds an instance of the gateway class.
	 *
	 * @since 4.2.0
	 * @var WC_Gateway_Stripe
	 */
	protected $gateway;

	/**
	 * Adds the necessary hooks.
	 *
	 * @since 4.2.0
	 */
	public function init_hooks() {
		add_action( 'wc_ajax_wc_stripe_verify_intent', [ $this, 'verify_intent' ] );
		add_action( 'wc_ajax_wc_stripe_create_setup_intent', [ $this, 'create_setup_intent' ] );

		add_action( 'wc_ajax_wc_stripe_create_and_confirm_setup_intent', [ $this, 'create_and_confirm_setup_intent_ajax' ] );

		add_action( 'wc_ajax_wc_stripe_create_payment_intent', [ $this, 'create_payment_intent_ajax' ] );
		add_action( 'wc_ajax_wc_stripe_update_payment_intent', [ $this, 'update_payment_intent_ajax' ] );
		add_action( 'wc_ajax_wc_stripe_init_setup_intent', [ $this, 'init_setup_intent_ajax' ] );

		add_action( 'wc_ajax_wc_stripe_update_order_status', [ $this, 'update_order_status_ajax' ] );
		add_action( 'wc_ajax_wc_stripe_update_failed_order', [ $this, 'update_failed_order_ajax' ] );
	}

	/**
	 * Returns an instantiated gateway.
	 *
	 * @since 4.2.0
	 * @return WC_Stripe_Payment_Gateway
	 */
	protected function get_gateway() {
		if ( ! isset( $this->gateway ) ) {
			$gateways      = WC()->payment_gateways()->payment_gateways();
			$this->gateway = $gateways[ WC_Gateway_Stripe::ID ];
		}

		return $this->gateway;
	}

	/**
	 * Returns an instantiated UPE gateway
	 *
	 * @since 5.6.0
	 * @throws WC_Stripe_Exception if UPE is not enabled.
	 * @return WC_Stripe_UPE_Payment_Gateway
	 */
	protected function get_upe_gateway() {
		$gateway = $this->get_gateway();
		if ( ! $gateway instanceof WC_Stripe_UPE_Payment_Gateway ) {
			WC_Stripe_Logger::log( 'Error instantiating the UPE Payment Gateway, UPE is not enabled.' );
			throw new WC_Stripe_Exception( __( "We're not able to process this payment.", 'woocommerce-gateway-stripe' ) );
		}
		return $gateway;
	}

	/**
	 * Loads the order from the current request.
	 *
	 * @since 4.2.0
	 * @throws WC_Stripe_Exception An exception if there is no order ID or the order does not exist.
	 * @return WC_Order
	 */
	protected function get_order_from_request() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'wc_stripe_confirm_pi' ) ) {
			throw new WC_Stripe_Exception( 'missing-nonce', __( 'CSRF verification failed.', 'woocommerce-gateway-stripe' ) );
		}

		// Load the order ID.
		$order_id = null;
		if ( isset( $_GET['order'] ) && absint( $_GET['order'] ) ) {
			$order_id = absint( $_GET['order'] );
		}

		// Retrieve the order.
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new WC_Stripe_Exception( 'missing-order', __( 'Missing order ID for payment confirmation', 'woocommerce-gateway-stripe' ) );
		}

		return $order;
	}

	/**
	 * Handles successful PaymentIntent authentications.
	 *
	 * @since 4.2.0
	 */
	public function verify_intent() {
		global $woocommerce;

		$order   = false;
		$gateway = $this->get_gateway();

		try {
			$order = $this->get_order_from_request();
		} catch ( WC_Stripe_Exception $e ) {
			/* translators: Error message text */
			$message = sprintf( __( 'Payment verification error: %s', 'woocommerce-gateway-stripe' ), $e->getLocalizedMessage() );
			wc_add_notice( esc_html( $message ), 'error' );

			$redirect_url = $woocommerce->cart->is_empty()
				? get_permalink( wc_get_page_id( 'shop' ) )
				: wc_get_checkout_url();

			$this->handle_error( $e, $redirect_url );
		}

		try {
			$gateway->verify_intent_after_checkout( $order );

			if ( isset( $_GET['save_payment_method'] ) && ! empty( $_GET['save_payment_method'] ) ) {
				$intent = $gateway->get_intent_from_order( $order );
				if ( isset( $intent->last_payment_error ) ) {
					// Currently, Stripe saves the payment method even if the authentication fails for 3DS cards.
					// Although, the card is not stored in DB we need to remove the source from the customer on Stripe
					// in order to keep the sources in sync with the data in DB.
					$customer = new WC_Stripe_Customer( wp_get_current_user()->ID );
					$customer->delete_source( $intent->last_payment_error->source->id );
				} else {
					$metadata = $intent->metadata;
					if ( isset( $metadata->save_payment_method ) && 'true' === $metadata->save_payment_method ) {
						$payment_method = WC_Stripe_Helper::get_payment_method_from_intent( $intent );
						$source_object  = WC_Stripe_API::get_payment_method(
							// The object on the intent may have been expanded so we need to check if it's just the ID or the full object.
							is_string( $payment_method ) ? $payment_method : $payment_method->id
						);
						$gateway->save_payment_method( $source_object );
					}
				}
			}

			if ( ! isset( $_GET['is_ajax'] ) ) {
				$redirect_url = isset( $_GET['redirect_to'] ) // wpcs: csrf ok.
					? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) // wpcs: csrf ok.
					: $gateway->get_return_url( $order );

				wp_safe_redirect( $redirect_url );
			}

			exit;
		} catch ( WC_Stripe_Exception $e ) {
			$this->handle_error( $e, $gateway->get_return_url( $order ) );
		}
	}

	/**
	 * Handles exceptions during intent verification.
	 *
	 * @since 4.2.0
	 * @param WC_Stripe_Exception $e           The exception that was thrown.
	 * @param string              $redirect_url An URL to use if a redirect is needed.
	 */
	protected function handle_error( $e, $redirect_url ) {
		// Log the exception before redirecting.
		$message = sprintf( 'PaymentIntent verification exception: %s', $e->getLocalizedMessage() );
		WC_Stripe_Logger::log( $message );

		// `is_ajax` is only used for PI error reporting, a response is not expected.
		if ( isset( $_GET['is_ajax'] ) ) {
			exit;
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Creates a Setup Intent through AJAX while adding cards.
	 */
	public function create_setup_intent() {
		if (
			! is_user_logged_in()
			|| ! isset( $_POST['stripe_source_id'] )
			|| ! isset( $_POST['nonce'] )
		) {
			return;
		}

		try {
			$source_id = wc_clean( wp_unslash( $_POST['stripe_source_id'] ) );

			// 1. Verify.
			if (
				! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wc_stripe_create_si' )
				|| ! ( 0 === strpos( $source_id, 'src_' ) || 0 === strpos( $source_id, 'pm_' ) )
			) {
				throw new Exception( __( 'Unable to verify your request. Please reload the page and try again.', 'woocommerce-gateway-stripe' ) );
			}

			// 2. Load the customer ID (and create a customer eventually).
			$customer = new WC_Stripe_Customer( wp_get_current_user()->ID );
			$customer->maybe_create_customer();

			// 3. Fetch the source object.
			$source_object = WC_Stripe_API::get_payment_method( $source_id );

			if ( ! empty( $source_object->error ) ) {
				throw new Exception( $source_object->error->message );
			}
			if ( is_wp_error( $source_object ) ) {
				throw new Exception( $source_object->get_error_message() );
			}

			// SEPA Direct Debit payments do not require any customer action after the source has been created.
			// Once the customer has provided their IBAN details and accepted the mandate, no further action is needed and the resulting source is directly chargeable.
			if ( 'sepa_debit' === $source_object->type ) {
				$response = [
					'status' => 'success',
				];
				echo wp_json_encode( $response );
				return;
			}

			// 4. Generate the setup intent
			$setup_intent = WC_Stripe_API::request(
				[
					'customer'       => $customer->get_id(),
					'confirm'        => 'true',
					'payment_method' => $source_id,
				],
				'setup_intents'
			);

			if ( ! empty( $setup_intent->error ) ) {
				$error_response_message = print_r( $setup_intent, true );
				WC_Stripe_Logger::log( 'Failed create Setup Intent while saving a card.' );
				WC_Stripe_Logger::log( "Response: $error_response_message" );
				throw new Exception( __( 'Your card could not be set up for future usage.', 'woocommerce-gateway-stripe' ) );
			}

			// 5. Respond.
			if ( 'requires_action' === $setup_intent->status ) {
				$response = [
					'status'        => 'requires_action',
					'client_secret' => $setup_intent->client_secret,
				];
			} elseif ( 'requires_payment_method' === $setup_intent->status
				|| 'requires_confirmation' === $setup_intent->status
				|| 'canceled' === $setup_intent->status ) {
				// These statuses should not be possible, as such we return an error.
				$response = [
					'status' => 'error',
					'error'  => [
						'type'    => 'setup_intent_error',
						'message' => __( 'Failed to save payment method.', 'woocommerce-gateway-stripe' ),
					],
				];
			} else {
				// This should only be reached when status is `processing` or `succeeded`, which are
				// the only statuses that we haven't explicitly handled.
				$response = [
					'status' => 'success',
				];
			}
		} catch ( Exception $e ) {
			$response = [
				'status' => 'error',
				'error'  => [
					'type'    => 'setup_intent_error',
					'message' => $e->getMessage(),
				],
			];
		}

		echo wp_json_encode( $response );
		exit;
	}

	/**
	 * Handle AJAX requests for creating a payment intent for Stripe UPE.
	 */
	public function create_payment_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_create_payment_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			// If paying from order, we need to get the total from the order instead of the cart.
			$order_id = isset( $_POST['stripe_order_id'] ) ? absint( $_POST['stripe_order_id'] ) : null;

			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order || ! $order->needs_payment() ) {
					throw new Exception( __( 'Unable to process your request. Please reload the page and try again.', 'woocommerce-gateway-stripe' ) );
				}
			}

			wp_send_json_success( $this->create_payment_intent( $order_id ), 200 );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Create payment intent error: ' . $e->getMessage() );
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
		}
	}

	/**
	 * Creates payment intent using current cart or order and store details.
	 *
	 * @param {int} $order_id The id of the order if intent created from Order.
	 * @throws Exception - If the create intent call returns with an error.
	 * @return array
	 */
	public function create_payment_intent( $order_id = null ) {
		$amount = WC()->cart->get_total( false );
		$order  = wc_get_order( $order_id );
		if ( is_a( $order, 'WC_Order' ) ) {
			$amount = $order->get_total();
		}

		$gateway                 = $this->get_upe_gateway();
		$enabled_payment_methods = $gateway->get_upe_enabled_at_checkout_payment_method_ids( $order_id );

		$currency       = get_woocommerce_currency();
		$capture        = empty( $gateway->get_option( 'capture' ) ) || $gateway->get_option( 'capture' ) === 'yes';
		$payment_intent = WC_Stripe_API::request(
			[
				'amount'               => WC_Stripe_Helper::get_stripe_amount( $amount, strtolower( $currency ) ),
				'currency'             => strtolower( $currency ),
				'payment_method_types' => $enabled_payment_methods,
				'capture_method'       => $capture ? 'automatic' : 'manual',
			],
			'payment_intents'
		);

		if ( ! empty( $payment_intent->error ) ) {
			throw new Exception( $payment_intent->error->message );
		}

		return [
			'id'            => $payment_intent->id,
			'client_secret' => $payment_intent->client_secret,
		];
	}

	/**
	 * Handle AJAX request for updating a payment intent for Stripe UPE.
	 *
	 * @since 5.6.0
	 */
	public function update_payment_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_update_payment_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			$order_id                  = isset( $_POST['stripe_order_id'] ) ? absint( $_POST['stripe_order_id'] ) : null;
			$payment_intent_id         = isset( $_POST['wc_payment_intent_id'] ) ? wc_clean( wp_unslash( $_POST['wc_payment_intent_id'] ) ) : '';
			$save_payment_method       = isset( $_POST['save_payment_method'] ) ? 'yes' === wc_clean( wp_unslash( $_POST['save_payment_method'] ) ) : false;
			$selected_upe_payment_type = ! empty( $_POST['selected_upe_payment_type'] ) ? wc_clean( wp_unslash( $_POST['selected_upe_payment_type'] ) ) : '';

			$order_from_payment = WC_Stripe_Helper::get_order_by_intent_id( $payment_intent_id );
			if ( ! $order_from_payment || $order_from_payment->get_id() !== $order_id ) {
				throw new Exception( __( 'Unable to verify your request. Please reload the page and try again.', 'woocommerce-gateway-stripe' ) );
			}

			wp_send_json_success( $this->update_payment_intent( $payment_intent_id, $order_id, $save_payment_method, $selected_upe_payment_type ), 200 );
		} catch ( Exception $e ) {
			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
		}
	}

	/**
	 * Updates payment intent to be able to save payment method.
	 *
	 * @since 5.6.0
	 *
	 * @param {string}  $payment_intent_id         The id of the payment intent to update.
	 * @param {int}     $order_id                  The id of the order if intent created from Order.
	 * @param {boolean} $save_payment_method       True if saving the payment method.
	 * @param {string}  $selected_upe_payment_type The name of the selected UPE payment type or empty string.
	 *
	 * @throws Exception  If the update intent call returns with an error.
	 * @return array|null An array with result of the update, or nothing
	 */
	public function update_payment_intent( $payment_intent_id = '', $order_id = null, $save_payment_method = false, $selected_upe_payment_type = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$gateway  = $this->get_upe_gateway();
		$amount   = $order->get_total();
		$currency = $order->get_currency();
		$customer = new WC_Stripe_Customer( wp_get_current_user()->ID );

		if ( $payment_intent_id ) {

			$request = [
				'amount'      => WC_Stripe_Helper::get_stripe_amount( $amount, strtolower( $currency ) ),
				'currency'    => strtolower( $currency ),
				'metadata'    => $gateway->get_metadata_from_order( $order ),
				/* translators: 1) blog name 2) order number */
				'description' => sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() ),
			];

			if ( '' !== $selected_upe_payment_type ) {
				// Only update the payment_method_types if we have a reference to the payment type the customer selected.
				$request['payment_method_types'] = [ $selected_upe_payment_type ];
				if (
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID === $selected_upe_payment_type &&
					in_array(
						WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
						$gateway->get_upe_enabled_payment_method_ids(),
						true
					)
				) {
					$request['payment_method_types'] = [
						WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
						WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
					];
				}
				$order->update_meta_data( '_stripe_upe_payment_type', $selected_upe_payment_type );
			}
			if ( ! empty( $customer ) && $customer->get_id() ) {
				$request['customer'] = $customer->get_id();
			}
			if ( $save_payment_method ) {
				$request['setup_future_usage'] = 'off_session';
			}

			$level3_data = $gateway->get_level3_data_from_order( $order );

			WC_Stripe_API::request_with_level3_data(
				$request,
				"payment_intents/{$payment_intent_id}",
				$level3_data,
				$order
			);

			$order->update_status( 'pending', __( 'Awaiting payment.', 'woocommerce-gateway-stripe' ) );
			$order->save();
			WC_Stripe_Helper::add_payment_intent_to_order( $payment_intent_id, $order );
		}

		return [
			'success' => true,
		];
	}

	/**
	 * Handle AJAX requests for creating a setup intent without confirmation for Stripe UPE.
	 *
	 * @since 5.6.0
	 * @version 5.6.0
	 */
	public function init_setup_intent_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_create_setup_intent_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to add this payment method. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			wp_send_json_success( $this->init_setup_intent(), 200 );
		} catch ( Exception $e ) {
			// Send back error, so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
		}
	}

	/**
	 * Creates a setup intent without confirmation.
	 *
	 * @since 5.6.0
	 * @version 5.6.0
	 * @return array
	 * @throws Exception If customer for the current user cannot be read/found.
	 */
	public function init_setup_intent() {
		// Determine the customer managing the payment methods, create one if we don't have one already.
		$user     = wp_get_current_user();
		$customer = new WC_Stripe_Customer( $user->ID );

		if ( ! $customer->get_id() ) {
			$customer_id = $customer->create_customer();
		} else {
			$customer_id = $customer->update_customer();
		}

		$gateway              = $this->get_upe_gateway();
		$payment_method_types = array_filter( $gateway->get_upe_enabled_payment_method_ids(), [ $gateway, 'is_enabled_for_saved_payments' ] );

		$setup_intent = WC_Stripe_API::request(
			[
				'customer'             => $customer_id,
				'confirm'              => 'false',
				'payment_method_types' => array_values( $payment_method_types ),
			],
			'setup_intents'
		);

		if ( ! empty( $setup_intent->error ) ) {
			throw new Exception( $setup_intent->error->message );
		}

		return [
			'id'            => $setup_intent->id,
			'client_secret' => $setup_intent->client_secret,
		];
	}

	/**
	 * Handle AJAX request after authenticating payment at checkout.
	 *
	 * This function is used to update the order status after the user has
	 * been asked to authenticate their payment.
	 *
	 * This function is used for both:
	 * - regular checkout
	 * - Pay for Order page (in theory).
	 *
	 * @throws WC_Stripe_Exception
	 */
	public function update_order_status_ajax() {
		$order = false;
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_update_order_status_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new WC_Stripe_Exception( 'missing-nonce', __( 'CSRF verification failed.', 'woocommerce-gateway-stripe' ) );
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : false;
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new WC_Stripe_Exception( 'order_not_found', __( "We're not able to process this payment. Please try again later.", 'woocommerce-gateway-stripe' ) );
			}

			$intent_id          = WC_Stripe_Helper::get_intent_id_from_order( $order );
			$intent_id_received = isset( $_POST['intent_id'] ) ? wc_clean( wp_unslash( $_POST['intent_id'] ) ) : null;
			if ( empty( $intent_id_received ) || $intent_id_received !== $intent_id ) {
				$note = sprintf(
					/* translators: %1: transaction ID of the payment or a translated string indicating an unknown ID. */
					__( 'A payment with ID %s was used in an attempt to pay for this order. This payment intent ID does not match any payments for this order, so it was ignored and the order was not updated.', 'woocommerce-gateway-stripe' ),
					$intent_id_received
				);
				$order->add_order_note( $note );
				throw new WC_Stripe_Exception( 'invalid_intent_id', __( "We're not able to process this payment. Please try again later.", 'woocommerce-gateway-stripe' ) );
			}
			$save_payment_method = isset( $_POST['payment_method_id'] ) && ! empty( wc_clean( wp_unslash( $_POST['payment_method_id'] ) ) );

			$gateway = $this->get_upe_gateway();
			$gateway->process_order_for_confirmed_intent( $order, $intent_id_received, $save_payment_method );
			wp_send_json_success(
				[
					'return_url' => $gateway->get_return_url( $order ),
				],
				200
			);
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			/* translators: error message */
			if ( $order ) {
				// Remove the awaiting confirmation order meta, don't save the order since it'll be saved in the next `update_status()` call.
				WC_Stripe_Helper::remove_payment_awaiting_action( $order, false );
				$order->update_status( 'failed' );
			}

			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => $e->getMessage(),
					],
				]
			);
		}
	}

	/**
	 * Handle AJAX request if error occurs while confirming intent.
	 * We will log the error and update the order.
	 *
	 * @throws WC_Stripe_Exception
	 */
	public function update_failed_order_ajax() {
		$order = false;
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_update_failed_order_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new WC_Stripe_Exception( 'missing-nonce', __( 'CSRF verification failed.', 'woocommerce-gateway-stripe' ) );
			}

			$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : null;
			$intent_id = isset( $_POST['intent_id'] ) ? wc_clean( wp_unslash( $_POST['intent_id'] ) ) : '';
			$order     = wc_get_order( $order_id );

			$order_from_payment = WC_Stripe_Helper::get_order_by_intent_id( $intent_id );
			if ( ! $order_from_payment || $order_from_payment->get_id() !== $order_id ) {
				wp_send_json_error( __( 'Unable to verify your request. Please reload the page and try again.', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! empty( $order_id ) && ! empty( $intent_id ) && is_object( $order ) ) {
				$payment_needed = 0 < $order->get_total();
				if ( $payment_needed ) {
					$intent = WC_Stripe_API::retrieve( "payment_intents/$intent_id" );
				} else {
					$intent = WC_Stripe_API::retrieve( "setup_intents/$intent_id" );
				}
				$error = $intent->last_payment_error || $intent->error;

				if ( ! empty( $error ) ) {
					WC_Stripe_Logger::log( 'Error when processing payment: ' . $error->message );
					throw new WC_Stripe_Exception( __( "We're not able to process this payment. Please try again later.", 'woocommerce-gateway-stripe' ) );
				}

				// Use the last charge within the intent to proceed.
				$gateway = $this->get_gateway();
				if ( isset( $intent->charges ) && ! empty( $intent->charges->data ) ) {
					$charge = end( $intent->charges->data );
					$gateway->process_response( $charge, $order );
				} else {
					// TODO: Add implementation for setup intents.
					$gateway->process_response( $intent, $order );
				}
				$gateway->save_intent_to_order( $order, $intent );
			}
		} catch ( WC_Stripe_Exception $e ) {
			// We are expecting an exception to be thrown here.
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			if ( $order ) {
				$order->update_status( 'failed' );
			}
		}

		wp_send_json_success();
	}

	/**
	 * Creates and confirm a payment intent with the given payment information.
	 * Used for dPE.
	 *
	 * @param array $payment_information The payment information needed for creating and confirming the intent.
	 *
	 * @throws WC_Stripe_Exception - If the create intent call returns with an error.
	 *
	 * @return stdClass
	 */
	public function create_and_confirm_payment_intent( $payment_information ) {
		// Throws a WC_Stripe_Exception if required information is missing.
		$required_params = [
			'amount',
			'capture_method',
			'currency',
			'customer',
			'level3',
			'metadata',
			'order',
			'payment_method',
			'save_payment_method_to_store',
			'shipping',
		];

		$non_empty_params = [ 'payment_method' ];

		$instance_params = [ 'order' => 'WC_Order' ];

		$this->validate_payment_intent_required_params( $required_params, $non_empty_params, $instance_params, $payment_information );

		$order                 = $payment_information['order'];
		$selected_payment_type = $payment_information['selected_payment_type'];
		$payment_method_types  = $payment_information['payment_method_types'];

		$request = $this->build_base_payment_intent_request_params( $payment_information );

		$request = array_merge(
			$request,
			[
				'amount'               => $payment_information['amount'],
				'confirm'              => 'true',
				'currency'             => $payment_information['currency'],
				'customer'             => $payment_information['customer'],
				/* translators: 1) blog name 2) order number */
				'description'          => sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() ),
				'metadata'             => $payment_information['metadata'],
				'payment_method_types' => $payment_method_types,
			]
		);

		if ( isset( $payment_information['statement_descriptor_suffix'] ) ) {
			$request['statement_descriptor_suffix'] = $payment_information['statement_descriptor_suffix'];
		}

		if ( isset( $payment_information['payment_method_options'] ) ) {
			$request['payment_method_options'] = $payment_information['payment_method_options'];
		}

		if ( $this->request_needs_redirection( $payment_method_types ) ) {
			$request['return_url'] = $payment_information['return_url'];
		}

		// For voucher payment methods type like Boleto & Oxxo, we shouldn't confirm the intent immediately as this is done on the front-end when displaying the voucher to the customer.
		// When the intent is confirmed, Stripe sends a webhook to the store which puts the order on-hold, which we only want to happen after successfully displaying the voucher.
		if ( $this->is_delayed_confirmation_required( $payment_method_types ) ) {
			$request['confirm'] = 'false';
		}

		// Run the necessary filter to make sure mandate information is added when it's required.
		$request = apply_filters(
			'wc_stripe_generate_create_intent_request',
			$request,
			$order,
			null // $prepared_source parameter is not necessary for adding mandate information.
		);

		$payment_intent = WC_Stripe_API::request_with_level3_data(
			$request,
			'payment_intents',
			$payment_information['level3'],
			$order
		);

		// Only update the payment_type if we have a reference to the payment type the customer selected.
		if ( '' !== $selected_payment_type ) {
			$order->update_meta_data( '_stripe_upe_payment_type', $selected_payment_type );
		}

		return $payment_intent;
	}

	/**
	 * Adds mandate data to the request.
	 *
	 * @param array $request The request to add mandate data to.
	 *
	 * @return array The request with mandate data added.
	*/
	private function add_mandate_data( $request ) {
		$request['mandate_data'] = [
			'customer_acceptance' => [
				'type'   => 'online',
				'online' => [
					'ip_address' => WC_Geolocation::get_ip_address(),
					'user_agent' => 'WooCommerce Stripe Gateway' . WC_STRIPE_VERSION . '; ' . get_bloginfo( 'url' ),
				],
			],
		];

		return $request;
	}

	/**
	 * Updates and confirm a payment intent with the given payment information.
	 * Used for dPE.
	 *
	 * @param object $payment_intent       The payment intent to update.
	 * @param array $payment_information The payment information needed for creating and confirming the intent.
	 *
	 * @throws WC_Stripe_Exception - If any of the required information is missing.
	 *
	 * @return array
	 */
	public function update_and_confirm_payment_intent( $payment_intent, $payment_information ) {
		// Throws a WC_Stripe_Exception if required information is missing.
		$required_params = [
			'capture_method',
			'payment_method',
			'shipping',
			'selected_payment_type',
			'payment_method_types',
			'level3',
			'order',
			'save_payment_method_to_store',
		];

		$instance_params = [ 'order' => 'WC_Order' ];

		$this->validate_payment_intent_required_params( $required_params, [], $instance_params, $payment_information );

		$request = $this->build_base_payment_intent_request_params( $payment_information );

		$order = $payment_information['order'];

		// Run the necessary filter to make sure mandate information is added when it's required.
		$request = apply_filters(
			'wc_stripe_generate_create_intent_request',
			$request,
			$order,
			null // $prepared_source parameter is not necessary for adding mandate information.
		);

		return WC_Stripe_API::request_with_level3_data(
			$request,
			"payment_intents/{$payment_intent->id}/confirm",
			$payment_information['level3'],
			$order
		);
	}

	/**
	 * Determines if the request contains all the required params for creating or updating a payment intent.
	 *
	 * @param array $required_params The required parameters for the payment intent.
	 * @param array $non_empty_params The parameters that must not contain an empty value.
	 * @param array $instance_params The parameters that must be of a specific type.
	 * @param array $payment_information The payment information to be validated.
	 * @return void
	 * @throws WC_Stripe_Exception
	 */
	private function validate_payment_intent_required_params( $required_params, $non_empty_params, $instance_params, $payment_information ) {
		$missing_params = [];
		foreach ( $required_params as $param ) {
			// Check if they're set. Some can be null.
			if ( ! array_key_exists( $param, $payment_information ) ) {
				$missing_params[] = $param;
			}
		}

		// Some params must not contain an empty value.
		foreach ( $non_empty_params as $param ) {
			if ( empty( $payment_information[ $param ] ) ) {
				$missing_params[] = $param;
			}
		}

		$shopper_error_message = __( 'There was a problem processing the payment.', 'woocommerce-gateway-stripe' );

		// Bail out if we're missing required information.
		if ( ! empty( $missing_params ) ) {
			throw new WC_Stripe_Exception(
				sprintf(
					'The information for creating and confirming the intent is missing the following data: %s.',
					implode( ', ', $missing_params )
				),
				$shopper_error_message
			);
		}

		// Check if the instance params are of the correct type.
		foreach ( $instance_params as $param => $type ) {
			if ( ! is_a( $payment_information[ $param ], $type ) ) {
				throw new WC_Stripe_Exception(
					sprintf(
						'The provided value for the "%s" parameter is not a %s.',
						$param,
						$type
					),
					__( 'Please reach out to us if the problem persists.', 'woocommerce-gateway-stripe' )
				);
			}
		}
	}

	/**
	 * Builds the base request parameters for creating/updating and confirming a payment intent.
	 *
	 * @param array $payment_information The payment information needed for creating/updating and confirming the intent.
	 *
	 * @return array The request parameters for creating/updating and confirming a payment intent.
	 */
	private function build_base_payment_intent_request_params( $payment_information ) {
		$selected_payment_type = $payment_information['selected_payment_type'];
		$payment_method_types  = $payment_information['payment_method_types'];
		$is_using_saved_token  = $payment_information['is_using_saved_payment_method'] ?? false;

		$request = [
			'capture_method' => $payment_information['capture_method'],
			'payment_method' => $payment_information['payment_method'],
			'shipping'       => $payment_information['shipping'],
		];

		// For Stripe Link & SEPA with deferred intent UPE, we must create mandate to acknowledge that terms have been shown to customer.
		if ( $this->is_mandate_data_required( $selected_payment_type, $is_using_saved_token ) ) {
			$request = $this->add_mandate_data( $request );
		}

		if ( $this->request_needs_redirection( $payment_method_types ) ) {
			$request['return_url'] = $payment_information['return_url'];
		}

		if ( $payment_information['save_payment_method_to_store'] ) {
			$request['setup_future_usage'] = 'off_session';
		}

		return $request;
	}

	/**
	 * Determines if mandate data is required for deferred intent UPE payment.
	 *
	 * A mandate must be provided before a deferred intent UPE payment can be processed.
	 * This applies to SEPA, Bancontact, iDeal, Sofort, Cash App and Link payment methods.
	 * https://stripe.com/docs/payments/finalize-payments-on-the-server
	 *
	 * @param string $selected_payment_type         The name of the selected UPE payment type.
	 * @param bool   $is_using_saved_payment_method Option. True if the customer is using a saved payment method, false otherwise.
	 *
	 * @return bool True if a mandate must be shown and acknowledged by customer before deferred intent UPE payment can be processed, false otherwise.
	 */
	public function is_mandate_data_required( $selected_payment_type, $is_using_saved_payment_method = false ) {

		if ( $is_using_saved_payment_method && 'cashapp' === $selected_payment_type ) {
			return false;
		}

		if ( in_array( $selected_payment_type, [ 'sepa_debit', 'bancontact', 'ideal', 'sofort', 'cashapp' ], true ) ) {
			return true;
		}

		return 'card' === $selected_payment_type && in_array( 'link', $this->get_upe_gateway()->get_upe_enabled_payment_method_ids(), true );
	}

	/**
	 * Creates and confirm a setup intent with the given payment method ID.
	 *
	 * @param array $payment_information The payment information to be used for the setup intent.
	 *
	 * @throws WC_Stripe_Exception If the create intent call returns with an error.
	 *
	 * @return array
	 */
	public function create_and_confirm_setup_intent( $payment_information ) {
		$request = [
			'payment_method'       => $payment_information['payment_method'],
			'payment_method_types' => [ $payment_information['selected_payment_type'] ],
			'customer'             => $payment_information['customer'],
			'confirm'              => 'true',
			'return_url'           => $payment_information['return_url'],
		];

		if ( isset( $payment_information['use_stripe_sdk'] ) ) {
			$request['use_stripe_sdk'] = $payment_information['use_stripe_sdk'];
		}

		// SEPA setup intents require mandate data.
		if ( $this->is_mandate_data_required( $payment_information['selected_payment_type'] ) ) {
			$request = $this->add_mandate_data( $request );
		}

		$setup_intent = WC_Stripe_API::request( $request, 'setup_intents' );

		if ( ! empty( $setup_intent->error ) ) {
			throw new WC_Stripe_Exception( print_r( $setup_intent->error, true ), $setup_intent->error->message );
		}

		return $setup_intent;
	}

	/**
	 * Handle AJAX requests for creating and confirming a setup intent.
	 *
	 * @throws Exception If the AJAX request is missing the required data or if there's an error creating and confirming the setup intent.
	 */
	public function create_and_confirm_setup_intent_ajax() {
		$setup_intent = null;

		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_create_and_confirm_setup_intent_nonce', false, false );

			if ( ! $is_nonce_valid ) {
				throw new WC_Stripe_Exception( 'Invalid nonce.', __( 'Unable to verify your request. Please refresh the page and try again.', 'woocommerce-gateway-stripe' ) );
			}

			$payment_method = sanitize_text_field( wp_unslash( $_POST['wc-stripe-payment-method'] ?? '' ) );
			$payment_type   = sanitize_text_field( wp_unslash( $_POST['wc-stripe-payment-type'] ?? 'card' ) );

			if ( ! $payment_method ) {
				throw new WC_Stripe_Exception( 'Payment method missing from request.', __( "We're not able to add this payment method. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			// Determine the customer managing the payment methods, create one if we don't have one already.
			$user     = wp_get_current_user();
			$customer = new WC_Stripe_Customer( $user->ID );

			// Manually create the payment information array to create & confirm the setup intent.
			$payment_information = [
				'payment_method'        => $payment_method,
				'customer'              => $customer->update_or_create_customer(),
				'selected_payment_type' => $payment_type,
				'return_url'            => wc_get_account_endpoint_url( 'payment-methods' ),
				'use_stripe_sdk'        => 'true', // We want the user to complete the next steps via the JS elements. ref https://docs.stripe.com/api/setup_intents/create#create_setup_intent-use_stripe_sdk
			];

			$setup_intent = $this->create_and_confirm_setup_intent( $payment_information );

			if ( empty( $setup_intent->status ) || ! in_array( $setup_intent->status, [ 'succeeded', 'processing', 'requires_action' ], true ) ) {
				throw new WC_Stripe_Exception( 'Response from Stripe: ' . print_r( $setup_intent, true ), __( 'There was an error adding this payment method. Please refresh the page and try again', 'woocommerce-gateway-stripe' ) );
			}

			wp_send_json_success(
				[
					'status'        => $setup_intent->status,
					'id'            => $setup_intent->id,
					'client_secret' => $setup_intent->client_secret,
					'next_action'   => $setup_intent->next_action,
					'payment_type'  => $payment_type,
					'return_url'    => rawurlencode( $payment_information['return_url'] ),
				],
				200
			);
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Failed to create and confirm setup intent. ' . $e->getMessage() );

			// Send back error so it can be displayed to the customer.
			wp_send_json_error(
				[
					'error' => [
						'message' => $e->getLocalizedMessage(),
					],
				]
			);
		}
	}

	/**
	 * Determines whether the request needs to redirect customer off-site to authorize payment.
	 * This is needed for the non-card UPE payment method (i.e. iDeal, giropay, etc.)
	 *
	 * @param array $payment_methods The list of payment methods used for the processing the payment.
	 *
	 * @return boolean True if the array consist of only one payment method and it isn't card, Boleto or Oxxo. False otherwise.
	 */
	private function request_needs_redirection( $payment_methods ) {
		return 1 === count( $payment_methods ) && ! in_array( $payment_methods[0], [ 'card', 'boleto', 'oxxo' ] );
	}

	/**
	 * Determines whether the intent needs to be confirmed later.
	 *
	 * Some payment methods such as Boleto and Oxxo require the payment to be confirmed later when
	 * displaying the voucher to the customer on the checkout or pay for order page.
	 *
	 * @param array $payment_methods The list of payment methods used for the processing the payment.
	 *
	 * @return boolean
	 */
	private function is_delayed_confirmation_required( $payment_methods ) {
		return in_array( 'boleto', $payment_methods, true ) || in_array( 'oxxo', $payment_methods, true );
	}

	/**
	 * Check for a UPE redirect payment method on order received page or setup intent on payment methods page.
	 *
	 * @deprecated 8.3.0
	 * @since 5.6.0
	 * @version 5.6.0
	 */
	public function maybe_process_upe_redirect() {
		wc_deprecated_function( __FUNCTION__, '8.3', 'WC_Stripe_Order_Handler::maybe_process_redirect_order' );

		$gateway = $this->get_gateway();
		if ( is_a( $gateway, 'WC_Stripe_UPE_Payment_Gateway' ) ) {
			$gateway->maybe_process_upe_redirect();
		}
	}
}
