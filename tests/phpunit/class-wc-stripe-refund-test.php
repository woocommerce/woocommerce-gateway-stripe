<?php
/**
 * Tests for WC_Stripe_Refund
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * @covers WC_Stripe_Refund
 */
class WC_Stripe_Refund_Test extends WP_UnitTestCase {

	public function test_constructor_accepts_stdclass() {
		$refund = new WC_Stripe_Refund( (object) [ 'id' => 're_std' ] );
		$this->assertSame( 're_std', $refund->get_id() );
	}

	public function test_constructor_accepts_stripe_object() {
		$refund = new WC_Stripe_Refund( \Stripe\Refund::constructFrom( [ 'id' => 're_sdk' ] ) );
		$this->assertSame( 're_sdk', $refund->get_id() );
	}

	public function test_constructor_rejects_unrelated_types() {
		$this->expectException( \InvalidArgumentException::class );
		new WC_Stripe_Refund( new \ArrayObject( [ 'id' => 'nope' ] ) );
	}

	public function test_raw_returns_underlying_object() {
		$raw    = (object) [ 'id' => 're_raw' ];
		$refund = new WC_Stripe_Refund( $raw );
		$this->assertSame( $raw, $refund->raw() );
	}

	public function test_get_id_returns_null_when_missing() {
		$refund = new WC_Stripe_Refund( (object) [] );
		$this->assertNull( $refund->get_id() );
	}

	/**
	 * @dataProvider provide_status_predicate_cases
	 */
	public function test_status_predicates( string $status, string $method, bool $expected ) {
		$refund = new WC_Stripe_Refund( (object) [ 'status' => $status ] );
		$this->assertSame( $expected, $refund->$method() );
	}

	public function provide_status_predicate_cases(): array {
		return [
			'succeeded → is_succeeded'        => [ 'succeeded', 'is_succeeded', true ],
			'pending → is_pending'            => [ 'pending', 'is_pending', true ],
			'failed → is_failed'              => [ 'failed', 'is_failed', true ],
			'canceled → is_canceled'          => [ 'canceled', 'is_canceled', true ],
			'requires_action → is_req_action' => [ 'requires_action', 'is_requires_action', true ],
			'succeeded → not is_failed'       => [ 'succeeded', 'is_failed', false ],
			'pending → not is_succeeded'      => [ 'pending', 'is_succeeded', false ],
		];
	}

	public function test_is_failed_or_canceled() {
		$failed   = new WC_Stripe_Refund( (object) [ 'status' => 'failed' ] );
		$canceled = new WC_Stripe_Refund( (object) [ 'status' => 'canceled' ] );
		$success  = new WC_Stripe_Refund( (object) [ 'status' => 'succeeded' ] );
		$missing  = new WC_Stripe_Refund( (object) [] );

		$this->assertTrue( $failed->is_failed_or_canceled() );
		$this->assertTrue( $canceled->is_failed_or_canceled() );
		$this->assertFalse( $success->is_failed_or_canceled() );
		$this->assertFalse( $missing->is_failed_or_canceled() );
	}

	public function test_get_currency_is_lowercase() {
		$refund = new WC_Stripe_Refund( (object) [ 'currency' => 'USD' ] );
		$this->assertSame( 'usd', $refund->get_currency() );
	}

	public function test_get_amount_raw() {
		$refund = new WC_Stripe_Refund( (object) [ 'amount' => 2500 ] );
		$this->assertSame( 2500, $refund->get_amount() );
		$this->assertNull( ( new WC_Stripe_Refund( (object) [] ) )->get_amount() );
	}

	/**
	 * @dataProvider provide_amount_decimal_cases
	 */
	public function test_get_amount_decimal( string $currency, int $amount, float $expected ) {
		$refund = new WC_Stripe_Refund(
			(object) [
				'currency' => $currency,
				'amount'   => $amount,
			]
		);
		$this->assertSame( $expected, $refund->get_amount_decimal() );
	}

	public function provide_amount_decimal_cases(): array {
		return [
			'USD divides by 100' => [ 'usd', 2500, 25.0 ],
			'EUR divides by 100' => [ 'eur', 1099, 10.99 ],
			'JPY stays whole'    => [ 'jpy', 2500, 2500.0 ],
			'KRW stays whole'    => [ 'krw', 9000, 9000.0 ],
		];
	}

