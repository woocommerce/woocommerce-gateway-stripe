<?php
/**
 * Class WC_REST_Stripe_Settings_Controller_Test_GB
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\RestApi;

/**
 * WC_REST_Stripe_Settings_Controller_Test_Multiple_Countries unit tests.
 */
class WC_REST_Stripe_Settings_Controller_Test_Multiple_Countries extends WP_UnitTestCase {

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
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Enable LPMs
		update_option( WC_Stripe_Feature_Flags::LPM_BACS_FEATURE_FLAG_NAME, 'yes' );
		update_option( WC_Stripe_Feature_Flags::LPM_ACH_FEATURE_FLAG_NAME, 'yes' );
		update_option( WC_Stripe_Feature_Flags::LPM_ACSS_FEATURE_FLAG_NAME, 'yes' );

		// All tests assume UPE feature is enabled.
		update_option( '_wcstripe_feature_upe', 'yes' );

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );
	}

	public function test_get_settings() {
		// test getting payment methods from Canada
		self::$gateway = WC_Helper_Rest_Server::reset_rest_server('CA');
		$response = $this->rest_get_settings();
		error_log('$response: ' . print_r($response->get_data()['available_payment_method_ids'], true));

		// test getting payments methos from UK
		self::$gateway = WC_Helper_Rest_Server::reset_rest_server('GB');
		$response = $this->rest_get_settings();
		error_log('$response: ' . print_r($response->get_data()['available_payment_method_ids'], true));

		// test getting payment methods from USA
		self::$gateway = WC_Helper_Rest_Server::reset_rest_server('US');
		$response = $this->rest_get_settings();
		error_log('$response: ' . print_r($response->get_data()['available_payment_method_ids'], true));
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
