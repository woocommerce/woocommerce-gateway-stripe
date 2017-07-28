<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles and process orders from asyncronous flows.
 *
 * @since 4.0.0
 */
class WC_Stripe_Order_Handler extends WC_Stripe_Payment_Gateway {
	private static $_this;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		self::$_this = $this;

		add_action( 'wp', array( $this, 'maybe_process_redirect_order' ) );
		add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
		add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
		add_action( 'wc_ajax_wc_stripe_validate_checkout', array( $this, 'validate_checkout' ) );
	}

	/**
	 * Public access to instance object.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_instance() {
		return self::$_this;
	}

	/**
	 * Processes payments.
	 * Note at this time the original source has already been
	 * saved to a customer card (if applicable) from process_payment.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function process_redirect_payment( $order_id, $retry = true, $source ) {
		try {
			$order = wc_get_order( $order_id );

			if ( 'processing' === $order->get_status() || 'completed' === $order->get_status() ) {
				return;
			}

			// Result from Stripe API request.
			$response = null;

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
				}

				WC_Stripe_Logger::log( "Info: (Redirect) Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

				// Prep source object.
				$source_object           = new stdClass();
				$source_object->token_id = '';
				$source_object->customer = $this->get_stripe_customer_id( $order );
				$source_object->source   = $source;

				/**
				 * First check if the source is chargeable at this time. If not,
				 * webhook will take care of it later.
				 */
				$source_info = WC_Stripe_API::retrieve( 'sources/' . $source );
				
				if ( 'chargeable' !== $source_info->status ) {
					return;
				}

				// Make the request.
				$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $source_object ) );

				if ( is_wp_error( $response ) ) {
					// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
					if ( 'customer' === $response->get_error_code() && $retry ) {
						delete_user_meta( $order->get_customer_id(), '_stripe_customer_id' );

						return $this->process_redirect_payment( $order_id, false, $source );
					} elseif ( preg_match( '/No such customer/i', $response->get_error_message() ) && $retry ) {
						delete_user_meta( $order->get_customer_id(), '_stripe_customer_id' );

						return $this->process_redirect_payment( $order_id, false, $source );
					// Source param wrong? The CARD may have been deleted on stripe's end. Remove token and show message.
					} elseif ( 'source' === $response->get_error_code() && $source->token_id ) {
						$token = WC_Payment_Tokens::get( $source->token_id );
						$token->delete();
						$message = __( 'This card is no longer available and has been removed.', 'woocommerce-gateway-stripe' );
						$order->add_order_note( $message );
					}

					$localized_messages = WC_Stripe_Helper::get_localized_messages();

					$message = isset( $localized_messages[ $response->get_error_code() ] ) ? $localized_messages[ $response->get_error_code() ] : $response->get_error_message();

					$order->add_order_note( $message );

					throw new Exception( $message );
				}

				$this->process_response( $response, $order );
				
			} else {
				$order->payment_complete();
			}

			do_action( 'wc_gateway_stripe_process_redirect_payment', $response, $order );

		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );
			}

			do_action( 'wc_gateway_stripe_process_redirect_payment_error', $e, $order );
		}
	}

	/**
	 * Processses the orders that are redirected.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function maybe_process_redirect_order() {
		if ( ! is_order_received_page() || empty( $_GET['client_secret'] ) || empty( $_GET['source'] ) ) {
			return;
		}

		$source   = wc_clean( $_GET['source'] );
		$order_id = wc_clean( $_GET['order_id'] );

		$this->process_redirect_payment( $order_id, true, $source );
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'stripe' === ( WC_Stripe_Helper::is_pre_30() ? $order->payment_method : $order->get_payment_method() ) ) {
			$charge   = get_post_meta( $order_id, '_stripe_charge_id', true );
			$captured = get_post_meta( $order_id, '_stripe_charge_captured', true );

			if ( $charge && 'no' === $captured ) {
				$result = WC_Stripe_API::request( array(
					'amount'   => WC_Stripe_Helper::get_stripe_amount( $order->get_total() ),
					'expand[]' => 'balance_transaction',
				), 'charges/' . $charge . '/capture' );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to capture charge!', 'woocommerce-gateway-stripe' ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $result->id ) );
					update_post_meta( $order_id, '_stripe_charge_captured', 'yes' );

					// Store other data such as fees
					update_post_meta( $order_id, 'Stripe Payment ID', $result->id );
					update_post_meta( $order_id, '_transaction_id', $result->id );

					if ( isset( $result->balance_transaction ) && isset( $result->balance_transaction->fee ) ) {
						// Fees and Net needs to both come from Stripe to be accurate as the returned
						// values are in the local currency of the Stripe account, not from WC.
						$fee = ! empty( $result->balance_transaction->fee ) ? WC_Stripe_Helper::format_balance_fee( $result->balance_transaction, 'fee' ) : 0;
						$net = ! empty( $result->balance_transaction->net ) ? WC_Stripe_Helper::format_balance_fee( $result->balance_transaction, 'net' ) : 0;
						update_post_meta( $order_id, 'Stripe Fee', $fee );
						update_post_meta( $order_id, 'Net Revenue From Stripe', $net );
					}
				}
			}
		}
	}

	/**
	 * Cancel pre-auth on refund/cancellation
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param  int $order_id
	 */
	public function cancel_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'stripe' === ( WC_Stripe_Helper::is_pre_30() ? $order->payment_method : $order->get_payment_method() ) ) {
			$charge   = get_post_meta( $order_id, '_stripe_charge_id', true );

			if ( $charge ) {
				$result = WC_Stripe_API::request( array(
					'amount' => WC_Stripe_Helper::get_stripe_amount( $order->get_total() ),
				), 'charges/' . $charge . '/refund' );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to refund charge!', 'woocommerce-gateway-stripe' ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'Stripe charge refunded (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $result->id ) );
					delete_post_meta( $order_id, '_stripe_charge_captured' );
					delete_post_meta( $order_id, '_stripe_charge_id' );
				}
			}
		}
	}

	/**
	 * Validates the checkout before submitting checkout form.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function validate_checkout() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_stripe_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
		}

		$errors = new WP_Error();
		parse_str( $_POST['required_fields'], $required_fields );
		parse_str( $_POST['all_fields'], $all_fields );
		$source_type = wc_clean( $_POST['source_type'] );
		$validate_shipping_fields = false;
		$create_account = false;

		/** 
		 * If ship to different address checkbox is checked then we need
		 * to validate shipping fields too.
		 */
		if ( isset( $all_fields['ship_to_different_address'] ) ) {
			$validate_shipping_fields = true;
		}

		// Check if createaccount is checked.
		if ( isset( $all_fields['createaccount'] ) ) {
			$create_account = true;
		}

		// Check if required fields are empty.
		foreach ( $required_fields as $field => $field_value ) {
			// Check for shipping field.
			if ( preg_match( '/^shipping_/', $field ) && ! $validate_shipping_fields ) {
				continue;
			}

			// Check create account name.
			if ( 'account_username' === $field && ! $create_account ) {
				continue;
			}

			// Check create account password.
			if ( 'account_password' === $field && ! $create_account ) {
				continue;
			}

			// Check if is SEPA.
			if ( empty( $field_value ) && 'stripe_sepa' !== $source_type ) {
				continue;
			}

			// Check if is Sofort.
			if ( '-1' === $field_value && 'stripe_sofort' === $source_type ) {
				$errors->add( 'validation', sprintf( __( '%s cannot be empty', 'woocommerce-gateway-stripe' ), ucfirst( str_replace( '_', ' ', $field ) ) ) );
			}

			if ( empty( $field_value ) ) {
				$errors->add( 'validation', sprintf( __( '%s cannot be empty', 'woocommerce-gateway-stripe' ), ucfirst( str_replace( '_', ' ', $field ) ) ) );
			}
		}

		// Check if email is valid format.
		if ( ! empty( $required_fields['billing_email'] ) && ! is_email( $required_fields['billing_email'] ) ) {
			$errors->add( 'validation', __( 'Email is not valid', 'woocommerce-gateway-stripe' ) );
		}

		if ( empty( $all_fields['woocommerce_checkout_update_totals'] ) && empty( $all_fields['terms'] ) && apply_filters( 'woocommerce_checkout_show_terms', wc_get_page_id( 'terms' ) > 0 ) ) {
			$errors->add( 'terms', __( 'You must accept our Terms &amp; Conditions.', 'woocommerce-gateway-stripe' ) );
		}

		if ( WC()->cart->needs_shipping() ) {
			$shipping_country = WC()->customer->get_shipping_country();

			if ( empty( $shipping_country ) ) {
				$errors->add( 'shipping', __( 'Please enter an address to continue.', 'woocommerce-gateway-stripe' ) );
			} elseif ( ! in_array( WC()->customer->get_shipping_country(), array_keys( WC()->countries->get_shipping_countries() ) ) ) {
				$errors->add( 'shipping', sprintf( __( 'Unfortunately <strong>we do not ship %s</strong>. Please enter an alternative shipping address.', 'woocommerce-gateway-stripe' ), WC()->countries->shipping_to_prefix() . ' ' . WC()->customer->get_shipping_country() ) );
			} else {
				$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

				foreach ( WC()->shipping->get_packages() as $i => $package ) {
					if ( ! isset( $chosen_shipping_methods[ $i ], $package['rates'][ $chosen_shipping_methods[ $i ] ] ) ) {
						$errors->add( 'shipping', __( 'No shipping method has been selected. Please double check your address, or contact us if you need any help.', 'woocommerce-gateway-stripe' ) );
					}
				}
			}
		}

		if ( WC()->cart->needs_payment() ) {
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

			if ( ! isset( $available_gateways[ $all_fields['payment_method'] ] ) ) {
				$errors->add( 'payment', __( 'Invalid payment method.', 'woocommerce-gateway-stripe' ) );
			} else {
				$available_gateways[ $all_fields['payment_method'] ]->validate_fields();
			}
		}

		if ( 0 === count( $errors->errors ) ) {
			wp_send_json( 'success' );
		} else {
			foreach ( $errors->get_error_messages() as $message ) {
				wc_add_notice( $message, 'error' );
			}

			$this->send_ajax_failure_response();
		}
	}

	/**
	 * Preps the error messages to be displayed.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function send_ajax_failure_response() {
		if ( is_ajax() ) {
			// only print notices if not reloading the checkout, otherwise they're lost in the page reload.
			if ( ! isset( WC()->session->reload_checkout ) ) {
				ob_start();
				wc_print_notices();
				$messages = ob_get_clean();
			}

			$response = array(
				'result'   => 'failure',
				'messages' => isset( $messages ) ? $messages : '',
				'refresh'  => isset( WC()->session->refresh_totals ),
				'reload'   => isset( WC()->session->reload_checkout ),
			);

			unset( WC()->session->refresh_totals, WC()->session->reload_checkout );

			wp_send_json( $response );
		}
	}
}

new WC_Stripe_Order_Handler();
