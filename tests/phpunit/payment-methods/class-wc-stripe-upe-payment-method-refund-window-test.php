<?php

/**
 * Tests each payment method's Stripe refund window: the deadline it derives from an order's
 * paid date, and whether that deadline has elapsed.
 */
class WC_Stripe_UPE_Payment_Method_Refund_Window_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {

	/**
	 * The refund deadline is the order's paid date shifted by the method's documented window.
	 * Methods with no documented window return null (never time-blocked).
	 *
	 * @param string      $payment_method_class The payment method class under test.
	 * @param string|null $expected_expression  Documented refund-window expression, or null when unlimited.
	 *
	 * @dataProvider provide_refund_windows
	 */
	public function test_get_refund_window_deadline( string $payment_method_class, ?string $expected_expression ): void {
		$payment_method = new $payment_method_class();

		$order = WC_Helper_Order::create_order();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, $payment_method_class::STRIPE_ID );
		// 2024-01-01 00:00:00 UTC — fixed so the deadline is deterministic and outside all known refund windows.
		$order->set_date_paid( 1704067200 );
		$order->save();

		$deadline = $payment_method->get_refund_window_deadline( $order );

		if ( null === $expected_expression ) {
			$this->assertNull( $deadline );
			return;
		}

		// Derive the expected deadline from the order's own paid date so the assertion is
		// timezone-agnostic; $expected_expression is the documented value, held independently
		// of the class constant so a changed constant will fail, and the test case should be updated.
		$expected = clone $order->get_date_paid();
		$expected->modify( $expected_expression );

