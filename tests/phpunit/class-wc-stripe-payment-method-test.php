<?php
/**
 * Tests for WC_Stripe_Payment_Method
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * @covers WC_Stripe_Payment_Method
 */
class WC_Stripe_Payment_Method_Test extends WP_UnitTestCase {

	public function test_constructor_accepts_stdclass() {
		$pm = new WC_Stripe_Payment_Method( (object) [ 'id' => 'pm_std' ] );
		$this->assertSame( 'pm_std', $pm->get_id() );
	}

	public function test_constructor_accepts_stripe_object() {
		$pm = new WC_Stripe_Payment_Method( \Stripe\PaymentMethod::constructFrom( [ 'id' => 'pm_sdk' ] ) );
		$this->assertSame( 'pm_sdk', $pm->get_id() );
	}

	public function test_constructor_rejects_unrelated_types() {
		$this->expectException( \InvalidArgumentException::class );
		new WC_Stripe_Payment_Method( new \ArrayObject( [ 'id' => 'nope' ] ) );
	}

	public function test_raw_returns_underlying_object() {
		$raw = (object) [ 'id' => 'pm_raw' ];
		$pm  = new WC_Stripe_Payment_Method( $raw );
		$this->assertSame( $raw, $pm->raw() );
	}

	public function test_type_and_object_predicates() {
		$card_pm = new WC_Stripe_Payment_Method(
			(object) [
				'type'   => WC_Stripe_Payment_Methods::CARD,
				'object' => 'payment_method',
			]
		);
		$this->assertTrue( $card_pm->is_payment_method_object() );
		$this->assertTrue( $card_pm->is_type( WC_Stripe_Payment_Methods::CARD ) );
		$this->assertTrue( $card_pm->is_card() );
		$this->assertSame( WC_Stripe_Payment_Methods::CARD, $card_pm->get_type() );
		$this->assertSame( 'payment_method', $card_pm->get_object() );

		$sepa_pm = new WC_Stripe_Payment_Method(
			(object) [
				'type'   => WC_Stripe_Payment_Methods::SEPA_DEBIT,
				'object' => 'payment_method',
			]
		);
		$this->assertFalse( $sepa_pm->is_card() );
		$this->assertTrue( $sepa_pm->is_type( WC_Stripe_Payment_Methods::SEPA_DEBIT ) );

		$legacy_source = new WC_Stripe_Payment_Method( (object) [ 'object' => 'source' ] );
		$this->assertFalse( $legacy_source->is_payment_method_object() );

		$missing = new WC_Stripe_Payment_Method( (object) [] );
		$this->assertNull( $missing->get_type() );
		$this->assertNull( $missing->get_object() );
		$this->assertFalse( $missing->is_card() );
	}

	public function test_customer_id_resolves_string_and_expanded() {
		$string   = new WC_Stripe_Payment_Method( (object) [ 'customer' => 'cus_string' ] );
		$expanded = new WC_Stripe_Payment_Method( (object) [ 'customer' => (object) [ 'id' => 'cus_expanded' ] ] );
		$missing  = new WC_Stripe_Payment_Method( (object) [] );

		$this->assertSame( 'cus_string', $string->get_customer_id() );
		$this->assertSame( 'cus_expanded', $expanded->get_customer_id() );
		$this->assertNull( $missing->get_customer_id() );
	}

	/**
	 * @dataProvider provide_card_brand_cases
	 */
	public function test_card_brand_fallback_chain( object $card, string $expected ) {
		$pm = new WC_Stripe_Payment_Method(
			(object) [
				'type' => WC_Stripe_Payment_Methods::CARD,
				'card' => $card,
			]
		);
		$this->assertSame( $expected, $pm->get_card_brand() );
	}

	public function provide_card_brand_cases(): array {
		return [
			'display_brand takes priority'       => [
				(object) [
					'display_brand' => 'cartes_bancaires',
					'networks'      => (object) [ 'preferred' => 'visa' ],
					'brand'         => 'visa',
				],
				'cartes_bancaires',
			],
			'fallback to networks.preferred'     => [
				(object) [
					'networks' => (object) [ 'preferred' => 'cartes_bancaires' ],
					'brand'    => 'visa',
				],
				'cartes_bancaires',
			],
			'final fallback to brand'            => [
				(object) [ 'brand' => 'visa' ],
				'visa',
			],
			'null networks.preferred falls thru' => [
				(object) [
					'networks' => (object) [ 'preferred' => null ],
					'brand'    => 'mastercard',
				],
				'mastercard',
			],
		];
	}

