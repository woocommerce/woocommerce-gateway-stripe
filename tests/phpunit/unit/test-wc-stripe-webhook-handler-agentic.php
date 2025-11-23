<?php

namespace WooCommerce\Stripe\Tests;

use Automattic\WooCommerce\Enums\OrderStatus;
use WC_Order;
use WC_Product_Simple;
use WC_Stripe_Webhook_Handler;
use WP_Error;
use WP_UnitTestCase;

/**
 * Test agentic checkout webhook handling.
 */
class WC_Stripe_Webhook_Handler_Agentic_Test extends WP_UnitTestCase {
	/**
	 * The webhook handler instance for testing.
	 *
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
	 * Test that is_agentic_checkout detects agentic sessions via metadata.
	 */
	public function test_detects_agentic_checkout_session_via_metadata() {
		$checkout_session = (object) [
			'id'       => 'cs_test_123',
			'metadata' => (object) [ 'agentic' => 'true' ],
		];

		$result = $this->handler->is_agentic_checkout( $checkout_session );
		$this->assertTrue( $result );
	}

	/**
	 * Test that is_agentic_checkout returns false for non-agentic sessions.
	 */
	public function test_returns_false_for_non_agentic_checkout_session() {
		$checkout_session = (object) [
			'id'       => 'cs_test_123',
			'metadata' => (object) [],
		];

		$result = $this->handler->is_agentic_checkout( $checkout_session );
		$this->assertFalse( $result );
	}

	/**
	 * Test that is_agentic_checkout detects agentic sessions via origin_context.
	 */
	public function test_detects_agentic_checkout_session_via_origin_context() {
		$checkout_session = (object) [
			'id'             => 'cs_test_123',
			'metadata'       => (object) [],
			'origin_context' => 'agent',
		];

		$result = $this->handler->is_agentic_checkout( $checkout_session );
		$this->assertTrue( $result );
	}

	/**
	 * Test creates order from agentic checkout session.
	 */
	public function test_creates_order_from_agentic_checkout_session() {
		// Create a test product with SKU.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_sku( 'test_sku' );
		$product->set_regular_price( 10.00 );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 100 );
		$product->save();

		$checkout_session = (object) [
			'id'               => 'cs_test_123',
			'metadata'         => (object) [ 'agentic' => 'true', 'agent' => 'TestAgent' ],
			'customer_details' => (object) [
				'name'    => 'John Doe',
				'email'   => 'john@example.com',
				'phone'   => '555-1234',
				'address' => (object) [
					'line1'       => '123 Main St',
					'line2'       => 'Apt 4B',
					'city'        => 'San Francisco',
					'state'       => 'CA',
					'postal_code' => '94107',
					'country'     => 'US',
				],
			],
			'line_items'       => (object) [
				'data' => [
					(object) [
						'price'        => (object) [
							'lookup_key' => 'test_sku',
						],
						'quantity'     => 2,
						'amount_total' => 2000, // 2 * $10 = $20 in cents
					],
				],
			],
			'amount_total'     => 2086, // $20.86 in cents
			'currency'         => 'usd',
			'payment_intent'   => 'pi_test_123',
			'payment_status'   => 'paid',
		];

		$order = $this->handler->create_order_from_checkout_session( $checkout_session );

		// Verify order was created.
		$this->assertInstanceOf( WC_Order::class, $order );
		$this->assertFalse( is_wp_error( $order ) );

		// Verify metadata.
		$this->assertEquals( 'true', $order->get_meta( '_wc_stripe_agentic_order' ) );
		$this->assertEquals( 'cs_test_123', $order->get_meta( '_wc_stripe_checkout_session_id' ) );

		// Verify billing details.
		$this->assertEquals( 'John Doe', $order->get_billing_first_name() );
		$this->assertEquals( 'john@example.com', $order->get_billing_email() );
		$this->assertEquals( '555-1234', $order->get_billing_phone() );
		$this->assertEquals( '123 Main St', $order->get_billing_address_1() );
		$this->assertEquals( 'Apt 4B', $order->get_billing_address_2() );
		$this->assertEquals( 'San Francisco', $order->get_billing_city() );
		$this->assertEquals( 'CA', $order->get_billing_state() );
		$this->assertEquals( '94107', $order->get_billing_postcode() );
		$this->assertEquals( 'US', $order->get_billing_country() );

