<?php

namespace WooCommerce\Stripe\Tests;

use WC_Order;
use WC_Stripe_API;
use WC_Stripe_Database_Cache;
use WC_Stripe_Webhook_Handler;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Product;
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
	 * @var callable|null HTTP mock filter callback to remove in tear_down.
	 */
	private $http_filter;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->handler     = new WC_Stripe_Webhook_Handler();
		$this->http_filter = null;

		// Clear any invalid API key cache left by other tests so that
		// WC_Stripe_API::retrieve() actually fires HTTP requests.
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );

		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
	}

	/**
	 * Tear down the test — always remove filters to prevent leaks.
	 */
	public function tear_down() {
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter );
		}
		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );

		parent::tear_down();
	}

	/**
	 * Tests that the webhook is ignored when the feature flag is disabled.
	 */
	public function test_process_checkout_session_completed_skips_when_disabled() {
		// Override the setUp-enabled flag for this specific test.
		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_false' );

		$notification = $this->build_notification( 'cs_test_disabled' );

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_disabled', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );

		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_false' );
	}

	/**
	 * Tests that non-agentic checkout sessions are ignored.
	 */
	public function test_process_checkout_session_completed_skips_non_agentic() {
		$notification = $this->build_notification( 'cs_test_non_agentic' );
		$mock_session = $this->build_checkout_session_response( 'cs_test_non_agentic', false );
		$this->mock_stripe_checkout_sessions_response( $mock_session );

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_non_agentic', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );
	}

	/**
	 * Tests that a duplicate session returns the existing order without creating a new one.
	 */
	public function test_process_checkout_session_completed_returns_existing_for_duplicate() {
		// Create an order that looks like it was already processed for this intent.
		$existing_order = wc_create_order();
		$existing_order->set_payment_method( 'stripe' );
		$existing_order->update_meta_data( '_stripe_intent_id', 'pi_test_cs_test_duplicate' );
		$existing_order->save();

		$notification                 = $this->build_notification( 'cs_test_duplicate' );
		$mock_session                 = $this->build_checkout_session_response( 'cs_test_duplicate', true );
		$mock_session->payment_intent = (object) [
			'id'            => 'pi_test_cs_test_duplicate',
			'agent_details' => (object) [],
		];
		$this->mock_stripe_checkout_sessions_response( $mock_session );

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
	}

	/**
	 * Tests that the mapper is called and errors are handled gracefully.
	 *
	 * The order mapper will fail because the mock session references
	 * a non-existent product, and the handler should catch and log
	 * without crashing.
	 */
	public function test_process_checkout_session_completed_handles_mapper_failure() {
		$failure_action_fired = false;
		add_action(
			'wc_stripe_agentic_order_creation_failed',
			function () use ( &$failure_action_fired ) {
				$failure_action_fired = true;
			}
		);

		$notification = $this->build_notification( 'cs_test_mapper_fail' );
		$mock_session = $this->build_checkout_session_response( 'cs_test_mapper_fail', true );
		$this->mock_stripe_checkout_sessions_response( $mock_session );

		// Should not throw — the handler catches the mapper's exception.
		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$this->assertTrue( $failure_action_fired );
	}

	/**
	 * Tests that a valid agentic session creates an order and fires the success action.
	 */
	public function test_process_checkout_session_completed_creates_order() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			[
				'regular_price' => '20.00',
				'price'         => '20.00',
			]
		);

		$success_action_fired = false;
		$created_order        = null;
		add_action(
			'wc_stripe_agentic_order_created',
			function ( $order ) use ( &$success_action_fired, &$created_order ) {
				$success_action_fired = true;
				$created_order        = $order;
			}
		);

		$notification = $this->build_notification( 'cs_test_happy' );
		$mock_session = $this->build_checkout_session_response( 'cs_test_happy', true, (string) $product->get_id() );
		$this->mock_stripe_checkout_sessions_response( $mock_session );

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$this->assertTrue( $success_action_fired );
		$this->assertInstanceOf( WC_Order::class, $created_order );
		$this->assertEquals( 'processing', $created_order->get_status() );
		$this->assertEquals( '20.00', $created_order->get_total() );
		$this->assertEquals( 'stripe', $created_order->get_payment_method() );
		$this->assertEquals( 'pi_test_cs_test_happy', $created_order->get_meta( '_stripe_intent_id', true ) );

		$created_order->delete( true );
		$product->delete( true );
	}

	/**
	 * Tests that a failed API fetch is handled gracefully without creating an order.
	 */
	public function test_process_checkout_session_completed_handles_api_fetch_failure() {
		$notification = $this->build_notification( 'cs_test_fetch_fail' );
		$this->mock_stripe_api_error();

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_fetch_fail', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );
	}

	/**
	 * Tests that a session with a missing payment intent ID is skipped without creating an order.
	 */
	public function test_process_checkout_session_completed_skips_when_payment_intent_missing() {
		$notification                 = $this->build_notification( 'cs_test_no_intent' );
		$mock_session                 = $this->build_checkout_session_response( 'cs_test_no_intent', true );
		$mock_session->payment_intent = (object) [
			'id'            => null,
			'agent_details' => (object) [],
		];
		$this->mock_stripe_checkout_sessions_response( $mock_session );

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_no_intent', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );
	}

	/**
	 * Tests that the wc_stripe_request_headers filter is always removed after processing,
	 * even when an error occurs before order creation.
	 */
	public function test_request_headers_filter_is_removed_after_processing_failure() {
		$this->assertFalse( has_filter( 'wc_stripe_request_headers' ) );

		$notification = $this->build_notification( 'cs_test_filter_cleanup' );
		$this->mock_stripe_api_error();

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$this->assertFalse( has_filter( 'wc_stripe_request_headers' ) );
	}

	/**
	 * Intercepts HTTP requests to the Stripe checkout sessions API and returns a mock response.
	 *
	 * @param object $response_body The mock response body object.
	 */
	private function mock_stripe_checkout_sessions_response( $response_body ) {
		$this->http_filter = function ( $preempt, $args, $url ) use ( $response_body ) {
			if ( str_starts_with( $url, 'https://api.stripe.com/v1/checkout/sessions/' ) ) {
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

		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}

	/**
	 * Builds a checkout.session.completed notification object (webhook payload).
	 *
	 * @param string $session_id The checkout session ID.
	 * @return object
	 */
	private function build_notification( $session_id ) {
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
	 * Intercepts HTTP requests to the Stripe API and returns an error response.
	 */
	private function mock_stripe_api_error() {
		$this->http_filter = function ( $preempt, $args, $url ) {
			if ( false !== strpos( $url, 'api.stripe.com' ) ) {
				return new \WP_Error( 'http_request_failed', 'Simulated Stripe API failure' );
			}
			return $preempt;
		};

		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}

	/**
	 * Builds a mock Stripe API response for a checkout session retrieval.
	 *
	 * @param string      $session_id The checkout session ID.
	 * @param bool        $agentic    Whether to include agentic line items.
	 * @param string|null $product_id Optional real WC product ID for the external_reference.
	 * @return object
	 */
	private function build_checkout_session_response( $session_id, $agentic, $product_id = null ) {
		$line_items_data = [];

		if ( $agentic ) {
			$line_items_data[] = (object) [
				'id'              => 'li_test_1',
				'description'     => 'Test Product',
				'quantity'        => 1,
				'amount_total'    => 2000,
				'amount_subtotal' => 2000,
				'amount_tax'      => 0,
				'price'           => (object) [
					'unit_amount'        => 2000,
					'external_reference' => $product_id ?? '99999999',
					'currency'           => 'usd',
				],
			];
		} else {
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

		$address = (object) [
			'city'        => 'San Francisco',
			'country'     => 'US',
			'line1'       => '123 Main St',
			'line2'       => '',
			'postal_code' => '94105',
			'state'       => 'CA',
		];

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
				'email'   => 'test@example.com',
				'name'    => 'John Smith',
				'phone'   => '+1234567890',
				'address' => $address,
			],
			'shipping_details' => (object) [
				'name'    => 'John Smith',
				'phone'   => '+1234567890',
				'address' => $address,
			],
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
