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

	/**
	 * The original main stripe gateway before test overrides.
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway|null
	 */
	private $original_main_gateway = null;

	public function set_up() {
		parent::set_up();
		$this->stripe_payment_tokens = new WC_Stripe_Payment_Tokens();

		// Save the original main gateway so we can restore it in tear_down.
		$reflection = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$reflection->setAccessible( true );
		$this->original_main_gateway = $reflection->getValue( WC_Stripe::get_instance() );
	}

	public function tear_down() {
		// Restore the original main gateway.
		$reflection = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$reflection->setAccessible( true );
		$reflection->setValue( WC_Stripe::get_instance(), $this->original_main_gateway );

		// Ensure no stale user session affects subsequent tests.
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Sets a mock gateway on the WC_Stripe singleton.
	 *
	 * @param object $mock_gateway The mock gateway to set.
	 */
	private function set_main_gateway( $mock_gateway ): void {
		$reflection = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$reflection->setAccessible( true );
		$reflection->setValue( WC_Stripe::get_instance(), $mock_gateway );
	}

	/**
	 * Creates a mock main gateway with oc_enabled set appropriately.
	 *
	 * Uses `onlyMethods` so that `is_oc_enabled()` uses the real property-backed
	 * implementation (reads `$this->oc_enabled`) rather than the default PHPUnit
	 * stub (which returns null). `get_upe_enabled_payment_method_ids()` is mocked
	 * to return a safe array so that the OCS-disabled sync path works without
	 * needing a fully-initialised gateway or live settings.
	 *
	 * @param bool $ocs_enabled Whether OCS should be enabled on the mock.
	 * @return object PHPUnit partial mock of WC_Stripe_UPE_Payment_Gateway.
	 */
	private function get_mock_gateway( bool $ocs_enabled ): object {
		$mock_gateway             = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_upe_enabled_payment_method_ids' ] )
			->getMock();
		$mock_gateway->oc_enabled = $ocs_enabled;
		$mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [ WC_Stripe_Payment_Methods::CARD ] );
		return $mock_gateway;
	}

	/**
	 * Test for `is_valid_payment_method_id`.
	 *
	 * @param string  $payment_method_id   The payment method ID to test.
	 * @param string  $payment_method_type The payment method type.
	 * @param bool    $expected            Expected result.
	 * @return void
	 * @dataProvider provide_test_is_valid_payment_method_id
	 */
	public function test_is_valid_payment_method_id( string $payment_method_id, string $payment_method_type, bool $expected ) {
		$result = $this->stripe_payment_tokens->is_valid_payment_method_id( $payment_method_id, $payment_method_type );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for `test_is_valid_payment_method_id`.
	 *
	 * @return array
	 */
	public function provide_test_is_valid_payment_method_id(): array {
		return [
			'pm_ without type is valid'         => [ 'pm_1234567890', '', true ],
			'pm_ with card type is valid'       => [ 'pm_1234567890', 'card', true ],
			'pm_ with sepa type is valid'       => [ 'pm_1234567890', 'sepa', true ],
			'src_ with card type is valid'      => [ 'src_1234567890', 'card', true ],
			'src_ with sepa type is invalid'    => [ 'src_1234567890', 'sepa', false ],
			'src_ with giropay type is invalid' => [ 'src_1234567890', 'giropay', false ],
		];
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
	 * When a new card is saved whose fingerprint matches an existing saved card,
	 * `add_token_to_user` must refresh the mutable card metadata (expiry, brand,
	 * last4) on the reused token. Otherwise the UI keeps showing the old expiry
	 * even though the Stripe PaymentMethod id was replaced.
	 *
	 * Reproduces STRIPE-1082.
	 *
	 * @return void
	 */
	public function test_add_token_to_user_refreshes_cc_metadata_on_fingerprint_match() {
		$user_id = $this->factory->user->create();
		update_user_option( $user_id, '_stripe_customer_id', 'cus_test_stripe_1082', false );

		$seed_token = new WC_Stripe_Payment_Token_CC();
		$seed_token->set_token( 'pm_old' );
		$seed_token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$seed_token->set_user_id( $user_id );
		$seed_token->set_expiry_month( '01' );
		$seed_token->set_expiry_year( '2027' );
		$seed_token->set_card_type( 'visa' );
		$seed_token->set_last4( '4242' );
		$seed_token->set_fingerprint( 'F_abc' );
		$seed_token->save();
		$seed_token_id = $seed_token->get_id();

		$incoming_pm = (object) [
			'id'                            => 'pm_new',
			'type'                          => WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::CARD => (object) [
				'brand'         => 'visa',
				'display_brand' => 'visa',
				'exp_month'     => 2,
				'exp_year'      => 2028,
				'last4'         => '4242',
				'fingerprint'   => 'F_abc',
			],
		];

		$customer = new WC_Stripe_Customer( $user_id );

		$reflection = new ReflectionMethod( WC_Stripe_Payment_Tokens::class, 'add_token_to_user' );
		$reflection->setAccessible( true );
		$result = $reflection->invoke( $this->stripe_payment_tokens, $incoming_pm, $customer, [] );

		$this->assertSame( $seed_token_id, $result->get_id(), 'Duplicate-matched token should be reused, not recreated.' );
		$this->assertSame( 'pm_new', $result->get_token() );
		$this->assertEquals( '02', $result->get_expiry_month() );
		$this->assertEquals( '2028', $result->get_expiry_year() );
		$this->assertSame( 'visa', $result->get_card_type() );
		$this->assertSame( '4242', $result->get_last4() );

		$reloaded = WC_Payment_Tokens::get( $seed_token_id );
		$this->assertSame( 'pm_new', $reloaded->get_token(), 'Refreshed PM id must be persisted.' );
		$this->assertEquals( '02', $reloaded->get_expiry_month(), 'Refreshed expiry_month must be persisted.' );
		$this->assertEquals( '2028', $reloaded->get_expiry_year(), 'Refreshed expiry_year must be persisted.' );
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
			WC_Payment_Token_CC::class         => [
				'class'    => WC_Payment_Token_CC::class,
				'expected' => WC_Stripe_Payment_Token_CC::class,
			],
			WC_Payment_Token_CashApp::class    => [
				'class'    => WC_Payment_Token_CashApp::class,
				'expected' => WC_Payment_Token_CashApp::class,
			],
			WC_Payment_Token_SEPA::class       => [
				'class'    => WC_Payment_Token_SEPA::class,
				'expected' => WC_Payment_Token_SEPA::class,
			],
			WC_Payment_Token_Link::class       => [
				'class'    => WC_Payment_Token_Link::class,
				'expected' => WC_Payment_Token_Link::class,
			],
			WC_Payment_Token_ACH::class        => [
				'class'    => 'test',
				'expected' => WC_Payment_Token_ACH::class,
				'type'     => WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID,
			],
			WC_Payment_Token_ACSS::class       => [
				'class'    => 'test',
				'expected' => WC_Payment_Token_ACSS::class,
				'type'     => WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID,
			],
			WC_Payment_Token_Becs_Debit::class => [
				'class'    => 'test',
				'expected' => WC_Payment_Token_Becs_Debit::class,
				'type'     => WC_Stripe_UPE_Payment_Method_Becs_Debit::STRIPE_ID,
			],
			'Klarna with overridden class'     => [
				'class'    => 'test_klarna',
				'expected' => 'test_klarna',
				'type'     => \WC_Stripe_UPE_Payment_Method_Klarna::STRIPE_ID,
			],
			'Klarna with default class'        => [
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
			'Non-Stripe payment token'                          => [
				'payment_token'   => new \WC_Payment_Token_CC(),
				'expected_result' => [],
			],
			'Non-Stripe payment token with SEPA type collision' => [
				'payment_token'   => $mock_sepa_collision_token,
				'expected_result' => [],
			],
			'Stripe payment token with unhandled type'          => [
				'payment_token'   => new \WC_Stripe_Payment_Token_CC(),
				'expected_result' => [],
			],
			'SEPA token'                                        => [
				'payment_token'   => $sepa_token,
				'expected_result' => [
					'last4' => '1234',
					'brand' => 'SEPA IBAN',
				],
			],
			'BACS Debit token'                                  => [
				'payment_token'   => $bacs_debit_token,
				'expected_result' => [
					'last4' => '2345',
					'brand' => 'Bacs Direct Debit',
				],
			],
			'CashApp token'                                     => [
				'payment_token'   => new \WC_Payment_Token_CashApp(),
				'expected_result' => [
					'brand' => 'Cash App Pay',
				],
			],
			'ACH token'                                         => [
				'payment_token'   => $ach_token,
				'expected_result' => [
					'last4' => '3456',
					'brand' => 'Test ACH Bank',
				],
			],
			'ACSS token'                                        => [
				'payment_token'   => $acss_token,
				'expected_result' => [
					'last4' => '4567',
					'brand' => 'Test ACSS Bank',
				],
			],
			'BECS Debit token'                                  => [
				'payment_token'   => $becs_debit_token,
				'expected_result' => [
					'last4' => '5678',
					'brand' => 'BECS Direct Debit',
				],
			],
			'Link token'                                        => [
				'payment_token'   => $link_token,
				'expected_result' => [
					'brand' => 'Stripe Link (link.test@example.com)',
				],
			],
			'Amazon Pay token'                                  => [
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

	// =========================================================================
	// Tests for woocommerce_get_customer_payment_tokens
	// =========================================================================

	/**
	 * When the user is not logged in, the tokens array must be returned unchanged
	 * (no Stripe API call or gateway access is needed).
	 */
	public function test_woocommerce_get_customer_payment_tokens_returns_unchanged_when_not_logged_in(): void {
		wp_set_current_user( 0 );

		$initial_tokens = [ 'mock_token_key' => new WC_Payment_Token_CC() ];

		$result = $this->stripe_payment_tokens->woocommerce_get_customer_payment_tokens( $initial_tokens, 1, WC_Stripe_UPE_Payment_Gateway::ID );

		$this->assertSame( $initial_tokens, $result );
	}

	/**
	 * When the gateway_id is not in the list of reusable UPE gateways the tokens
	 * array must be returned unchanged (early-exit before any gateway/API access).
	 */
	public function test_woocommerce_get_customer_payment_tokens_returns_unchanged_for_non_reusable_gateway(): void {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$initial_tokens = [ 'mock_token_key' => new WC_Payment_Token_CC() ];

		$result = $this->stripe_payment_tokens->woocommerce_get_customer_payment_tokens( $initial_tokens, $user_id, 'non_stripe_gateway' );

		$this->assertSame( $initial_tokens, $result );
	}

	/**
	 * When the number of existing tokens has reached the posts_per_page limit the
	 * tokens array must be returned unchanged (no sync happens).
	 */
	public function test_woocommerce_get_customer_payment_tokens_returns_unchanged_when_at_token_limit(): void {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$limit          = (int) get_option( 'posts_per_page', 10 );
		$initial_tokens = array_fill( 0, $limit, new WC_Payment_Token_CC() );

		$result = $this->stripe_payment_tokens->woocommerce_get_customer_payment_tokens( $initial_tokens, $user_id, WC_Stripe_UPE_Payment_Gateway::ID );

		$this->assertSame( $initial_tokens, $result );
	}

	/**
	 * When OCS is enabled, calling woocommerce_get_customer_payment_tokens with the
	 * main 'stripe' gateway ID must also return tokens that are stored under
	 * sub-gateway IDs (e.g. 'stripe_cashapp'), because the OCS element handles all
	 * payment methods in one consolidated block.
	 *
	 * The test sets up:
	 * - A logged-in WordPress user with a Stripe customer ID.
	 * - A CashApp payment token stored in WooCommerce under 'stripe_cashapp'.
	 * - A mocked Stripe API (via pre_http_request) that confirms the token still
	 *   exists in Stripe so it is NOT pruned during the sync step.
	 * - A mocked main gateway with OCS enabled.
	 *
	 * Expected: the CashApp token appears in the result even though the query was
	 * issued against the main 'stripe' gateway ID.
	 */
	public function test_woocommerce_get_customer_payment_tokens_includes_sub_gateway_tokens_when_ocs_enabled(): void {
		// Create and log-in a user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		// Give the user a Stripe customer ID so WC_Stripe_Customer::get_id() returns a non-empty value,
		// allowing get_all_payment_methods() to proceed to the (mocked) API call.
		$stripe_customer_id = 'cus_test_ocs_' . $user_id;
		update_user_option( $user_id, '_stripe_customer_id', $stripe_customer_id, false );

		// Store a CashApp token in WooCommerce under the 'stripe_cashapp' sub-gateway.
		$cashapp_pm_id = 'pm_cashapp_test_ocs';
		$cashapp_token = new WC_Payment_Token_CashApp();
		$cashapp_token->set_token( $cashapp_pm_id );
		$cashapp_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_Payment_Methods::CASHAPP_PAY ] );
		$cashapp_token->set_cashtag( '$testcashtag' );
		$cashapp_token->set_user_id( $user_id );
		$cashapp_token->save();

		// Mock the Stripe API to return the CashApp payment method (prevents the token being
		// treated as orphaned and deleted during the sync step).
		$stripe_api_response = [
			'data'     => [
				[
					'id'      => $cashapp_pm_id,
					'type'    => WC_Stripe_Payment_Methods::CASHAPP_PAY,
					'cashapp' => [ 'cashtag' => '$testcashtag' ],
				],
			],
			'has_more' => false,
		];

		$mock_http_request = function ( $preempt, $request_args, $url ) use ( $stripe_api_response ) {
			if ( false === strpos( $url, 'payment_methods' ) ) {
				return $preempt;
			}
			return [
				'headers'  => [],
				'body'     => wp_json_encode( $stripe_api_response ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
			];
		};
		add_filter( 'pre_http_request', $mock_http_request, 10, 3 );

		// Enable OCS on the mock main gateway.
		// Also populate payment_methods with a CashApp stub so the sub-gateway sync (triggered by
		// the second WC_Stripe_Payment_Tokens instance created during plugin init) does not treat
		// the stored token as orphaned and delete it before the OCS merge can return it.
		$mock_cashapp_pm = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_Cash_App_Pay::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_enabled_at_checkout', 'is_enabled' ] )
			->getMock();
		$mock_cashapp_pm->method( 'is_enabled_at_checkout' )->willReturn( true );
		$mock_cashapp_pm->method( 'is_enabled' )->willReturn( true );

		$mock_gateway = $this->get_mock_gateway( true );
		$mock_gateway->payment_methods[ WC_Stripe_Payment_Methods::CASHAPP_PAY ] = $mock_cashapp_pm;
		$this->set_main_gateway( $mock_gateway );

		try {
			$result = $this->stripe_payment_tokens->woocommerce_get_customer_payment_tokens( [], $user_id, WC_Stripe_UPE_Payment_Gateway::ID );
		} finally {
			remove_filter( 'pre_http_request', $mock_http_request, 10 );
		}

		// The CashApp token should appear in the result because OCS sub-gateway tokens are merged.
		$result_token_ids = array_map( fn( $token ) => $token->get_token(), $result );
		$this->assertContains( $cashapp_pm_id, $result_token_ids, 'OCS-enabled query for stripe gateway should include sub-gateway (CashApp) tokens.' );
	}

	/**
	 * When OCS is disabled, calling woocommerce_get_customer_payment_tokens with
	 * the main 'stripe' gateway ID must NOT include tokens stored under sub-gateway
	 * IDs (e.g. 'stripe_cashapp').
	 */
	public function test_woocommerce_get_customer_payment_tokens_excludes_sub_gateway_tokens_when_ocs_disabled(): void {
		// Create and log-in a user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		// Store a CashApp token under the 'stripe_cashapp' sub-gateway.
		$cashapp_pm_id = 'pm_cashapp_test_no_ocs';
		$cashapp_token = new WC_Payment_Token_CashApp();
		$cashapp_token->set_token( $cashapp_pm_id );
		$cashapp_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_Payment_Methods::CASHAPP_PAY ] );
		$cashapp_token->set_cashtag( '$testcashtag' );
		$cashapp_token->set_user_id( $user_id );
		$cashapp_token->save();

		// Disable OCS on the mock main gateway. The user has no Stripe customer ID so the
		// API will not be called (WC_Stripe_Customer::get_id() returns empty → returns []).
		$this->set_main_gateway( $this->get_mock_gateway( false ) );

		// Call with no initial tokens so there is nothing to sync/delete.
		$result = $this->stripe_payment_tokens->woocommerce_get_customer_payment_tokens( [], $user_id, WC_Stripe_UPE_Payment_Gateway::ID );

		$result_token_ids = array_map( fn( $token ) => $token->get_token(), $result );
		$this->assertNotContains( $cashapp_pm_id, $result_token_ids, 'OCS-disabled query for stripe gateway should NOT include sub-gateway (CashApp) tokens.' );
	}

	/**
	 * When OCS is enabled but a sub-gateway payment method is disabled (e.g. Klarna toggled
	 * off in settings), its stored tokens must NOT appear in the results.
	 */
	public function test_woocommerce_get_customer_payment_tokens_excludes_disabled_sub_gateway_tokens_when_ocs_enabled(): void {
		// Create and log-in a user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		// Store a CashApp token in WooCommerce under the 'stripe_cashapp' sub-gateway.
		$cashapp_pm_id = 'pm_cashapp_disabled_ocs';
		$cashapp_token = new WC_Payment_Token_CashApp();
		$cashapp_token->set_token( $cashapp_pm_id );
		$cashapp_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_Payment_Methods::CASHAPP_PAY ] );
		$cashapp_token->set_cashtag( '$testcashtag' );
		$cashapp_token->set_user_id( $user_id );
		$cashapp_token->save();

		// Mock the CashApp payment method as disabled (is_enabled() returns false).
		$mock_cashapp_pm = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_Cash_App_Pay::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_enabled_at_checkout', 'is_enabled' ] )
			->getMock();
		$mock_cashapp_pm->method( 'is_enabled_at_checkout' )->willReturn( true );
		$mock_cashapp_pm->method( 'is_enabled' )->willReturn( false );

		$mock_gateway = $this->get_mock_gateway( true );
		$mock_gateway->payment_methods[ WC_Stripe_Payment_Methods::CASHAPP_PAY ] = $mock_cashapp_pm;
		$this->set_main_gateway( $mock_gateway );

		// No Stripe customer ID so the API is not called and no sync occurs.
		$result = $this->stripe_payment_tokens->woocommerce_get_customer_payment_tokens( [], $user_id, WC_Stripe_UPE_Payment_Gateway::ID );

		$result_token_ids = array_map( fn( $token ) => $token->get_token(), $result );
		$this->assertNotContains( $cashapp_pm_id, $result_token_ids, 'Disabled sub-gateway tokens must not appear when OCS is enabled.' );
	}

	/**
	 * When OCS is enabled and a sub-gateway method is toggled on but not available
	 * at checkout (e.g. currency incompatibility), its stored tokens must NOT appear
	 * in the merged results.
	 */
	public function test_woocommerce_get_customer_payment_tokens_excludes_unavailable_sub_gateway_tokens_when_ocs_enabled(): void {
		// Create and log-in a user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		// Store a CashApp token in WooCommerce under the 'stripe_cashapp' sub-gateway.
		$cashapp_pm_id = 'pm_cashapp_unavailable_ocs';
		$cashapp_token = new WC_Payment_Token_CashApp();
		$cashapp_token->set_token( $cashapp_pm_id );
		$cashapp_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_Payment_Methods::CASHAPP_PAY ] );
		$cashapp_token->set_cashtag( '$testcashtag' );
		$cashapp_token->set_user_id( $user_id );
		$cashapp_token->save();

		// Mock the CashApp payment method as enabled (toggle on) but not available at checkout
		// (e.g. currency not supported), simulating the classic-checkout regression reported
		// in https://github.com/woocommerce/woocommerce-gateway-stripe/pull/5146.
		$mock_cashapp_pm = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_Cash_App_Pay::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_enabled_at_checkout', 'is_enabled' ] )
			->getMock();
		$mock_cashapp_pm->method( 'is_enabled' )->willReturn( true );
		$mock_cashapp_pm->method( 'is_enabled_at_checkout' )->willReturn( false );

		$mock_gateway = $this->get_mock_gateway( true );
		$mock_gateway->payment_methods[ WC_Stripe_Payment_Methods::CASHAPP_PAY ] = $mock_cashapp_pm;
		$this->set_main_gateway( $mock_gateway );

		// No Stripe customer ID so the API is not called and no sync occurs.
		$result = $this->stripe_payment_tokens->woocommerce_get_customer_payment_tokens( [], $user_id, WC_Stripe_UPE_Payment_Gateway::ID );

		$result_token_ids = array_map( fn( $token ) => $token->get_token(), $result );
		$this->assertNotContains( $cashapp_pm_id, $result_token_ids, 'Sub-gateway tokens for a method not available at checkout must not appear when OCS is enabled.' );
	}

	// =========================================================================
	// Tests for get_account_saved_payment_methods_list_item OCS gateway remapping
	// =========================================================================

	/**
	 * When OCS is enabled, sub-gateway tokens (e.g. from 'stripe_sepa_debit') must
	 * have their gateway ID remapped to the main 'stripe' gateway in the list item
	 * so that the blocks checkout PaymentUtils can find them.
	 */
	public function test_get_account_saved_payment_methods_list_item_remaps_gateway_when_ocs_enabled(): void {
		$this->set_main_gateway( $this->get_mock_gateway( true ) );

		$sepa_token = new WC_Payment_Token_SEPA();
		$sepa_token->set_last4( '1234' );
		$sepa_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_Payment_Methods::SEPA_DEBIT ] );
		$sepa_token->set_token( 'pm_sepa_remap_test' );
		$sepa_token->set_user_id( 1 );
		$sepa_token->save();

		$result = $this->stripe_payment_tokens->get_account_saved_payment_methods_list_item( [ 'method' => [] ], $sepa_token );

		$this->assertArrayHasKey( 'gateway', $result['method'], 'Gateway key should be set when OCS is enabled and token is from a sub-gateway.' );
		$this->assertSame( WC_Stripe_UPE_Payment_Gateway::ID, $result['method']['gateway'], 'Gateway should be remapped to the main stripe gateway when OCS is enabled.' );
	}

	/**
	 * When OCS is enabled but the token already belongs to the main 'stripe' gateway,
	 * no remapping should happen.
	 */
	public function test_get_account_saved_payment_methods_list_item_does_not_remap_main_gateway_when_ocs_enabled(): void {
		$this->set_main_gateway( $this->get_mock_gateway( true ) );

		$cashapp_token = new WC_Payment_Token_CashApp();
		$cashapp_token->set_cashtag( '$test' );
		$cashapp_token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$cashapp_token->set_token( 'pm_cc_main_gateway' );
		$cashapp_token->set_user_id( 1 );
		$cashapp_token->save();

		$result = $this->stripe_payment_tokens->get_account_saved_payment_methods_list_item( [ 'method' => [] ], $cashapp_token );

		$this->assertArrayNotHasKey( 'gateway', $result['method'], 'Gateway should NOT be remapped for tokens already on the main stripe gateway.' );
	}

	/**
	 * When OCS is disabled, sub-gateway tokens must NOT have their gateway ID
	 * remapped — the gateway key must not be set.
	 */
	public function test_get_account_saved_payment_methods_list_item_does_not_remap_gateway_when_ocs_disabled(): void {
		$this->set_main_gateway( $this->get_mock_gateway( false ) );

		$sepa_token = new WC_Payment_Token_SEPA();
		$sepa_token->set_last4( '1234' );
		$sepa_token->set_gateway_id( WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ WC_Stripe_Payment_Methods::SEPA_DEBIT ] );
		$sepa_token->set_token( 'pm_sepa_no_remap_test' );
		$sepa_token->set_user_id( 1 );
		$sepa_token->save();

		$result = $this->stripe_payment_tokens->get_account_saved_payment_methods_list_item( [ 'method' => [] ], $sepa_token );

		$this->assertArrayNotHasKey( 'gateway', $result['method'], 'Gateway should NOT be remapped when OCS is disabled.' );
	}
}