	public function test_card_brand_returns_null_when_no_brand() {
		$pm = new WC_Stripe_Payment_Method( (object) [ 'card' => (object) [] ] );
		$this->assertNull( $pm->get_card_brand() );

		$missing = new WC_Stripe_Payment_Method( (object) [] );
		$this->assertNull( $missing->get_card_brand() );
	}

	public function test_card_fields() {
		$pm = new WC_Stripe_Payment_Method(
			(object) [
				'card' => (object) [
					'last4'       => '4242',
					'fingerprint' => 'fp_x',
					'exp_month'   => 10,
					'exp_year'    => 2030,
					'country'     => 'US',
					'funding'     => 'credit',
				],
			]
		);
		$this->assertSame( '4242', $pm->get_card_last4() );
		$this->assertSame( 'fp_x', $pm->get_card_fingerprint() );
		$this->assertSame( 10, $pm->get_card_exp_month() );
		$this->assertSame( 2030, $pm->get_card_exp_year() );
		$this->assertSame( 'US', $pm->get_card_country() );
		$this->assertSame( 'credit', $pm->get_card_funding() );
		$this->assertFalse( $pm->is_prepaid_card() );

		$missing = new WC_Stripe_Payment_Method( (object) [] );
		$this->assertNull( $missing->get_card_last4() );
		$this->assertNull( $missing->get_card_exp_month() );
	}

	public function test_is_prepaid_card() {
		$prepaid = new WC_Stripe_Payment_Method( (object) [ 'card' => (object) [ 'funding' => 'prepaid' ] ] );
		$credit  = new WC_Stripe_Payment_Method( (object) [ 'card' => (object) [ 'funding' => 'credit' ] ] );
		$missing = new WC_Stripe_Payment_Method( (object) [] );

		$this->assertTrue( $prepaid->is_prepaid_card() );
		$this->assertFalse( $credit->is_prepaid_card() );
		$this->assertFalse( $missing->is_prepaid_card() );
	}

	public function test_is_india_card() {
		$indian_card = new WC_Stripe_Payment_Method(
			(object) [
				'type' => WC_Stripe_Payment_Methods::CARD,
				'card' => (object) [ 'country' => WC_Stripe_Country_Code::INDIA ],
			]
		);
		$this->assertTrue( $indian_card->is_india_card() );

		$us_card = new WC_Stripe_Payment_Method(
			(object) [
				'type' => WC_Stripe_Payment_Methods::CARD,
				'card' => (object) [ 'country' => 'US' ],
			]
		);
		$this->assertFalse( $us_card->is_india_card() );

		$sepa_india = new WC_Stripe_Payment_Method(
			(object) [
				'type' => WC_Stripe_Payment_Methods::SEPA_DEBIT,
				'card' => (object) [ 'country' => WC_Stripe_Country_Code::INDIA ],
			]
		);
		$this->assertFalse( $sepa_india->is_india_card(), 'India detection must be gated on card type' );
	}

	public function test_get_card_preferred_network() {
		$with_preferred = new WC_Stripe_Payment_Method(
			(object) [
				'card' => (object) [ 'networks' => (object) [ 'preferred' => 'cartes_bancaires' ] ],
			]
		);
		$this->assertSame( 'cartes_bancaires', $with_preferred->get_card_preferred_network() );

		$without = new WC_Stripe_Payment_Method( (object) [ 'card' => (object) [] ] );
		$this->assertNull( $without->get_card_preferred_network() );
	}

	public function test_sepa_accessors() {
		$pm = new WC_Stripe_Payment_Method(
			(object) [
				'sepa_debit' => (object) [
					'last4'       => '3000',
					'fingerprint' => 'sepa_fp',
				],
			]
		);
		$this->assertSame( '3000', $pm->get_sepa_last4() );
		$this->assertSame( 'sepa_fp', $pm->get_sepa_fingerprint() );

		$missing = new WC_Stripe_Payment_Method( (object) [] );
		$this->assertNull( $missing->get_sepa_last4() );
		$this->assertNull( $missing->get_sepa_fingerprint() );
	}

