<?php
/**
 * Plugin Name: WC Stripe E2E Agentic Session Stub
 * Description: Serves a fixture agentic checkout session for E2E tests. The order-creation flow retrieves the completed session back from Stripe before mapping it to an order; a forged webhook has no session on Stripe's side, so this stub answers that one retrieval locally.
 *
 * @package WooCommerce_Stripe/Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'pre_http_request',
	function ( $preempt, $parsed_args, $url ) {
		// Guarded to the E2E environment: never stub outside it.
		if ( ! filter_var( getenv( 'E2E_TESTING' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return $preempt;
		}

		// Session ids are minted per test run (unique suffix) so a stale order
		// from a previous run can never satisfy the spec's assertions.
		if ( ! preg_match( '#api\.stripe\.com/v1/checkout/sessions/(cs_test_e2e_agentic_[A-Za-z0-9_]+)#', $url, $matches ) ) {
			return $preempt;
		}

		$session_id = $matches[1];

		// Amounts must stay consistent with the E2E seeding: the mapper
		// recalculates the order from catalog prices (24.99 USD product,
		// 10.00 USD flat rate) and refuses a session whose totals differ.
		$address = [
			'line1'       => '123 Test Street',
			'line2'       => '',
			'city'        => 'Testville',
			'state'       => 'CA',
			'postal_code' => '94000',
			'country'     => 'US',
		];

		$session = [
			'id'               => $session_id,
			'object'           => 'checkout.session',
			'livemode'         => false,
			'currency'         => 'usd',
			'amount_subtotal'  => 2499,
			'amount_total'     => 3499,
			'total_details'    => [
				'amount_tax'      => 0,
				'amount_discount' => 0,
				'amount_shipping' => 1000,
			],
			'payment_intent'   => [
				'id'            => 'pi_e2e_agentic_order',
				'object'        => 'payment_intent',
				'agent_details' => [
					'network_business_profile' => 'E2E Test Agent',
				],
			],
			'customer'         => 'cus_e2e_agentic',
			'customer_details' => [
				'email'   => 'agentic-e2e-buyer@example.com',
				'name'    => 'Agentic Buyer',
				'phone'   => '+15551234567',
				'address' => $address,
			],
			'shipping_details' => [
				'name'    => 'Agentic Buyer',
				'address' => $address,
			],
			'line_items'       => [
				'object' => 'list',
				'data'   => [
					[
						'id'              => 'li_e2e_order_1',
						'object'          => 'item',
						'quantity'        => 1,
						'amount_subtotal' => 2499,
						'amount_total'    => 2499,
						'amount_tax'      => 0,
						'amount_discount' => 0,
						'price'           => [
							'id'                 => 'price_e2e_agentic_1',
							'currency'           => 'usd',
							'unit_amount'        => 2499,
							'external_reference' => 'E2E-AGENTIC-1',
							'product'            => [
								'id'   => 'prod_e2e_agentic_1',
								'name' => 'Agentic E2E Product',
							],
						],
					],
				],
			],
			'shipping_cost'    => [
				'amount_total'  => 1000,
				'amount_tax'    => 0,
				'shipping_rate' => [
					'id'           => 'shr_e2e_agentic_flat',
					'display_name' => 'Flat rate',
					'metadata'     => [
						'wc_rate_id' => 'flat_rate:1',
					],
				],
			],
			'metadata'         => [],
		];

		return [
			'headers'  => [ 'content-type' => 'application/json' ],
			'body'     => wp_json_encode( $session ),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	},
	10,
	3
);
