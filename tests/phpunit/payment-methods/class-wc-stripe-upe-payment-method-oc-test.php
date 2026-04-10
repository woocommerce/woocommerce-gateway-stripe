<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_OC.
 */
class WC_Stripe_UPE_Payment_Method_OC_Test extends WP_UnitTestCase {
	/**
	 * Tests for `__construct` method.
	 *
	 * @return void
	 */
	public function test_instance() {
		$payment_method = new WC_Stripe_UPE_Payment_Method_OC();

		$this->assertSame( 'Stripe', $payment_method->title );
		$this->assertContains( 'subscriptions', $payment_method->supports );
		$this->assertContains( 'tokenization', $payment_method->supports );
	}

	/**
	 * Tests for `get_title` method.
	 *
	 * @param mixed    $payment_details Payment details or false.
	 * @param string[] $query_params    Query string parameters merged into $_GET.
	 * @param string   $expected        Expected title.
	 * @param bool     $is_checkout     When true, `is_checkout()` is forced via filter (classic checkout).
	 * @return void
	 *
	 * @dataProvider provide_test_get_title
	 */
	public function test_get_title( $payment_details, ?array $query_params, string $expected, bool $is_checkout = false ) {
		if ( $is_checkout ) {
			add_filter( 'woocommerce_is_checkout', '__return_true' );
		}
		$original_get = $_GET;
		try {
			if ( is_array( $payment_details ) ) {
				$payment_details = json_decode( wp_json_encode( $payment_details ) );
			}
			if ( ! empty( $query_params ) ) {
				$_GET = array_merge( $_GET, $query_params );
			}

			$payment_method = new WC_Stripe_UPE_Payment_Method_OC();
			$actual         = $payment_method->get_title( $payment_details );

			$this->assertEquals( $expected, $actual );
		} finally {
			if ( $is_checkout ) {
				remove_filter( 'woocommerce_is_checkout', '__return_true' );
			}
			$_GET = $original_get;
		}
	}

	/**
	 * Data provider for `test_get_title`.
	 *
	 * @return array
	 */
	public function provide_test_get_title() {
		return [
			'with payment details'                => [
				'payment details' => [
					'type' => WC_Stripe_Payment_Methods::ALIPAY,
				],
				'query params'    => [],
				'expected'        => 'Alipay',
				'is checkout'     => false,
			],
			'block checkout page / pay for order' => [
				'payment details' => false,
				'query params'    => [
					'pay_for_order' => 'true',
				],
				'expected'        => 'Payment methods',
				'is checkout'     => false,
			],
			'classic checkout page'               => [
				'payment details' => false,
				'query params'    => [],
				'expected'        => 'Payment options',
				'is checkout'     => true,
			],
			'default, hardcoded'                  => [
				'payment details' => false,
				'query params'    => [],
				'expected'        => 'Stripe',
				'is checkout'     => false,
			],
		];
	}

	/**
	 * Tests for `is_available`, `get_retrievable_type`, `is_capability_active`, and `requires_automatic_capture` methods.
	 * @return void
	 */
	public function test_feature_methods() {
		$payment_method = new WC_Stripe_UPE_Payment_Method_OC();

		$this->assertFalse( $payment_method->is_available() );
		$this->assertEquals( WC_Stripe_Payment_Methods::CARD, $payment_method->get_retrievable_type() );
		$this->assertTrue( $payment_method->is_capability_active() );
		$this->assertFalse( $payment_method->requires_automatic_capture() );
	}

	/**
	 * Test for `get_testing_instructions`.
	 *
	 * @return void
	 */
	public function test_get_testing_instructions() {
		$expected = '<div id="wc-stripe-payment-method-instructions-card" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use card <number>4242 4242 4242 4242</number> with any expiry and CVC. <a href="https://docs.stripe.com/testing" target="_blank">More test cards</a>.</div><div id="wc-stripe-payment-method-instructions-blik" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use any 6-digit number.</div><div id="wc-stripe-payment-method-instructions-sepa_debit" class="wc-stripe-payment-method-instruction" style="display: none;"><strong>Test mode:</strong> use account <number>AT611904300234573201</number>. <a href="https://docs.stripe.com/testing?payment-method=sepa-direct-debit#non-card-payments" target="_blank">More test methods</a>.</div>';

		$payment_method = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_OC::class )
			->onlyMethods( [ 'get_upe_enabled_payment_method_ids' ] )
			->getMock();

		$payment_method->expects( $this->once() )
			->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn(
				[
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::BLIK,
					WC_Stripe_Payment_Methods::SEPA_DEBIT,
				]
			);

		$actual = $payment_method->get_testing_instructions();

		$this->assertEquals( $expected, $actual );
	}
}
