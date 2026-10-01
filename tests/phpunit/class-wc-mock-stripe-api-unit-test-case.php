<?php

/**
 * This stub assists IDE in recognizing PHPUnit tests.
 *
 * @package WooCommerce_Stripe/Tests/WP_UnitTestCase
 */

/**
 * WP_UnitTestCase class
 */
abstract class WC_Mock_Stripe_API_Unit_Test_Case extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_API
	 */
	protected $stripe_api;

	/**
	 * Response returned by the get_payment_method_configurations stub. Held in a property so a later
	 * mock_payment_method_configurations() call can swap it (PHPUnit stubs are first-match-wins).
	 *
	 * @var object|null
	 */
	private $payment_method_configurations_response = null;

	/**
	 * The account service as it was before this test replaced it.
	 *
	 * @var WC_Stripe_Account|null
	 */
	private $original_account = null;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->original_account = WC_Stripe::get_instance()->account;
		$this->stripe_api       = $this->createMock( WC_Stripe_API::class );
		$this->stripe_api->method( 'get_payment_method_configurations' )->willReturnCallback(
			function () {
				return $this->payment_method_configurations_response;
			}
		);
		WC_Stripe_API::set_instance( $this->stripe_api );
		$this->reset_payment_method_configuration_state();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		// Tests here swap the WC_Stripe singleton's account for a partial mock built with
		// disableOriginalConstructor(). Nothing else resets that singleton, so a mock left
		// installed keeps answering later tests, and its unstubbed real methods run against a
		// null $connect — a fatal in whichever test next reaches the account.
		if ( null !== $this->original_account ) {
			WC_Stripe::get_instance()->account = $this->original_account;
			$this->original_account            = null;
		}

		parent::tear_down();
		$this->reset_payment_method_configuration_state();
		// Restore the real API singleton: the mock's stub closure is bound to this test case, so
		// leaving it installed would leak this test's stubbed responses into non-harness tests.
		WC_Stripe_API::set_instance( null );
	}

	/**
	 * Reset all PMC cache layers. The cache key is mode-prefixed and tear_down runs after the DB
	 * rollback has reverted testmode, so a current-mode-only clear leaks the other mode's entry.
	 */
	protected function reset_payment_method_configuration_state() {
		WC_Stripe_Payment_Method_Configurations::clear_payment_method_configuration_cache();
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 'test' );
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Payment_Method_Configurations::CONFIGURATION_CACHE_KEY, 'live' );
		delete_option( WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
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

		$this->payment_method_configurations_response = (object) [
			'data' => [
				(object) $payment_method_configuration,
			],
		];

		// Anything fetched before this point (e.g. during gateway re-init) cached the previous
		// response; reset so the configuration mocked here is what subsequent reads see.
		$this->reset_payment_method_configuration_state();
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
