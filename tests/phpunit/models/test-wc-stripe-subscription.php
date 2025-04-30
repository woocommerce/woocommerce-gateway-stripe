<?php
/**
 * Class WC_Stripe_Subscription
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Subscription
 */

/**
 * Class WC_Stripe_Subscription tests.
 */
class WC_Stripe_Subscription_Test extends WP_UnitTestCase {
	/**
	 * Tests for `get_by_id`, `query`, `get_for_order`, `get_for_renewal_order`.
	 *
	 * @return void
	 */
	public function test_retrieve() {
		$subscription = new WC_Subscription();
		$subscription->set_status( 'pending' );
		$subscription->save_meta_data();
		$subscription->save();

		// get_by_id
		WC_Subscriptions::set_wcs_get_subscription(
			function () use ( $subscription ) {
				return $subscription;
			}
		);

		$subscription_id = $subscription->get_id();
		$this->assertEquals( $subscription_id, ( WC_Stripe_Subscription::get_by_id( $subscription_id ) )->get_id() );

		// query
		WC_Subscriptions_Helpers::$wcs_get_subscriptions = [ $subscription ];

		$subscriptions = WC_Stripe_Subscription::query( [ 'status' => 'pending' ] );
		$this->assertEquals( $subscription_id, $subscriptions[0]->get_id() );

		$order = WC_Helper_Order::create_order( 1, WC_Helper_Product::create_simple_product() );

		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_order = [ $subscription ];

		// get_for_order
		$subscriptions = WC_Stripe_Subscription::get_for_order( $order );
		$this->assertEquals( $subscription_id, $subscriptions[0]->get_id() );

		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order = [ $subscription ];

		// get_for_renewal_order
		$subscriptions = WC_Stripe_Subscription::get_for_renewal_order( $order );
		$this->assertEquals( $subscription_id, $subscriptions[0]->get_id() );

		// cleanup
		WC_Subscriptions_Helpers::$wcs_get_subscriptions                   = null;
		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_order         = null;
		WC_Subscriptions_Helpers::$wcs_get_subscriptions_for_renewal_order = null;
	}

	/**
	 * Tests for getters and setters.
	 *
	 * @return void
	 */
	public function test_properties() {
		$source_id      = 'src_123';
		$customer_id    = 'cus_123';
		$card_id        = 'card_123';
		$payment_method = 'pm_123';
		$refund_id      = 'refund_123';

		$subscription = new WC_Stripe_Subscription( new WC_Subscription() );
		$subscription->set_source_id( $source_id );
		$subscription->set_stripe_customer_id( $customer_id );
		$subscription->set_stripe_card_id( $card_id );
		$subscription->set_delayed_update_payment_all( $payment_method );
		$subscription->set_refund_id( $refund_id );

		$subscription->save_meta_data();
		$subscription->save();

		$this->assertEquals( $source_id, $subscription->get_source_id() );
		$this->assertEquals( $customer_id, $subscription->get_stripe_customer_id() );
		$this->assertEquals( $card_id, $subscription->get_stripe_card_id() );
		$this->assertEquals( $payment_method, $subscription->get_delayed_update_payment_all() );
		$this->assertEquals( $refund_id, $subscription->get_refund_id() );

		$subscription->delete_source_id();
		$subscription->delete_stripe_customer_id();
		$subscription->delete_stripe_card_id();
		$subscription->delete_refund_id();

		$this->assertEmpty( $subscription->get_source_id() );
		$this->assertEmpty( $subscription->get_stripe_customer_id() );
		$this->assertEmpty( $subscription->get_stripe_card_id() );
		$this->assertEmpty( $subscription->get_refund_id() );
	}

	/**
	 * Test for `to_instance`.
	 *
	 * @return void
	 */
	public function test_to_instance() {
		$subscription = new WC_Subscription();
		$this->assertInstanceOf( WC_Stripe_Subscription::class, WC_Stripe_Subscription::to_instance( $subscription ) );
	}
}