	public function test_us_bank_accessors() {
		$pm = new WC_Stripe_Payment_Method(
			(object) [
				'id'              => 'pm_ach',
				'us_bank_account' => (object) [
					'last4'        => '6789',
					'fingerprint'  => 'ach_fp',
					'bank_name'    => 'Stripe Test Bank',
					'account_type' => 'checking',
				],
			]
		);
		$this->assertSame( '6789', $pm->get_us_bank_last4() );
		$this->assertSame( 'ach_fp', $pm->get_us_bank_fingerprint() );
		$this->assertSame( 'Stripe Test Bank', $pm->get_us_bank_bank_name() );
		$this->assertSame( 'checking', $pm->get_us_bank_account_type() );
		$this->assertTrue( $pm->has_us_bank_details() );

		$missing_bank = new WC_Stripe_Payment_Method( (object) [ 'id' => 'pm_no_bank' ] );
		$this->assertFalse( $missing_bank->has_us_bank_details() );

		$missing_id = new WC_Stripe_Payment_Method( (object) [ 'us_bank_account' => (object) [ 'last4' => '1234' ] ] );
		$this->assertFalse( $missing_id->has_us_bank_details(), 'has_us_bank_details requires an id per ACH token guard' );
	}

	public function test_link_email() {
		$pm = new WC_Stripe_Payment_Method( (object) [ 'link' => (object) [ 'email' => 'link@example.com' ] ] );
		$this->assertSame( 'link@example.com', $pm->get_link_email() );

		$missing = new WC_Stripe_Payment_Method( (object) [] );
		$this->assertNull( $missing->get_link_email() );
	}

	public function test_billing_email_with_default() {
		$pm = new WC_Stripe_Payment_Method(
			(object) [
				'billing_details' => (object) [ 'email' => 'buyer@example.com' ],
			]
		);
		$this->assertSame( 'buyer@example.com', $pm->get_billing_email() );
		$this->assertSame( 'buyer@example.com', $pm->get_billing_email( 'fallback@x.com' ) );

		$missing = new WC_Stripe_Payment_Method( (object) [] );
		$this->assertNull( $missing->get_billing_email() );
		$this->assertSame( '', $missing->get_billing_email( '' ) );
		$this->assertSame( 'fallback@x.com', $missing->get_billing_email( 'fallback@x.com' ) );

		$empty_email = new WC_Stripe_Payment_Method(
			(object) [
				'billing_details' => (object) [ 'email' => '' ],
			]
		);
		$this->assertSame( '', $empty_email->get_billing_email( '' ), 'Empty string is treated as absent and replaced by default' );
	}

	public function test_billing_name_phone_address() {
		$address = (object) [
			'line1'       => '1 Test St',
			'city'        => 'Testville',
			'country'     => 'US',
			'postal_code' => '94103',
		];
		$pm      = new WC_Stripe_Payment_Method(
			(object) [
				'billing_details' => (object) [
					'name'    => 'Jane Tester',
					'phone'   => '+15551234567',
					'address' => $address,
				],
			]
		);

		$this->assertSame( 'Jane Tester', $pm->get_billing_name() );
		$this->assertSame( '+15551234567', $pm->get_billing_phone() );
		$this->assertSame( $address, $pm->get_billing_address() );

		$missing = new WC_Stripe_Payment_Method( (object) [] );
		$this->assertNull( $missing->get_billing_name() );
		$this->assertNull( $missing->get_billing_phone() );
		$this->assertNull( $missing->get_billing_address() );
	}

	public function test_metadata_value() {
		$pm = new WC_Stripe_Payment_Method(
			(object) [
				'metadata' => (object) [ 'source' => 'test-script' ],
			]
		);
		$this->assertSame( 'test-script', $pm->get_metadata_value( 'source' ) );
		$this->assertNull( $pm->get_metadata_value( 'unknown' ) );

		$missing = new WC_Stripe_Payment_Method( (object) [] );
		$this->assertNull( $missing->get_metadata_value( 'anything' ) );
	}
}
