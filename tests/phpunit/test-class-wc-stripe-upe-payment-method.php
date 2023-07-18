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
		'id'   => 'pm_mock_payment_method_id',
		'type' => 'card',
		'card' => [
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
		'id'         => 'pm_mock_payment_method_id',
		'type'       => 'sepa_debit',
		'sepa_debit' => [
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
	 * Mock capabilities object from Stripe response--all inactive.
	 */
	const MOCK_INACTIVE_CAPABILITIES_RESPONSE = [
		'bancontact_payments' => 'inactive',
		'card_payments'       => 'inactive',
		'eps_payments'        => 'inactive',
		'giropay_payments'    => 'inactive',
		'ideal_payments'      => 'inactive',
		'p24_payments'        => 'inactive',
		'sepa_debit_payments' => 'inactive',
		'sofort_payments'     => 'inactive',
		'transfers'           => 'inactive',
		'boleto_payments'     => 'inactive',
		'oxxo_payments'       => 'inactive',
		'link_payments'       => 'inactive',
	];

	/**
	 * Mock capabilities object from Stripe response--all active.
	 */
	const MOCK_ACTIVE_CAPABILITIES_RESPONSE = [
		'bancontact_payments' => 'active',
		'card_payments'       => 'active',
		'eps_payments'        => 'active',
		'giropay_payments'    => 'active',
		'ideal_payments'      => 'active',
		'p24_payments'        => 'active',
		'sepa_debit_payments' => 'active',
		'sofort_payments'     => 'active',
		'transfers'           => 'active',
		'boleto_payments'     => 'active',
		'oxxo_payments'       => 'active',
		'link_payments'       => 'active',
	];

	/**
	 * Initial setup
	 */
	public function set_up() {
		parent::set_up();
		$this->reset_payment_method_mocks();
	}

	/**
	 * Reset mock_payment_methods to array of mocked payment methods
	 * with no mocked expectations for methods.
	 */
	private function reset_payment_method_mocks() {
		$this->mock_payment_methods = [];
		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $payment_method_class ) {
			$mocked_payment_method = $this->getMockBuilder( $payment_method_class )
				->setMethods(
					[
						'get_capabilities_response',
						'get_woocommerce_currency',
						'is_subscription_item_in_cart',
					]
				)
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
		$mock_visa_details       = [
			'type' => 'card',
			'card' => $this->array_to_object(
				[
					'network' => 'visa',
					'funding' => 'debit',
				]
			),
		];
		$mock_mastercard_details = [
			'type' => 'card',
			'card' => $this->array_to_object(
				[
					'network' => 'mastercard',
					'funding' => 'credit',
				]
			),
		];
		$mock_giropay_details    = [
			'type' => 'giropay',
		];
		$mock_p24_details        = [
			'type' => 'p24',
		];
		$mock_eps_details        = [
			'type' => 'eps',
		];
		$mock_sepa_details       = [
			'type' => 'sepa_debit',
		];
		$mock_sofort_details     = [
			'type' => 'sofort',
		];
		$mock_bancontact_details = [
			'type' => 'bancontact',
		];
		$mock_ideal_details      = [
			'type' => 'ideal',
		];
		$mock_boleto_details     = [
			'type' => 'boleto',
		];
		$mock_oxxo_details       = [
			'type' => 'oxxo',
		];

		$card_method       = $this->mock_payment_methods['card'];
		$giropay_method    = $this->mock_payment_methods['giropay'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$ideal_method      = $this->mock_payment_methods['ideal'];
		$boleto_method     = $this->mock_payment_methods['boleto'];
		$oxxo_method       = $this->mock_payment_methods['oxxo'];

		$this->assertEquals( 'card', $card_method->get_id() );
		$this->assertEquals( 'Credit card / debit card', $card_method->get_label() );
		$this->assertEquals( 'Pay with credit card / debit card', $card_method->get_title() );
		$this->assertEquals( 'Visa debit card', $card_method->get_title( $mock_visa_details ) );
		$this->assertEquals( 'Mastercard credit card', $card_method->get_title( $mock_mastercard_details ) );
		$this->assertTrue( $card_method->is_reusable() );
		$this->assertEquals( 'card', $card_method->get_retrievable_type() );

		$this->assertEquals( 'giropay', $giropay_method->get_id() );
		$this->assertEquals( 'giropay', $giropay_method->get_label() );
		$this->assertEquals( 'Pay with giropay', $giropay_method->get_title() );
		$this->assertEquals( 'Pay with giropay', $giropay_method->get_title( $mock_giropay_details ) );
		$this->assertFalse( $giropay_method->is_reusable() );
		$this->assertEquals( null, $giropay_method->get_retrievable_type() );

		$this->assertEquals( 'p24', $p24_method->get_id() );
		$this->assertEquals( 'Przelewy24', $p24_method->get_label() );
		$this->assertEquals( 'Pay with Przelewy24', $p24_method->get_title() );
		$this->assertEquals( 'Pay with Przelewy24', $p24_method->get_title( $mock_p24_details ) );
		$this->assertFalse( $p24_method->is_reusable() );
		$this->assertEquals( null, $p24_method->get_retrievable_type() );

		$this->assertEquals( 'eps', $eps_method->get_id() );
		$this->assertEquals( 'EPS', $eps_method->get_label() );
		$this->assertEquals( 'Pay with EPS', $eps_method->get_title() );
		$this->assertEquals( 'Pay with EPS', $eps_method->get_title( $mock_eps_details ) );
		$this->assertFalse( $eps_method->is_reusable() );
		$this->assertEquals( null, $eps_method->get_retrievable_type() );

		$this->assertEquals( 'sepa_debit', $sepa_method->get_id() );
		$this->assertEquals( 'SEPA Direct Debit', $sepa_method->get_label() );
		$this->assertEquals( 'Pay with SEPA Direct Debit', $sepa_method->get_title() );
		$this->assertEquals( 'Pay with SEPA Direct Debit', $sepa_method->get_title( $mock_sepa_details ) );
		$this->assertTrue( $sepa_method->is_reusable() );
		$this->assertEquals( 'sepa_debit', $sepa_method->get_retrievable_type() );

		$this->assertEquals( 'sofort', $sofort_method->get_id() );
		$this->assertEquals( 'Sofort', $sofort_method->get_label() );
		$this->assertEquals( 'Pay with Sofort', $sofort_method->get_title() );
		$this->assertEquals( 'Pay with Sofort', $sofort_method->get_title( $mock_sofort_details ) );
		$this->assertTrue( $sofort_method->is_reusable() );
		$this->assertEquals( 'sepa_debit', $sofort_method->get_retrievable_type() );

		$this->assertEquals( 'bancontact', $bancontact_method->get_id() );
		$this->assertEquals( 'Bancontact', $bancontact_method->get_label() );
		$this->assertEquals( 'Pay with Bancontact', $bancontact_method->get_title() );
		$this->assertEquals( 'Pay with Bancontact', $bancontact_method->get_title( $mock_bancontact_details ) );
		$this->assertTrue( $bancontact_method->is_reusable() );
		$this->assertEquals( 'sepa_debit', $bancontact_method->get_retrievable_type() );

		$this->assertEquals( 'ideal', $ideal_method->get_id() );
		$this->assertEquals( 'iDEAL', $ideal_method->get_label() );
		$this->assertEquals( 'Pay with iDEAL', $ideal_method->get_title() );
		$this->assertEquals( 'Pay with iDEAL', $ideal_method->get_title( $mock_ideal_details ) );
		$this->assertTrue( $ideal_method->is_reusable() );
		$this->assertEquals( 'sepa_debit', $ideal_method->get_retrievable_type() );

		$this->assertEquals( 'boleto', $boleto_method->get_id() );
		$this->assertEquals( 'Boleto', $boleto_method->get_label() );
		$this->assertEquals( 'Pay with Boleto', $boleto_method->get_title() );
		$this->assertEquals( 'Pay with Boleto', $boleto_method->get_title( $mock_boleto_details ) );
		$this->assertFalse( $boleto_method->is_reusable() );
		$this->assertEquals( null, $boleto_method->get_retrievable_type() );

		$this->assertEquals( 'oxxo', $oxxo_method->get_id() );
		$this->assertEquals( 'OXXO', $oxxo_method->get_label() );
		$this->assertEquals( 'Pay with OXXO', $oxxo_method->get_title() );
		$this->assertEquals( 'Pay with OXXO', $oxxo_method->get_title( $mock_oxxo_details ) );
		$this->assertFalse( $oxxo_method->is_reusable() );
		$this->assertEquals( null, $oxxo_method->get_retrievable_type() );
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
		$stripe_settings             = get_option( 'woocommerce_stripe_settings' );
		$stripe_settings['testmode'] = 'no';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		$card_method       = $this->mock_payment_methods['card'];
		$giropay_method    = $this->mock_payment_methods['giropay'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$ideal_method      = $this->mock_payment_methods['ideal'];
		$boleto_method     = $this->mock_payment_methods['boleto'];
		$oxxo_method       = $this->mock_payment_methods['oxxo'];

		$this->assertTrue( $card_method->is_enabled_at_checkout() );
		$this->assertFalse( $giropay_method->is_enabled_at_checkout() );
		$this->assertFalse( $p24_method->is_enabled_at_checkout() );
		$this->assertFalse( $eps_method->is_enabled_at_checkout() );
		$this->assertFalse( $sepa_method->is_enabled_at_checkout() );
		$this->assertFalse( $sofort_method->is_enabled_at_checkout() );
		$this->assertFalse( $bancontact_method->is_enabled_at_checkout() );
		$this->assertFalse( $ideal_method->is_enabled_at_checkout() );
		$this->assertFalse( $boleto_method->is_enabled_at_checkout() );
		$this->assertFalse( $oxxo_method->is_enabled_at_checkout() );
	}

	/**
	 * Payment method is only enabled when capability response contains active for payment method.
	 */
	public function test_payment_methods_are_only_enabled_when_capability_is_active() {
		// Disable testmode.
		$stripe_settings             = get_option( 'woocommerce_stripe_settings' );
		$stripe_settings['testmode'] = 'no';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		$payment_method_ids = array_map( [ $this, 'get_id' ], $this->mock_payment_methods );
		foreach ( $payment_method_ids as $id ) {
			if ( 'card' === $id || 'boleto' === $id || 'oxxo' === $id ) {
				continue;
			}

			$mock_capabilities_response = self::MOCK_INACTIVE_CAPABILITIES_RESPONSE;
			$currency                   = 'link' === $id ? 'USD' : 'EUR';

			$this->set_mock_payment_method_return_value( 'get_capabilities_response', $mock_capabilities_response, true );
			$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', $currency );
			$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );

			$payment_method = $this->mock_payment_methods[ $id ];
			$this->assertFalse( $payment_method->is_enabled_at_checkout() );

			$capability_key                                = $payment_method->get_id() . '_payments';
			$mock_capabilities_response[ $capability_key ] = 'active';

			$this->set_mock_payment_method_return_value( 'get_capabilities_response', $mock_capabilities_response, true );
			$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', $currency );
			$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );

			$payment_method = $this->mock_payment_methods[ $id ];
			$this->assertTrue( $payment_method->is_enabled_at_checkout() );
		}
	}

	/**
	 * Payment method is only enabled when its supported currency is present or method supports all currencies.
	 */
	public function test_payment_methods_are_only_enabled_when_currency_is_supported() {
		$payment_method_ids = array_map( [ $this, 'get_id' ], $this->mock_payment_methods );
		foreach ( $payment_method_ids as $id ) {
			$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', 'CASHMONEY', true );
			$this->set_mock_payment_method_return_value( 'get_capabilities_response', self::MOCK_ACTIVE_CAPABILITIES_RESPONSE );
			$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );

			$payment_method       = $this->mock_payment_methods[ $id ];
			$supported_currencies = $payment_method->get_supported_currencies();
			if ( empty( $supported_currencies ) ) {
				$this->assertTrue( $payment_method->is_enabled_at_checkout() );
			} else {
				$this->assertFalse( $payment_method->is_enabled_at_checkout() );

				$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', end( $supported_currencies ), true );
				$this->set_mock_payment_method_return_value( 'get_capabilities_response', self::MOCK_ACTIVE_CAPABILITIES_RESPONSE );
				$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );

				$payment_method = $this->mock_payment_methods[ $id ];
				$this->assertTrue( $payment_method->is_enabled_at_checkout() );
			}
		}
	}

	/**
	 * If subscription product is in cart, enabled payment methods must be reusable.
	 */
	public function test_payment_methods_are_reusable_if_cart_contains_subscription() {
		$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', true );
		$this->set_mock_payment_method_return_value( 'get_capabilities_response', self::MOCK_ACTIVE_CAPABILITIES_RESPONSE );

		foreach ( $this->mock_payment_methods as $payment_method_id => $payment_method ) {
			$payment_method
				->expects( $this->any() )
				->method( 'get_woocommerce_currency' )
				->will(
					$this->returnValue( WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID === $payment_method_id ? 'USD' : 'EUR' )
				);

			if ( $payment_method->is_reusable() ) {
				$this->assertTrue( $payment_method->is_enabled_at_checkout() );
			} else {
				$this->assertFalse( $payment_method->is_enabled_at_checkout() );
			}
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
					break;
				case WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID:
					$link_payment_method_mock = $this->array_to_object( self::MOCK_LINK_PAYMENT_METHOD_TEMPLATE );
					$token                    = $payment_method->create_payment_token_for_user( $user_id, $link_payment_method_mock );
					$this->assertTrue( 'WC_Payment_Token_Link' === get_class( $token ) );
					$this->assertSame( $token->get_email(), $link_payment_method_mock->link->email );
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
}
