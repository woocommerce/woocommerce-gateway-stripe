<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_UPE_Payment_Gateway;
use WC_Stripe_Account;
use WC_Stripe_Database_Cache;
use WC_Stripe_Status;
use WC_Subscription;
use WC_Subscriptions_Helpers;
use WP_UnitTestCase;

/**
 * Tests for the WC_Stripe_Status class.
 *
 * Class WC_Stripe_Status_Test.
 */
class WC_Stripe_Status_Test extends WP_UnitTestCase {
	/**
	 * Test for `render_status_report_section`.
	 *
	 * @return void
	 */
	public function test_render_status_report_section() {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn(
				[
					'card',
				]
			);

		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$account->method( 'get_cached_account_data' )
			->willReturn(
				[
					'id'    => 'acct_123',
				]
			);

		$status = new WC_Stripe_Status( $gateway, $account );

		ob_start();
		$status->render_status_report_section();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'WooCommerce Stripe Payment Gateway', $output );
		$this->assertStringContainsString( 'acct_123', $output );
		$this->assertStringContainsString( 'card', $output );
	}

	/**
	 * Test for `debug_tools`.
	 *
	 * @return void
	 */
	public function test_debug_tools() {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$status = new WC_Stripe_Status( $gateway, $account );

		$tools = $status->debug_tools( [] );

		$this->assertArrayHasKey( 'wc_stripe_list_detached_subscriptions', $tools );

		$this->assertSame( 'List Stripe subscriptions with detached payment method', $tools['wc_stripe_list_detached_subscriptions']['name'] );
		$this->assertSame( 'List subscriptions', $tools['wc_stripe_list_detached_subscriptions']['button'] );
		$this->assertSame( 'This tool will list all Stripe subscriptions with detached payment methods.<br/><strong class="red">Note:</strong> This tool will make an API request to Stripe for each active Stripe subscription in your store that is due to renew in the next month. For stores with many subscriptions, this may temporarily impact performance.<br/><strong>Not recommended if you have more than 100 active subscriptions due for renewal within 30 days.</strong>', $tools['wc_stripe_list_detached_subscriptions']['desc'] );
	}

	/**
	 * Test for `list_detached_subscriptions`.
	 *
	 * @param array $subscriptions An array of subscriptions to test.
	 * @param string $expected_output The expected output string.
	 * @return void
	 *
	 * @dataProvider provide_test_list_detached_subscriptions
	 */
	public function test_list_detached_subscriptions( $subscriptions, $expected_output ) {
		// Clear database cache
		WC_Stripe_Database_Cache::delete( 'detached_subscriptions_1000' );

		// Mock response from Stripe API.
		$test_request = function () {
			return [
				'response' => 200,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					[
						'customer' => null,
					]
				),
			];
		};
		add_filter( 'pre_http_request', $test_request, 10, 3 );

		if ( count( $subscriptions ) > 0 ) {
			$mocked_subscriptions = [];
			foreach ( $subscriptions as $subscription_data ) {
				$subscription = new WC_Subscription();
				$subscription->set_id( $subscription_data['id'] );
				$subscription->set_status( 'active' );
				$subscription->set_payment_method( 'stripe_klarna' );
				$subscription->save();

				$subscription->update_meta_data( '_stripe_customer_id', $subscription_data['customer_id'] );
				$subscription->update_meta_data( '_stripe_source_id', $subscription_data['source_id'] );
				$subscription->save_meta_data();

				$mocked_subscriptions[] = $subscription;
			}

			WC_Subscriptions_Helpers::$wcs_get_subscriptions = $mocked_subscriptions;
		}

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$account = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$status = new WC_Stripe_Status( $gateway, $account );

		ob_start();
		$status->list_detached_subscriptions();
		$output = ob_get_clean();

		WC_Subscriptions_Helpers::$wcs_get_subscriptions = null;

		remove_filter( 'pre_http_request', $test_request, 10 );

		WC_Stripe_Database_Cache::delete( 'detached_subscriptions_1000' );

		$this->assertStringContainsString( $expected_output, $output );
	}

	/**
	 * Data provider for `test_list_detached_subscriptions`.
	 *
	 * @return array
	 */
	public function provide_test_list_detached_subscriptions() {
		return [
			'no subscriptions'   => [
				'subscriptions'   => [],
				'expected_output' => 'No detached subscriptions found.',
			],
			'with subscriptions' => [
				'subscriptions'   => [
					[
						'id'          => 1,
						'customer_id' => 'cus_123',
						'source_id'   => 'src_123',
					],
				],
				'expected_output' => 'Payment method page &rarr;',
			],
		];
	}
}
