<?php
/**
 * This stub assists IDE in recognizing PHPUnit tests.
 *
 * @package WooCommerce_Stripe/Tests/WP_UnitTestCase
 */

/**
 * WP_UnitTestCase class
 */
class WC_Mock_Stripe_API_Unit_Test_Case extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_API
	 */
	protected $stripe_api;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->stripe_api = $this->createMock( WC_Stripe_API::class );
		WC_Stripe_API::set_instance( $this->stripe_api );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();
		WC_Stripe_Payment_Method_Configurations::reset_primary_configuration();
	}

	/**
	 * Expect payment method configuration to be updated with enabled payment method IDs and disabled payment method IDs.
	 *
	 * @param array $enabled_payment_method_ids
	 * @param array $disabled_payment_method_ids
	 */
	protected function expect_payment_method_configurations_update( $enabled_payment_method_ids = [], $disabled_payment_method_ids = [] ) {
		$this->stripe_api->expects( $this->once() )->method( 'update_payment_method_configurations' )->with(
			$this->equalTo( 'pmc_abcdef' ),
			$this->callback(
				function ( $actual ) use ( $enabled_payment_method_ids, $disabled_payment_method_ids ) {
					foreach ( $enabled_payment_method_ids as $payment_method ) {
						if ( ! isset( $actual[ $payment_method ] ) || 'on' !== $actual[ $payment_method ]['display_preference']['preference'] ) {
							return false;
						}
					}
					foreach ( $disabled_payment_method_ids as $payment_method ) {
						if ( ! isset( $actual[ $payment_method ] ) || 'off' !== $actual[ $payment_method ]['display_preference']['preference'] ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * Mock the payment method configurations.
	 *
	 * @param array $enabled_payment_method_ids
	 * @param array $disabled_payment_method_ids
	 */
	protected function mock_payment_method_configurations( $enabled_payment_method_ids = [], $disabled_payment_method_ids = [] ) {
		$payment_method_configuration = [
			'id'       => 'pmc_abcdef',
			'object'   => 'payment_method_configuration',
			'active'   => true,
			'parent'   => WC_Stripe_Payment_Method_Configurations::TEST_MODE_CONFIGURATION_PARENT_ID,
			'livemode' => false,
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

	/**
	 * @param array $account_data
	 *
	 * @return void
	 */
	protected function set_stripe_account_data( $account_data ) {
		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
												->disableOriginalConstructor()
												->setMethods( [ 'get_cached_account_data' ] )
												->getMock();
		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn( $account_data );
	}
}
