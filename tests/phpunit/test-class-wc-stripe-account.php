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
	/**
	 * The Stripe account instance.
	 *
	 * @var WC_Stripe_Account
	 */
	private $account;

	public function setUp() {
		parent::setUp();

		$stripe_settings                         = get_option( 'woocommerce_stripe_settings' );
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		$this->mock_connect = $this->getMockBuilder( 'WC_Stripe_Connect' )
									->disableOriginalConstructor()
									->setMethods(
										[
											'is_connected',
										]
									)
									->getMock();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-account.php';
		$this->account = new WC_Stripe_Account( $this->mock_connect, 'WC_Helper_Stripe_Api' );
	}

	public function tearDown() {
		parent::tearDown();

		delete_transient( 'wcstripe_account_data_test' );
		delete_transient( 'wcstripe_account_data_live' );
		delete_option( 'woocommerce_stripe_settings' );
	}

	public function test_get_cached_account_data_returns_empty_when_stripe_is_not_connected() {
		$this->mock_connect->method( 'is_connected' )->willReturn( false );
		$cached_data = $this->account->get_cached_account_data();

		$this->assertEmpty( $cached_data );
	}

	public function test_get_cached_account_data_returns_data_when_cache_is_valid() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$account = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];
		set_transient( 'wcstripe_account_data_test', $account );

		$cached_data = $this->account->get_cached_account_data();

		$this->assertSame( $cached_data, $account );
	}

	public function test_get_cached_account_data_fetch_data_when_cache_is_invalid() {
		$this->mock_connect->method( 'is_connected' )->willReturn( true );
		$expected_cached_data = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];

		$cached_data = $this->account->get_cached_account_data();

		$this->assertSame( $cached_data, $expected_cached_data );
	}

	public function test_clear_cache() {
		$account = [
			'id'    => '1234',
			'email' => 'test@example.com',
		];
		set_transient( 'wcstripe_account_data_test', $account );
		set_transient( 'wcstripe_account_data_live', $account );

		$this->account->clear_cache();
		$this->assertFalse( get_transient( 'wcstripe_account_data_test' ) );
		$this->assertFalse( get_transient( 'wcstripe_account_data_live' ) );
	}
}
