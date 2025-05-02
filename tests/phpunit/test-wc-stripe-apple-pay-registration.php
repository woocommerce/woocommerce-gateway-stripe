<?php
/**
 * These teste make assertions against class WC_Stripe_Apple_Pay_Registration.
 *
 * @package WooCommerce_Stripe/Tests/Apple_Pay_Registration
 */

/**
 * WC_Stripe_Apple_Pay_Registration unit tests.
 */
class WC_Stripe_Apple_Pay_Registration_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * System under test.
	 *
	 * @var WC_Stripe_Apple_Pay_Registration
	 */
	private $wc_apple_pay_registration;

	/**
	 * Mocked system under test.
	 *
	 * @var WC_Stripe_Apple_Pay_Registration
	 */
	private $mock_wc_apple_pay_registration;

	/**
	 * Domain association file name.
	 *
	 * @var string
	 */
	private $file_name;

	/**
	 * Domain association file contents.
	 *
	 * @var string
	 */
	private $file_contents;

	/**
	 * UPE test helper.
	 *
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->wc_apple_pay_registration = new WC_Stripe_Apple_Pay_Registration();

		$this->mock_wc_apple_pay_registration = $this->getMockBuilder( 'WC_Stripe_Apple_Pay_Registration' )
		->disableOriginalConstructor()
		->setMethods(
			[
				'update_domain_association_file',
			]
		)
		->getMock();

		$this->file_name             = 'apple-developer-merchantid-domain-association';
		$this->initial_file_contents = file_get_contents( WC_STRIPE_PLUGIN_PATH . '/' . $this->file_name ); // @codingStandardsIgnoreLine

		$this->mock_wc_apple_pay_registration->stripe_settings = [
			'enabled'    => 'yes',
			'secret_key' => '123',
		];

		$this->upe_helper = new UPE_Test_Helper();
	}

	/**
	 * Disable UPE and enable/disable Apple Pay/Google Pay.
	 *
	 * @param bool $payment_request_enabled Whether Apple Pay/Google Pay should be enabled.
	 */
	private function legacy_checkout_setup( $payment_request_enabled = true ) {
		$this->upe_helper->disable_upe();
		$this->upe_helper->reload_payment_gateways();

		$settings                    = WC_Stripe_Helper::get_stripe_settings();
		$settings['payment_request'] = $payment_request_enabled ? 'yes' : 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		WC_Stripe::get_instance()->get_main_stripe_gateway()->init_settings();
	}

	/**
	 * Enable UPE and enable/disable Apple Pay/Google Pay.
	 *
	 * @param bool $payment_request_enabled Whether Apple Pay/Google Pay should be enabled.
	 */
	private function upe_checkout_setup( $payment_request_enabled = true ) {
		$this->upe_helper->enable_upe();
		$this->upe_helper->reload_payment_gateways();

		if ( $payment_request_enabled ) {
			$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::APPLE_PAY ] );
		} else {
			$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ] );
		}
	}

	public function tear_down() {
		$path     = untrailingslashit( ABSPATH );
		$dir      = '.well-known';
		$fullpath = $path . '/' . $dir . '/' . $this->file_name;
		// Unlink domain association file before tests.
		@unlink( $fullpath ); // @codingStandardsIgnoreLine

		parent::tear_down();
	}

	public function test_update_domain_association_file() {
		$path     = untrailingslashit( ABSPATH );
		$dir      = '.well-known';
		$fullpath = $path . '/' . $dir . '/' . $this->file_name;

		$this->wc_apple_pay_registration->update_domain_association_file();
		$updated_file_contents = file_get_contents( $fullpath ); // @codingStandardsIgnoreLine

		$this->assertEquals( $updated_file_contents, $this->initial_file_contents );
	}

	public function test_add_domain_association_rewrite_rule() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->wc_apple_pay_registration->add_domain_association_rewrite_rule();
		flush_rewrite_rules();

		global $wp_rewrite;
		$rewrite_rule = 'index.php?' . $this->file_name . '=1';

		$this->assertContains( $rewrite_rule, $wp_rewrite->rewrite_rules() );
	}

	public function test_verify_domain_if_configured_no_secret_key() {
		$this->legacy_checkout_setup();

		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_cached_account_data',
				]
			)
			->getMock();

		WC_Stripe::get_instance()->account
			->expects( $this->never() )
			->method( 'get_cached_account_data' );

		$this->mock_wc_apple_pay_registration->stripe_settings['secret_key'] = '';

		$this->mock_wc_apple_pay_registration->verify_domain_if_configured();
	}

	public function test_verify_domain_if_configured_supported_country() {
		$this->legacy_checkout_setup();

		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_cached_account_data',
				]
			)
			->getMock();

		WC_Stripe::get_instance()->account
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'country' => 'US' ] );

		$this->mock_wc_apple_pay_registration
			->expects( $this->once() )
			->method( 'update_domain_association_file' );

		$this->mock_wc_apple_pay_registration->verify_domain_if_configured();
	}

	public function test_verify_domain_if_configured_unsupported_country() {
		$this->legacy_checkout_setup();

		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_cached_account_data',
				]
			)
			->getMock();

		WC_Stripe::get_instance()->account
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'country' => 'IN' ] );

		$this->mock_wc_apple_pay_registration
			->expects( $this->never() )
			->method( 'update_domain_association_file' );

		$this->mock_wc_apple_pay_registration->verify_domain_if_configured();
	}

	/**
	 * Test for when Apple Pay (legacy PRB) are disabled.
	 */
	public function test_verify_domain_if_configured_apple_pay_disabled() {
		$this->legacy_checkout_setup( false );

		$this->mock_wc_apple_pay_registration
			->expects( $this->never() )
			->method( 'update_domain_association_file' );

		$this->mock_wc_apple_pay_registration->verify_domain_if_configured();
	}

	/**
	 * Test for UPE, Apple Pay enabled.
	 */
	public function test_verify_domain_if_configured_upe_apple_pay_enabled() {
		$this->upe_checkout_setup();

		$this->mock_wc_apple_pay_registration
			->expects( $this->once() )
			->method( 'update_domain_association_file' );

		$this->mock_wc_apple_pay_registration->verify_domain_if_configured();
	}

	/**
	 * Test for UPE, Apple Pay disabled.
	 */
	public function test_verify_domain_if_configured_upe_apple_pay_disabled() {
		$this->upe_checkout_setup( false );

		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::CARD ] );

		$this->mock_wc_apple_pay_registration
			->expects( $this->never() )
			->method( 'update_domain_association_file' );

		// Restore initial setting for UPE.
		$this->upe_helper->disable_upe();
		$this->upe_helper->reload_payment_gateways();
	}
}
