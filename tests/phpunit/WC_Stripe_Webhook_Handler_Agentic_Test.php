<?php

namespace WooCommerce\Stripe\Tests;

use Exception;
use WC_Order;
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

	public function set_up() {
		parent::set_up();
		$this->handler = new WC_Stripe_Webhook_Handler();
	}

	public function tear_down() {
		remove_all_filters( 'wc_stripe_is_agentic_commerce_enabled' );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Tests that the webhook is ignored when the feature flag is disabled.
	 */
	public function test_skips_when_feature_flag_disabled() {
		// Feature flag is off by default.
		$notification = $this->build_notification( 'cs_test_disabled' );

		$this->handler->process_checkout_session_completed( $notification );

		// resolved_order should remain null — nothing processed.
		$resolved = $this->get_resolved_order();
		$this->assertNull( $resolved );
	}

	/**
	 * Tests that concurrent duplicate webhooks are blocked by the lock.
	 */
	public function test_concurrent_duplicate_blocked_by_lock() {
		$this->enable_feature_flag();

		$session_id = 'cs_test_locked';
		$lock_key   = 'agentic_lock_' . $session_id;

		// Simulate an in-progress lock.
		WC_Stripe_Database_Cache::set( $lock_key, time(), 5 * MINUTE_IN_SECONDS );

		$notification = $this->build_notification( $session_id );
		$this->handler->process_checkout_session_completed( $notification );

		$resolved = $this->get_resolved_order();
		$this->assertNull( $resolved );

		// Clean up.
		WC_Stripe_Database_Cache::delete( $lock_key );
	}

	/**
	 * Tests that the lock is released after processing, even on failure.
	 */
	public function test_lock_released_after_processing() {
		$this->enable_feature_flag();
		$this->mock_stripe_api_retrieve( $this->build_agentic_session( 'cs_test_lock_release' ) );

		$notification = $this->build_notification( 'cs_test_lock_release' );
		$this->handler->process_checkout_session_completed( $notification );

		$lock_key = 'agentic_lock_cs_test_lock_release';
		$this->assertNull( WC_Stripe_Database_Cache::get( $lock_key ) );
	}

	/**
	 * Tests that a non-agentic session (no agent_details) is silently skipped.
	 */
	public function test_skips_non_agentic_session() {
		$this->enable_feature_flag();

		$session = (object) [
			'id'             => 'cs_test_non_agentic',
			'payment_intent' => (object) [
				'id'            => 'pi_test_non_agentic',
				'agent_details' => null,
			],
		];
		$this->mock_stripe_api_retrieve( $session );

		$notification = $this->build_notification( 'cs_test_non_agentic' );
		$this->handler->process_checkout_session_completed( $notification );

		$resolved = $this->get_resolved_order();
		$this->assertNull( $resolved );
	}

	/**
	 * Tests that a session with empty network_business_profile is skipped.
	 */
	public function test_skips_session_with_empty_network_business_profile() {
		$this->enable_feature_flag();

		$session = (object) [
			'id'             => 'cs_test_empty_nbp',
			'payment_intent' => (object) [
				'id'            => 'pi_test_empty_nbp',
				'agent_details' => (object) [
					'network_business_profile' => '',
				],
			],
		];
		$this->mock_stripe_api_retrieve( $session );

		$notification = $this->build_notification( 'cs_test_empty_nbp' );
		$this->handler->process_checkout_session_completed( $notification );

		$resolved = $this->get_resolved_order();
		$this->assertNull( $resolved );
	}

	/**
	 * Tests that a session missing the payment intent ID is logged and skipped.
	 */
	public function test_skips_session_missing_payment_intent_id() {
		$this->enable_feature_flag();

		$session = (object) [
			'id'             => 'cs_test_no_pi',
			'payment_intent' => (object) [
				'agent_details' => (object) [
					'network_business_profile' => 'nbp_123',
				],
				// No 'id' field.
			],
		];
		$this->mock_stripe_api_retrieve( $session );

		$notification = $this->build_notification( 'cs_test_no_pi' );
		$this->handler->process_checkout_session_completed( $notification );

		$resolved = $this->get_resolved_order();
		$this->assertNull( $resolved );
	}

	/**
	 * Tests that an API error response is handled gracefully.
	 */
	public function test_handles_api_error_gracefully() {
		$this->enable_feature_flag();

		// Return a WP_Error from the API.
		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_error', 'Connection failed' );
			},
			10,
			3
		);

		$notification = $this->build_notification( 'cs_test_api_error' );

		// Should not throw.
		$this->handler->process_checkout_session_completed( $notification );

		$resolved = $this->get_resolved_order();
		$this->assertNull( $resolved );
	}

	/**
	 * Tests that a duplicate session returns the existing order without creating a new one.
	 */
	public function test_returns_existing_order_for_duplicate_intent() {
		$this->enable_feature_flag();

		$existing_order = wc_create_order();
		$existing_order->set_payment_method( 'stripe' );
		$existing_order->update_meta_data( '_stripe_intent_id', 'pi_test_duplicate' );
		$existing_order->save();

		$this->mock_stripe_api_retrieve( $this->build_agentic_session( 'cs_test_dup', 'pi_test_duplicate' ) );

		$notification = $this->build_notification( 'cs_test_dup' );
		$this->handler->process_checkout_session_completed( $notification );

		$resolved = $this->get_resolved_order();
		$this->assertInstanceOf( WC_Order::class, $resolved );
		$this->assertEquals( $existing_order->get_id(), $resolved->get_id() );

		// Verify no new orders were created.
		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_duplicate', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertCount( 1, $orders );

		$existing_order->delete( true );
	}

	/**
	 * Tests that the mapper is called and its exception is caught gracefully.
	 * The stub mapper always throws, so this tests the error handling path.
	 */
	public function test_mapper_exception_fires_failure_action() {
		$this->enable_feature_flag();
		$this->mock_stripe_api_retrieve( $this->build_agentic_session( 'cs_test_mapper_fail' ) );

		$failure_action_fired = false;
		$captured_exception   = null;
		add_action(
			'wc_stripe_agentic_order_creation_failed',
			function ( $e ) use ( &$failure_action_fired, &$captured_exception ) {
				$failure_action_fired = true;
				$captured_exception   = $e;
			}
		);

		$notification = $this->build_notification( 'cs_test_mapper_fail' );

		// Should not throw.
		$this->handler->process_checkout_session_completed( $notification );

		$this->assertTrue( $failure_action_fired );
		$this->assertInstanceOf( Exception::class, $captured_exception );
		$this->assertStringContainsString( 'not yet implemented', $captured_exception->getMessage() );
	}

	/**
	 * Tests that the Stripe API version override header is applied during the retrieve call
	 * and cleaned up afterward.
	 */
	public function test_api_version_override_applied_and_cleaned_up() {
		$this->enable_feature_flag();

		$captured_headers = null;
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args ) use ( &$captured_headers ) {
				$captured_headers = $parsed_args['headers'] ?? [];
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode( $this->build_agentic_session( 'cs_test_version' ) ),
				];
			},
			10,
			3
		);

		$notification = $this->build_notification( 'cs_test_version' );
		$this->handler->process_checkout_session_completed( $notification );

		// The header should have been set during the request.
		$this->assertNotNull( $captured_headers );
		$this->assertArrayHasKey( 'Stripe-Version', $captured_headers );
		$this->assertEquals( \WC_Stripe_API::AGENTIC_COMMERCE_API_VERSION, $captured_headers['Stripe-Version'] );

		// The filter should be removed after processing.
		$this->assertFalse( has_filter( 'wc_stripe_request_headers' ) );
	}

	/**
	 * Tests that the checkout.session.completed event is dispatched correctly from process_webhook.
	 */
	public function test_process_webhook_dispatches_checkout_session_completed() {
		$this->enable_feature_flag();
		$this->mock_stripe_api_retrieve( $this->build_agentic_session( 'cs_test_dispatch' ) );

		$notification = $this->build_notification( 'cs_test_dispatch' );
		$body         = wp_json_encode( $notification );

		// process_webhook calls process_checkout_session_completed internally.
		// If the feature flag is on and the session is agentic, the failure action should fire
		// (because the mapper stub throws).
		$action_fired = false;
		add_action(
			'wc_stripe_agentic_order_creation_failed',
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$this->handler->process_webhook( $body );
		$this->assertTrue( $action_fired );
	}

	/**
	 * Tests that build_checkout_session_retrieve_url produces correct URLs.
	 *
	 * @dataProvider provide_build_url_cases
	 */
	public function test_build_checkout_session_retrieve_url( $session_id, $additional_expand, $expected_url ) {
		$method = new \ReflectionMethod( WC_Stripe_Webhook_Handler::class, 'build_checkout_session_retrieve_url' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $session_id, $additional_expand );
		$this->assertEquals( $expected_url, $result );
	}

	public function provide_build_url_cases() {
		return [
			'default expand only'    => [
				'cs_123',
				[],
				'checkout/sessions/cs_123?expand[]=payment_intent.agent_details',
			],
			'with additional expand' => [
				'cs_456',
				[ 'line_items' ],
				'checkout/sessions/cs_456?expand[]=payment_intent.agent_details&expand[]=line_items',
			],
			'session id with special chars' => [
				'cs_test/special&chars',
				[],
				'checkout/sessions/cs_test%2Fspecial%26chars?expand[]=payment_intent.agent_details',
			],
		];
	}

	// ---- Helpers ----

	/**
	 * Enables the agentic commerce feature flag via filter.
	 */
	private function enable_feature_flag() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
	}

	/**
	 * Builds a webhook notification object for checkout.session.completed.
	 *
	 * @param string $session_id The checkout session ID.
	 * @return object
	 */
	private function build_notification( $session_id ) {
		return (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) [
					'id'             => $session_id,
					'payment_intent' => 'pi_test_' . $session_id,
				],
			],
		];
	}

	/**
	 * Builds an agentic checkout session response (as returned by Stripe API).
	 *
	 * @param string $session_id       The session ID.
	 * @param string $payment_intent_id The payment intent ID.
	 * @return object
	 */
	private function build_agentic_session( $session_id, $payment_intent_id = null ) {
		return (object) [
			'id'             => $session_id,
			'payment_intent' => (object) [
				'id'            => $payment_intent_id ?? 'pi_test_' . $session_id,
				'agent_details' => (object) [
					'network_business_profile' => 'nbp_test_123',
				],
			],
		];
	}

	/**
	 * Mocks WC_Stripe_API::retrieve() via pre_http_request filter.
	 *
	 * @param object $response_body The object to return as the API response.
	 */
	private function mock_stripe_api_retrieve( $response_body ) {
		add_filter(
			'pre_http_request',
			function () use ( $response_body ) {
				return [
					'response' => 200,
					'headers'  => [ 'Content-Type' => 'application/json' ],
					'body'     => wp_json_encode( $response_body ),
				];
			},
			10,
			3
		);
	}

	/**
	 * Gets the resolved_order from the handler via reflection.
	 *
	 * @return WC_Order|null
	 */
	private function get_resolved_order() {
		$prop = new \ReflectionProperty( WC_Stripe_Webhook_Handler::class, 'resolved_order' );
		$prop->setAccessible( true );
		return $prop->getValue( $this->handler );
	}
}
