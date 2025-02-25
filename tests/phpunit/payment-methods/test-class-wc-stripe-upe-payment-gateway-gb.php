<?php
/**
 * Unit tests for the UPE payment gateway
 */
class WC_Stripe_UPE_Payment_Gateway_Test_GB extends WP_UnitTestCase {
	/**
	 * Initial setup.
	 */
	public function set_up() {
		parent::set_up();

		update_option( WC_Stripe_Feature_Flags::LPM_ACH_FEATURE_FLAG_NAME, 'yes' );
		update_option( WC_Stripe_Feature_Flags::LPM_BACS_FEATURE_FLAG_NAME, 'yes' );

		// Since it will be used by the mock, we need to set this beforehand.
		$this->set_stripe_account_data( [ 'country' => 'GB' ] );

		$this->mock_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->setConstructorArgs( [] )
			->setMethods(
				[
					'create_and_confirm_intent_for_off_session',
					'generate_payment_request',
					'get_latest_charge_from_intent',
					'get_return_url',
					'get_stripe_customer_id',
					'has_subscription',
					'maybe_process_pre_orders',
					'mark_order_as_pre_ordered',
					'is_pre_order_item_in_cart',
					'is_pre_order_product_charged_upfront',
					'prepare_order_source',
					'stripe_request',
					'get_stripe_customer_from_order',
					'display_order_fee',
					'display_order_payout',
					'get_intent_from_order',
					'has_pre_order_charged_upon_release',
					'has_pre_order',
					'is_subscriptions_enabled',
					'update_saved_payment_method',
				]
			)
			->getMock();
	}

	public function tear_down() {
		delete_option( WC_Stripe_Feature_Flags::LPM_ACH_FEATURE_FLAG_NAME );
		delete_option( WC_Stripe_Feature_Flags::LPM_BACS_FEATURE_FLAG_NAME );

		parent::tear_down();
	}

	/**
	 * @dataProvider get_upe_available_payment_methods_provider
	 */
	public function test_get_upe_available_payment_methods_for_gb( $country, $available_payment_methods ) {
		$expected = $this->mock_gateway->get_upe_available_payment_methods();

		$this->assertSame( $available_payment_methods, $expected );
		$this->assertContains( WC_Stripe_Payment_Methods::BACS_DEBIT, $expected );
	}

	public function get_upe_available_payment_methods_provider() {
		return [
			[
				'GB',
				[
					WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Alipay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Eps::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bancontact::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Boleto::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Ideal::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Oxxo::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_P24::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Multibanco::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID, // TODO: Verify if Link is actually returned/needed in the frontend.
					WC_Stripe_UPE_Payment_Method_Wechat_Pay::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Bacs_Debit::STRIPE_ID,
				],
			],
		];
	}

	/**
	 * @param array $account_data
	 *
	 * @return void
	 */
	private function set_stripe_account_data( $account_data ) {
		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
												->disableOriginalConstructor()
												->setMethods( [ 'get_cached_account_data' ] )
												->getMock();
		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn( $account_data );
	}
}
