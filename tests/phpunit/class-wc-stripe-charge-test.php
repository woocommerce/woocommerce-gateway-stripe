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

	public function test_raw_returns_underlying_object() {
		$raw    = (object) [ 'id' => 'ch_raw' ];
		$charge = new WC_Stripe_Charge( $raw );
		$this->assertSame( $raw, $charge->raw() );
	}

	/**
	 * @dataProvider provide_status_predicates
	 */
	public function test_status_predicate( string $status, bool $expected_succeeded ) {
		$charge = new WC_Stripe_Charge( (object) [ 'status' => $status ] );
		$this->assertSame( $status, $charge->get_status() );
		$this->assertSame( $expected_succeeded, $charge->is_succeeded() );
	}

	public function provide_status_predicates(): array {
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

	public function test_get_currency_is_uppercase() {
		$charge = new WC_Stripe_Charge( (object) [ 'currency' => 'usd' ] );
		$this->assertSame( 'USD', $charge->get_currency() );

		$missing = new WC_Stripe_Charge( (object) [] );
		$this->assertNull( $missing->get_currency() );
	}

	public function test_get_amounts() {
		$charge = new WC_Stripe_Charge(
			(object) [
				'amount'          => 10000,
				'amount_captured' => 9000,
				'amount_refunded' => 1000,
			]
		);
		$this->assertSame( 10000, $charge->get_amount() );
		$this->assertSame( 9000, $charge->get_amount_captured() );
		$this->assertSame( 1000, $charge->get_amount_refunded() );

		$missing = new WC_Stripe_Charge( (object) [] );
		$this->assertNull( $missing->get_amount() );
		$this->assertNull( $missing->get_amount_captured() );
		$this->assertNull( $missing->get_amount_refunded() );
	}

	/**
	 * @dataProvider provide_id_resolution_cases
	 */
	public function test_id_resolution_for_refs( string $property, $value, ?string $expected, string $method ) {
		$charge = new WC_Stripe_Charge( (object) [ $property => $value ] );
		$this->assertSame( $expected, $charge->$method() );
	}

	public function provide_id_resolution_cases(): array {
		return [
			'customer string'              => [ 'customer', 'cus_123', 'cus_123', 'get_customer_id' ],
			'customer expanded'            => [ 'customer', (object) [ 'id' => 'cus_456' ], 'cus_456', 'get_customer_id' ],
			'customer null'                => [ 'customer', null, null, 'get_customer_id' ],
			'payment_intent string'        => [ 'payment_intent', 'pi_xyz', 'pi_xyz', 'get_payment_intent_id' ],
			'payment_method expanded'      => [ 'payment_method', (object) [ 'id' => 'pm_abc' ], 'pm_abc', 'get_payment_method_id' ],
			'balance_transaction string'   => [ 'balance_transaction', 'txn_1', 'txn_1', 'get_balance_transaction_id' ],
			'balance_transaction expanded' => [ 'balance_transaction', (object) [ 'id' => 'txn_2' ], 'txn_2', 'get_balance_transaction_id' ],
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
