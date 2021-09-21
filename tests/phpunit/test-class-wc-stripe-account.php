<?php
/**
 * Class WC_Stripe_Account_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Account
 */

/**
 * Class WC_Stripe_Account tests.
 */
class WC_Stripe_Account_Test extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-account.php';
		$this->account = new WC_Stripe_Account();

		$stripe_settings                         = get_option( 'woocommerce_stripe_settings' );
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		$this->mock_api_client = $this->getMockBuilder( 'WC_Stripe_API' )
									->disableOriginalConstructor()
									->setMethods(
										[
											'request',
										]
									)
									->getMock();
	}

	public function tearDown() {
		parent::tearDown();

		delete_transient( 'wcstripe_account_data' );
		delete_option( 'woocommerce_stripe_settings' );
	}

	public function test_get_cached_account_data_returns_empty_when_stripe_is_not_connected() {
		delete_option( 'woocommerce_stripe_settings' );
		$cached_data = $this->account->get_cached_account_data();

		$this->assertEmpty( $cached_data );
	}

	public function test_get_cached_account_data_returns_data_when_cache_is_valid() {
		$account = [
			'id'   => '1234',
			'mode' => 'test',
		];
		set_transient( 'wcstripe_account_data', $account );

		$cached_data = $this->account->get_cached_account_data();

		$this->assertSame( $cached_data, $account );
	}

	public function test_get_cached_account_data_fetch_data_when_cache_is_invalid() {
		$response_data        = [
			'id' => '1234',
		];
		$expected_cached_data = [
			'id'   => '1234',
			'mode' => 'test',
		];
		$this->mock_api_client->expects( $this->once() )->method( 'request' )->will(
			$this->returnValue( $response_data )
		);

		$cached_data = $this->account->get_cached_account_data();

		$this->assertEmpty( $cached_data );
	}

	public function test_clear_cache() {
		$account = [
			'id'   => '1234',
			'mode' => 'test',
		];
		set_transient( 'wcstripe_account_data', $account );

		$this->account->clear_cache();
		$this->assertFalse( get_transient( 'wcstripe_account_data' ) );
	}

	public function test_get_account_status() {
		$account = [
			'id'              => '1234',
			'email'           => 'test@example.com',
			'capabilities'    => [],
			'payouts_enabled' => false,
			'settings'        => [
				'payouts' => [],
			],
			'mode'            => 'test',
		];
		set_transient( 'wcstripe_account_data', $account );

		$expected_response = [
			'email'           => 'test@example.com',
			'paymentsEnabled' => false,
			'depositsEnabled' => false,
			'accountLink'     => 'https://stripe.com/support',
			'mode'            => 'test',
		];

		$account_status = $this->account->get_account_status();

		$this->assertSame( $account_status, $expected_response );
	}

	public function test_get_account_status_with_error_when_account_is_empty() {
		delete_option( 'woocommerce_stripe_settings' );

		$expected_response = [
			'error' => true,
		];

		$account_status = $this->account->get_account_status();
		$this->assertSame( $account_status, $expected_response );
	}
}
