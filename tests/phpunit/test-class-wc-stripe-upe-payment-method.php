<?php
/**
 * Unit tests for UPE payment methods
 */
class WC_Stripe_UPE_Payment_Method_Test extends WP_UnitTestCase {
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
			'brand'     => 'visa',
			'network'   => 'visa',
			'exp_month' => '7',
			'exp_year'  => '2099',
			'funding'   => 'credit',
			'last4'     => '4242',
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
	 * Mock capabilities object from Stripe response--all inactive.
	 */
	const MOCK_INACTIVE_CAPABILITIES_RESPONSE = [
		'alipay_payments'            => 'inactive',
		'bancontact_payments'        => 'inactive',
		'card_payments'              => 'inactive',
		'eps_payments'               => 'inactive',
		'giropay_payments'           => 'inactive',
		'klarna_payments'            => 'inactive',
		'affirm_payments'            => 'inactive',
		'clearpay_afterpay_payments' => 'inactive',
		'ideal_payments'             => 'inactive',
		'p24_payments'               => 'inactive',
		'sepa_debit_payments'        => 'inactive',
		'sofort_payments'            => 'inactive',
		'transfers'                  => 'inactive',
		'multibanco_payments'        => 'inactive',
		'boleto_payments'            => 'inactive',
		'oxxo_payments'              => 'inactive',
		'link_payments'              => 'inactive',
		'wechat_pay_payments'        => 'inactive',
	];

	/**
	 * Mock capabilities object from Stripe response--all active.
	 */
	const MOCK_ACTIVE_CAPABILITIES_RESPONSE = [
		'alipay_payments'            => 'active',
		'bancontact_payments'        => 'active',
		'card_payments'              => 'active',
		'eps_payments'               => 'active',
		'giropay_payments'           => 'active',
		'klarna_payments'            => 'active',
		'affirm_payments'            => 'active',
		'clearpay_afterpay_payments' => 'active',
		'ideal_payments'             => 'active',
		'p24_payments'               => 'active',
		'sepa_debit_payments'        => 'active',
		'sofort_payments'            => 'active',
		'transfers'                  => 'active',
		'multibanco_payments'        => 'active',
		'boleto_payments'            => 'active',
		'oxxo_payments'              => 'active',
		'link_payments'              => 'active',
		'cashapp_payments'           => 'active',
		'wechat_pay_payments'        => 'active',
	];

	/**
	 * Initial setup
	 */
	public function set_up() {
		parent::set_up();
		WC_Stripe_Helper::delete_main_stripe_settings();
		$this->reset_payment_method_mocks();
	}