	public function test_get_amount_decimal_with_currency_override() {
		// Refund missing its own currency (legacy webhook shape) — caller supplies charge currency.
		$refund = new WC_Stripe_Refund( (object) [ 'amount' => 5000 ] );
		$this->assertSame( 50.0, $refund->get_amount_decimal( 'usd' ) );
		$this->assertSame( 5000.0, $refund->get_amount_decimal( 'JPY' ) );
	}

	public function test_get_amount_decimal_returns_null_when_amount_missing() {
		$refund = new WC_Stripe_Refund( (object) [ 'currency' => 'usd' ] );
		$this->assertNull( $refund->get_amount_decimal() );
	}

	/**
	 * @dataProvider provide_id_resolution_cases
	 */
	public function test_id_resolution_for_refs( string $property, $value, ?string $expected, string $method ) {
		$refund = new WC_Stripe_Refund( (object) [ $property => $value ] );
		$this->assertSame( $expected, $refund->$method() );
	}

	public function provide_id_resolution_cases(): array {
		return [
			'charge string'                       => [ 'charge', 'ch_1', 'ch_1', 'get_charge_id' ],
			'charge expanded'                     => [ 'charge', (object) [ 'id' => 'ch_2' ], 'ch_2', 'get_charge_id' ],
			'charge null'                         => [ 'charge', null, null, 'get_charge_id' ],
			'payment_intent string'               => [ 'payment_intent', 'pi_1', 'pi_1', 'get_payment_intent_id' ],
			'balance_transaction string'          => [ 'balance_transaction', 'txn_1', 'txn_1', 'get_balance_transaction_id' ],
			'balance_transaction expanded'        => [ 'balance_transaction', (object) [ 'id' => 'txn_2' ], 'txn_2', 'get_balance_transaction_id' ],
			'failure_balance_transaction string'  => [ 'failure_balance_transaction', 'txn_fail', 'txn_fail', 'get_failure_balance_transaction_id' ],
			'failure_balance_transaction missing' => [ 'other', 'anything', null, 'get_failure_balance_transaction_id' ],
		];
	}

	public function test_reason_and_failure_reason() {
		$refund = new WC_Stripe_Refund(
			(object) [
				'reason'         => 'requested_by_customer',
				'failure_reason' => 'lost_or_stolen_card',
			]
		);
		$this->assertSame( 'requested_by_customer', $refund->get_reason() );
		$this->assertSame( 'lost_or_stolen_card', $refund->get_failure_reason() );

		$empty = new WC_Stripe_Refund( (object) [] );
		$this->assertNull( $empty->get_reason() );
		$this->assertNull( $empty->get_failure_reason() );
	}

	public function test_get_friendly_failure_reason_delegates_to_helper() {
		// Use a known code from WC_Stripe_Helper::get_refund_reason_description(): 'lost_or_stolen_card'.
		$refund   = new WC_Stripe_Refund( (object) [ 'failure_reason' => 'lost_or_stolen_card' ] );
		$friendly = $refund->get_friendly_failure_reason();

		$this->assertNotNull( $friendly );
		$this->assertSame( WC_Stripe_Helper::get_refund_reason_description( 'lost_or_stolen_card' ), $friendly );

		$clean = new WC_Stripe_Refund( (object) [ 'status' => 'succeeded' ] );
		$this->assertNull( $clean->get_friendly_failure_reason() );
	}

	public function test_receipt_number() {
		$refund = new WC_Stripe_Refund( (object) [ 'receipt_number' => '1234-5678' ] );
		$this->assertSame( '1234-5678', $refund->get_receipt_number() );
		$this->assertNull( ( new WC_Stripe_Refund( (object) [] ) )->get_receipt_number() );
	}

	public function test_metadata_value() {
		$refund = new WC_Stripe_Refund(
			(object) [
				'metadata' => (object) [
					'order_id'      => '99',
					'initiated_via' => 'admin',
				],
			]
		);
		$this->assertSame( '99', $refund->get_metadata_value( 'order_id' ) );
		$this->assertSame( 'admin', $refund->get_metadata_value( 'initiated_via' ) );
		$this->assertNull( $refund->get_metadata_value( 'unknown' ) );

		$empty = new WC_Stripe_Refund( (object) [] );
		$this->assertNull( $empty->get_metadata_value( 'anything' ) );
	}
}
