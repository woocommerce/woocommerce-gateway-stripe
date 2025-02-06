<?php
/**
 * Class WC_REST_Stripe_Settings_Controller_Test_GB
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\RestApi;

/**
 * WC_REST_Stripe_Settings_Controller_Test_GB unit tests.
 */
class WC_REST_Stripe_Settings_Controller_Test_GB extends WP_UnitTestCase {

	/**
	 * Tested REST route.
	 */
	const SETTINGS_ROUTE = '/wc/v3/wc_stripe/settings';

	/**
	 * Gateway instance that the controller uses.
	 *
	 * @var WC_Gateway_Stripe
	 */
	private static $gateway;

	/**
	 * Enable UPE and store gateway instance.
	 *
	 * We are doing this here because if we did it in set_up(), the method body would get called before every single test
	 * however the REST controller is instantiated only once. If we reloaded gateways then, WC()->payment_gateways()
	 * would contain another gateway instance than the controller.
	 *
	 * @see UPE_Test_Utils::reload_payment_gateways()
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		$upe_helper = new UPE_Test_Helper();

		// Enable Bacs for tests.
		update_option( WC_Stripe_Feature_Flags::LPM_BACS_FEATURE_FLAG_NAME, 'yes' );

		// All tests assume UPE is enabled.
		update_option( '_wcstripe_feature_upe', 'yes' );
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();
		self::$gateway = WC()->payment_gateways()->payment_gateways()[ WC_Gateway_Stripe::ID ];
	}

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		if ( version_compare( WC_VERSION, '3.4.0', '<' ) ) {
			$this->markTestSkipped( 'The controller is not compatible with older WC versions, due to the missing `update_option` method on the gateway.' );
		}

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$stripe_settings                                    = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']                         = 'yes';
		$stripe_settings['testmode']                        = 'yes';
		$stripe_settings['test_publishable_key']            = 'pk_test_key';
		$stripe_settings['test_secret_key']                 = 'sk_test_key';
		$stripe_settings['country']                         = 'GB';
		$stripe_settings['stripe_upe_payment_method_order'] = [
			'card',
			'alipay',
			'klarna',
			'afterpay_clearpay',
			'eps',
			'bancontact',
			'boleto',
			'ideal',
			'oxxo',
			'sepa_debit',
			'p24',
			'multibanco',
			'link',
			'wechat_pay',
			'bacs_debit',
		];
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$account = [
			'country'      => 'GB',
			'capabilities' => [],
		];
		set_transient( 'wcstripe_account_data_test', $account );

		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		self::$gateway = WC()->payment_gateways()->payment_gateways()[ WC_Gateway_Stripe::ID ];
	}

	public function test_get_settings_returns_available_payment_method_ids_for_gb() {
		$response             = $this->rest_get_settings();
		$expected_method_ids  = array_keys( self::$gateway->payment_methods );
		$available_method_ids = $response->get_data()['available_payment_method_ids'];

		$this->assertEquals( $expected_method_ids, $available_method_ids );
		$this->assertContains( WC_Stripe_Payment_Methods::BACS_DEBIT, $available_method_ids );
	}

	public function test_get_settings_returns_ordered_payment_method_ids_for_gb() {
		$response = $this->rest_get_settings();

		$expected_methods = $this->get_gateway()->payment_methods;
		unset( $expected_methods['link'] );
		$expected_method_ids = array_keys( $expected_methods );
		$ordered_method_ids  = $response->get_data()['ordered_payment_method_ids'];

		$this->assertEquals(
			$expected_method_ids,
			$ordered_method_ids
		);
		$this->assertContains( WC_Stripe_Payment_Methods::BACS_DEBIT, $ordered_method_ids );
	}

	/**
	 * @return WP_REST_Response
	 */
	private function rest_get_settings() {
		$request = new WP_REST_Request( 'GET', self::SETTINGS_ROUTE );

		return rest_do_request( $request );
	}

	/**
	 * @return WC_Gateway_Stripe
	 */
	private function get_gateway() {
		return self::$gateway;
	}

}
