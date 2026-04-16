<?php
/**
 * Tests for WC_Stripe_Setup_Intent
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * @covers WC_Stripe_Setup_Intent
 */
class WC_Stripe_Setup_Intent_Test extends WP_UnitTestCase {

	public function test_constructor_accepts_stdclass() {
		$intent = new WC_Stripe_Setup_Intent( (object) [ 'id' => 'seti_std' ] );
		$this->assertSame( 'seti_std', $intent->get_id() );
	}

	public function test_constructor_accepts_stripe_object() {
		$intent = new WC_Stripe_Setup_Intent( \Stripe\SetupIntent::constructFrom( [ 'id' => 'seti_sdk' ] ) );
		$this->assertSame( 'seti_sdk', $intent->get_id() );
	}

	public function test_constructor_rejects_unrelated_types() {
		$this->expectException( \InvalidArgumentException::class );
		new WC_Stripe_Setup_Intent( new \ArrayObject( [ 'id' => 'nope' ] ) );
	}

	public function test_raw_returns_underlying_object() {
		$raw    = (object) [ 'id' => 'seti_raw' ];
		$intent = new WC_Stripe_Setup_Intent( $raw );
		$this->assertSame( $raw, $intent->raw() );
	}

	/**
	 * @dataProvider provide_status_predicates
	 */
	public function test_status_predicates( string $status, string $method, bool $expected ) {
		$intent = new WC_Stripe_Setup_Intent( (object) [ 'status' => $status ] );
		$this->assertSame( $expected, $intent->$method() );
	}

	public function provide_status_predicates(): array {
		return [
			'succeeded → is_succeeded'             => [ WC_Stripe_Intent_Status::SUCCEEDED, 'is_succeeded', true ],
			'processing → is_processing'           => [ WC_Stripe_Intent_Status::PROCESSING, 'is_processing', true ],
			'requires_action → is_requires_action' => [ WC_Stripe_Intent_Status::REQUIRES_ACTION, 'is_requires_action', true ],
			'requires_pm → is_requires_pm'         => [ WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD, 'is_requires_payment_method', true ],
			'canceled → is_canceled'               => [ WC_Stripe_Intent_Status::CANCELED, 'is_canceled', true ],
			'succeeded → not is_requires_action'   => [ WC_Stripe_Intent_Status::SUCCEEDED, 'is_requires_action', false ],
		];
	}

	public function test_is_successful_for_setup() {
		$cases = [
			[ WC_Stripe_Intent_Status::SUCCEEDED, true ],
			[ WC_Stripe_Intent_Status::PROCESSING, true ],
			[ WC_Stripe_Intent_Status::REQUIRES_ACTION, true ],
			[ WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION, true ],
			[ WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD, false ],
			[ WC_Stripe_Intent_Status::CANCELED, false ],
		];
		foreach ( $cases as $case ) {
			$intent = new WC_Stripe_Setup_Intent( (object) [ 'status' => $case[0] ] );
			$this->assertSame( $case[1], $intent->is_successful_for_setup(), 'Status: ' . $case[0] );
		}

		$missing = new WC_Stripe_Setup_Intent( (object) [] );
		$this->assertFalse( $missing->is_successful_for_setup() );
	}

	public function test_client_secret() {
		$intent = new WC_Stripe_Setup_Intent( (object) [ 'client_secret' => 'seti_secret' ] );
		$this->assertSame( 'seti_secret', $intent->get_client_secret() );
		$this->assertNull( ( new WC_Stripe_Setup_Intent( (object) [] ) )->get_client_secret() );
	}

	public function test_customer_and_payment_method_resolution() {
		$intent = new WC_Stripe_Setup_Intent(
			(object) [
				'customer'       => (object) [ 'id' => 'cus_expanded' ],
				'payment_method' => 'pm_string',
			]
		);
		$this->assertSame( 'cus_expanded', $intent->get_customer_id() );
		$this->assertSame( 'pm_string', $intent->get_payment_method_id() );
	}

	public function test_mandate_id_handles_string_and_expanded() {
		$string_mandate = new WC_Stripe_Setup_Intent( (object) [ 'mandate' => 'mandate_abc' ] );
		$this->assertSame( 'mandate_abc', $string_mandate->get_mandate_id() );

		$expanded_mandate = new WC_Stripe_Setup_Intent(
			(object) [
				'mandate' => (object) [ 'id' => 'mandate_xyz' ],
			]
		);
		$this->assertSame( 'mandate_xyz', $expanded_mandate->get_mandate_id() );

		$missing = new WC_Stripe_Setup_Intent( (object) [] );
		$this->assertNull( $missing->get_mandate_id() );
	}

	public function test_order_id_from_metadata() {
		$intent = new WC_Stripe_Setup_Intent( (object) [ 'metadata' => (object) [ 'order_id' => '77' ] ] );
		$this->assertSame( 77, $intent->get_order_id_from_metadata() );

		$missing = new WC_Stripe_Setup_Intent( (object) [] );
		$this->assertNull( $missing->get_order_id_from_metadata() );
	}

	public function test_setup_error_accessors() {
		$intent = new WC_Stripe_Setup_Intent(
			(object) [
				'last_setup_error' => (object) [
					'code'    => 'card_declined',
					'message' => 'Your card was declined.',
				],
			]
		);
		$this->assertTrue( $intent->has_setup_error() );
		$this->assertSame( 'card_declined', $intent->get_setup_error_code() );
		$this->assertSame( 'Your card was declined.', $intent->get_setup_error_message() );

		$ok = new WC_Stripe_Setup_Intent( (object) [] );
		$this->assertFalse( $ok->has_setup_error() );
		$this->assertNull( $ok->get_setup_error_code() );
		$this->assertNull( $ok->get_setup_error_message() );
	}
}
