<?php

/**
 * Class WC_Stripe_Payment_Token_CC tests.
 */
class WC_Stripe_Payment_Token_CC_Test extends WP_UnitTestCase {

	/**
	 * Instance of WC_Stripe_Payment_Token_CC to test.
	 *
	 * @var WC_Stripe_Payment_Token_CC
	 */
	protected $token;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->token = new WC_Stripe_Payment_Token_CC();
	}

	/**
	 * Test default token properties upon instantiation.
	 */
	public function test_default_properties(): void {
		$this->assertSame( 'cc', $this->token->get_type(), 'The token "type" property should match "cc".' );
		$this->assertSame( '', $this->token->get_wallet_type(), 'The default wallet_type should be an empty string.' );
		$this->assertSame( '', $this->token->get_fingerprint(), 'The default fingerprint should be an empty string.' );
	}

	/**
	 * Test getters and setters for wallet_type across contexts.
	 *
	 * @dataProvider provide_wallet_types
	 *
	 * @param string $wallet_type Wallet slug to set.
	 * @return void
	 */
	public function test_get_and_set_wallet_type( string $wallet_type ): void {
		$this->token->set_wallet_type( $wallet_type );
		$this->assertSame( $wallet_type, $this->token->get_wallet_type(), 'get_wallet_type() view context should match.' );
		$this->assertSame( $wallet_type, $this->token->get_wallet_type( 'edit' ), 'get_wallet_type() edit context should match.' );
	}

	/**
	 * Data provider for wallet types.
	 *
	 * @return array
	 */
	public function provide_wallet_types(): array {
		return [
			'apple_pay'    => [ 'apple_pay' ],
			'google_pay'   => [ 'google_pay' ],
			'link'         => [ 'link' ],
			'empty string' => [ '' ],
		];
	}

	/**
	 * Test getters and setters for fingerprint across contexts.
	 */
	public function test_get_and_set_fingerprint(): void {
		$this->token->set_fingerprint( 'fp_cc_test_123' );
		$this->assertSame( 'fp_cc_test_123', $this->token->get_fingerprint(), 'get_fingerprint() view context should match.' );
		$this->assertSame( 'fp_cc_test_123', $this->token->get_fingerprint( 'edit' ), 'get_fingerprint() edit context should match.' );
	}

	/**
	 * Builds a fully populated CC token for display-name assertions.
	 *
	 * @param string $wallet_type Wallet slug to seed (empty for manual entry).
	 * @param string $card_type   Card brand label slug.
	 * @return WC_Stripe_Payment_Token_CC
	 */
	private function build_token( string $wallet_type = '', string $card_type = 'visa' ): WC_Stripe_Payment_Token_CC {
		$token = new WC_Stripe_Payment_Token_CC();
		$token->set_card_type( $card_type );
		$token->set_last4( '4242' );
		$token->set_expiry_month( '03' );
		$token->set_expiry_year( '2027' );
		if ( '' !== $wallet_type ) {
			$token->set_wallet_type( $wallet_type );
		}

		return $token;
	}

	/**
	 * Guards the wallet slug → label map. `link` and unknown wallets must return
	 * an empty string so the saved-methods list (and `get_display_name`) fall
	 * back to bare-card rendering for them — `link` is covered by a separate
	 * dedicated token class.
	 *
	 * @return void
	 */
	public function test_get_wallet_brand_label_maps_only_apple_and_google_pay(): void {
		$this->assertSame( 'Apple Pay', $this->build_token( 'apple_pay' )->get_wallet_brand_label() );
		$this->assertSame( 'Google Pay', $this->build_token( 'google_pay' )->get_wallet_brand_label() );
		$this->assertSame( '', $this->build_token( 'link' )->get_wallet_brand_label() );
		$this->assertSame( '', $this->build_token( '' )->get_wallet_brand_label() );
		$this->assertSame( '', $this->build_token( 'unrecognized' )->get_wallet_brand_label() );
	}

	/**
	 * Manual-entry cards must keep WooCommerce's stock display string so we
	 * never regress non-wallet rendering on classic checkout, blocks, or the
	 * order-pay screen.
	 *
	 * @return void
	 */
	public function test_get_display_name_falls_back_to_parent_for_manual_card(): void {
		$this->assertSame(
			'Visa ending in 4242 (expires 03/27)',
			$this->build_token()->get_display_name()
		);
	}

	/**
	 * Wallet-tokenized cards must wrap the card brand with the wallet label so
	 * the classic checkout radio matches the My Account label exactly, e.g.
	 * "Apple Pay (Visa) ending in 4242 (expires 03/27)".
	 *
	 * @dataProvider provide_wallet_display_name_scenarios
	 *
	 * @param string $wallet_type Stored `card.wallet.type` slug.
	 * @param string $card_type   Stored card type brand.
	 * @param string $expected    Expected display string.
	 * @return void
	 */
	public function test_get_display_name_wraps_wallet_brand( string $wallet_type, string $card_type, string $expected ): void {
		$this->assertSame( $expected, $this->build_token( $wallet_type, $card_type )->get_display_name() );
	}

	/**
	 * Data provider for wallet display name scenarios.
	 *
	 * @return array
	 */
	public function provide_wallet_display_name_scenarios(): array {
		return [
			'apple_pay wraps visa brand'        => [ 'apple_pay', 'visa', 'Apple Pay (Visa) ending in 4242 (expires 03/27)' ],
			'google_pay wraps visa brand'       => [ 'google_pay', 'visa', 'Google Pay (Visa) ending in 4242 (expires 03/27)' ],
			'apple_pay wraps mastercard brand'  => [ 'apple_pay', 'mastercard', 'Apple Pay (MasterCard) ending in 4242 (expires 03/27)' ],
			'google_pay wraps mastercard brand' => [ 'google_pay', 'mastercard', 'Google Pay (MasterCard) ending in 4242 (expires 03/27)' ],
			'link falls back to parent'         => [ 'link', 'visa', 'Visa ending in 4242 (expires 03/27)' ],
		];
	}

	/**
	 * Tests for `is_equal_payment_method()`.
	 *
	 * @dataProvider provide_test_is_equal_payment_method
	 *
	 * @param string $token_fingerprint Fingerprint set on the token.
	 * @param object $payment_method    Payment method object to compare.
	 * @param bool   $expected          Expected comparison result.
	 * @param string $message           Assertion failure message.
	 * @return void
	 */
	public function test_is_equal_payment_method( string $token_fingerprint, object $payment_method, bool $expected, string $message ): void {
		$this->token->set_fingerprint( $token_fingerprint );
		$this->assertSame( $expected, $this->token->is_equal_payment_method( $payment_method ), $message );
	}

	/**
	 * Data provider for `test_is_equal_payment_method`.
	 *
	 * @return array
	 */
	public function provide_test_is_equal_payment_method(): array {
		return [
			'type and fingerprint match'              => [
				'fp_test_123',
				(object) [
					'type' => WC_Stripe_Payment_Methods::CARD,
					'card' => (object) [
						'fingerprint' => 'fp_test_123',
						'last4'       => '4242',
					],
				],
				true,
				'is_equal_payment_method() should return true when type is card and fingerprint matches.',
			],
			'mismatched fingerprint'                  => [
				'fp_test_123',
				(object) [
					'type' => WC_Stripe_Payment_Methods::CARD,
					'card' => (object) [
						'fingerprint' => 'fp_other_456',
						'last4'       => '4242',
					],
				],
				false,
				'is_equal_payment_method() should return false when fingerprint does not match.',
			],
			'mismatched type sepa_debit'              => [
				'fp_test_123',
				(object) [
					'type'       => WC_Stripe_Payment_Methods::SEPA_DEBIT,
					'sepa_debit' => (object) [
						'fingerprint' => 'fp_test_123',
					],
				],
				false,
				'is_equal_payment_method() should return false when payment method type is not card.',
			],
			'mismatched type us_bank_account'         => [
				'fp_test_123',
				(object) [
					'type'            => WC_Stripe_Payment_Methods::ACH,
					'us_bank_account' => (object) [
						'fingerprint' => 'fp_test_123',
					],
				],
				false,
				'is_equal_payment_method() should return false when payment method type is us_bank_account.',
			],
			'both fingerprints empty'                 => [
				'',
				(object) [
					'type' => WC_Stripe_Payment_Methods::CARD,
					'card' => (object) [
						'fingerprint' => '',
					],
				],
				true,
				'is_equal_payment_method() should return true when both token and payment method fingerprints are empty.',
			],
			'token has fingerprint but card is null'  => [
				'fp_test_123',
				(object) [
					'type' => WC_Stripe_Payment_Methods::CARD,
					'card' => (object) [
						'fingerprint' => null,
					],
				],
				false,
				'is_equal_payment_method() should return false when payment method fingerprint is null.',
			],
			'missing card property on payment method' => [
				'fp_test_123',
				(object) [
					'type' => WC_Stripe_Payment_Methods::CARD,
				],
				false,
				'is_equal_payment_method() should return false when card property is missing.',
			],
			'card object missing fingerprint'         => [
				'fp_test_123',
				(object) [
					'type' => WC_Stripe_Payment_Methods::CARD,
					'card' => (object) [
						'last4' => '4242',
					],
				],
				false,
				'is_equal_payment_method() should return false when fingerprint is missing from card object.',
			],
		];
	}
}
