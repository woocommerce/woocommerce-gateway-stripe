<?php
/**
 * Tests for WC_Stripe_Payment_Intent
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * @covers WC_Stripe_Payment_Intent
 */
class WC_Stripe_Payment_Intent_Test extends WP_UnitTestCase {

	public function test_constructor_accepts_stdclass() {
		$intent = new WC_Stripe_Payment_Intent( (object) [ 'id' => 'pi_std' ] );
		$this->assertSame( 'pi_std', $intent->get_id() );
	}

	public function test_raw_returns_underlying_object() {
		$raw    = (object) [ 'id' => 'pi_raw' ];
		$intent = new WC_Stripe_Payment_Intent( $raw );
		$this->assertSame( $raw, $intent->raw() );
	}

	/**
	 * @dataProvider provide_status_predicates
	 */
	public function test_status_predicates( string $status, string $method, bool $expected ) {
		$intent = new WC_Stripe_Payment_Intent( (object) [ 'status' => $status ] );
		$this->assertSame( $expected, $intent->$method() );
	}

	public function provide_status_predicates(): array {
		return [
			'succeeded → is_succeeded'                    => [ WC_Stripe_Intent_Status::SUCCEEDED, 'is_succeeded', true ],
			'processing → is_processing'                  => [ WC_Stripe_Intent_Status::PROCESSING, 'is_processing', true ],
			'requires_action → is_requires_action'        => [ WC_Stripe_Intent_Status::REQUIRES_ACTION, 'is_requires_action', true ],
			'requires_capture → is_requires_capture'      => [ WC_Stripe_Intent_Status::REQUIRES_CAPTURE, 'is_requires_capture', true ],
			'requires_confirmation → is_req_confirmation' => [ WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION, 'is_requires_confirmation', true ],
			'requires_payment_method → is_req_pm'         => [ WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD, 'is_requires_payment_method', true ],
			'canceled → is_canceled'                      => [ WC_Stripe_Intent_Status::CANCELED, 'is_canceled', true ],
			'pending → not is_succeeded'                  => [ 'pending', 'is_succeeded', false ],
			'succeeded → not is_canceled'                 => [ WC_Stripe_Intent_Status::SUCCEEDED, 'is_canceled', false ],
		];
	}

	public function test_is_successful_status() {
		$succeeded  = new WC_Stripe_Payment_Intent( (object) [ 'status' => WC_Stripe_Intent_Status::SUCCEEDED ] );
		$capture    = new WC_Stripe_Payment_Intent( (object) [ 'status' => WC_Stripe_Intent_Status::REQUIRES_CAPTURE ] );
		$processing = new WC_Stripe_Payment_Intent( (object) [ 'status' => WC_Stripe_Intent_Status::PROCESSING ] );
		$failed     = new WC_Stripe_Payment_Intent( (object) [ 'status' => 'pending' ] );
		$missing    = new WC_Stripe_Payment_Intent( (object) [] );

		$this->assertTrue( $succeeded->is_successful_status() );
		$this->assertTrue( $capture->is_successful_status() );
		$this->assertTrue( $processing->is_successful_status() );
		$this->assertFalse( $failed->is_successful_status() );
		$this->assertFalse( $missing->is_successful_status() );
	}

	public function test_is_requires_confirmation_or_action() {
		$confirm = new WC_Stripe_Payment_Intent( (object) [ 'status' => WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION ] );
		$action  = new WC_Stripe_Payment_Intent( (object) [ 'status' => WC_Stripe_Intent_Status::REQUIRES_ACTION ] );
		$other   = new WC_Stripe_Payment_Intent( (object) [ 'status' => WC_Stripe_Intent_Status::SUCCEEDED ] );

		$this->assertTrue( $confirm->is_requires_confirmation_or_action() );
		$this->assertTrue( $action->is_requires_confirmation_or_action() );
		$this->assertFalse( $other->is_requires_confirmation_or_action() );
	}

	public function test_client_secret_currency_amount() {
		$intent = new WC_Stripe_Payment_Intent(
			(object) [
				'client_secret' => 'pi_test_secret',
				'currency'      => 'usd',
				'amount'        => 2500,
			]
		);
		$this->assertSame( 'pi_test_secret', $intent->get_client_secret() );
		$this->assertSame( 'USD', $intent->get_currency() );
		$this->assertSame( 2500, $intent->get_amount() );
	}

	public function test_customer_and_payment_method_resolution() {
		$intent = new WC_Stripe_Payment_Intent(
			(object) [
				'customer'       => 'cus_string',
				'payment_method' => (object) [ 'id' => 'pm_xyz' ],
			]
		);
		$this->assertSame( 'cus_string', $intent->get_customer_id() );
		$this->assertSame( 'pm_xyz', $intent->get_payment_method_id() );

		$missing = new WC_Stripe_Payment_Intent( (object) [] );
		$this->assertNull( $missing->get_customer_id() );
		$this->assertNull( $missing->get_payment_method_id() );
	}

