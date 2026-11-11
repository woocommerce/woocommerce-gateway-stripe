<?php

/**
 * These tests assert various things about processing an initial payment for a WooCommerce Subscriptions.
 *
 * The responses from HTTP requests are mocked using the WP filter `pre_http_request`.
 *
 * There are a few methods that need to be mocked in the class WC_Stripe_UPE_Payment_Gateway, which is
 * why that class is mocked even though the method under test is part of that class.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Subscription_Initial
 *
 * WC_Stripe_Subscription_Initial_Test
 */
class WC_Stripe_Subscription_Initial_Test extends WP_UnitTestCase {
	public function test_subscription_billing_sync_preserves_uncollected_fields_and_other_addresses(): void {
		$subscription = new WC_Subscription( WC_Helper_Order::create_order()->get_id() );
		$subscription->set_billing_first_name( 'Mary Jane' );
		$subscription->set_billing_last_name( 'van Example' );
		$subscription->set_billing_address_2( 'Suite 5' );
		$subscription->save();
		$original     = $subscription->get_address( 'billing' );
		$shipping     = $subscription->get_address( 'shipping' );
		$customer     = new WC_Customer( $subscription->get_customer_id() );
		$account_city = $customer->get_billing_city();
		$gateway      = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )->disableOriginalConstructor()->onlyMethods( [] )->getMock();
		$gateway->update_subscription_billing_details(
			$subscription,
			(object) [
				'billing_details' => (object) [
					'name'    => 'Mary Jane van Example',
					'email'   => 'updated@example.com',
					'phone'   => null,
					'address' => (object) [
						'city'    => 'Updated city',
						'line2'   => '',
						'country' => null,
					],
				],
			]
		);
		$this->assertSame(
			array_merge(
				$original,
				[
					'city'  => 'Updated city',
					'email' => 'updated@example.com',
				]
			),
			$subscription->get_address( 'billing' )
		);
		$this->assertSame( $shipping, $subscription->get_address( 'shipping' ) );
		$this->assertSame( $account_city, ( new WC_Customer( $subscription->get_customer_id() ) )->get_billing_city() );
	}

	/** @dataProvider subscription_billing_submission_provider */
	public function test_subscription_billing_sync_requires_successful_new_inline_method( bool $saved, bool $express, string $status, bool $updated ): void {
		$subscription = new WC_Subscription( WC_Helper_Order::create_order()->get_id() );
		$subscription->set_billing_first_name( 'Original' );
		$subscription->save();
		WC_Subscriptions::set_wcs_get_subscription(
			static function () use ( $subscription ) {
				return $subscription;
			}
		);
		wp_set_current_user( $subscription->get_customer_id() );
		$_POST                    = $express ? [ 'express_checkout_type' => 'apple_pay' ] : [];
		$gateway                  = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )->disableOriginalConstructor()->onlyMethods( [ 'prepare_payment_information_from_request', 'validate_selected_payment_method_type', 'stripe_request', 'maybe_disallow_prepaid_card', 'process_setup_intent_for_order', 'get_return_url', 'get_upe_gateway_id_for_order' ] )->getMock();
		$gateway->id              = 'stripe';
		$gateway->payment_methods = [];
		$gateway->method( 'prepare_payment_information_from_request' )->willReturn(
			[
				'payment_method'                => 'pm_test',
				'selected_payment_type'         => 'card',
				'save_payment_method_to_store'  => false,
				'is_using_saved_payment_method' => $saved,
				'customer'                      => 'cus_test',
				'token'                         => false,
			]
		);
		$gateway->method( 'stripe_request' )->willReturn( (object) [ 'billing_details' => (object) [ 'name' => 'Updated Subscriber' ] ] );
		$gateway->method( 'process_setup_intent_for_order' )->willReturn(
			(object) [
				'id'     => 'seti_test',
				'status' => $status,
			]
		);
		$gateway->method( 'get_return_url' )->willReturn( 'https://example.org/subscription' );
		$gateway->method( 'get_upe_gateway_id_for_order' )->willReturn( 'stripe' );
		try {
			$result = $gateway->process_change_subscription_payment_with_deferred_intent( $subscription->get_id() );
			$this->assertSame( 'success', $result['result'] );
			$this->assertSame( $updated ? 'Updated' : 'Original', $subscription->get_billing_first_name() );
		} finally {
			WC_Subscriptions::set_wcs_get_subscription( null );
			$_POST = [];
			wp_set_current_user( 0 );
		}
	}

	public function subscription_billing_submission_provider(): array {
		return [
			'new method'       => [ false, false, 'succeeded', true ],
			'saved method'     => [ true, false, 'succeeded', false ],
			'express checkout' => [ false, true, 'succeeded', false ],
			'not confirmed'    => [ false, false, 'processing', false ],
		];
	}

	/**
	 * Tests whether the initial payment succeeds and includes the `setup_future_usage` parameter.
	 *
	 * @return void
	 */
	public function test_initial_intent_parameters(): void {
		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$wc_gateway_stripe = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
								->disableOriginalConstructor()
								->onlyMethods( [ 'get_upe_enabled_at_checkout_payment_method_ids' ] )
								->getMock();

		// Mock the UPE method to return card payment method
		$wc_gateway_stripe
			->expects( $this->any() )
			->method( 'get_upe_enabled_at_checkout_payment_method_ids' )
			->will( $this->returnValue( [ WC_Stripe_Payment_Methods::CARD ] ) );

		// Mock the payment methods to include the card method, which is required for the test to run.
		$wc_gateway_stripe->payment_methods = [ WC_Stripe_Payment_Methods::CARD => new \WC_Stripe_UPE_Payment_Method_CC() ];

		$intent_controller_mock = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
									->disableOriginalConstructor()
									->getMock();

		$intent_controller_mock->expects( $this->any() )
				->method( 'create_and_confirm_payment_intent' )
				->willReturn(
					(object) [
						'id'             => 'pi_123abc',
						'object'         => 'payment_intent',
						'payment_method' => (object) [
							WC_Stripe_Payment_Methods::CARD => (object) [
								'brand'     => 'visa',
								'exp_month' => 12,
								'exp_year'  => 2034,
								'last4'     => '4242',
							],
						],
						'status'         => WC_Stripe_Intent_Status::SUCCEEDED,
					],
				);

		$wc_gateway_stripe->intent_controller = $intent_controller_mock;

		$initial_order = WC_Helper_Order::create_order();
		$order_id      = $initial_order->get_id();
		$customer      = 'cus_123abc';

		$initial_order->set_payment_method( WC_Stripe_UPE_Payment_Gateway::ID );
		$initial_order->save();

		$order_helper->update_stripe_customer_id( $initial_order, $customer );
		$initial_order->save_meta_data();

		$old_post = $_POST;
		$_POST    = [
			'payment_method'           => 'stripe',
			'wc-stripe-payment-method' => 'pm_test_123',
		];

		$pre_http_request_response_callback = function (
			$preempt,
			$request_args,
			$url
		) use (
			$customer,
			$order_id
		) {
			// Continue without mocking the request if it's not the endpoint we care about.
			if ( 0 !== strpos( $url, 'https://api.stripe.com/v1/payment_methods/pm_test_123' ) ) {
				return false;
			}

			return [
				'headers'  => [],
				'body'     => json_encode(
					(object) [
						'id'                            => 'pm_test_123',
						'object'                        => 'payment_method',
						WC_Stripe_Payment_Methods::CARD => (object) [
							'brand'     => 'visa',
							'exp_month' => 12,
							'exp_year'  => 2034,
							'last4'     => '4242',
						],
						'customer'                      => $customer,
					],
				),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
			];
		};

		add_filter( 'pre_http_request', $pre_http_request_response_callback, 10, 3 );

		// Act: call process_subscription_payment().
		// We need to use `wc_gateway_stripe` here because we mocked this class earlier.
		$result = $wc_gateway_stripe->process_payment( $order_id );

		// Assert: nothing was returned.
		$this->assertEquals( 'success', $result['result'] );
		$this->assertArrayHasKey( 'redirect', $result );

		$order = wc_get_order( $order_id );

		$actual = $order_helper->get_stripe_intent_id( $order );

		// Clean up.
		remove_filter( 'pre_http_request', $pre_http_request_response_callback, 10 );
		$_POST = $old_post;
		WC_Stripe_Helper::delete_main_stripe_settings();

		$this->assertEquals( 'pi_123abc', $actual );
	}
}
