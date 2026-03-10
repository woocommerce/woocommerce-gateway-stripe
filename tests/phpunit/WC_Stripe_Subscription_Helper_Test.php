<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Order_Helper;
use WC_Stripe_Subscription_Helper;
use WC_Subscription;
use WP_UnitTestCase;

/**
 * Class WC_Stripe_Subscription_Helper_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_Subscription_Helper
 *
 * Tests for the WC_Stripe_Subscription_Helper class.
 */
class WC_Stripe_Subscription_Helper_Test extends WP_UnitTestCase {

	/**
	 * Subscription helper instance.
	 *
	 * @var WC_Stripe_Subscription_Helper
	 */
	protected $helper;

	/**
	 * @inheritDoc
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->helper = new WC_Stripe_Subscription_Helper();
	}

	/**
	 * Tests that WC_Stripe_Subscription_Helper extends WC_Stripe_Order_Helper.
	 *
	 * @return void
	 */
	public function test_extends_order_helper(): void {
		$this->assertInstanceOf( WC_Stripe_Order_Helper::class, $this->helper );
	}

	/**
	 * Tests that get_instance() returns a WC_Stripe_Subscription_Helper instance.
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance = WC_Stripe_Subscription_Helper::get_instance();
		$this->assertInstanceOf( WC_Stripe_Subscription_Helper::class, $instance );
	}

	/**
	 * Tests that get_instance() returns the same singleton instance across calls.
	 *
	 * @return void
	 */
	public function test_singleton(): void {
		$instance1 = WC_Stripe_Subscription_Helper::get_instance();
		$instance2 = WC_Stripe_Subscription_Helper::get_instance();
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Tests that the Subscription_Helper singleton is separate from the Order_Helper singleton.
	 *
	 * @return void
	 */
	public function test_separate_singleton_from_order_helper(): void {
		$subscription_helper = WC_Stripe_Subscription_Helper::get_instance();
		$order_helper        = WC_Stripe_Order_Helper::get_instance();
		$this->assertNotSame( $order_helper, $subscription_helper );
	}

	/**
	 * Tests set_instance() with a WC_Stripe_Subscription_Helper instance.
	 *
	 * @return void
	 */
	public function test_set_instance(): void {
		$original_instance = WC_Stripe_Subscription_Helper::get_instance();

		$new_instance = new WC_Stripe_Subscription_Helper();
		WC_Stripe_Subscription_Helper::set_instance( $new_instance );
		$this->assertSame( $new_instance, WC_Stripe_Subscription_Helper::get_instance() );

		// Restore original instance.
		WC_Stripe_Subscription_Helper::set_instance( $original_instance );
	}

	/**
	 * Tests set_instance() with null resets the singleton.
	 *
	 * @return void
	 */
	public function test_set_instance_with_null(): void {
		WC_Stripe_Subscription_Helper::set_instance( null );
		// After resetting, get_instance() should create a new instance.
		$new_instance = WC_Stripe_Subscription_Helper::get_instance();
		$this->assertInstanceOf( WC_Stripe_Subscription_Helper::class, $new_instance );
	}

	/**
	 * Tests that inherited metadata getters and setters work on a subscription object.
	 *
	 * @return void
	 */
	public function test_subscription_metadata(): void {
		$subscription = new WC_Subscription();
		$subscription->set_id( 1 );
		$subscription->set_status( 'active' );
		$subscription->save();

		// Test source ID.
		$this->helper->update_stripe_source_id( $subscription, 'pm_test_123' );
		$this->assertEquals( 'pm_test_123', $this->helper->get_stripe_source_id( $subscription ) );

		// Test customer ID.
		$this->helper->update_stripe_customer_id( $subscription, 'cus_test_456' );
		$this->assertEquals( 'cus_test_456', $this->helper->get_stripe_customer_id( $subscription ) );

		// Test deletion.
		$this->helper->delete_stripe_source_id( $subscription );
		$this->helper->delete_stripe_customer_id( $subscription );
		$subscription->save_meta_data();

		$this->assertEmpty( $this->helper->get_stripe_source_id( $subscription ) );
		$this->assertEmpty( $this->helper->get_stripe_customer_id( $subscription ) );
	}

	/**
	 * Tests that delete_stripe_refund_id works on a subscription object.
	 *
	 * @return void
	 */
	public function test_delete_stripe_refund_id(): void {
		$subscription = new WC_Subscription();
		$subscription->set_id( 2 );
		$subscription->save();

		$this->helper->update_stripe_refund_id( $subscription, 're_test_789' );
		$this->assertEquals( 're_test_789', $this->helper->get_stripe_refund_id( $subscription ) );

		$this->helper->delete_stripe_refund_id( $subscription );
		$subscription->save_meta_data();

		$this->assertEmpty( $this->helper->get_stripe_refund_id( $subscription ) );
	}
}
