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
		$token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_UPE_Payment_Method_Cash_App_Pay::STRIPE_ID ] );
		$token->set_token( 'pm_1234' );
		$token->set_user_id( 1 );
		$token->save();

		// SEPA token.
		$token = new WC_Payment_Token_SEPA();
		$token->set_token( 'pm_1234' );
		$token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID ] );
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

		// ACH token.
		$token = new WC_Payment_Token_ACH();
		$token->set_last4( '6789' );
		$token->set_bank_name( 'Test Bank' );
		$token->set_account_type( 'checking' );
		$token->set_fingerprint( 'Fxxxxxxxxxxxxxxx' );
		$token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID ] );
		$token->set_token( 'pm_1234' );
		$token->set_user_id( 1 );
		$token->save();

		// BECS Debit token.
		$token = new WC_Payment_Token_Becs_Debit();
		$token->set_last4( '4356' );
		$token->set_fingerprint( 'Fxxxxxxxxxxxxxxx' );
		$token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_UPE_Payment_Method_Becs_Debit::STRIPE_ID ] );
		$token->set_token( 'pm_1234' );
		$token->set_user_id( 1 );
		$token->save();

		// ACSS token.
		$token = new WC_Payment_Token_ACSS();
		$token->set_last4( '4321' );
		$token->set_bank_name( 'Test Bank' );
		$token->set_fingerprint( 'Fxxxxxxxxxxxxxxx' );
		$token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID ] );
		$token->set_token( 'pm_1234' );
		$token->set_user_id( 1 );
		$token->save();

		$gateway_id = WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $payment_method->type ];

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

		$payment_method_ach = [
			'id'                           => 'pm_mock_payment_method_id',
			'type'                         => WC_Stripe_Payment_Methods::ACH,
			WC_Stripe_Payment_Methods::ACH => (object) [
				'last4'        => '6789',
				'bank_name'    => 'Test Bank',
				'account_type' => 'checking',
				'fingerprint'  => 'Fxxxxxxxxxxxxxxx',
			],
		];

		$payment_method_becs_debit = [
			'id'                                  => 'pm_mock_payment_method_id',
			'type'                                => WC_Stripe_Payment_Methods::BECS_DEBIT,
			WC_Stripe_Payment_Methods::BECS_DEBIT => (object) [
				'last4'       => '4356',
				'fingerprint' => 'Fxxxxxxxxxxxxxxx',
			],
		];

		$payment_method_acss = [
			'id'                                  => 'pm_mock_payment_method_id',
			'type'                                => WC_Stripe_Payment_Methods::ACSS_DEBIT,
			WC_Stripe_Payment_Methods::ACSS_DEBIT => (object) [
				'last4'       => '4321',
				'bank_name'   => 'Test Bank',
				'fingerprint' => 'Fxxxxxxxxxxxxxxx',
			],
		];

		return [
			'existing CC'         => [
				'payment method' => (object) $payment_method_cc,
				'expected'       => true,
			],
			'unknown CC'          => [
				'payment method' => (object) $payment_method_cc_unknown,
				'expected'       => false,
			],
			'existing CashApp'    => [
				'payment method' => (object) $payment_method_cashapp,
				'expected'       => true,
			],
			'existing Sepa'       => [
				'payment method' => (object) $payment_method_sepa,
				'expected'       => true,
			],
			'existing Link'       => [
				'payment method' => (object) $payment_method_link,
				'expected'       => false,
			],
			'existing ACH'        => [
				'payment method' => (object) $payment_method_ach,
				'expected'       => true,
			],
			'existing BECS Debit' => [
				'payment method' => (object) $payment_method_becs_debit,
				'expected'       => true,
			],
			'existing ACSS'       => [
				'payment method' => (object) $payment_method_acss,
				'expected'       => true,
			],
		];
	}

	/**
	 * Test for `woocommerce_payment_token_class`.
	 *
	 * @return void
	 * @dataProvider provide_test_woocommerce_payment_token_class
	 */
	public function test_woocommerce_payment_token_class( $class, $expected, string $type = '' ) {
		$actual = $this->stripe_payment_tokens->woocommerce_payment_token_class( $class, $type );
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
			WC_Payment_Token_ACH::class    => [
				'class'    => 'test',
				'expected' => WC_Payment_Token_ACH::class,
				'type'     => WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID,
			],
			WC_Payment_Token_ACSS::class    => [
				'class'    => 'test',
				'expected' => WC_Payment_Token_ACSS::class,
				'type'     => WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID,
			],
			WC_Payment_Token_Becs_Debit::class    => [
				'class'    => 'test',
				'expected' => WC_Payment_Token_Becs_Debit::class,
				'type'     => WC_Stripe_UPE_Payment_Method_Becs_Debit::STRIPE_ID,
			],
			'Klarna with overridden class'    => [
				'class'    => 'test_klarna',
				'expected' => 'test_klarna',
				'type'     => \WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID,
			],
			'Klarna with default class'    => [
				'class'    => 'WC_Payment_Token_klarna',
				'expected' => \WC_Stripe_Klarna_Payment_Token::class,
				'type'     => \WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID,
			],
		];
	}

	/**
	 * Data provider for {@see test_get_account_saved_payment_methods_list_item()}.
	 *
	 * @return array
	 */
	public function provide_test_get_account_saved_payment_methods_list_item(): array {
		$mock_sepa_collision_token = $this->getMockBuilder( WC_Payment_Token_CC::class )->getMock();

		$mock_sepa_collision_token->method( 'get_type' )->willReturn( WC_Stripe_Payment_Methods::SEPA );

		$sepa_token = new \WC_Payment_Token_SEPA();
		$sepa_token->set_last4( '1234' );

		$bacs_debit_token = new \WC_Payment_Token_Bacs_Debit();
		$bacs_debit_token->set_last4( '2345' );

		$ach_token = new \WC_Payment_Token_ACH();
		$ach_token->set_last4( '3456' );
		$ach_token->set_bank_name( 'Test ACH Bank' );

		$acss_token = new \WC_Payment_Token_ACSS();
		$acss_token->set_last4( '4567' );
		$acss_token->set_bank_name( 'Test ACSS Bank' );

		$becs_debit_token = new \WC_Payment_Token_Becs_Debit();
		$becs_debit_token->set_last4( '5678' );

		$link_token = new \WC_Payment_Token_Link();
		$link_token->set_email( 'link.test@example.com' );

		$amazon_pay_token = new \WC_Payment_Token_Amazon_Pay();
		$amazon_pay_token->set_email( 'amazon.test@example.com' );

		return [
			'Non-Stripe payment token' => [
				'payment_token'   => new \WC_Payment_Token_CC(),
				'expected_result' => [],
			],
			'Non-Stripe payment token with SEPA type collision' => [
				'payment_token'   => $mock_sepa_collision_token,
				'expected_result' => [],
			],
			'Stripe payment token with unhandled type' => [
				'payment_token'   => new \WC_Stripe_Payment_Token_CC(),
				'expected_result' => [],
			],
			'SEPA token' => [
				'payment_token'   => $sepa_token,
				'expected_result' => [
					'last4' => '1234',
					'brand' => 'SEPA IBAN',
				],
			],
			'BACS Debit token' => [
				'payment_token'   => $bacs_debit_token,
				'expected_result' => [
					'last4' => '2345',
					'brand' => 'Bacs Direct Debit',
				],
			],
			'CashApp token' => [
				'payment_token'   => new \WC_Payment_Token_CashApp(),
				'expected_result' => [
					'brand' => 'Cash App Pay',
				],
			],
			'ACH token' => [
				'payment_token'   => $ach_token,
				'expected_result' => [
					'last4' => '3456',
					'brand' => 'Test ACH Bank',
				],
			],
			'ACSS token' => [
				'payment_token'   => $acss_token,
				'expected_result' => [
					'last4' => '4567',
					'brand' => 'Test ACSS Bank',
				],
			],
			'BECS Debit token' => [
				'payment_token'   => $becs_debit_token,
				'expected_result' => [
					'last4' => '5678',
					'brand' => 'BECS Direct Debit',
				],
			],
			'Link token' => [
				'payment_token'   => $link_token,
				'expected_result' => [
					'brand' => 'Stripe Link (link.test@example.com)',
				],
			],
			'Amazon Pay token' => [
				'payment_token'   => $amazon_pay_token,
				'expected_result' => [
					'brand' => 'Amazon Pay (amazon.test@example.com)',
				],
			],
		];
	}

	/**
	 * Test `get_account_saved_payment_methods_list_item()`.
	 *
	 * @param WC_Payment_Token $payment_token   Payment token.
	 * @param array            $expected_result Expected result.
	 * @return void
	 * @dataProvider provide_test_get_account_saved_payment_methods_list_item
	 */
	public function test_get_account_saved_payment_methods_list_item( WC_Payment_Token $payment_token, array $expected_result ): void {
		$initial_item = [
			'method' => [],
		];

		$result = $this->stripe_payment_tokens->get_account_saved_payment_methods_list_item( $initial_item, $payment_token );

		if ( [] === $expected_result ) {
			$this->assertEquals( $expected_result, $result['method'] );
			$this->assertEquals( $initial_item, $result );
			return;
		}

		$this->assertCount( count( $expected_result ), $result['method'] );

		foreach ( $expected_result as $expected_key => $expected_value ) {
			$this->assertArrayHasKey( $expected_key, $result['method'] );
			$this->assertEquals( $expected_value, $result['method'][ $expected_key ] );
		}
	}
}
