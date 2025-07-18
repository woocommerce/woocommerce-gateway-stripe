<?php

namespace WooCommerce\Stripe\Tests\Admin;

use WC_Stripe_Subscription_Detached_Bulk_Action;
use WP_UnitTestCase;

/**
 * This test makes assertions against the class WC_Stripe_Subscription_Detached_Bulk_Action.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Subscription_Detached_Bulk_Action
 *
 * WC_Stripe_Subscription_Detached_Bulk_Action unit tests.
 */
class WC_Stripe_Subscription_Detached_Bulk_Action_Test extends WP_UnitTestCase {
	/**
	 * @inheritDoc
	 */
	public function set_up() {
		parent::set_up();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-subscription-detached-bulk-action.php';
	}

	/**
	 * Tests for `subscriptions_bulk_actions`.
	 *
	 * @return void
	 */
	public function test_subscriptions_bulk_actions() {
		$bulk_action_class = new WC_Stripe_Subscription_Detached_Bulk_Action();
		$bulk_action_class->subscriptions_bulk_actions( [] );

		$this->assertArrayHasKey( 'check-for-payment-method-detachment', $bulk_action_class->subscriptions_bulk_actions( [] ) );
		$this->assertEquals(
			__( 'Check for payment method detachment', 'woocommerce-gateway-stripe' ),
			$bulk_action_class->subscriptions_bulk_actions( [] )['check-for-payment-method-detachment']
		);
	}
}
