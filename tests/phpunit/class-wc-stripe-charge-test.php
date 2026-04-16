<?php
/**
 * Tests for WC_Stripe_Charge
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * @covers WC_Stripe_Charge
 */
class WC_Stripe_Charge_Test extends WP_UnitTestCase {

	public function test_constructor_accepts_stdclass() {
		$charge = new WC_Stripe_Charge( (object) [ 'id' => 'ch_std' ] );
		$this->assertSame( 'ch_std', $charge->get_id() );
	}

	public function test_constructor_accepts_stripe_object() {
		$charge = new WC_Stripe_Charge( \Stripe\Charge::constructFrom( [ 'id' => 'ch_sdk' ] ) );
		$this->assertSame( 'ch_sdk', $charge->get_id() );
	}

	public function test_constructor_rejects_unrelated_types() {
		$this->expectException( \InvalidArgumentException::class );
		new WC_Stripe_Charge( new \ArrayObject( [ 'id' => 'nope' ] ) );
	}

	public function test_raw_returns_underlying_object() {
		$raw    = (object) [ 'id' => 'ch_raw' ];
		$charge = new WC_Stripe_Charge( $raw );
		$this->assertSame( $raw, $charge->raw() );
	}

	public function test_get_id_returns_null_when_missing() {
		$charge = new WC_Stripe_Charge( (object) [] );
		$this->assertNull( $charge->get_id() );
	}

	/**
	 * @dataProvider provide_status_predicate_cases
	 */
	public function test_status_predicates( string $status, bool $expected_succeeded ) {
		$charge = new WC_Stripe_Charge( (object) [ 'status' => $status ] );
		$this->assertSame( $status, $charge->get_status() );
		$this->assertSame( $expected_succeeded, $charge->is_succeeded() );
	}

	public function provide_status_predicate_cases(): array {
		return [
			'succeeded' => [ 'succeeded', true ],
			'pending'   => [ 'pending', false ],
			'failed'    => [ 'failed', false ],
		];
	}

	public function test_is_captured() {
		$captured   = new WC_Stripe_Charge( (object) [ 'captured' => true ] );
		$uncaptured = new WC_Stripe_Charge( (object) [ 'captured' => false ] );
		$missing    = new WC_Stripe_Charge( (object) [] );

		$this->assertTrue( $captured->is_captured() );
		$this->assertFalse( $uncaptured->is_captured() );
		$this->assertFalse( $missing->is_captured() );
	}

	public function test_is_refunded() {
		$yes     = new WC_Stripe_Charge( (object) [ 'refunded' => true ] );
		$no      = new WC_Stripe_Charge( (object) [ 'refunded' => false ] );
		$missing = new WC_Stripe_Charge( (object) [] );

		$this->assertTrue( $yes->is_refunded() );
		$this->assertFalse( $no->is_refunded() );
		$this->assertFalse( $missing->is_refunded() );
	}

	public function test_radar_block_detection() {
		$blocked = new WC_Stripe_Charge(
			(object) [
				'outcome' => (object) [
					'type'   => 'blocked',
					'reason' => 'highest_risk_level',
				],
			]
		);
		$this->assertTrue( $blocked->is_blocked_by_radar() );
		$this->assertSame( 'highest_risk_level', $blocked->get_radar_block_reason() );

		$ok = new WC_Stripe_Charge( (object) [ 'outcome' => (object) [ 'type' => 'authorized' ] ] );
		$this->assertFalse( $ok->is_blocked_by_radar() );
		$this->assertNull( $ok->get_radar_block_reason() );

		$missing = new WC_Stripe_Charge( (object) [] );
		$this->assertFalse( $missing->is_blocked_by_radar() );
	}

	public function test_get_currency_is_lowercase() {
		$charge = new WC_Stripe_Charge( (object) [ 'currency' => 'USD' ] );
		$this->assertSame( 'usd', $charge->get_currency() );
	}

	public function test_get_currency_returns_null_when_missing() {
		$charge = new WC_Stripe_Charge( (object) [] );
		$this->assertNull( $charge->get_currency() );
	}

	/**
	 * @dataProvider provide_amount_decimal_cases
	 */
	public function test_get_amount_decimal( string $currency, int $amount, float $expected ) {
		$charge = new WC_Stripe_Charge(
			(object) [
				'currency' => $currency,
				'amount'   => $amount,
			]
		);
		$this->assertSame( $expected, $charge->get_amount_decimal() );
	}

	public function provide_amount_decimal_cases(): array {
		return [
			'USD divides by 100' => [ 'usd', 2500, 25.0 ],
			'EUR divides by 100' => [ 'eur', 1099, 10.99 ],
			'JPY stays whole'    => [ 'jpy', 2500, 2500.0 ],
			'KRW stays whole'    => [ 'krw', 9000, 9000.0 ],
		];
	}

	public function test_get_amount_decimal_returns_null_when_missing() {
		$charge = new WC_Stripe_Charge( (object) [ 'currency' => 'usd' ] );
		$this->assertNull( $charge->get_amount_decimal() );
	}

