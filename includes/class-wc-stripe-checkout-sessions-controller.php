<?php

use Automattic\WooCommerce\Enums\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Checkout_Sessions_Controller {
	protected $gateway;

	public function init_hooks() {
		add_action( 'wc_ajax_wc_stripe_create_checkout_session', [ $this, 'create_checkout_session_ajax' ] );

		add_action( 'wc_ajax_wc_stripe_update_order_status', [ $this, 'update_order_status_ajax' ] );
		add_action( 'wc_ajax_wc_stripe_update_failed_order', [ $this, 'update_failed_order_ajax' ] );
	}

	protected function get_gateway() {
		if ( ! isset( $this->gateway ) ) {
			$gateways      = WC()->payment_gateways()->payment_gateways();
			$this->gateway = $gateways[ WC_Stripe_UPE_Payment_Gateway::ID ];
		}

		return $this->gateway;
	}

	protected function get_upe_gateway() {
		$gateway = $this->get_gateway();
		if ( ! $gateway instanceof WC_Stripe_UPE_Payment_Gateway ) {
			WC_Stripe_Logger::log( 'Error instantiating the UPE Payment Gateway, UPE is not enabled.' );
			throw new WC_Stripe_Exception( __( "We're not able to process this payment.", 'woocommerce-gateway-stripe' ) );
		}
		return $gateway;
	}

	public function create_checkout_session_ajax() {
		try {
			$is_nonce_valid = check_ajax_referer( 'wc_stripe_create_checkout_session_nonce', false, false );
			if ( ! $is_nonce_valid ) {
				throw new Exception( __( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-gateway-stripe' ) );
			}

			// If paying from order, we need to get the total from the order instead of the cart.
			$payment_method_type = isset( $_POST['payment_method_type'] ) ? wc_clean( wp_unslash( $_POST['payment_method_type'] ) ) : '';

			wp_send_json_success( $this->create_checkout_session( $payment_method_type ), 200 );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Create checkout session error: ' . $e->getMessage() );
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

	public function create_checkout_session( $payment_method_type = null ) {
		$enabled_payment_methods = $payment_method_type ? [ $payment_method_type ] : [];

		WC()->cart->calculate_totals();

		$user     = wp_get_current_user();
		$customer = new WC_Stripe_Customer( $user->ID );
		$customer->update_or_create_customer();

		$currency   = get_woocommerce_currency();
		$line_items = [];
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_name   = $cart_item['data']->get_name();
			$line_items[] = [
				'price_data' => [
					'currency' => strtolower( $currency ),
					'product_data' => [
						'name' => $product_name,
					],
					'unit_amount' => WC_Stripe_Helper::get_stripe_amount( $cart_item['line_subtotal'] / $cart_item['quantity'], $currency ),
				],
				'quantity' => $cart_item['quantity'],
			];
		}

		$request = [
			'ui_mode'              => 'custom',
			'customer'             => $customer->get_id(),
			'line_items'           => $line_items,
			'payment_method_types' => $enabled_payment_methods,
			'payment_intent_data'  => [],
			'mode'                 => 'payment',
			'return_url'           => wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() ),
		];

		$checkout_session = WC_Stripe_API::request( $request, 'checkout/sessions' );

		if ( ! empty( $checkout_session->error ) ) {
			throw new Exception( $checkout_session->error->message );
		}

		wp_send_json( [ 'client_secret' => $checkout_session->client_secret ] );
	}

	public function update_order_status_ajax() {
		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$order        = false;

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

			$intent_id          = $order_helper->get_intent_id_from_order( $order );
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
				$order_helper->remove_payment_awaiting_action( $order, false );
				$order->update_status( OrderStatus::FAILED );
			}

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
				$charge  = $gateway->get_latest_charge_from_intent( $intent );
				if ( ! empty( $charge ) ) {
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
				$order->update_status( OrderStatus::FAILED );
			}
		}

		wp_send_json_success();
	}
}
