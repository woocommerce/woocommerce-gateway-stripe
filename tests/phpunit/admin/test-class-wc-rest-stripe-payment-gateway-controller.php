<?php
/**
 * Class WC_REST_Stripe_Payment_Gateway_Controller_Test
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\RestApi;

/**
 * WC_REST_Stripe_Payment_Gateway_Controller_Test unit tests.
 *
 * Adding both @runTestsInSeparateProcesses and @preserveGlobalState annotations
 * to prevent the global state to leak between these and WC_REST_Stripe_Settings_Controller_Test
 * tests.
 *
 * TODO: Find a way to isolate the gateway so it does not affect the Stripe settings
 * tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_REST_Stripe_Payment_Gateway_Controller_Test extends WP_UnitTestCase {

	/**
	 * Tested REST route.
	 */
	const REST_ROUTE = '/wc/v3/wc_stripe/payment-gateway/stripe_alipay';

	/**
	 * Gateway instance that the controller uses.
	 *
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->gateway = WC()->payment_gateways()->payment_gateways()[ WC_Gateway_Stripe_Alipay::ID ];

		if ( version_compare( WC_VERSION, '3.4.0', '<' ) ) {
			$this->markTestSkipped( 'The controller is not compatible with older WC versions, due to the missing `update_option` method on the gateway.' );
		}

		add_action( 'rest_api_init', [ $this, 'deregister_wc_blocks_rest_api' ], 5 );

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );
	}

	/**
	 * @dataProvider boolean_field_provider
	 */
	public function test_boolean_fields( $rest_key, $option_name, $inverse = false ) {
		// It returns option value under expected key with HTTP code 200.
		$this->get_gateway()->update_option( $option_name, 'yes' );
		$response = $this->rest_get_settings();
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $inverse ? false : true, $response->get_data()[ $rest_key ] );

		// When option is "yes", return true (or if $inverse, false).
		$this->get_gateway()->update_option( $option_name, 'yes' );
		$this->assertEquals( $inverse ? false : true, $this->rest_get_settings()->get_data()[ $rest_key ] );

		// When option is "no", return false (or if $inverse, true).
		$this->get_gateway()->update_option( $option_name, 'no' );
		$this->assertEquals( $inverse ? true : false, $this->rest_get_settings()->get_data()[ $rest_key ] );

		// Update if new value is boolean.
		$this->get_gateway()->update_option( $option_name, $inverse ? 'yes' : 'no' );
		$request = new WP_REST_Request( 'POST', self::REST_ROUTE );
		$request->set_param( $rest_key, true );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $inverse ? false : true, $this->rest_get_settings()->get_data()[ $rest_key ] );

		// Do not update if rest key not present in update request.
		$status_before_request = $this->rest_get_settings()->get_data()[ $rest_key ];
		$request               = new WP_REST_Request( 'POST', self::REST_ROUTE );
		$response              = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $status_before_request, $this->rest_get_settings()->get_data()[ $rest_key ] );

		// Do not update if REST value is not boolean.
		$status_before_request = $this->rest_get_settings()->get_data()[ $rest_key ];
		$request               = new WP_REST_Request( 'POST', self::REST_ROUTE );
		$request->set_param( $rest_key, 'foo' );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $status_before_request, $this->rest_get_settings()->get_data()[ $rest_key ] );
	}

	/**
	 * @dataProvider text_field_provider
	 */
	public function test_text_fields( $rest_key, $option_name ) {
		// It returns option value under expected key with HTTP code 200.
		$this->get_gateway()->update_option( $option_name, 'foo' );
		$response = $this->rest_get_settings();
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'foo', $response->get_data()[ $rest_key ] );

		// Update it if new value is provided.
		$this->get_gateway()->update_option( $option_name, 'foo' );
		$request = new WP_REST_Request( 'POST', self::REST_ROUTE );
		$request->set_param( $rest_key, 'bar' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'bar', $this->rest_get_settings()->get_data()[ $rest_key ] );

		// Do not update if rest key not present in update request.
		$value_before_request = $this->rest_get_settings()->get_data()[ $rest_key ];
		$request              = new WP_REST_Request( 'POST', self::REST_ROUTE );
		$response             = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $value_before_request, $this->rest_get_settings()->get_data()[ $rest_key ] );

		// Update to an empty string if REST value is not a valid text.
		$request = new WP_REST_Request( 'POST', self::REST_ROUTE );
		$request->set_param( $rest_key, false );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( '', $this->rest_get_settings()->get_data()[ $rest_key ] );
	}

	public function test_get_settings_fails_if_user_cannot_manage_woocommerce() {
		$cb = $this->create_can_manage_woocommerce_cap_override( false );
		add_filter( 'user_has_cap', $cb );
		$response = $this->rest_get_settings();
		$this->assertEquals( 403, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );
		$response = $this->rest_get_settings();
		$this->assertEquals( 200, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );
	}

	public function test_update_settings_fails_if_user_cannot_manage_woocommerce() {
		$cb = $this->create_can_manage_woocommerce_cap_override( false );
		add_filter( 'user_has_cap', $cb );
		$response = rest_do_request( new WP_REST_Request( 'POST', self::REST_ROUTE ) );
		$this->assertEquals( 403, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );
		$response = rest_do_request( new WP_REST_Request( 'POST', self::REST_ROUTE ) );
		$this->assertEquals( 200, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );
	}

	public function boolean_field_provider() {
		return [
			'is_stripe_alipay_enabled' => [ 'is_stripe_alipay_enabled', 'enabled' ],
		];
	}

	public function text_field_provider() {
		return [
			'stripe_alipay_name'        => [ 'stripe_alipay_name', 'title' ],
			'stripe_alipay_description' => [ 'stripe_alipay_description', 'description' ],
		];
	}

	/**
	 * @param bool $can_manage_woocommerce
	 *
	 * @return Closure
	 */
	private function create_can_manage_woocommerce_cap_override( $can_manage_woocommerce ) {
		return function ( $allcaps ) use ( $can_manage_woocommerce ) {
			$allcaps['manage_woocommerce'] = $can_manage_woocommerce;

			return $allcaps;
		};
	}

	/**
	 * Deregister WooCommerce Blocks REST routes to prevent _doing_it_wrong() notices
	 * after calls to rest_do_request().
	 */
	public function deregister_wc_blocks_rest_api() {
		try {
			if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
				throw new Exception( 'This is not WC Blocks >= 2.6.0. Skipping to `catch` block!' );
			}

			/* For WooCommerce Blocks >= 2.6.0: */
			$wc_blocks_rest_api = Package::container()->get( RestApi::class );
			remove_action( 'rest_api_init', [ $wc_blocks_rest_api, 'register_rest_routes' ] );
		} catch ( Exception $e ) {
			/* For WooCommerce Blocks < 2.6.0: */
			remove_action( 'rest_api_init', [ RestApi::class, 'register_rest_routes' ] );
		}
	}

	/**
	 * @return WP_REST_Response
	 */
	private function rest_get_settings() {
		$request = new WP_REST_Request( 'GET', self::REST_ROUTE );

		return rest_do_request( $request );
	}

	/**
	 * @return WC_Gateway_Stripe
	 */
	private function get_gateway() {
		return $this->gateway;
	}
}