		// Verify payment method.
		$this->assertEquals( 'stripe', $order->get_payment_method() );

		// Verify transaction ID.
		$this->assertEquals( 'pi_test_123', $order->get_transaction_id() );

		// Verify order status.
		$this->assertTrue( $order->has_status( [ OrderStatus::PROCESSING, OrderStatus::COMPLETED ] ) );

		// Clean up.
		$product->delete( true );
		$order->delete( true );
	}

	/**
	 * Test order creation with manual capture.
	 */
	public function test_sets_order_on_hold_for_manual_capture() {
		// Create a test product with SKU.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_sku( 'test_sku_manual' );
		$product->set_regular_price( 10.00 );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 100 );
		$product->save();

		$checkout_session = (object) [
			'id'               => 'cs_test_456',
			'metadata'         => (object) [ 'agentic' => 'true' ],
			'customer_details' => (object) [
				'name'  => 'Jane Doe',
				'email' => 'jane@example.com',
			],
			'line_items'       => (object) [
				'data' => [
					(object) [
						'price'        => (object) [ 'lookup_key' => 'test_sku_manual' ],
						'quantity'     => 1,
						'amount_total' => 1000,
					],
				],
			],
			'amount_total'     => 1000,
			'currency'         => 'usd',
			'payment_intent'   => (object) [
				'id'             => 'pi_test_456',
				'capture_method' => 'manual',
			],
			'payment_status'   => 'unpaid',
		];

		$order = $this->handler->create_order_from_checkout_session( $checkout_session );

		// Verify order was created.
		$this->assertInstanceOf( WC_Order::class, $order );
		$this->assertFalse( is_wp_error( $order ) );

		// Verify order is on-hold for manual capture.
		$this->assertEquals( OrderStatus::ON_HOLD, $order->get_status() );
		$this->assertEquals( 'pi_test_456', $order->get_meta( '_stripe_intent_id' ) );
		$this->assertEquals( 'yes', $order->get_meta( '_stripe_manual_capture' ) );

		// Clean up.
		$product->delete( true );
		$order->delete( true );
	}

	/**
	 * Test order creation returns error for invalid SKU.
	 */
	public function test_returns_error_for_invalid_sku() {
		$checkout_session = (object) [
			'id'               => 'cs_test_789',
			'metadata'         => (object) [ 'agentic' => 'true' ],
			'customer_details' => (object) [
				'name'  => 'Test User',
				'email' => 'test@example.com',
			],
			'line_items'       => (object) [
				'data' => [
					(object) [
						'price'        => (object) [ 'lookup_key' => 'invalid_sku_does_not_exist' ],
						'quantity'     => 1,
						'amount_total' => 1000,
					],
				],
			],
			'amount_total'     => 1000,
			'currency'         => 'usd',
			'payment_intent'   => 'pi_test_789',
			'payment_status'   => 'paid',
		];

		$result = $this->handler->create_order_from_checkout_session( $checkout_session );

		// Verify error was returned.
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'order_creation_failed', $result->get_error_code() );
	}

	/**
	 * Test that webhook processes agentic checkout.session.completed event.
	 */
	public function test_webhook_processes_agentic_checkout_session_completed() {
		// Create a test product with SKU.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_sku( 'webhook_test_sku' );
		$product->set_regular_price( 15.00 );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 100 );
		$product->save();

		$notification = (object) [
			'type' => 'checkout.session.completed',
			'data' => (object) [
				'object' => (object) [
					'id'               => 'cs_webhook_test',
					'metadata'         => (object) [ 'agentic' => 'true', 'agent' => 'WebhookAgent' ],
					'customer_details' => (object) [
						'name'  => 'Webhook Test',
						'email' => 'webhook@example.com',
					],
					'line_items'       => (object) [
						'data' => [
							(object) [
								'price'        => (object) [ 'lookup_key' => 'webhook_test_sku' ],
								'quantity'     => 1,
								'amount_total' => 1500,
							],
						],
					],
					'amount_total'     => 1500,
					'currency'         => 'usd',
					'payment_intent'   => 'pi_webhook_test',
					'payment_status'   => 'paid',
				],
			],
		];

		// Process the webhook.
		$this->handler->process_webhook( wp_json_encode( $notification ) );

		// Find the created order by session ID.
		$orders = wc_get_orders(
			[
				'limit'      => 1,
				'meta_key'   => '_wc_stripe_checkout_session_id',
				'meta_value' => 'cs_webhook_test',
			]
		);

		$this->assertNotEmpty( $orders, 'Order should be created from webhook' );
		$order = $orders[0];
		$this->assertEquals( 'true', $order->get_meta( '_wc_stripe_agentic_order' ) );

		// Clean up.
		$product->delete( true );
		$order->delete( true );
	}

	/**
	 * Test that action hook fires when order is created.
	 */
	public function test_action_fires_when_agentic_order_created() {
		$action_fired = false;
		$received_order = null;
		$received_session = null;

		add_action(
			'wc_stripe_agentic_order_created',
			function ( $order, $session ) use ( &$action_fired, &$received_order, &$received_session ) {
				$action_fired = true;
				$received_order = $order;
				$received_session = $session;
			},
			10,
			2
		);

		// Create a test product.
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_sku( 'action_test_sku' );
		$product->set_regular_price( 10.00 );
		$product->save();

		$checkout_session = (object) [
			'id'               => 'cs_action_test',
			'metadata'         => (object) [ 'agentic' => 'true' ],
			'customer_details' => (object) [
				'name'  => 'Action Test',
				'email' => 'action@example.com',
			],
			'line_items'       => (object) [
				'data' => [
					(object) [
						'price'        => (object) [ 'lookup_key' => 'action_test_sku' ],
						'quantity'     => 1,
						'amount_total' => 1000,
					],
				],
			],
			'amount_total'     => 1000,
			'currency'         => 'usd',
			'payment_intent'   => 'pi_action_test',
			'payment_status'   => 'paid',
		];

		$order = $this->handler->create_order_from_checkout_session( $checkout_session );

		$this->assertTrue( $action_fired, 'wc_stripe_agentic_order_created action should fire' );
		$this->assertInstanceOf( WC_Order::class, $received_order );
		$this->assertEquals( $checkout_session->id, $received_session->id );

		// Clean up.
		$product->delete( true );
		if ( $order instanceof WC_Order ) {
			$order->delete( true );
		}
	}

	/**
	 * Test that action hook fires when order creation fails.
	 */
	public function test_action_fires_when_agentic_order_fails() {
		$action_fired = false;
		$received_error = null;
		$received_session = null;

		add_action(
			'wc_stripe_agentic_order_failed',
			function ( $error, $session ) use ( &$action_fired, &$received_error, &$received_session ) {
				$action_fired = true;
				$received_error = $error;
				$received_session = $session;
			},
			10,
			2
		);

		$checkout_session = (object) [
			'id'               => 'cs_fail_test',
			'metadata'         => (object) [ 'agentic' => 'true' ],
			'customer_details' => (object) [
				'name'  => 'Fail Test',
				'email' => 'fail@example.com',
			],
			'line_items'       => (object) [
				'data' => [
					(object) [
						'price'        => (object) [ 'lookup_key' => 'nonexistent_sku' ],
						'quantity'     => 1,
						'amount_total' => 1000,
					],
				],
			],
			'amount_total'     => 1000,
			'currency'         => 'usd',
			'payment_intent'   => 'pi_fail_test',
			'payment_status'   => 'paid',
		];

		$result = $this->handler->create_order_from_checkout_session( $checkout_session );

		$this->assertTrue( $action_fired, 'wc_stripe_agentic_order_failed action should fire' );
		$this->assertInstanceOf( WP_Error::class, $received_error );
		$this->assertEquals( $checkout_session->id, $received_session->id );
	}
}
