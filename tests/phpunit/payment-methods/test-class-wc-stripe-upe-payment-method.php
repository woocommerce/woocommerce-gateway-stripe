<?php
/**
 * Unit tests for UPE payment methods
 */
class WC_Stripe_UPE_Payment_Method_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Array of mocked UPE payment methods.
	 *
	 * @var array
	 */
	private $mock_payment_methods = [];

	/**
	 * Base template for Stripe card payment method.
	 */
	const MOCK_CARD_PAYMENT_METHOD_TEMPLATE = [
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

	/**
	 * Base template for Stripe link payment method.
	 */
	const MOCK_LINK_PAYMENT_METHOD_TEMPLATE = [
		'id'   => 'pm_mock_payment_method_id',
		'type' => 'link',
		'link' => [
			'email' => 'test@test.com',
		],
	];

	/**
	 * Base template for Stripe Amazon Pay payment method.
	 */
	const MOCK_AMAZON_PAY_PAYMENT_METHOD_TEMPLATE = [
		'id'              => 'pm_mock_payment_method_id',
		'type'            => 'amazon_pay',
		'billing_details' => [
			'email' => 'test@test.com',
		],
	];

	/**
	 * Base template for Stripe ACH payment method.
	 */
	const MOCK_ACH_PAYMENT_METHOD_TEMPLATE = [
		'id'                           => 'pm_mock_payment_method_id',
		'type'                         => WC_Stripe_Payment_Methods::ACH,
		WC_Stripe_Payment_Methods::ACH => [
			'last4'        => '6789',
			'bank_name'    => 'Test Bank',
			'account_type' => 'checking',
			'fingerprint'  => 'fp_test_123',
		],
	];

	/**
	 * Base template for Stripe ACSS payment method.
	 */
	const MOCK_ACSS_PAYMENT_METHOD_TEMPLATE = [
		'id'                                  => 'pm_mock_payment_method_id',
		'type'                                => WC_Stripe_Payment_Methods::ACSS_DEBIT,
		WC_Stripe_Payment_Methods::ACSS_DEBIT => [
			'last4'       => '4321',
			'bank_name'   => 'Test Bank',
			'fingerprint' => 'fingerprint_test',
		],
	];

	/**
	 * Base template for Stripe SEPA payment method.
	 */
	const MOCK_SEPA_PAYMENT_METHOD_TEMPLATE = [
		'id'                                  => 'pm_mock_payment_method_id',
		'type'                                => WC_Stripe_Payment_Methods::SEPA_DEBIT,
		WC_Stripe_Payment_Methods::SEPA_DEBIT => [
			'bank_code'      => '00000000',
			'branch_code'    => '',
			'country'        => 'DE',
			'fingerprint'    => 'Fxxxxxxxxxxxxxxx',
			'generated_from' => [
				'charge'        => null,
				'setup_attempt' => null,
			],
			'last4'          => '4242',
		],
	];

	/**
	 * Base template for Stripe Cash App Pay payment method.
	 */
	const MOCK_CASH_APP_PAYMENT_METHOD_TEMPLATE = [
		'id'                                   => 'pm_mock_payment_method_id',
		'type'                                 => WC_Stripe_Payment_Methods::CASHAPP_PAY,
		WC_Stripe_Payment_Methods::CASHAPP_PAY => [
			'cashtag'  => '$test_cashtag',
			'buyer_id' => 'test_buyer_id',
		],
	];

	/**
	 * Base template for Stripe Cash App Pay payment method.
	 */
	const MOCK_BACS_PAYMENT_METHOD_TEMPLATE = [
		'id'                                  => 'pm_mock_payment_method_id',
		'type'                                => WC_Stripe_Payment_Methods::BACS_DEBIT,
		WC_Stripe_Payment_Methods::BACS_DEBIT => [
			'last4'       => '4321',
			'fingerprint' => 'F1ng3rpr1n7',
		],
	];

	/**
	 * Base template for Stripe AU BECS Debit Pay payment method.
	 */
	const MOCK_BECS_DEBIT_PAYMENT_METHOD_TEMPLATE = [
		'id'                                        => 'pm_mock_payment_method_id',
		'type'                                      => WC_Stripe_Payment_Methods::BECS_DEBIT,
		WC_Stripe_Payment_Methods::BECS_DEBIT        => [
			'last4'       => '4321',
			'fingerprint' => 'F1ng3rpr1n7',
		],
	];

	/**
	 * Mock capabilities object from Stripe response--all inactive.
	 */
	const MOCK_INACTIVE_CAPABILITIES_RESPONSE = [
		'alipay_payments'              => 'inactive',
		'bancontact_payments'          => 'inactive',
		'blik_payments'                => 'inactive',
		'card_payments'                => 'inactive',
		'eps_payments'                 => 'inactive',
		'giropay_payments'             => 'inactive',
		'klarna_payments'              => 'inactive',
		'affirm_payments'              => 'inactive',
		'clearpay_afterpay_payments'   => 'inactive',
		'ideal_payments'               => 'inactive',
		'p24_payments'                 => 'inactive',
		'sepa_debit_payments'          => 'inactive',
		'sofort_payments'              => 'inactive',
		'transfers'                    => 'inactive',
		'multibanco_payments'          => 'inactive',
		'boleto_payments'              => 'inactive',
		'oxxo_payments'                => 'inactive',
		'link_payments'                => 'inactive',
		'wechat_pay_payments'          => 'inactive',
		'us_bank_account_ach_payments' => 'inactive',
		'bacs_debit_payments'          => 'inactive',
		'au_becs_debit_payments'       => 'inactive',
	];

	/**
	 * Mock capabilities object from Stripe response--all active.
	 */
	const MOCK_ACTIVE_CAPABILITIES_RESPONSE = [
		'alipay_payments'              => 'active',
		'amazon_pay_payments'          => 'active',
		'bancontact_payments'          => 'active',
		'blik_payments'                => 'active',
		'card_payments'                => 'active',
		'eps_payments'                 => 'active',
		'giropay_payments'             => 'active',
		'klarna_payments'              => 'active',
		'affirm_payments'              => 'active',
		'clearpay_afterpay_payments'   => 'active',
		'ideal_payments'               => 'active',
		'p24_payments'                 => 'active',
		'sepa_debit_payments'          => 'active',
		'sofort_payments'              => 'active',
		'transfers'                    => 'active',
		'multibanco_payments'          => 'active',
		'boleto_payments'              => 'active',
		'oxxo_payments'                => 'active',
		'link_payments'                => 'active',
		'cashapp_payments'             => 'active',
		'wechat_pay_payments'          => 'active',
		'acss_debit_payments'          => 'active',
		'us_bank_account_ach_payments' => 'active',
		'bacs_debit_payments'          => 'active',
		'au_becs_debit_payments'       => 'active',
	];

	/**
	 * Initial setup
	 */
	public function set_up() {
		parent::set_up();
		WC_Stripe_Helper::delete_main_stripe_settings();
		update_option( WC_Stripe_Feature_Flags::LPM_ACH_FEATURE_FLAG_NAME, 'yes' );
		update_option( WC_Stripe_Feature_Flags::LPM_ACSS_FEATURE_FLAG_NAME, 'yes' );
		update_option( WC_Stripe_Feature_Flags::LPM_BACS_FEATURE_FLAG_NAME, 'yes' );
		update_option( WC_Stripe_Feature_Flags::LPM_BECS_DEBIT_FEATURE_FLAG_NAME, 'yes' );
		$this->reset_payment_method_mocks();
	}

	public function tear_down() {
		WC_Stripe_Helper::delete_main_stripe_settings();
		delete_option( WC_Stripe_Feature_Flags::LPM_ACH_FEATURE_FLAG_NAME );
		delete_option( WC_Stripe_Feature_Flags::LPM_ACSS_FEATURE_FLAG_NAME );
		delete_option( WC_Stripe_Feature_Flags::LPM_BACS_FEATURE_FLAG_NAME );
		delete_option( WC_Stripe_Feature_Flags::LPM_BECS_DEBIT_FEATURE_FLAG_NAME );
		parent::tear_down();
	}

	/**
	 * Reset mock_payment_methods to array of mocked payment methods
	 * with no mocked expectations for methods.
	 */
	private function reset_payment_method_mocks( $exclude_methods = [] ) {
		$this->mock_payment_methods = [];

		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $payment_method_class ) {
			$mocked_methods = [
				'get_capabilities_response',
				'get_woocommerce_currency',
				'is_subscription_item_in_cart',
				'get_current_order_amount',
				'is_inside_currency_limits',
			];

			// Remove any methods that should not be mocked.
			$mocked_methods = array_diff( $mocked_methods, $exclude_methods );

			$mocked_payment_method = $this->getMockBuilder( $payment_method_class )
				->setMethods( $mocked_methods )
				->getMock();

			$this->mock_payment_methods[ $mocked_payment_method->get_id() ] = $mocked_payment_method;
		}
	}

	/**
	 * Helper function to mock subscriptions for internal UPE payment methods.
	 *
	 * @param string $function_name Name of function to be mocked.
	 * @param mixed $value Mocked value for function.
	 * @param bool $overwrite_mocks Overwrite mocks to remove any existing mocked functions in mock_payment_methods;
	 */
	private function set_mock_payment_method_return_value( $function_name, $value, $overwrite_mocks = false ) {
		if ( $overwrite_mocks ) {
			$this->reset_payment_method_mocks();
		}

		foreach ( $this->mock_payment_methods as $mock_payment_method ) {
			$mock_payment_method->expects( $this->any() )
				->method( $function_name )
				->will(
					$this->returnValue( $value )
				);
		}
	}

	/**
	 * Convert response array to object.
	 */
	private function array_to_object( $array ) {
		return json_decode( wp_json_encode( $array ) );
	}

	/**
	 * Function to be used with array_map
	 * to return array of payment method IDs.
	 */
	private function get_id( $payment_method ) {
		return $payment_method->get_id();
	}

	/**
	 * Tests basic properties for payment methods.
	 */
	public function test_payment_methods_show_correct_default_outputs() {
		$mock_alipay_details     = [
			'type' => WC_Stripe_Payment_Methods::ALIPAY,
		];
		$mock_blik_details       = [
			'type' => WC_Stripe_Payment_Methods::BLIK,
		];
		$mock_p24_details        = [
			'type' => WC_Stripe_Payment_Methods::P24,
		];
		$mock_eps_details        = [
			'type' => WC_Stripe_Payment_Methods::EPS,
		];
		$mock_sepa_details       = [
			'type' => WC_Stripe_Payment_Methods::SEPA_DEBIT,
		];
		$mock_sofort_details     = [
			'type' => WC_Stripe_Payment_Methods::SOFORT,
		];
		$mock_bancontact_details = [
			'type' => WC_Stripe_Payment_Methods::BANCONTACT,
		];
		$mock_ideal_details      = [
			'type' => WC_Stripe_Payment_Methods::IDEAL,
		];
		$mock_boleto_details     = [
			'type' => WC_Stripe_Payment_Methods::BOLETO,
		];
		$mock_multibanco_details = [
			'type' => WC_Stripe_Payment_Methods::MULTIBANCO,
		];
		$mock_oxxo_details       = [
			'type' => WC_Stripe_Payment_Methods::OXXO,
		];
		$mock_wechat_pay_details = [
			'type' => WC_Stripe_Payment_Methods::WECHAT_PAY,
		];
		$mock_acss_details       = [
			'type' => WC_Stripe_Payment_Methods::ACSS_DEBIT,
		];
		$mock_becs_debit_details = [
			'type' => WC_Stripe_Payment_Methods::BECS_DEBIT,
		];

		$blik_method       = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::BLIK ];
		$card_method       = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::CARD ];
		$alipay_method     = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::ALIPAY ];
		$p24_method        = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::P24 ];
		$eps_method        = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::EPS ];
		$sepa_method       = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::SEPA_DEBIT ];
		$sofort_method     = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::SOFORT ];
		$bancontact_method = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::BANCONTACT ];
		$ideal_method      = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::IDEAL ];
		$multibanco_method = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::MULTIBANCO ];
		$boleto_method     = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::BOLETO ];
		$oxxo_method       = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::OXXO ];
		$wechat_pay_method = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::WECHAT_PAY ];
		$ach_method        = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::ACH ];
		$acss_method       = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::ACSS_DEBIT ];
		$becs_debit_method = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::BECS_DEBIT ];

		$this->assertEquals( WC_Stripe_Payment_Methods::BLIK, $blik_method->get_id() );
		$this->assertEquals( 'BLIK', $blik_method->get_label() );
		$this->assertEquals( 'BLIK', $blik_method->get_title() );
		$this->assertEquals( 'BLIK', $blik_method->get_title( $mock_blik_details ) );
		$this->assertFalse( $blik_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::BLIK, $blik_method->get_retrievable_type() );
		$this->assertEquals(
			'<strong>Test mode:</strong> use any 6-digit number to authorize payment.',
			$blik_method->get_testing_instructions()
		);

		$this->assertEquals( WC_Stripe_Payment_Methods::CARD, $card_method->get_id() );
		$this->assertEquals( 'Credit / Debit Card', $card_method->get_label() );
		$this->assertEquals( 'Credit / Debit Card', $card_method->get_title() );
		$this->assertTrue( $card_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::CARD, $card_method->get_retrievable_type() );
		$this->assertEquals(
			'<strong>Test mode:</strong> use the test VISA card 4242424242424242 with any expiry date and CVC. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing" target="_blank">here</a>.',
			$card_method->get_testing_instructions()
		);

		$this->assertEquals( WC_Stripe_Payment_Methods::ALIPAY, $alipay_method->get_id() );
		$this->assertEquals( 'Alipay', $alipay_method->get_label() );
		$this->assertEquals( 'Alipay', $alipay_method->get_title() );
		$this->assertEquals( 'Alipay', $alipay_method->get_title( $mock_alipay_details ) );
		$this->assertFalse( $alipay_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::ALIPAY, $alipay_method->get_retrievable_type() );

		$this->assertEquals( WC_Stripe_Payment_Methods::P24, $p24_method->get_id() );
		$this->assertEquals( 'Przelewy24', $p24_method->get_label() );
		$this->assertEquals( 'Przelewy24', $p24_method->get_title() );
		$this->assertEquals( 'Przelewy24', $p24_method->get_title( $mock_p24_details ) );
		$this->assertFalse( $p24_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::P24, $p24_method->get_retrievable_type() );
		$this->assertEquals( '', $p24_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::EPS, $eps_method->get_id() );
		$this->assertEquals( 'EPS', $eps_method->get_label() );
		$this->assertEquals( 'EPS', $eps_method->get_title() );
		$this->assertEquals( 'EPS', $eps_method->get_title( $mock_eps_details ) );
		$this->assertFalse( $eps_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::EPS, $eps_method->get_retrievable_type() );
		$this->assertEquals( '', $eps_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::SEPA_DEBIT, $sepa_method->get_id() );
		$this->assertEquals( 'SEPA Direct Debit', $sepa_method->get_label() );
		$this->assertEquals( 'SEPA Direct Debit', $sepa_method->get_title() );
		$this->assertEquals( 'SEPA Direct Debit', $sepa_method->get_title( $mock_sepa_details ) );
		$this->assertTrue( $sepa_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::SEPA_DEBIT, $sepa_method->get_retrievable_type() );
		$this->assertEquals(
			'<strong>Test mode:</strong> use the test account number AT611904300234573201. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a href="https://docs.stripe.com/testing?payment-method=sepa-direct-debit#non-card-payments" target="_blank">here</a>.',
			$sepa_method->get_testing_instructions()
		);

		$this->assertEquals( WC_Stripe_Payment_Methods::SOFORT, $sofort_method->get_id() );
		$this->assertEquals( 'Sofort', $sofort_method->get_label() );
		$this->assertEquals( 'Sofort', $sofort_method->get_title() );
		$this->assertEquals( 'Sofort', $sofort_method->get_title( $mock_sofort_details ) );
		$this->assertTrue( $sofort_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::SEPA_DEBIT, $sofort_method->get_retrievable_type() );
		$this->assertEquals( '', $sofort_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::BANCONTACT, $bancontact_method->get_id() );
		$this->assertEquals( 'Bancontact', $bancontact_method->get_label() );
		$this->assertEquals( 'Bancontact', $bancontact_method->get_title() );
		$this->assertEquals( 'Bancontact', $bancontact_method->get_title( $mock_bancontact_details ) );
		$this->assertFalse( $bancontact_method->is_reusable() ); // Bancontact is not reusable if "SEPA tokens for other methods" setting is not enabled.
		$this->assertEquals( WC_Stripe_Payment_Methods::SEPA_DEBIT, $bancontact_method->get_retrievable_type() );
		$this->assertEquals( '', $bancontact_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::IDEAL, $ideal_method->get_id() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_label() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title( $mock_ideal_details ) );
		$this->assertFalse( $ideal_method->is_reusable() ); // iDEAL is not reusable if "SEPA tokens for other methods" setting is not enabled.
		$this->assertEquals( WC_Stripe_Payment_Methods::SEPA_DEBIT, $ideal_method->get_retrievable_type() );
		$this->assertEquals( '', $ideal_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::MULTIBANCO, $multibanco_method->get_id() );
		$this->assertEquals( 'Multibanco', $multibanco_method->get_label() );
		$this->assertEquals( 'Multibanco', $multibanco_method->get_title() );
		$this->assertEquals( 'Multibanco', $multibanco_method->get_title( $mock_multibanco_details ) );
		$this->assertFalse( $multibanco_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::MULTIBANCO, $multibanco_method->get_retrievable_type() );
		$this->assertEquals( '', $multibanco_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::BOLETO, $boleto_method->get_id() );
		$this->assertEquals( 'Boleto', $boleto_method->get_label() );
		$this->assertEquals( 'Boleto', $boleto_method->get_title() );
		$this->assertEquals( 'Boleto', $boleto_method->get_title( $mock_boleto_details ) );
		$this->assertFalse( $boleto_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::BOLETO, $boleto_method->get_retrievable_type() );
		$this->assertEquals( '', $boleto_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::OXXO, $oxxo_method->get_id() );
		$this->assertEquals( 'OXXO', $oxxo_method->get_label() );
		$this->assertEquals( 'OXXO', $oxxo_method->get_title() );
		$this->assertEquals( 'OXXO', $oxxo_method->get_title( $mock_oxxo_details ) );
		$this->assertFalse( $oxxo_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::OXXO, $oxxo_method->get_retrievable_type() );
		$this->assertEquals( '', $oxxo_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::WECHAT_PAY, $wechat_pay_method->get_id() );
		$this->assertEquals( 'WeChat Pay', $wechat_pay_method->get_label() );
		$this->assertEquals( 'WeChat Pay', $wechat_pay_method->get_title() );
		$this->assertEquals( 'WeChat Pay', $wechat_pay_method->get_title( $mock_wechat_pay_details ) );
		$this->assertFalse( $wechat_pay_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::WECHAT_PAY, $wechat_pay_method->get_retrievable_type() );
		$this->assertEquals( '', $wechat_pay_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::ACH, $ach_method->get_id() );
		$this->assertEquals( 'ACH Direct Debit', $ach_method->get_label() );
		$this->assertEquals( 'ACH Direct Debit', $ach_method->get_title() );
		$this->assertTrue( $ach_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::ACH, $ach_method->get_retrievable_type() );
		$this->assertEquals( '', $ach_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::ACSS_DEBIT, $acss_method->get_id() );
		$this->assertEquals( 'Pre-Authorized Debit', $acss_method->get_label() );
		$this->assertEquals( 'Pre-Authorized Debit', $acss_method->get_title() );
		$this->assertEquals( 'Pre-Authorized Debit', $acss_method->get_title( $mock_acss_details ) );
		$this->assertTrue( $acss_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::ACSS_DEBIT, $acss_method->get_retrievable_type() );
		$this->assertEquals( '', $acss_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::BECS_DEBIT, $becs_debit_method->get_id() );
		$this->assertEquals( 'BECS Direct Debit', $becs_debit_method->get_label() );
		$this->assertEquals( 'BECS Direct Debit', $becs_debit_method->get_title() );
		$this->assertEquals( 'BECS Direct Debit', $becs_debit_method->get_title( $mock_becs_debit_details ) );
		$this->assertTrue( $becs_debit_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::BECS_DEBIT, $becs_debit_method->get_retrievable_type() );
		$this->assertEquals( '', $becs_debit_method->get_testing_instructions() );
	}

	/**
	 * Card payment method is always enabled.
	 */
	public function test_card_payment_method_capability_is_always_enabled() {
		// Enable all payment methods.
		$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', 'EUR' );
		$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );
		$this->set_mock_payment_method_return_value( 'get_capabilities_response', self::MOCK_INACTIVE_CAPABILITIES_RESPONSE );

		// Disable testmode.
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$card_method              = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::CARD ];
		$blik_method              = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::BLIK ];
		$klarna_method            = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::KLARNA ];
		$afterpay_clearpay_method = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::AFTERPAY_CLEARPAY ];
		$affirm_method            = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::AFFIRM ];
		$p24_method               = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::P24 ];
		$eps_method               = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::EPS ];
		$sepa_method              = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::SEPA_DEBIT ];
		$sofort_method            = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::SOFORT ];
		$bancontact_method        = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::BANCONTACT ];
		$ideal_method             = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::IDEAL ];
		$boleto_method            = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::BOLETO ];
		$multibanco_method        = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::MULTIBANCO ];
		$oxxo_method              = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::OXXO ];
		$wechat_pay_method        = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::WECHAT_PAY ];
		$ach_method               = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::ACH ];
		$acss_method              = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::ACSS_DEBIT ];
		$becs_debit_method        = $this->mock_payment_methods[ WC_Stripe_Payment_Methods::BECS_DEBIT ];

		$this->assertTrue( $card_method->is_enabled_at_checkout() );
		$this->assertFalse( $blik_method->is_enabled_at_checkout() );
		$this->assertFalse( $klarna_method->is_enabled_at_checkout() );
		$this->assertFalse( $affirm_method->is_enabled_at_checkout() );
		$this->assertFalse( $afterpay_clearpay_method->is_enabled_at_checkout() );
		$this->assertFalse( $p24_method->is_enabled_at_checkout() );
		$this->assertFalse( $eps_method->is_enabled_at_checkout() );
		$this->assertFalse( $sepa_method->is_enabled_at_checkout() );
		$this->assertFalse( $sofort_method->is_enabled_at_checkout() );
		$this->assertFalse( $bancontact_method->is_enabled_at_checkout() );
		$this->assertFalse( $ideal_method->is_enabled_at_checkout() );
		$this->assertFalse( $boleto_method->is_enabled_at_checkout() );
		$this->assertFalse( $multibanco_method->is_enabled_at_checkout() );
		$this->assertFalse( $oxxo_method->is_enabled_at_checkout() );
		$this->assertFalse( $wechat_pay_method->is_enabled_at_checkout() );
		$this->assertFalse( $ach_method->is_enabled_at_checkout() );
		$this->assertFalse( $acss_method->is_enabled_at_checkout() );
		$this->assertFalse( $becs_debit_method->is_enabled_at_checkout() );
	}

	/**
	 * Payment method is only enabled when capability response contains active for payment method.
	 */
	public function test_payment_methods_are_only_enabled_when_capability_is_active() {
		// Disable testmode.
		$stripe_settings             = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['testmode'] = 'no';
		$stripe_settings['capture']  = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		WC_Stripe::get_instance()->get_main_stripe_gateway()->init_settings();

		$payment_method_ids = array_map( [ $this, 'get_id' ], $this->mock_payment_methods );
		foreach ( $payment_method_ids as $id ) {
			if ( WC_Stripe_Payment_Methods::CARD === $id || WC_Stripe_Payment_Methods::BOLETO === $id || WC_Stripe_Payment_Methods::OXXO === $id || WC_Stripe_Payment_Methods::GIROPAY === $id ) {
				continue;
			}

			$mock_capabilities_response = self::MOCK_INACTIVE_CAPABILITIES_RESPONSE;

			$this->set_mock_payment_method_return_value( 'get_capabilities_response', $mock_capabilities_response, true );
			$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );
			$this->set_mock_payment_method_return_value( 'get_current_order_amount', 150 );
			$this->set_mock_payment_method_return_value( 'is_inside_currency_limits', true );

			$payment_method = $this->mock_payment_methods[ $id ];

			$supported_currencies = $payment_method->get_supported_currencies() ?? [];
			$currency             = end( $supported_currencies );

			$this->assertFalse( $payment_method->is_enabled_at_checkout( null, $currency ) );

			$capability_key                                = WC_Stripe_Helper::get_payment_method_capability_id( $payment_method->get_id() );
			$mock_capabilities_response[ $capability_key ] = 'active';

			$this->set_mock_payment_method_return_value( 'get_capabilities_response', $mock_capabilities_response, true );
			$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', $currency );
			$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );
			$this->set_mock_payment_method_return_value( 'get_current_order_amount', 150 );
			$this->set_mock_payment_method_return_value( 'is_inside_currency_limits', true );

			$payment_method = $this->mock_payment_methods[ $id ];
			$this->assertTrue( $payment_method->is_enabled_at_checkout( null, $currency ), "Payment method {$id} is not enabled" );
		}
	}

	/**
	 * Payment method is only enabled when its supported currency is present or method supports all currencies.
	 */
	public function test_payment_methods_are_only_enabled_when_currency_is_supported() {
		$stripe_settings            = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['capture'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		WC_Stripe::get_instance()->get_main_stripe_gateway()->init_settings();

		$this->set_mock_payment_method_return_value( 'get_current_order_amount', 150, true );

		$payment_method_ids = array_map( [ $this, 'get_id' ], $this->mock_payment_methods );
		foreach ( $payment_method_ids as $id ) {
			if ( WC_Stripe_Payment_Methods::GIROPAY === $id ) {
				continue;
			}

			$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', 'CASHMONEY', true );
			$this->set_mock_payment_method_return_value( 'get_capabilities_response', self::MOCK_ACTIVE_CAPABILITIES_RESPONSE );
			$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );
			$this->set_mock_payment_method_return_value( 'get_current_order_amount', 150 );

			$payment_method       = $this->mock_payment_methods[ $id ];
			$supported_currencies = $payment_method->get_supported_currencies();
			if ( empty( $supported_currencies ) ) {
				$this->assertTrue( $payment_method->is_enabled_at_checkout() );
			} else {
				$woocommerce_currency = end( $supported_currencies );

				$this->assertFalse( $payment_method->is_enabled_at_checkout( null, $woocommerce_currency ) );

				$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', $woocommerce_currency, true );
				$this->set_mock_payment_method_return_value( 'get_capabilities_response', self::MOCK_ACTIVE_CAPABILITIES_RESPONSE );
				$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );
				$this->set_mock_payment_method_return_value( 'get_current_order_amount', 150 );
				$this->set_mock_payment_method_return_value( 'is_inside_currency_limits', true );

				$payment_method = $this->mock_payment_methods[ $id ];
				$this->assertTrue( $payment_method->is_enabled_at_checkout( null, $woocommerce_currency ), "Payment method {$id} is not enabled" );
			}
		}
	}

	/**
	 * When has_domestic_transactions_restrictions is true, the payment method is disabled when the store currency and account currency don't match.
	 */
	public function test_payment_methods_with_domestic_restrictions_are_disabled_on_currency_mismatch() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'testmode' => 'yes' ] );

		$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', WC_Stripe_Currency_Code::MEXICAN_PESO, true );

		// This is a currency supported by all of the BNPLs.
		$stripe_account_currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		foreach ( WC_Stripe_Payment_Methods::BNPL_PAYMENT_METHODS as $payment_method_id ) {
			$payment_method = $this->mock_payment_methods[ $payment_method_id ];
			$this->assertFalse( $payment_method->is_enabled_at_checkout( null, $stripe_account_currency ), "Payment method {$payment_method_id} is enabled" );
		}
	}

	/**
	 * When has_domestic_transactions_restrictions is true, the payment method is enabled when the store currency and account currency match.
	 */
	public function test_payment_methods_with_domestic_restrictions_are_enabled_on_currency_match() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'testmode' => 'yes' ] );
		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
				->disableOriginalConstructor()
				->setMethods(
					[
						'get_cached_account_data',
					]
				)
				->getMock();
		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn(
			[
				'country'          => 'US',
				'default_currency' => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
			]
		);

		$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR, true );

		// This is a currency supported by all of the BNPLs.
		$stripe_account_currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		// Bypass the currency limits check while we're testing domestic restrictions.
		$this->set_mock_payment_method_return_value( 'is_inside_currency_limits', true );

		foreach ( WC_Stripe_Payment_Methods::BNPL_PAYMENT_METHODS as $payment_method_id ) {
			$payment_method = $this->mock_payment_methods[ $payment_method_id ];
			$this->assertTrue( $payment_method->is_enabled_at_checkout( null, $stripe_account_currency ), "Payment method {$payment_method_id} is not enabled" );
		}
	}

	public function test_bnpl_is_unavailable_when_not_within_currency_limits() {
		$store_currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->set_mock_payment_method_return_value( 'get_current_order_amount', 0.3 );

		foreach ( WC_Stripe_Payment_Methods::BNPL_PAYMENT_METHODS as $payment_method_id ) {
			$payment_method = $this->mock_payment_methods[ $payment_method_id ];
			$this->assertFalse( $payment_method->is_inside_currency_limits( $store_currency ), "Payment method {$payment_method_id} is inside currency limits" );
		}
	}

	public function test_bnpl_is_available_when_within_currency_limits() {
		$store_currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		// We're testing the is_inside_currency_limits() function so don't want to mock it.
		$this->reset_payment_method_mocks( [ 'is_inside_currency_limits' ] );
		$this->set_mock_payment_method_return_value( 'get_current_order_amount', 150 );

		foreach ( WC_Stripe_Payment_Methods::BNPL_PAYMENT_METHODS as $payment_method_id ) {
			$payment_method = $this->mock_payment_methods[ $payment_method_id ];
			if ( empty( $payment_method->get_limits_per_currency() ) ) {
				continue;
			}
			$this->assertTrue( $payment_method->is_inside_currency_limits( $store_currency ), "Payment method {$payment_method_id} is not inside currency limits" );
		}
	}

	public function test_bnpl_is_available_when_order_is_anmount_is_zero() {
		$store_currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		// We're testing the is_inside_currency_limits() function so don't want to mock it.
		$this->reset_payment_method_mocks( [ 'is_inside_currency_limits' ] );
		$this->set_mock_payment_method_return_value( 'get_current_order_amount', 0 );

		foreach ( WC_Stripe_Payment_Methods::BNPL_PAYMENT_METHODS as $payment_method_id ) {
			$payment_method = $this->mock_payment_methods[ $payment_method_id ];
			$this->assertTrue( $payment_method->is_inside_currency_limits( $store_currency ), "Payment method {$payment_method_id} is not inside currency limits" );
		}
	}

	/**
	 * If subscription product is in cart, enabled payment methods must be reusable.
	 */
	public function test_payment_methods_are_reusable_if_cart_contains_subscription() {
		$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', true );
		$this->set_mock_payment_method_return_value( 'get_current_order_amount', 150 );
		$this->set_mock_payment_method_return_value( 'get_capabilities_response', self::MOCK_ACTIVE_CAPABILITIES_RESPONSE );

		foreach ( $this->mock_payment_methods as $payment_method_id => $payment_method ) {
			$store_currency = 'EUR';
			if ( in_array(
				$payment_method_id,
				[
					WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID,
					WC_Stripe_UPE_Payment_Method_Amazon_Pay::STRIPE_ID,
				],
				true
			) ) {
				$store_currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;
			} elseif ( WC_Stripe_UPE_Payment_Method_Bacs_Debit::STRIPE_ID === $payment_method_id ) {
				$store_currency = WC_Stripe_Currency_Code::POUND_STERLING;
			} elseif ( WC_Stripe_UPE_Payment_Method_Becs_Debit::STRIPE_ID === $payment_method_id ) {
				$store_currency = WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR;
			}

			$account_currency = null;

			// Use different currencies for ACSS or payment methods that have domestic transactions restrictions.
			if ( $payment_method->has_domestic_transactions_restrictions() || WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID === $payment_method_id ) {
				$store_currency   = $payment_method->get_supported_currencies()[0];
				$account_currency = $store_currency;
			}

			$payment_method
				->expects( $this->any() )
				->method( 'get_woocommerce_currency' )
				->will(
					$this->returnValue( $store_currency )
				);

			if ( $payment_method->is_reusable() ) {
				$this->assertTrue( $payment_method->is_enabled_at_checkout( null, $account_currency ), "Payment method {$payment_method_id} is not enabled" );
			} else {
				$this->assertFalse( $payment_method->is_enabled_at_checkout( null, $account_currency ), "Payment method {$payment_method_id} is enabled" );
			}
		}
	}

	public function test_payment_methods_support_custom_name_and_description() {
		$payment_method_ids = [
			WC_Stripe_Payment_Methods::ACH,
			WC_Stripe_Payment_Methods::ACSS_DEBIT,
			WC_Stripe_Payment_Methods::BECS_DEBIT,
			WC_Stripe_Payment_Methods::BLIK,
			WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::KLARNA,
			WC_Stripe_Payment_Methods::AFTERPAY_CLEARPAY,
			WC_Stripe_Payment_Methods::AFFIRM,
			WC_Stripe_Payment_Methods::P24,
			WC_Stripe_Payment_Methods::EPS,
			WC_Stripe_Payment_Methods::SEPA_DEBIT,
			WC_Stripe_Payment_Methods::SOFORT,
			WC_Stripe_Payment_Methods::BANCONTACT,
			WC_Stripe_Payment_Methods::IDEAL,
			WC_Stripe_Payment_Methods::BOLETO,
			WC_Stripe_Payment_Methods::MULTIBANCO,
			WC_Stripe_Payment_Methods::OXXO,
			WC_Stripe_Payment_Methods::WECHAT_PAY,
		];

		foreach ( $payment_method_ids as $payment_method_id ) {
			$payment_method = $this->mock_payment_methods[ $payment_method_id ];

			// Update the payment method settings to have a custom name and description.
			$original_payment_settings               = get_option( 'woocommerce_stripe_' . $payment_method_id . '_settings', [] );
			$updated_payment_settings                = $original_payment_settings;
			$custom_name                             = 'Custom Name for ' . $payment_method_id;
			$custom_description                      = 'Custom description for ' . $payment_method_id;
			$updated_payment_settings['title']       = $custom_name;
			$updated_payment_settings['description'] = $custom_description;
			update_option( 'woocommerce_stripe_' . $payment_method_id . '_settings', $updated_payment_settings );

			$this->assertEquals( $custom_name, $payment_method->get_title() );
			$this->assertEquals( $custom_description, $payment_method->get_description() );

			// Restore original settings.
			update_option( 'woocommerce_stripe_' . $payment_method_id . '_settings', $original_payment_settings );
		}

		// Test custom description when SPE is enabled. Should be always empty.
		update_option( WC_Stripe_Feature_Flags::SPE_FEATURE_FLAG_NAME, 'yes' );

		$stripe_settings                           = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['single_payment_element'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$payment_method_id                       = WC_Stripe_Payment_Methods::CARD;
		$custom_description                      = 'Custom description for ' . $payment_method_id;
		$original_payment_settings               = get_option( 'woocommerce_stripe_' . $payment_method_id . '_settings', [] );
		$updated_payment_settings                = $original_payment_settings;
		$updated_payment_settings['description'] = $custom_description;
		update_option( 'woocommerce_stripe_' . $payment_method_id . '_settings', $updated_payment_settings );

		$mocked_payment_method = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_CC::class )
			->setMethods(
				[
					'get_capabilities_response',
					'get_woocommerce_currency',
					'is_subscription_item_in_cart',
					'get_current_order_amount',
					'is_inside_currency_limits',
				]
			)
			->getMock();

		$this->assertEmpty( $mocked_payment_method->get_description() );
	}

	/**
	 * Test the type of payment token created for the user.
	 */
	public function test_create_payment_token_for_user() {
		$user_id = 1;

		foreach ( $this->mock_payment_methods as $payment_method_id => $payment_method ) {
			if ( ! $payment_method->is_reusable() ) {
				continue;
			}

			switch ( $payment_method_id ) {
				case WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID:
					$card_payment_method_mock = $this->array_to_object( self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE );
					$token                    = $payment_method->create_payment_token_for_user( $user_id, $card_payment_method_mock );
					$this->assertTrue( WC_Stripe_Payment_Token_CC::class === get_class( $token ) );
					$this->assertSame( $token->get_last4(), $card_payment_method_mock->card->last4 );
					$this->assertSame( $token->get_token(), $card_payment_method_mock->id );
					// Test display brand
					$cartes_bancaires_brand                        = 'cartes_bancaires';
					$card_payment_method_mock->card->display_brand = $cartes_bancaires_brand;
					$token = $payment_method->create_payment_token_for_user( $user_id, $card_payment_method_mock );
					$this->assertSame( $token->get_card_type(), $cartes_bancaires_brand );
					unset( $card_payment_method_mock->card->display_brand );
					// Test preferred network
					$card_payment_method_mock->card->networks            = new stdClass();
					$card_payment_method_mock->card->networks->preferred = $cartes_bancaires_brand;
					$token = $payment_method->create_payment_token_for_user( $user_id, $card_payment_method_mock );
					$this->assertSame( $token->get_card_type(), $cartes_bancaires_brand );
					unset( $card_payment_method_mock->card->networks->preferred );
					break;
				case WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID:
					$link_payment_method_mock = $this->array_to_object( self::MOCK_LINK_PAYMENT_METHOD_TEMPLATE );
					$token                    = $payment_method->create_payment_token_for_user( $user_id, $link_payment_method_mock );
					$this->assertTrue( WC_Payment_Token_Link::class === get_class( $token ) );
					$this->assertSame( $token->get_email(), $link_payment_method_mock->link->email );
					break;
				case WC_Stripe_UPE_Payment_Method_Amazon_Pay::STRIPE_ID:
					$amazon_payment_method_mock = $this->array_to_object( self::MOCK_AMAZON_PAY_PAYMENT_METHOD_TEMPLATE );
					$token                      = $payment_method->create_payment_token_for_user( $user_id, $amazon_payment_method_mock );
					$this->assertTrue( WC_Payment_Token_Amazon_Pay::class === get_class( $token ) );
					$this->assertSame( $token->get_email(), $amazon_payment_method_mock->billing_details->email );
					break;
				case WC_Stripe_UPE_Payment_Method_Cash_App_Pay::STRIPE_ID:
					$cash_app_payment_method_mock = $this->array_to_object( self::MOCK_CASH_APP_PAYMENT_METHOD_TEMPLATE );
					$token                        = $payment_method->create_payment_token_for_user( $user_id, $cash_app_payment_method_mock );
					$this->assertTrue( WC_Payment_Token_CashApp::class === get_class( $token ) );
					$this->assertSame( $token->get_cashtag(), $cash_app_payment_method_mock->cashapp->cashtag );
					break;
				case WC_Stripe_UPE_Payment_Method_ACH::STRIPE_ID:
					$ach_payment_method_mock = $this->array_to_object( self::MOCK_ACH_PAYMENT_METHOD_TEMPLATE );
					$token                   = $payment_method->create_payment_token_for_user( $user_id, $ach_payment_method_mock );
					$this->assertTrue( WC_Payment_Token_ACH::class === get_class( $token ) );
					$this->assertSame( $token->get_last4(), $ach_payment_method_mock->{WC_Stripe_Payment_Methods::ACH}->last4 );
					$this->assertSame( $token->get_token(), $ach_payment_method_mock->id );
					$this->assertSame( $token->get_bank_name(), $ach_payment_method_mock->{WC_Stripe_Payment_Methods::ACH}->bank_name );
					$this->assertSame( $token->get_account_type(), $ach_payment_method_mock->{WC_Stripe_Payment_Methods::ACH}->account_type );
					break;
				case WC_Stripe_UPE_Payment_Method_Bacs_Debit::STRIPE_ID:
					$bacs_payment_method_mock = $this->array_to_object( self::MOCK_BACS_PAYMENT_METHOD_TEMPLATE );
					$token                    = $payment_method->create_payment_token_for_user( $user_id, $bacs_payment_method_mock );
					$this->assertTrue( WC_Payment_Token_Bacs_Debit::class === get_class( $token ) );
					$this->assertSame( $token->get_last4(), $bacs_payment_method_mock->bacs_debit->last4 );
					break;
				case WC_Stripe_UPE_Payment_Method_ACSS::STRIPE_ID:
					$acss_payment_method_mock = $this->array_to_object( self::MOCK_ACSS_PAYMENT_METHOD_TEMPLATE );
					$token                    = $payment_method->create_payment_token_for_user( $user_id, $acss_payment_method_mock );
					$this->assertTrue( WC_Payment_Token_ACSS::class === get_class( $token ) );
					$this->assertSame( $token->get_last4(), $acss_payment_method_mock->acss_debit->last4 );
					$this->assertSame( $token->get_bank_name(), $acss_payment_method_mock->acss_debit->bank_name );
					break;
				case WC_Stripe_UPE_Payment_Method_Becs_Debit::STRIPE_ID:
					$becs_debit_payment_method_mock = $this->array_to_object( self::MOCK_BECS_DEBIT_PAYMENT_METHOD_TEMPLATE );
					$token                          = $payment_method->create_payment_token_for_user( $user_id, $becs_debit_payment_method_mock );
					$this->assertTrue( WC_Payment_Token_Becs_Debit::class === get_class( $token ) );
					$this->assertSame( $token->get_last4(), $becs_debit_payment_method_mock->{WC_Stripe_UPE_Payment_Method_Becs_Debit::STRIPE_ID}->last4 );
					break;
				default:
					$sepa_payment_method_mock = $this->array_to_object( self::MOCK_SEPA_PAYMENT_METHOD_TEMPLATE );
					$token                    = $payment_method->create_payment_token_for_user( $user_id, $sepa_payment_method_mock );
					$this->assertTrue( WC_Payment_Token_SEPA::class === get_class( $token ) );
					$this->assertSame( $token->get_last4(), $sepa_payment_method_mock->sepa_debit->last4 );
					$this->assertSame( $token->get_token(), $sepa_payment_method_mock->id );

			}
		}
	}

	/**
	 * Test for `update_payment_token` method.
	 *
	 * @return void
	 */
	public function test_update_payment_token() {
		$token = new WC_Stripe_Payment_Token_CC();
		$token->set_expiry_month( '12' );
		$token->set_expiry_year( '2024' );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
		$token->set_token( 'pm_1234' );
		$token->set_user_id( 1 );
		$token->set_fingerprint( 'Lstxxxx' );
		$token->save();

		$expected = self::MOCK_CARD_PAYMENT_METHOD_TEMPLATE['id'];

		$payment_method = new WC_Stripe_UPE_Payment_Method_CC();
		$actual         = $payment_method->update_payment_token( $token, $expected );

		$this->assertSame( $expected, $actual->get_token() );
	}

	/**
	 * Tests that UPE methods are only enabled if Stripe is enabled.
	 */
	public function test_upe_method_enabled() {
		$stripe_settings            = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::LINK ], [] );

		$link_upe_method = new WC_Stripe_UPE_Payment_Method_Link();
		$this->assertTrue( $link_upe_method->is_enabled() );
	}

	/**
	 * Tests that UPE methods are not enabled if Stripe is disabled.
	 */
	public function test_upe_method_disabled() {
		$stripe_settings            = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::LINK ], [] );

		$link_upe_method = new WC_Stripe_UPE_Payment_Method_Link();
		$this->assertFalse( $link_upe_method->is_enabled() );
	}
}
