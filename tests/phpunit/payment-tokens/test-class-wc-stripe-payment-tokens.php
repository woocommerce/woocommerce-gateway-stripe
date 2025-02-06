<?php
/**
 * Class WC_Stripe_Payment_Tokens tests.
 */
class WC_Stripe_Payment_Tokens_Test extends WP_UnitTestCase {

	/**
	 * WC_Stripe_Payment_Tokens instance.
	 *
	 * @var WC_Stripe_Payment_Tokens
	 */
	private $stripe_payment_tokens;

	public function set_up() {
		parent::set_up();
		$this->stripe_payment_tokens = new WC_Stripe_Payment_Tokens();
	}

	public function test_is_valid_payment_method_id() {
		$this->assertTrue( $this->stripe_payment_tokens->is_valid_payment_method_id( 'pm_1234567890' ) );
		$this->assertTrue( $this->stripe_payment_tokens->is_valid_payment_method_id( 'pm_1234567890', 'card' ) );
		$this->assertTrue( $this->stripe_payment_tokens->is_valid_payment_method_id( 'pm_1234567890', 'sepa' ) );

		// Test with source id (only card payment method type is valid).
		$this->assertTrue( $this->stripe_payment_tokens->is_valid_payment_method_id( 'src_1234567890', 'card' ) );
		$this->assertFalse( $this->stripe_payment_tokens->is_valid_payment_method_id( 'src_1234567890', 'sepa' ) );
		$this->assertFalse( $this->stripe_payment_tokens->is_valid_payment_method_id( 'src_1234567890', 'giropay' ) );
	}

	/**
	 * Test for `get_duplicate_token` method.
	 *
	 * @param object $payment_method Payment method object.
	 * @param boolean $instance_expected Whether an instance of token is expected.
	 * @return void
	 * @dataProvider provide_test_get_duplicate_token
	 */
	public function test_get_duplicate_token( $payment_method, $instance_expected ) {
		// CC token.
		$token = new WC_Stripe_Payment_Token_CC();
		$token->set_expiry_month( '12' );
		$token->set_expiry_year( '2024' );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( 'pm_1234' );
		$token->set_user_id( 1 );
		$token->set_fingerprint( 'Fxxxxxxxxxxxxxxx' );
		$token->save();

		// CashApp token.
		$token = new WC_Payment_Token_CashApp();
		$token->set_cashtag( '$test_cashtag' );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( 'pm_1234' );
		$token->set_user_id( 1 );
		$token->save();

		// SEPA token.
		$token = new WC_Payment_Token_SEPA();
		$token->set_token( 'pm_1234' );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_last4( '1234' );
		$token->set_fingerprint( 'Fxxxxxxxxxxxxxxx' );
		$token->set_user_id( 1 );
		$token->save();

		// Link token.
		$token = new WC_Payment_Token_Link();
		$token->set_token( 'pm_1234' );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_email( 'test@example.com' );
		$token->set_user_id( 1 );
		$token->save();

		$gateway_id = WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID ];

