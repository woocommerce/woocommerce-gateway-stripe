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
			'requires_confirm → is_req_confirm'    => [ WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION, 'is_requires_confirmation', true ],
			'canceled → is_canceled'               => [ WC_Stripe_Intent_Status::CANCELED, 'is_canceled', true ],
			'succeeded → not is_requires_action'   => [ WC_Stripe_Intent_Status::SUCCEEDED, 'is_requires_action', false ],
		];
	}

	/**
	 * @dataProvider provide_successful_for_setup_cases
	 */
	public function test_is_successful_for_setup( string $status, bool $expected ) {
		$intent = new WC_Stripe_Setup_Intent( (object) [ 'status' => $status ] );
		$this->assertSame( $expected, $intent->is_successful_for_setup() );
	}

	public function provide_successful_for_setup_cases(): array {
		return [
			'succeeded → true'             => [ WC_Stripe_Intent_Status::SUCCEEDED, true ],
			'processing → true'            => [ WC_Stripe_Intent_Status::PROCESSING, true ],
			'requires_action → true'       => [ WC_Stripe_Intent_Status::REQUIRES_ACTION, true ],
			'requires_confirmation → true' => [ WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION, true ],
			'requires_payment_method → no' => [ WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD, false ],
			'canceled → no'                => [ WC_Stripe_Intent_Status::CANCELED, false ],
		];
	}

	public function test_is_successful_for_setup_handles_missing_status() {
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

		$missing = new WC_Stripe_Setup_Intent( (object) [] );
		$this->assertNull( $missing->get_customer_id() );
		$this->assertNull( $missing->get_payment_method_id() );
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

	public function test_next_action() {
		$next_action = (object) [
			'type'            => 'redirect_to_url',
			'redirect_to_url' => (object) [ 'url' => 'https://example.com/3ds' ],
		];
		$intent      = new WC_Stripe_Setup_Intent( (object) [ 'next_action' => $next_action ] );
		$this->assertSame( $next_action, $intent->get_next_action() );

		$missing = new WC_Stripe_Setup_Intent( (object) [] );
		$this->assertNull( $missing->get_next_action() );
	}

	public function test_error_accessors() {
		$error  = (object) [
			'code'    => 'setup_intent_authentication_failure',
			'message' => 'Authentication failed.',
		];
		$intent = new WC_Stripe_Setup_Intent( (object) [ 'error' => $error ] );

		$this->assertTrue( $intent->has_error() );
		$this->assertSame( 'Authentication failed.', $intent->get_error_message() );
		$this->assertSame( $error, $intent->get_error_object() );

		$ok = new WC_Stripe_Setup_Intent( (object) [] );
		$this->assertFalse( $ok->has_error() );
		$this->assertNull( $ok->get_error_message() );
		$this->assertNull( $ok->get_error_object() );
	}

	public function test_order_id_from_metadata() {
		$intent = new WC_Stripe_Setup_Intent( (object) [ 'metadata' => (object) [ 'order_id' => '77' ] ] );
		$this->assertSame( 77, $intent->get_order_id_from_metadata() );

		$missing = new WC_Stripe_Setup_Intent( (object) [] );
		$this->assertNull( $missing->get_order_id_from_metadata() );
	}
}