		$this->assertEquals( $expected, $deadline );
	}

	/**
	 * Every concrete UPE payment method paired with its documented refund-window expression.
	 *
	 * A null expression covers methods with no documented time limit (cards, Link) and methods that
	 * can't be refunded at all (Boleto, OXXO).
	 *
	 * @return array<string,array{0:string,1:string|null}>
	 */
	public function provide_refund_windows(): array {
		return [
			'CC (card)'         => [ WC_Stripe_UPE_Payment_Method_CC::class, null ],
			'OC (card)'         => [ WC_Stripe_UPE_Payment_Method_OC::class, null ],
			'Link'              => [ WC_Stripe_UPE_Payment_Method_Link::class, null ],
			'Boleto'            => [ WC_Stripe_UPE_Payment_Method_Boleto::class, null ],
			'OXXO'              => [ WC_Stripe_UPE_Payment_Method_Oxxo::class, null ],
			'ACH'               => [ WC_Stripe_UPE_Payment_Method_ACH::class, '+180 days' ],
			'ACSS'              => [ WC_Stripe_UPE_Payment_Method_ACSS::class, '+180 days' ],
			'Affirm'            => [ WC_Stripe_UPE_Payment_Method_Affirm::class, '+120 days' ],
			'Afterpay/Clearpay' => [ WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::class, '+120 days' ],
			'Alipay'            => [ WC_Stripe_UPE_Payment_Method_Alipay::class, '+90 days' ],
			'Amazon Pay'        => [ WC_Stripe_UPE_Payment_Method_Amazon_Pay::class, '+90 days' ],
			'Bacs Debit'        => [ WC_Stripe_UPE_Payment_Method_Bacs_Debit::class, '+180 days' ],
			'Bancontact'        => [ WC_Stripe_UPE_Payment_Method_Bancontact::class, '+180 days' ],
			'BECS Debit'        => [ WC_Stripe_UPE_Payment_Method_Becs_Debit::class, '+90 days' ],
			'BLIK'              => [ WC_Stripe_UPE_Payment_Method_Blik::class, '+13 months' ],
			'Cash App Pay'      => [ WC_Stripe_UPE_Payment_Method_Cash_App_Pay::class, '+90 days' ],
			'EPS'               => [ WC_Stripe_UPE_Payment_Method_Eps::class, '+180 days' ],
			'giropay'           => [ WC_Stripe_UPE_Payment_Method_Giropay::class, '+180 days' ],
			'iDEAL'             => [ WC_Stripe_UPE_Payment_Method_Ideal::class, '+180 days' ],
			'Klarna'            => [ WC_Stripe_UPE_Payment_Method_Klarna::class, '+180 days' ],
			'Multibanco'        => [ WC_Stripe_UPE_Payment_Method_Multibanco::class, '+365 days' ],
			'P24'               => [ WC_Stripe_UPE_Payment_Method_P24::class, '+180 days' ],
			'SEPA'              => [ WC_Stripe_UPE_Payment_Method_Sepa::class, '+180 days' ],
			'SOFORT'            => [ WC_Stripe_UPE_Payment_Method_Sofort::class, '+180 days' ],
			'WeChat Pay'        => [ WC_Stripe_UPE_Payment_Method_Wechat_Pay::class, '+180 days' ],
		];
	}

	/**
	 * A method that can't be refunded via Stripe must never report a deadline, even when it
	 * declares a refund window — otherwise the admin refund button would be time-blocked for a
	 * method whose refunds are handled outside Stripe.
	 *
	 * Use a stub class because no real method combines can_refund = false with a documented window.
	 */
	public function test_get_refund_window_deadline_is_null_when_method_cannot_refund(): void {
		// Klarna carries a '+180 days' window; override only its refundability.
		$payment_method = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_Klarna::class )
			->onlyMethods( [ 'can_refund_via_stripe' ] )
			->getMock();
		$payment_method->method( 'can_refund_via_stripe' )->willReturn( false );

		$order = WC_Helper_Order::create_order();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID );
		// 2024-01-01 00:00:00 UTC — fixed so the deadline is deterministic and outside all known refund windows.
		$order->set_date_paid( 1704067200 );
		$order->save();

		$this->assertNull( $payment_method->get_refund_window_deadline( $order ) );
	}

	/**
	 * The window is only "expired" when the order was paid with this method, has a paid date,
	 * the method has a documented window, and the paid date + the refund window is in the past.
	 *
	 * @param string   $payment_method_class The payment method instance under test.
	 * @param string   $order_payment_type   The Stripe UPE payment type stored on the order.
	 * @param int|null $paid_days_ago        Days ago the order was paid, or null for no paid date.
	 * @param bool     $expected_expired     Whether the window should be reported as expired.
	 *
	 * @dataProvider provide_has_refund_window_expired
	 */
	public function test_has_refund_window_expired( string $payment_method_class, string $order_payment_type, ?int $paid_days_ago, bool $expected_expired ): void {
		$payment_method = new $payment_method_class();

		$order = WC_Helper_Order::create_order();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, $order_payment_type );
		if ( null !== $paid_days_ago ) {
			$order->set_date_paid( time() - $paid_days_ago * DAY_IN_SECONDS );
		}
		$order->save();

		$this->assertSame( $expected_expired, $payment_method->has_refund_window_expired( $order ) );
	}

	/**
	 * Provide test cases for {@see test_has_refund_window_expired()}.
	 *
	 * Note that the numbers exceed the refund window by some margin to ensure we handle
	 * month-based window variance (e.g. BLIK's 13 month window varies by a few days
	 * depending on the purchase date).
	 *
	 * @return array<string,array{0:string,1:string,2:int|null,3:bool}>
	 */
	public function provide_has_refund_window_expired(): array {
		return [
			// Note: this case is intentionally beyond Klarna's 180 day window to ensure we would return false if the payment type matched.
			'paid with a different method'    => [ WC_Stripe_UPE_Payment_Method_Klarna::class, WC_Stripe_Payment_Methods::AFFIRM, 200, false ],
			'no paid date'                    => [ WC_Stripe_UPE_Payment_Method_Klarna::class, WC_Stripe_Payment_Methods::KLARNA, null, false ],
			'unlimited method, long ago'      => [ WC_Stripe_UPE_Payment_Method_CC::class, WC_Stripe_Payment_Methods::CARD, 1000, false ],
			'within the window'               => [ WC_Stripe_UPE_Payment_Method_Klarna::class, WC_Stripe_Payment_Methods::KLARNA, 10, false ],
			'beyond the window'               => [ WC_Stripe_UPE_Payment_Method_Klarna::class, WC_Stripe_Payment_Methods::KLARNA, 200, true ],
			'BLIK within its 13-month window' => [ WC_Stripe_UPE_Payment_Method_Blik::class, WC_Stripe_Payment_Methods::BLIK, 300, false ],
			'BLIK beyond its 13-month window' => [ WC_Stripe_UPE_Payment_Method_Blik::class, WC_Stripe_Payment_Methods::BLIK, 430, true ],
		];
	}
}
