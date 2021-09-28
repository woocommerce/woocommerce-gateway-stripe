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
	 * Mock capabilities object from Stripe response.
	 */
	const MOCK_CAPABILITIES_RESPONSE = [
		'bancontact_payments' => 'inactive',
		'card_payments'       => 'inactive',
		'eps_payments'        => 'inactive',
		'giropay_payments'    => 'inactive',
		'ideal_payments'      => 'inactive',
		'p24_payments'        => 'inactive',
		'sepa_debit_payments' => 'inactive',
		'sofort_payments'     => 'inactive',
		'transfers'           => 'inactive',
	];

	/**
	 * Initial setup
	 */
	public function setUp() {
		parent::setUp();
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

		$card_method       = $this->mock_payment_methods['card'];
		$giropay_method    = $this->mock_payment_methods['giropay'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$ideal_method      = $this->mock_payment_methods['ideal'];

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
		$this->assertEquals( 'SOFORT', $sofort_method->get_label() );
		$this->assertEquals( 'Pay with SOFORT', $sofort_method->get_title() );
		$this->assertEquals( 'Pay with SOFORT', $sofort_method->get_title( $mock_sofort_details ) );
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
		$this->assertFalse( $ideal_method->is_reusable() );
	}

	/**
	 * Card payment method is always enabled.
	 */
	public function test_card_payment_method_capability_is_always_enabled() {
		// Enable all payment methods.
		$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', 'EUR' );
		$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );

		$mock_capabilities_response = self::MOCK_CAPABILITIES_RESPONSE;
		$this->set_mock_payment_method_return_value( 'get_capabilities_response', $mock_capabilities_response );

		$card_method       = $this->mock_payment_methods['card'];
		$giropay_method    = $this->mock_payment_methods['giropay'];
		$p24_method        = $this->mock_payment_methods['p24'];
		$eps_method        = $this->mock_payment_methods['eps'];
		$sepa_method       = $this->mock_payment_methods['sepa_debit'];
		$sofort_method     = $this->mock_payment_methods['sofort'];
		$bancontact_method = $this->mock_payment_methods['bancontact'];
		$ideal_method      = $this->mock_payment_methods['ideal'];

		$this->assertTrue( $card_method->is_enabled_at_checkout() );
		$this->assertFalse( $giropay_method->is_enabled_at_checkout() );
		$this->assertFalse( $p24_method->is_enabled_at_checkout() );
		$this->assertFalse( $eps_method->is_enabled_at_checkout() );
		$this->assertFalse( $sepa_method->is_enabled_at_checkout() );
		$this->assertFalse( $sofort_method->is_enabled_at_checkout() );
		$this->assertFalse( $bancontact_method->is_enabled_at_checkout() );
		$this->assertFalse( $ideal_method->is_enabled_at_checkout() );
	}

	/**
	 * Payment method is only enabled when capability response contains active for payment method.
	 */
	public function test_payment_methods_are_only_enabled_when_capability_is_active() {
		$payment_method_ids = array_map( [ $this, 'get_id' ], $this->mock_payment_methods );
		foreach ( $payment_method_ids as $id ) {
			if ( 'card' === $id ) {
				continue;
			}

			$mock_capabilities_response = self::MOCK_CAPABILITIES_RESPONSE;

			$this->set_mock_payment_method_return_value( 'get_capabilities_response', $mock_capabilities_response, true );
			$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', 'EUR' );
			$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );

			$payment_method = $this->mock_payment_methods[ $id ];
			$this->assertFalse( $payment_method->is_enabled_at_checkout() );

			$capability_key                                = $payment_method->get_id() . '_payments';
			$mock_capabilities_response[ $capability_key ] = 'active';

			$this->set_mock_payment_method_return_value( 'get_capabilities_response', $mock_capabilities_response, true );
			$this->set_mock_payment_method_return_value( 'get_woocommerce_currency', 'EUR' );
			$this->set_mock_payment_method_return_value( 'is_subscription_item_in_cart', false );

			$payment_method = $this->mock_payment_methods[ $id ];
			$this->assertTrue( $payment_method->is_enabled_at_checkout() );
		}
	}
}
