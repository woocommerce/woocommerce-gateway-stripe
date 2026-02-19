<?php

namespace WooCommerce\Stripe\Tests;

use WC_Order;
use WC_Stripe_API;
use WC_Stripe_Database_Cache;
use WC_Stripe_Webhook_Handler;
use WP_UnitTestCase;

/**
 * Tests for agentic commerce checkout.session.completed webhook handling.
 *
 * @covers WC_Stripe_Webhook_Handler::process_checkout_session_completed
 */
class WC_Stripe_Webhook_Handler_Agentic_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Webhook_Handler
	 */
	private $handler;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->handler = new WC_Stripe_Webhook_Handler();

		// Clear any invalid API key cache left by other tests so that
		// WC_Stripe_API::retrieve() actually fires HTTP requests.
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
	}

	/**
	 * Tests that the webhook is ignored when the feature flag is disabled.
	 */
	public function test_process_checkout_session_completed_skips_when_disabled() {
		$notification = $this->build_notification( 'cs_test_disabled', true );

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_disabled', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );
	}

	/**
	 * Tests that non-agentic checkout sessions are ignored.
	 */
	public function test_process_checkout_session_completed_skips_non_agentic() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		$notification = $this->build_notification( 'cs_test_non_agentic', false );
		$mock_session = $this->build_checkout_session_response( 'cs_test_non_agentic', false );
		$http_filter  = $this->mock_stripe_api_response( $mock_session );

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_non_agentic', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );

		remove_filter( 'pre_http_request', $http_filter );
		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
	}

	/**
	 * Tests that a duplicate session returns the existing order without creating a new one.
	 */
	public function test_process_checkout_session_completed_returns_existing_for_duplicate() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		// Create an order that looks like it was already processed for this intent.
		$existing_order = wc_create_order();
		$existing_order->set_payment_method( 'stripe' );
		$existing_order->update_meta_data( '_stripe_intent_id', 'pi_test_cs_test_duplicate' );
		$existing_order->save();

		$notification                 = $this->build_notification( 'cs_test_duplicate', true );
		$mock_session                 = $this->build_checkout_session_response( 'cs_test_duplicate', true );
		$mock_session->payment_intent = (object) [
			'id'            => 'pi_test_cs_test_duplicate',
			'agent_details' => (object) [],
		];
		$http_filter                  = $this->mock_stripe_api_response( $mock_session );

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		// Verify no new orders were created.
		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_duplicate', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertCount( 1, $orders );
		$this->assertEquals( $existing_order->get_id(), $orders[0]->get_id() );

		$existing_order->delete( true );
		remove_filter( 'pre_http_request', $http_filter );
		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
	}

	/**
	 * Tests that the mapper is called and errors are handled gracefully.
	 *
	 * The order mapper will fail because the mock session references
	 * a non-existent product, and the handler should catch and log
	 * without crashing.
	 */
	public function test_process_checkout_session_completed_handles_mapper_failure() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		$failure_action_fired = false;
		add_action(
			'wc_stripe_agentic_order_creation_failed',
			function () use ( &$failure_action_fired ) {
				$failure_action_fired = true;
			}
		);

		$notification = $this->build_notification( 'cs_test_mapper_fail', true );
		$mock_session = $this->build_checkout_session_response( 'cs_test_mapper_fail', true );
		$http_filter  = $this->mock_stripe_api_response( $mock_session );

		// Should not throw — the handler catches the mapper's exception.
		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$this->assertTrue( $failure_action_fired );

		remove_filter( 'pre_http_request', $http_filter );
		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
	}

	/**
	 * Intercepts HTTP requests to the Stripe API and returns a mock response.
	 *
	 * @param object $response_body The mock response body object.
	 * @return callable The filter callback (for later removal).
	 */
	private function mock_stripe_api_response( $response_body ) {
		$callback = function ( $preempt, $args, $url ) use ( $response_body ) {
			if ( false !== strpos( $url, 'api.stripe.com' ) ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode( $response_body ),
				];
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $callback, 10, 3 );

		return $callback;
	}

	/**
	 * Builds a checkout.session.completed notification object (webhook payload).
	 *
	 * @param string $session_id The checkout session ID.
	 * @param bool   $agentic    Whether to mark the session as agentic.
	 * @return object
	 */
	private function build_notification( $session_id, $agentic ) {
		$session = [
			'id'             => $session_id,
			'payment_intent' => 'pi_test_' . $session_id,
			'payment_status' => 'paid',
			'currency'       => 'usd',
			'amount_total'   => 2000,
			'metadata'       => (object) [],
		];

		return (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) $session,
			],
		];
	}

	/**
	 * Builds a mock Stripe API response for a checkout session retrieval.
	 *
	 * @param string $session_id The checkout session ID.
	 * @param bool   $agentic    Whether to include agentic line items.
	 * @return object
	 */
	private function build_checkout_session_response( $session_id, $agentic ) {
		$line_items_data = [];

		if ( $agentic ) {
			// Line item with an external_reference pointing to a non-existent product.
			$line_items_data[] = (object) [
				'id'              => 'li_test_1',
				'description'     => 'Test Product',
				'quantity'        => 1,
				'amount_total'    => 2000,
				'amount_subtotal' => 2000,
				'amount_tax'      => 0,
				'price'           => (object) [
					'unit_amount'        => 2000,
					'external_reference' => '99999999',
					'currency'           => 'usd',
				],
			];
		} else {
			// Line item without external_reference (not agentic).
			$line_items_data[] = (object) [
				'id'              => 'li_test_1',
				'description'     => 'Test Product',
				'quantity'        => 1,
				'amount_total'    => 2000,
				'amount_subtotal' => 2000,
				'amount_tax'      => 0,
				'price'           => (object) [
					'unit_amount' => 2000,
					'currency'    => 'usd',
				],
			];
		}

		return (object) [
			'id'               => $session_id,
			'payment_intent'   => (object) [
				'id'            => 'pi_test_' . $session_id,
				'agent_details' => (object) [],
			],
			'customer'         => 'cus_test_789',
			'customer_email'   => 'test@example.com',
			'currency'         => 'usd',
			'amount_total'     => 2000,
			'amount_subtotal'  => 2000,
			'customer_details' => (object) [
				'email' => 'test@example.com',
				'name'  => 'John Smith',
				'phone' => '+1234567890',
			],
			'shipping_details' => null,
			'total_details'    => (object) [
				'amount_shipping' => 0,
				'amount_tax'      => 0,
				'amount_discount' => 0,
			],
			'line_items'       => (object) [
				'data' => $line_items_data,
			],
		];
	}
}
