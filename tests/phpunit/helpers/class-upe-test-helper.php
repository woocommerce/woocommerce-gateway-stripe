<?php

/**
 * Provides methods useful when testing UPE-related logic.
 */
class UPE_Test_Helper {

	/**
	 * @var WC_Stripe_API
	 */
	private $stripe_api;

	public function __construct() {
		$this->stripe_api = $this->createMock( WC_Stripe_API::class );
		WC_Stripe_API::set_instance( $this->stripe_api );
	}

	/**
	 * Creates a mock object for the specified class
	 *
	 * @param string $class_name Name of the class to mock
	 * @return PHPUnit\Framework\MockObject\MockObject
	 */
	private function create_mock( $class_name ) {
		$mock_builder = new PHPUnit\Framework\MockObject\Generator();
		return $mock_builder->getMock( $class_name );
	}

	public function enable_upe_feature_flag() {
		// Force the UPE feature flag on.
		add_filter(
			'pre_option__wcstripe_feature_upe',
			function() {
				return 'yes';
			}
		);
		WC_Stripe_Helper::delete_main_stripe_settings();
		$this->reload_payment_gateways();
	}

	public function reload_payment_gateways() {
		$closure = Closure::bind(
			function () {
				$this->stripe_gateway = null;
			},
			woocommerce_gateway_stripe(),
			WC_Stripe::class
		);
		$closure();
		WC()->payment_gateways()->payment_gateways = [];
		WC()->payment_gateways()->init();
		WC_Stripe_Helper::$stripe_legacy_gateways = [];
	}

	public function enable_upe() {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
	}

	/**
	 * Mock the payment method configurations.
	 *
	 * @param array $enabled_payment_method_ids
	 * @param array $disabled_payment_method_ids
	 */
	public function mock_payment_method_configurations( $enabled_payment_method_ids = [], $disabled_payment_method_ids = [] ) {
		$payment_method_configuration = [
			'id'       => 'pmc_abcdef',
			'object'   => 'payment_method_configuration',
			'active'   => true,
			'parent'   => true,
		];

		foreach ( $enabled_payment_method_ids as $payment_method ) {
			$payment_method_configuration[ $payment_method ] = (object) [
				'display_preference' => (object) [ 'value' => 'on' ],
			];
		}

		foreach ( $disabled_payment_method_ids as $payment_method ) {
			$payment_method_configuration[ $payment_method ] = (object) [
				'display_preference' => (object) [ 'value' => 'off' ],
			];
		}

		$this->stripe_api->method( 'get_payment_method_configurations' )->willReturn(
			(object) [
				'data' => [
					(object) $payment_method_configuration,
				],
			],
		);
	}

	public function expect_payment_method_configurations_update( $enabled_payment_method_ids, $disabled_payment_method_ids ) {
		$payment_method_configuration = [];

		foreach ( $enabled_payment_method_ids as $payment_method ) {
			$payment_method_configuration[ $payment_method ] = [
				'display_preference' => [ 'preference' => 'on' ],
			];
		}

		foreach ( $disabled_payment_method_ids as $payment_method ) {
			$payment_method_configuration[ $payment_method ] = [
				'display_preference' => [ 'preference' => 'off' ],
			];
		}
		$this->stripe_api->expects( \PHPUnit_Framework_TestCase::once() )->method( 'update_payment_method_configurations' )->with(
			\PHPUnit_Framework_TestCase::equalTo( 'pmc_abcdef' ),
			\PHPUnit_Framework_TestCase::equalTo( $payment_method_configuration ),
		);
	}
}