	public function test_captured_and_refunded_decimal_amounts() {
		$charge = new WC_Stripe_Charge(
			(object) [
				'currency'        => 'usd',
				'amount'          => 10000,
				'amount_captured' => 10000,
				'amount_refunded' => 2500,
			]
		);
		$this->assertSame( 100.0, $charge->get_amount_captured_decimal() );
		$this->assertSame( 25.0, $charge->get_amount_refunded_decimal() );
	}

	/**
	 * @dataProvider provide_id_or_expanded_cases
	 */
	public function test_id_or_expanded_accessors( string $property, $value, ?string $expected ) {
		$charge = new WC_Stripe_Charge( (object) [ $property => $value ] );

		switch ( $property ) {
			case 'customer':
				$actual = $charge->get_customer_id();
				break;
			case 'payment_intent':
				$actual = $charge->get_payment_intent_id();
				break;
			case 'payment_method':
				$actual = $charge->get_payment_method_id();
				break;
			case 'balance_transaction':
				$actual = $charge->get_balance_transaction_id();
				break;
			default:
				$this->fail( 'Unknown property in test: ' . $property );
		}

		$this->assertSame( $expected, $actual );
	}

	public function provide_id_or_expanded_cases(): array {
		return [
			'customer string'              => [ 'customer', 'cus_123', 'cus_123' ],
			'customer expanded object'     => [ 'customer', (object) [ 'id' => 'cus_456' ], 'cus_456' ],
			'customer null'                => [ 'customer', null, null ],
			'customer empty string'        => [ 'customer', '', null ],
			'payment_intent string'        => [ 'payment_intent', 'pi_xyz', 'pi_xyz' ],
			'payment_method object'        => [ 'payment_method', (object) [ 'id' => 'pm_abc' ], 'pm_abc' ],
			'balance_transaction string'   => [ 'balance_transaction', 'txn_1', 'txn_1' ],
			'balance_transaction expanded' => [ 'balance_transaction', (object) [ 'id' => 'txn_2' ], 'txn_2' ],
		];
	}

	public function test_mandate_id_from_card() {
		$charge = new WC_Stripe_Charge(
			(object) [
				'payment_method_details' => (object) [
					'card' => (object) [ 'mandate' => 'mandate_card_1' ],
				],
			]
		);
		$this->assertSame( 'mandate_card_1', $charge->get_mandate_id() );
	}

	public function test_mandate_id_from_acss_debit() {
		$charge = new WC_Stripe_Charge(
			(object) [
				'payment_method_details' => (object) [
					'acss_debit' => (object) [ 'mandate' => 'mandate_acss_1' ],
				],
			]
		);
		$this->assertSame( 'mandate_acss_1', $charge->get_mandate_id() );
	}

	public function test_mandate_id_prefers_card_over_acss() {
		$charge = new WC_Stripe_Charge(
			(object) [
				'payment_method_details' => (object) [
					'card'       => (object) [ 'mandate' => 'mandate_card_2' ],
					'acss_debit' => (object) [ 'mandate' => 'mandate_acss_2' ],
				],
			]
		);
		$this->assertSame( 'mandate_card_2', $charge->get_mandate_id() );
	}

	public function test_mandate_id_returns_null_when_missing() {
		$this->assertNull( ( new WC_Stripe_Charge( (object) [] ) )->get_mandate_id() );
		$this->assertNull(
			( new WC_Stripe_Charge( (object) [ 'payment_method_details' => (object) [ 'card' => (object) [] ] ] ) )->get_mandate_id()
		);
	}

	public function test_order_id_from_metadata() {
		$charge = new WC_Stripe_Charge( (object) [ 'metadata' => (object) [ 'order_id' => '42' ] ] );
		$this->assertSame( 42, $charge->get_order_id_from_metadata() );

		$missing = new WC_Stripe_Charge( (object) [] );
		$this->assertNull( $missing->get_order_id_from_metadata() );
	}

	public function test_receipt_url() {
		$url    = 'https://pay.stripe.com/receipts/abc';
		$charge = new WC_Stripe_Charge( (object) [ 'receipt_url' => $url ] );
		$this->assertSame( $url, $charge->get_receipt_url() );
		$this->assertNull( ( new WC_Stripe_Charge( (object) [] ) )->get_receipt_url() );
	}

	public function test_refund_accessors() {
		$first = (object) [ 'id' => 'rf_1' ];
		$last  = (object) [ 'id' => 'rf_3' ];

		$charge = new WC_Stripe_Charge(
			(object) [
				'refunds' => (object) [
					'data' => [ $first, (object) [ 'id' => 'rf_2' ], $last ],
				],
			]
		);
		$this->assertSame( $first, $charge->get_first_refund() );
		$this->assertSame( $last, $charge->get_latest_refund() );

		$empty = new WC_Stripe_Charge( (object) [ 'refunds' => (object) [ 'data' => [] ] ] );
		$this->assertNull( $empty->get_first_refund() );
		$this->assertNull( $empty->get_latest_refund() );

		$missing = new WC_Stripe_Charge( (object) [] );
		$this->assertNull( $missing->get_first_refund() );
		$this->assertNull( $missing->get_latest_refund() );
	}
}
