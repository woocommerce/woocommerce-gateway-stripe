<?php

namespace WooCommerce\Stripe\Tests\Admin;

use Automattic\WooCommerce\Blocks\RestApi;
use WooCommerce\Stripe\Tests\Helpers\UPE_Test_Helper;
use WC_REST_Stripe_Settings_Controller;
use WC_Stripe_API;
use WC_Stripe_Database_Cache;
use WC_Stripe_Helper;
use WC_Stripe_Payment_Methods;
use WC_Stripe_UPE_Payment_Gateway;
use WooCommerce\Stripe\Tests\WC_Mock_Stripe_API_Unit_Test_Case;

/**
 * Class WC_REST_Stripe_Settings_Controller_Test_GB
 *
 * WC_REST_Stripe_Settings_Controller_GB_Test unit tests.
 */
class WC_REST_Stripe_Settings_Controller_GB_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Tested REST route.
	 */
	const SETTINGS_ROUTE = '/wc/v3/wc_stripe/settings';

	/**
	 * Gateway instance that the controller uses.
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private static $gateway;

	/**
	 * Controller instance
	 *
	 * @var WC_REST_Stripe_Settings_Controller
	 */
	private $controller;

	/**
	 * Enable UPE and store gateway instance.
	 *
	 * We are doing this here because if we did it in set_up(), the method body would get called before every single test
	 * however the REST controller is instantiated only once. If we reloaded gateways then, WC()->payment_gateways()
	 * would contain another gateway instance than the controller.
	 *
	 * @see UPE_Test_Utils::reload_payment_gateways()
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		self::$gateway = WC()->payment_gateways()->payment_gateways()[ WC_Stripe_UPE_Payment_Gateway::ID ];
	}


	/**
	 * Pre-test setup
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( version_compare( WC_VERSION, '3.4.0', '<' ) ) {
			$this->markTestSkipped( 'The controller is not compatible with older WC versions, due to the missing `update_option` method on the gateway.' );
		}

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		$stripe_settings['country']              = 'GB';

		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->set_stripe_account_data(
			[
				'country'      => 'GB',
				'capabilities' => [],
			]
		);

		$this->controller = new WC_REST_Stripe_Settings_Controller( new WC_Stripe_UPE_Payment_Gateway() );

		self::$gateway = WC()->payment_gateways()->payment_gateways()[ WC_Stripe_UPE_Payment_Gateway::ID ];
	}

	public function tear_down() {
		// The tests in this file do not mock ALL the calls to the Stripe API, and as we use mocked API keys they trigger the 401 rate-limiter,
		// this is not a problem for these tests as they don't depend on the reponses.
		//
		// TODO: Remove this once we've mocked all calls to the Stripe API (either using the pre_http_request filter, or by using a mocked WC_Stripe_API class).
		WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );

		parent::tear_down();
	}


	public function test_get_settings_returns_available_payment_method_ids_for_gb() {
		$expected_method_ids = [
			WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::ALIPAY,
			WC_Stripe_Payment_Methods::AMAZON_PAY,
			WC_Stripe_Payment_Methods::KLARNA,
			WC_Stripe_Payment_Methods::AFTERPAY_CLEARPAY,
			WC_Stripe_Payment_Methods::EPS,
			WC_Stripe_Payment_Methods::BANCONTACT,
			WC_Stripe_Payment_Methods::BOLETO,
			WC_Stripe_Payment_Methods::IDEAL,
			WC_Stripe_Payment_Methods::OXXO,
			WC_Stripe_Payment_Methods::SEPA_DEBIT,
			WC_Stripe_Payment_Methods::P24,
			WC_Stripe_Payment_Methods::MULTIBANCO,
			WC_Stripe_Payment_Methods::LINK,
			WC_Stripe_Payment_Methods::WECHAT_PAY,
			WC_Stripe_Payment_Methods::ACSS_DEBIT,
			WC_Stripe_Payment_Methods::BACS_DEBIT,
		];
		$this->mock_payment_method_configurations( $expected_method_ids, [] );

		$response             = $this->controller->get_settings();
		$available_method_ids = $response->get_data()['available_payment_method_ids'];

		$this->assertEquals(
			$expected_method_ids,
			$available_method_ids,
		);
	}

	public function test_get_settings_returns_ordered_payment_method_ids_for_gb() {
		// Link and Amazon Pay are excluded as they are express methods only.
		$expected_ordered_method_ids = [
			WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::ALIPAY,
			WC_Stripe_Payment_Methods::KLARNA,
			WC_Stripe_Payment_Methods::AFTERPAY_CLEARPAY,
			WC_Stripe_Payment_Methods::EPS,
			WC_Stripe_Payment_Methods::BANCONTACT,
			WC_Stripe_Payment_Methods::BOLETO,
			WC_Stripe_Payment_Methods::IDEAL,
			WC_Stripe_Payment_Methods::OXXO,
			WC_Stripe_Payment_Methods::SEPA_DEBIT,
			WC_Stripe_Payment_Methods::P24,
			WC_Stripe_Payment_Methods::MULTIBANCO,
			WC_Stripe_Payment_Methods::WECHAT_PAY,
			WC_Stripe_Payment_Methods::ACSS_DEBIT,
			WC_Stripe_Payment_Methods::BACS_DEBIT,
		];
		$this->mock_payment_method_configurations( $expected_ordered_method_ids, [] );

		$response           = $this->controller->get_settings();
		$ordered_method_ids = $response->get_data()['ordered_payment_method_ids'];

		$this->assertEquals(
			$expected_ordered_method_ids,
			$ordered_method_ids
		);
	}
}