	public function tear_down() {
		WC_Stripe_Helper::delete_main_stripe_settings();
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

		$card_method       = $this->mock_payment_methods['card'];
		$alipay_method     = $this->mock_payment_methods['alipay'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$ideal_method      = $this->mock_payment_methods['ideal'];
		$multibanco_method = $this->mock_payment_methods['multibanco'];
		$boleto_method     = $this->mock_payment_methods['boleto'];
		$oxxo_method       = $this->mock_payment_methods['oxxo'];
		$wechat_pay_method = $this->mock_payment_methods['wechat_pay'];

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
		$this->assertTrue( $bancontact_method->is_reusable() );
		$this->assertEquals( WC_Stripe_Payment_Methods::SEPA_DEBIT, $bancontact_method->get_retrievable_type() );
		$this->assertEquals( '', $bancontact_method->get_testing_instructions() );

		$this->assertEquals( WC_Stripe_Payment_Methods::IDEAL, $ideal_method->get_id() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_label() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_title( $mock_ideal_details ) );
		$this->assertTrue( $ideal_method->is_reusable() );
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

		$card_method              = $this->mock_payment_methods['card'];
		$klarna_method            = $this->mock_payment_methods['klarna'];
		$afterpay_clearpay_method = $this->mock_payment_methods['afterpay_clearpay'];
		$affirm_method            = $this->mock_payment_methods['affirm'];
		$p24_method               = $this->mock_payment_methods['p24'];
		$eps_method               = $this->mock_payment_methods['eps'];
		$sepa_method              = $this->mock_payment_methods['sepa_debit'];
		$sofort_method            = $this->mock_payment_methods['sofort'];
		$bancontact_method        = $this->mock_payment_methods['bancontact'];
		$ideal_method             = $this->mock_payment_methods['ideal'];
		$boleto_method            = $this->mock_payment_methods['boleto'];
		$multibanco_method        = $this->mock_payment_methods['multibanco'];
		$oxxo_method              = $this->mock_payment_methods['oxxo'];
		$wechat_pay_method        = $this->mock_payment_methods['wechat_pay'];

		$this->assertTrue( $card_method->is_enabled_at_checkout() );
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

			$capability_key                                = $payment_method->get_id() . '_payments';
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
				'country' => 'US',
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
			$store_currency   = WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID === $payment_method_id ? WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR : 'EUR';
			$account_currency = null;

			if ( $payment_method->has_domestic_transactions_restrictions() ) {
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
					$this->assertTrue( 'WC_Payment_Token_CC' === get_class( $token ) );
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
					$this->assertTrue( 'WC_Payment_Token_Link' === get_class( $token ) );
					$this->assertSame( $token->get_email(), $link_payment_method_mock->link->email );
					break;
				case WC_Stripe_UPE_Payment_Method_Cash_App_Pay::STRIPE_ID:
					$cash_app_payment_method_mock = $this->array_to_object( self::MOCK_CASH_APP_PAYMENT_METHOD_TEMPLATE );
					$token                        = $payment_method->create_payment_token_for_user( $user_id, $cash_app_payment_method_mock );
					$this->assertTrue( 'WC_Payment_Token_CashApp' === get_class( $token ) );
					$this->assertSame( $token->get_cashtag(), $cash_app_payment_method_mock->cashapp->cashtag );
					break;
				default:
					$sepa_payment_method_mock = $this->array_to_object( self::MOCK_SEPA_PAYMENT_METHOD_TEMPLATE );
					$token                    = $payment_method->create_payment_token_for_user( $user_id, $sepa_payment_method_mock );
					$this->assertTrue( 'WC_Payment_Token_SEPA' === get_class( $token ) );
					$this->assertSame( $token->get_last4(), $sepa_payment_method_mock->sepa_debit->last4 );
					$this->assertSame( $token->get_token(), $sepa_payment_method_mock->id );

			}
		}
	}

	/**
	 * Tests that UPE methods are only enabled if Stripe is enabled and the individual methods is enabled in the settings.
	 */
	public function test_upe_method_enabled() {
		// Enable Stripe and reset the accepted payment methods.
		$stripe_settings            = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled'] = 'yes';
		$stripe_settings['upe_checkout_experience_accepted_payments'] = [];
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// For each method we'll test the following combinations:
		$stripe_enabled_settings    = [ 'yes', 'no', '' ];
		$upe_method_enabled_options = [ true, false ];

		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $payment_method ) {
			foreach ( $stripe_enabled_settings as $stripe_enabled ) {
				foreach ( $upe_method_enabled_options as $upe_method_enabled_option ) {
					// Update the settings.
					$stripe_settings['enabled'] = $stripe_enabled;

					$payment_method_index = array_search( $payment_method::STRIPE_ID, $stripe_settings['upe_checkout_experience_accepted_payments'] );

					if ( $upe_method_enabled_option && false === $payment_method_index ) {
						$stripe_settings['upe_checkout_experience_accepted_payments'][] = $payment_method::STRIPE_ID;
					} elseif ( ! $upe_method_enabled_option && false !== $payment_method_index ) {
						unset( $stripe_settings['upe_checkout_experience_accepted_payments'][ $payment_method_index ] );
					}

					WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

					// Verify that the payment method is enabled/disabled.
					$payment_method_instance = new $payment_method();

					// The UPE method is only enabled if Stripe is enabled and the method is enabled in the settings.
					if ( 'yes' === $stripe_enabled && $upe_method_enabled_option ) {
						$this->assertTrue( $payment_method_instance->is_enabled() );
					} else {
						$this->assertFalse( $payment_method_instance->is_enabled() );
					}
				}
			}
		}
	}
}
