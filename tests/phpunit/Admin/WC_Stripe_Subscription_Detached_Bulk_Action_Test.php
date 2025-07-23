<?php

namespace WooCommerce\Stripe\Tests\Admin;

use WC_Stripe_Database_Cache;
use WC_Stripe_Subscription_Detached_Bulk_Action;
use WC_Subscription;
use WC_Subscriptions;
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

	/**
	 * Tests for `handle_subscription_detachment_check`.
	 *
	 * @param string $action The action being performed.
	 * @param WC_Subscription|null $subscription The subscription object or null.
	 * @param string $expected_redirect_url The expected redirect URL after the action is performed.
	 * @return void
	 * @dataProvider provide_test_handle_subscription_detachment_check
	 */
	public function test_handle_subscription_detachment_check( $action, $subscription, $expected_redirect_url ) {
		$bulk_action_class = new WC_Stripe_Subscription_Detached_Bulk_Action();

		// Mock the wcs_get_subscription function.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $subscription ) {
				return $subscription;
			}
		);

		$redirect_url = $bulk_action_class->handle_subscription_detachment_check( '', $action, [ 1 ] );

		// Clean up.
		WC_Subscriptions::$wcs_get_subscription = null;
		WC_Stripe_Database_Cache::delete( 'payment_method_for_source_src_123' );

		$this->assertSame( $expected_redirect_url, $redirect_url );
	}

	/**
	 * Provider for `test_handle_subscription_detachment_check`.
	 *
	 * @return array
	 */
	public function provide_test_handle_subscription_detachment_check() {
		$subscription = new WC_Subscription();

		$source_id             = 'src_123';
		$subscription_detached = new WC_Subscription();
		$subscription_detached->set_id( 2 );
		$subscription_detached->update_meta_data( '_stripe_source_id', $source_id );

		$payment_method = (object) [
			'customer' => null,
		];

		WC_Stripe_Database_Cache::set( 'payment_method_for_source_' . $source_id, $payment_method, HOUR_IN_SECONDS );

		return [
			'not the detached action'                  => [
				'action'                => 'not-check-for-payment-method-detachment',
				'subscription'          => null,
				'expected redirect_url' => '',
			],
			'not actual subscriptions'                 => [
				'action'                => 'check-for-payment-method-detachment',
				'subscription'          => null,
				'expected redirect_url' => '?detached-subscriptions',
			],
			'existing subscriptions, but not detached' => [
				'action'                => 'check-for-payment-method-detachment',
				'subscription'          => $subscription,
				'expected redirect_url' => '?detached-subscriptions',
			],
			'existing subscriptions, and detached'     => [
				'action'                => 'check-for-payment-method-detachment',
				'subscription'          => $subscription_detached,
				'expected redirect_url' => '?detached-subscriptions=2',
			],
		];
	}
}