		$found_token = WC_Stripe_Payment_Tokens::get_duplicate_token( $payment_method, 1, $gateway_id );
		if ( $instance_expected ) {
			$this->assertInstanceOf( WC_Payment_Token::class, $found_token );
		} else {
			$this->assertNull( $found_token );
		}
	}

	/**
	 * Provider for `test_get_duplicate_token` method.
	 *
	 * @return array
	 */
	public function provide_test_get_duplicate_token() {
		// Known CC method.
		$payment_method_cc                                    = [
			'id'                            => 'pm_mock_payment_method_id',
			'type'                          => WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::CARD => [
				'brand'       => 'visa',
				'network'     => 'visa',
				'exp_month'   => '7',
				'exp_year'    => '2099',
				'funding'     => 'credit',
				'last4'       => '4242',
				'fingerprint' => 'Fxxxxxxxxxxxxxxx',
			],
		];
		$payment_method_cc[ WC_Stripe_Payment_Methods::CARD ] = (object) $payment_method_cc[ WC_Stripe_Payment_Methods::CARD ];

		// Unknown CC method.
		$payment_method_cc_unknown                                    = [
			'id'                            => 'pm_mock_payment_method_id',
			'type'                          => WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::CARD => [
				'brand'       => 'visa',
				'network'     => 'visa',
				'exp_month'   => '7',
				'exp_year'    => '2099',
				'funding'     => 'credit',
				'last4'       => '4242',
				'fingerprint' => 'Fxxxxxxxxxxxxxxx_unknown',
			],
		];
		$payment_method_cc_unknown[ WC_Stripe_Payment_Methods::CARD ] = (object) $payment_method_cc_unknown[ WC_Stripe_Payment_Methods::CARD ];

		// Known CashApp method.
		$payment_method_cashapp = [
			'id'                                   => 'pm_mock_payment_method_id',
			'type'                                 => WC_Stripe_Payment_Methods::CASHAPP_PAY,
			WC_Stripe_Payment_Methods::CASHAPP_PAY => [
				'cashtag' => '$test_cashtag',
			],
		];
		$payment_method_cashapp[ WC_Stripe_Payment_Methods::CASHAPP_PAY ] = (object) $payment_method_cashapp[ WC_Stripe_Payment_Methods::CASHAPP_PAY ];

		// Known Sepa method.
		$payment_method_sepa = [
			'id'                                  => 'pm_mock_payment_method_id',
			'type'                                => WC_Stripe_Payment_Methods::SEPA_DEBIT,
			WC_Stripe_Payment_Methods::SEPA_DEBIT => [
				'last4'       => '1234',
				'fingerprint' => 'Fxxxxxxxxxxxxxxx',
			],
		];
		$payment_method_sepa[ WC_Stripe_Payment_Methods::SEPA_DEBIT ] = (object) $payment_method_sepa[ WC_Stripe_Payment_Methods::SEPA_DEBIT ];

		// Known Link method.
		$payment_method_link = [
			'id'                            => 'pm_mock_payment_method_id',
			'type'                          => WC_Stripe_Payment_Methods::LINK,
			WC_Stripe_Payment_Methods::LINK => [
				'email' => 'test@example.com',
			],
		];

		return [
			'existing CC'      => [
				'payment method' => (object) $payment_method_cc,
				'expected'       => true,
			],
			'unknown CC'       => [
				'payment method' => (object) $payment_method_cc_unknown,
				'expected'       => false,
			],
			'existing CashApp' => [
				'payment method' => (object) $payment_method_cashapp,
				'expected'       => true,
			],
			'existing Sepa'    => [
				'payment method' => (object) $payment_method_sepa,
				'expected'       => true,
			],
			'existing Link'    => [
				'payment method' => (object) $payment_method_link,
				'expected'       => false,
			],
		];
	}

	/**
	 * Test for `woocommerce_payment_token_class`.
	 *
	 * @return void
	 * @dataProvider provide_test_woocommerce_payment_token_class
	 */
	public function test_woocommerce_payment_token_class( $class, $expected ) {
		$actual = $this->stripe_payment_tokens->woocommerce_payment_token_class( $class, '' );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_woocommerce_payment_token_class` method.
	 *
	 * @return array
	 */
	public function provide_test_woocommerce_payment_token_class() {
		return [
			WC_Payment_Token_CC::class      => [
				'class'    => WC_Payment_Token_CC::class,
				'expected' => WC_Stripe_Payment_Token_CC::class,
			],
			WC_Payment_Token_CashApp::class => [
				'class'    => WC_Payment_Token_CashApp::class,
				'expected' => WC_Payment_Token_CashApp::class,
			],
			WC_Payment_Token_SEPA::class    => [
				'class'    => WC_Payment_Token_SEPA::class,
				'expected' => WC_Payment_Token_SEPA::class,
			],
			WC_Payment_Token_Link::class    => [
				'class'    => WC_Payment_Token_Link::class,
				'expected' => WC_Payment_Token_Link::class,
			],
		];
	}
}