	public function test_payment_method_types() {
		$intent = new WC_Stripe_Payment_Intent( (object) [ 'payment_method_types' => [ 'card', 'link' ] ] );
		$this->assertSame( [ 'card', 'link' ], $intent->get_payment_method_types() );

		$empty = new WC_Stripe_Payment_Intent( (object) [] );
		$this->assertSame( [], $empty->get_payment_method_types() );
	}

	public function test_metadata_accessors() {
		$intent = new WC_Stripe_Payment_Intent(
			(object) [
				'metadata' => (object) [
					'order_id'            => '42',
					'save_payment_method' => 'true',
				],
			]
		);
		$this->assertSame( 42, $intent->get_order_id_from_metadata() );
		$this->assertSame( 'true', $intent->get_metadata_value( 'save_payment_method' ) );
		$this->assertNull( $intent->get_metadata_value( 'unknown_key' ) );

		$empty = new WC_Stripe_Payment_Intent( (object) [] );
		$this->assertNull( $empty->get_order_id_from_metadata() );
		$this->assertNull( $empty->get_metadata_value( 'anything' ) );
	}

	public function test_payment_error_accessors() {
		$intent = new WC_Stripe_Payment_Intent(
			(object) [
				'last_payment_error' => (object) [
					'code'    => 'authentication_required',
					'message' => 'Authentication required.',
				],
			]
		);
		$this->assertTrue( $intent->has_payment_error() );
		$this->assertSame( 'authentication_required', $intent->get_payment_error_code() );
		$this->assertSame( 'Authentication required.', $intent->get_payment_error_message() );
		$this->assertTrue( $intent->is_authentication_required_error() );

		$ok = new WC_Stripe_Payment_Intent( (object) [] );
		$this->assertFalse( $ok->has_payment_error() );
		$this->assertNull( $ok->get_payment_error_code() );
		$this->assertNull( $ok->get_payment_error_message() );
		$this->assertFalse( $ok->is_authentication_required_error() );
	}

	public function test_payment_error_source_id_prefers_payment_method() {
		$intent = new WC_Stripe_Payment_Intent(
			(object) [
				'last_payment_error' => (object) [
					'payment_method' => (object) [ 'id' => 'pm_err' ],
					'source'         => (object) [ 'id' => 'src_err' ],
				],
			]
		);
		$this->assertSame( 'pm_err', $intent->get_payment_error_source_id() );
	}

	public function test_payment_error_source_id_falls_back_to_source() {
		$intent = new WC_Stripe_Payment_Intent(
			(object) [
				'last_payment_error' => (object) [
					'source' => (object) [ 'id' => 'src_legacy' ],
				],
			]
		);
		$this->assertSame( 'src_legacy', $intent->get_payment_error_source_id() );
	}

	public function test_payment_error_source_id_returns_null_when_absent() {
		$intent  = new WC_Stripe_Payment_Intent( (object) [ 'last_payment_error' => (object) [] ] );
		$missing = new WC_Stripe_Payment_Intent( (object) [] );

		$this->assertNull( $intent->get_payment_error_source_id() );
		$this->assertNull( $missing->get_payment_error_source_id() );
	}

	public function test_get_latest_charge_id_prefers_charges_data() {
		$intent = new WC_Stripe_Payment_Intent(
			(object) [
				'charges'       => (object) [
					'data' => [ (object) [ 'id' => 'ch_1' ], (object) [ 'id' => 'ch_2' ] ],
				],
				'latest_charge' => 'ch_ignored',
			]
		);
		$this->assertSame( 'ch_2', $intent->get_latest_charge_id() );
	}

	public function test_get_latest_charge_id_falls_back_to_latest_charge_string() {
		$intent = new WC_Stripe_Payment_Intent( (object) [ 'latest_charge' => 'ch_only' ] );
		$this->assertSame( 'ch_only', $intent->get_latest_charge_id() );
	}

	public function test_get_latest_charge_id_handles_expanded_latest_charge() {
		$intent = new WC_Stripe_Payment_Intent(
			(object) [
				'latest_charge' => (object) [ 'id' => 'ch_expanded' ],
			]
		);
		$this->assertSame( 'ch_expanded', $intent->get_latest_charge_id() );
	}

	public function test_get_latest_charge_id_returns_null_when_absent() {
		$intent = new WC_Stripe_Payment_Intent( (object) [] );
		$this->assertNull( $intent->get_latest_charge_id() );
	}
}
