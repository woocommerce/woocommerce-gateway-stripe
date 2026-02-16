<?php

namespace WooCommerce\Stripe\Tests;

use WC_Order;
use WC_Stripe_Webhook_Handler;
use WP_UnitTestCase;

/**
 * Tests for agentic commerce checkout.session.completed webhook handling.
 *
 * @covers WC_Stripe_Webhook_Handler::process_checkout_session_completed
 * @covers WC_Stripe_Webhook_Handler::is_agentic_checkout_session
 * @covers WC_Stripe_Webhook_Handler::get_payment_intent_id_from_checkout_session
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
	}

	/**
	 * @dataProvider provide_test_is_agentic_checkout_session
	 */
	public function test_is_agentic_checkout_session( $checkout_session, $expected ) {
		$this->assertSame( $expected, $this->handler->is_agentic_checkout_session( $checkout_session ) );
	}

	/**
	 * Provider for `test_is_agentic_checkout_session`.
	 *
	 * @return array
	 */
	public function provide_test_is_agentic_checkout_session() {
		return [
			'ui_mode agentic'              => [
				'checkout_session' => (object) [
					'id'       => 'cs_test_1',
					'ui_mode'  => 'agentic',
					'metadata' => (object) [],
				],
				'expected'         => true,
			],
			'metadata agentic flag'        => [
				'checkout_session' => (object) [
					'id'       => 'cs_test_2',
					'metadata' => (object) [ 'agentic' => 'true' ],
				],
				'expected'         => true,
			],
			'regular checkout session'     => [
				'checkout_session' => (object) [
					'id'       => 'cs_test_3',
					'ui_mode'  => 'hosted',
					'metadata' => (object) [],
				],
				'expected'         => false,
			],
			'empty metadata no ui_mode'    => [
				'checkout_session' => (object) [
					'id'       => 'cs_test_4',
					'metadata' => (object) [],
				],
				'expected'         => false,
			],
			'metadata agentic false'       => [
				'checkout_session' => (object) [
					'id'       => 'cs_test_5',
					'metadata' => (object) [ 'agentic' => 'false' ],
				],
				'expected'         => false,
			],
			'both ui_mode and metadata'    => [
				'checkout_session' => (object) [
					'id'       => 'cs_test_6',
					'ui_mode'  => 'agentic',
					'metadata' => (object) [ 'agentic' => 'true' ],
				],
				'expected'         => true,
			],
		];
	}

	/**
	 * @dataProvider provide_test_get_payment_intent_id_from_checkout_session
	 */
	public function test_get_payment_intent_id_from_checkout_session( $checkout_session, $expected ) {
		$this->assertSame(
			$expected,
			$this->handler->get_payment_intent_id_from_checkout_session( $checkout_session )
		);
	}

	/**
	 * Provider for `test_get_payment_intent_id_from_checkout_session`.
	 *
	 * @return array
	 */
	public function provide_test_get_payment_intent_id_from_checkout_session() {
		return [
			'string payment intent'   => [
				'checkout_session' => (object) [ 'payment_intent' => 'pi_test_123' ],
				'expected'         => 'pi_test_123',
			],
			'expanded object'         => [
				'checkout_session' => (object) [
					'payment_intent' => (object) [ 'id' => 'pi_test_456' ],
				],
				'expected'         => 'pi_test_456',
			],
			'null payment intent'     => [
				'checkout_session' => (object) [],
				'expected'         => null,
			],
			'empty string'            => [
				'checkout_session' => (object) [ 'payment_intent' => '' ],
				'expected'         => null,
			],
		];
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

		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$orders = wc_get_orders(
			[
				'meta_key'   => '_stripe_intent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'pi_test_cs_test_non_agentic', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
		$this->assertEmpty( $orders );

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

		$notification = $this->build_notification( 'cs_test_duplicate', true );

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
		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
	}

	/**
	 * Tests that the mapper is called and errors are handled gracefully.
	 *
	 * The order mapper stub throws an exception, which the webhook handler
	 * should catch and log without crashing.
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

		// Should not throw — the handler catches the mapper's exception.
		$this->handler->process_webhook( wp_json_encode( $notification ) );

		$this->assertTrue( $failure_action_fired );

		remove_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
	}

	/**
	 * Builds a checkout.session.completed notification object.
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
			'ui_mode'        => $agentic ? 'agentic' : 'hosted',
		];

		return (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) $session,
			],
		];
	}
}
