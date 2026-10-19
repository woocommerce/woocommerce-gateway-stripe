<?php
/**
 * Plugin Name: WC Stripe E2E Mock Gateway
 * Description: Registers a non-Stripe gateway that supports saved payment methods and subscription payment-method changes, for specs that opt in by cookie.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	function () {
		if ( ! isset( $_COOKIE['wc_stripe_e2e_mock_gateway'] ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		final class WC_Gateway_E2E_Mock extends WC_Payment_Gateway {
			public function __construct() {
				$this->id           = 'e2e_mock';
				$this->method_title = 'E2E Mock Gateway';
				$this->title        = 'E2E Mock Gateway';
				$this->enabled      = 'yes';
				$this->has_fields   = false;
				// None of the core offline gateways declare these, and the add-payment-method
				// and change-payment-method pages only list gateways that do.
				$this->supports = [
					'products',
					'tokenization',
					'add_payment_method',
					'subscriptions',
					'subscription_cancellation',
					'subscription_payment_method_change',
					'subscription_payment_method_change_customer',
				];
			}

			public function is_available() {
				return true;
			}

			public function process_payment( $order_id ) {
				$order = wc_get_order( $order_id );
				$order->payment_complete( 'e2e-mock-' . time() );
				return [
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				];
			}

			public function add_payment_method() {
				$token = new WC_Payment_Token_CC();
				$token->set_token( 'e2e_mock_' . time() );
				$token->set_gateway_id( $this->id );
				$token->set_card_type( 'visa' );
				$token->set_last4( '0000' );
				$token->set_expiry_month( '12' );
				$token->set_expiry_year( (string) ( (int) gmdate( 'Y' ) + 2 ) );
				$token->set_user_id( get_current_user_id() );
				$token->save();
				return [
					'result'   => 'success',
					'redirect' => wc_get_account_endpoint_url( 'payment-methods' ),
				];
			}
		}

		add_filter(
			'woocommerce_payment_gateways',
			function ( $gateways ) {
				$gateways[] = 'WC_Gateway_E2E_Mock';
				return $gateways;
			}
		);
	},
	20
);
