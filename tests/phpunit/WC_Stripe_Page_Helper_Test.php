<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Page_Helper;
use WP_UnitTestCase;

/**
 */
class WC_Stripe_Page_Helper_Test extends WP_UnitTestCase {
	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// Reset the query parameters
		unset( $_GET );
	}

	/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	public function tear_down() {
		// Reset the custom orders table option
		update_option( 'woocommerce_custom_orders_table_enabled', 'no' );

		parent::tear_down();
	}

	/**
	 * Test if the subscription edit page is correctly identified.
	 *
	 * @param bool $custom_orders_table_enabled Whether custom orders table is enabled.
	 * @param array $query_params Query parameters to simulate the request.
	 * @param bool $expected Expected result of the check.
	 * @return void
	 *
	 * @dataProvider provide_test_is_subscription_edit_page
	 */
	public function test_is_subscription_edit( $custom_orders_table_enabled, $query_params, $expected ) {
		if ( $custom_orders_table_enabled ) {
			update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );
		} else {
			update_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		}

		$_GET = $query_params;
		$this->assertSame( $expected, WC_Stripe_Page_Helper::is_subscription_edit_page() );
	}

	/**
	 * Provider for `test_is_subscription_edit_page` tests.
	 *
	 * @return array
	 */
	public function provide_test_is_subscription_edit_page() {
		$subscription = wp_insert_post(
			[
				'post_type'   => 'shop_subscription',
				'post_title'  => 'Test Subscription',
				'post_status' => 'publish',
			]
		);

		$regular_post = wp_insert_post(
			[
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			]
		);

		return [
			'custom orders table, valid subscription edit page' => [
				'custom orders table enabled' => true,
				'query_params'                => [
					'page' => 'wc-orders--shop_subscription',
					'id'   => '123',
				],
				'expected'                    => true,
			],
			'custom orders table, invalid subscription edit page' => [
				'custom orders table enabled' => true,
				'query_params'                => [
					'page' => 'wc-orders--shop_order',
					'id'   => '123',
				],
				'expected'                    => false,
			],
			'custom orders table, missing id' => [
				'custom orders table enabled' => true,
				'query_params'                => [
					'page' => 'wc-orders--shop_subscription',
				],
				'expected'                    => false,
			],
			'custom orders table disabled, valid subscription edit page' => [
				'custom orders table enabled' => false,
				'query_params'                => [
					'post'   => $subscription,
					'action' => 'edit',
				],
				'expected'                    => true,
			],
			'custom orders table disabled, invalid subscription edit page' => [
				'custom orders table enabled' => false,
				'query_params'                => [
					'post'   => $subscription,
					'action' => 'view',
				],
				'expected'                    => false,
			],
			'custom orders table disabled, wrong post type' => [
				'custom orders table enabled' => false,
				'query_params'                => [
					'post'   => $regular_post,
					'action' => 'edit',
				],
				'expected'                    => false,
			],
		];
	}
}
