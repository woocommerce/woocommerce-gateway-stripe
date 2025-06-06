<?php

namespace WooCommerce\Stripe\Tests;

use WooCommerce\Stripe\Tests\Helpers\UPE_Test_Helper;
use WC_Stripe;
use WC_Stripe_Apple_Pay_Registration;
use WC_Stripe_Helper;
use WC_Stripe_Payment_Methods;

/**
 * These teste make assertions against class WC_Stripe_Apple_Pay_Registration.
 *
 * @package WooCommerce/Stripe/Apple_Pay_Registration
 *
 * WC_Stripe_Apple_Pay_Registration unit tests.
 */
class WC_Stripe_Apple_Pay_Registration_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * Mocked system under test.
	 *
	 * @var WC_Stripe_Apple_Pay_Registration
	 */
	private $mock_wc_apple_pay_registration;

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

		$this->mock_wc_apple_pay_registration = $this->getMockBuilder( 'WC_Stripe_Apple_Pay_Registration' )
		->disableOriginalConstructor()
		->setMethods(
			[
				'register_domain',
			]
		)
		->getMock();

		$settings                    = WC_Stripe_Helper::get_stripe_settings();
		$settings['enabled']         = 'yes';
		$settings['testmode']        = 'yes';
		$settings['test_secret_key'] = '123';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

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

	public function test_register_domain_if_configured_no_secret_key() {
		$this->legacy_checkout_setup( true );

		$this->mock_wc_apple_pay_registration
			->expects( $this->never() )
			->method( 'register_domain' );

		$settings = WC_Stripe_Helper::get_stripe_settings();
		unset( $settings['test_secret_key'] );
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		$this->mock_wc_apple_pay_registration->register_domain_if_configured();
	}

	public function test_register_domain_if_configured_supported_country() {
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
			->method( 'register_domain' );

		$this->mock_wc_apple_pay_registration->register_domain_if_configured();
	}

	public function test_register_domain_if_configured_unsupported_country() {
		$this->legacy_checkout_setup( true );

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
			->method( 'register_domain' );

		$this->mock_wc_apple_pay_registration->register_domain_if_configured();
	}

	/**
	 * Test for when Apple Pay (legacy PRB) are disabled.
	 */
	public function test_register_domain_if_configured_apple_pay_disabled() {
		$this->legacy_checkout_setup( false );

		$this->mock_wc_apple_pay_registration
			->expects( $this->never() )
			->method( 'register_domain' );

		$this->mock_wc_apple_pay_registration->register_domain_if_configured();
	}

	/**
	 * Test for UPE, Apple Pay enabled.
	 */
	public function test_register_domain_if_configured_upe_apple_pay_enabled() {
		$this->upe_checkout_setup();

		$this->mock_wc_apple_pay_registration
			->expects( $this->once() )
			->method( 'register_domain' );

		$this->mock_wc_apple_pay_registration->register_domain_if_configured();
	}

	/**
	 * Test for UPE, Apple Pay disabled.
	 */
	public function test_register_domain_if_configured_upe_apple_pay_disabled() {
		$this->upe_checkout_setup( false );

		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::CARD ] );

		$this->mock_wc_apple_pay_registration
			->expects( $this->never() )
			->method( 'register_domain' );
	}
}
