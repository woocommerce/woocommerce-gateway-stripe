<?php
/**
 * Class WC_REST_Stripe_Settings_Controller_Test
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\RestApi;

/**
 * WC_REST_Stripe_Settings_Controller_Test unit tests.
 */
class WC_REST_Stripe_Settings_Controller_Test extends WP_UnitTestCase {

	use UPE_Utils;

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
	 * We are doing this here because if we did it in setUp(), the method body would get called before every single test
	 * however the REST controller is instantiated only once. If we reloaded gateways then, WC()->payment_gateways()
	 * would contain another gateway instance than the controller.
	 *
	 * @see UPE_Utils::reload_payment_gateways()
	 */
	public static function setUpBeforeClass() {
		// All tests assume UPE is enabled.
		update_option( '_wcstripe_feature_upe', 'yes' );
		self::enable_upe();
		self::reload_payment_gateways();
		self::$gateway = WC()->payment_gateways()->payment_gateways()[ WC_Gateway_Stripe::ID ];
	}

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		if ( version_compare( WC_VERSION, '3.4.0', '<' ) ) {
			$this->markTestSkipped( 'The controller is not compatible with older WC versions, due to the missing `update_option` method on the gateway.' );
		}

		add_action( 'rest_api_init', [ $this, 'deregister_wc_blocks_rest_api' ], 5 );

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );
	}

	public function test_get_settings_request_returns_status_code_200() {
		$response = $this->rest_get_settings();

		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_settings_returns_enabled_payment_method_ids() {
		$response = $this->rest_get_settings();

		$enabled_method_ids = $response->get_data()['enabled_payment_method_ids'];

		$this->assertEquals(
			[ 'card' ],
			$enabled_method_ids
		);
	}

	public function test_get_settings_returns_available_payment_method_ids() {
		$response = $this->rest_get_settings();

		$expected_method_ids = WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS;
		$expected_method_ids = array_map(
			function ( $method_class ) {
				return $method_class::STRIPE_ID;
			},
			$expected_method_ids
		);

		$available_method_ids = $response->get_data()['available_payment_method_ids'];

		$this->assertEquals(
			$expected_method_ids,
			$available_method_ids
		);
	}

	public function test_get_settings_request_returns_test_mode_flag() {
		$this->get_gateway()->update_option( 'testmode', 'yes' );
		$this->assertEquals( true, $this->rest_get_settings()->get_data()['is_test_mode_enabled'] );

		$this->get_gateway()->update_option( 'testmode', 'no' );
		$this->assertEquals( false, $this->rest_get_settings()->get_data()['is_test_mode_enabled'] );
	}

	public function test_get_settings_returns_if_stripe_is_enabled() {
		$this->get_gateway()->enable();
		$response = $this->rest_get_settings();
		$this->assertTrue( $response->get_data()['is_stripe_enabled'] );

		$this->get_gateway()->disable();
		$response = $this->rest_get_settings();
		$this->assertFalse( $response->get_data()['is_stripe_enabled'] );
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

	public function test_update_settings_request_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_stripe_enabled', true );
		$request->set_param( 'enabled_payment_method_ids', [ 'card' ] );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_update_settings_enables_stripe() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_stripe_enabled', true );

		rest_do_request( $request );

		$this->assertTrue( $this->get_gateway()->is_enabled() );
	}

	public function test_update_settings_disables_stripe() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_stripe_enabled', false );

		rest_do_request( $request );

		$this->assertFalse( $this->get_gateway()->is_enabled() );
	}

	public function test_update_settings_does_not_toggle_is_stripe_enabled_if_not_supplied() {
		$status_before_request = $this->get_gateway()->is_enabled();

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );

		rest_do_request( $request );

		$this->assertEquals( $status_before_request, $this->get_gateway()->is_enabled() );
	}

	public function test_update_settings_returns_error_on_non_bool_is_stripe_enabled_value() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_stripe_enabled', 'foo' );

		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_update_settings_saves_enabled_payment_methods() {
		$this->get_gateway()->update_option( 'upe_checkout_experience_accepted_payments', [ 'card' ] );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'enabled_payment_method_ids', [ 'card', 'giropay' ] );

		rest_do_request( $request );

		$this->assertEquals( [ 'card', 'giropay' ], $this->get_gateway()->get_option( 'upe_checkout_experience_accepted_payments' ) );
	}

	public function test_update_settings_validation_fails_if_invalid_gateway_id_supplied() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'enabled_payment_method_ids', [ 'foo', 'baz' ] );

		$response = rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_update_payment_request_settings_returns_status_code_200() {
		$this->get_gateway()->update_option( 'payment_request_button_theme', 'dark' );
		$this->get_gateway()->update_option( 'payment_request_button_size', 'default' );
		$this->get_gateway()->update_option( 'payment_request_button_type', 'buy' );
		$this->assertEquals( 'dark', $this->get_gateway()->get_option( 'payment_request_button_theme' ) );
		$this->assertEquals( 'default', $this->get_gateway()->get_option( 'payment_request_button_size' ) );
		$this->assertEquals( 'buy', $this->get_gateway()->get_option( 'payment_request_button_type' ) );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'payment_request_button_theme', 'light' );
		$request->set_param( 'payment_request_button_size', 'medium' );
		$request->set_param( 'payment_request_button_type', 'book' );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'light', $this->get_gateway()->get_option( 'payment_request_button_theme' ) );
		$this->assertEquals( 'medium', $this->get_gateway()->get_option( 'payment_request_button_size' ) );
		$this->assertEquals( 'book', $this->get_gateway()->get_option( 'payment_request_button_type' ) );
	}

	public function test_update_settings_fails_if_user_cannot_manage_woocommerce() {
		$cb = $this->create_can_manage_woocommerce_cap_override( false );
		add_filter( 'user_has_cap', $cb );
		$response = rest_do_request( new WP_REST_Request( 'POST', self::SETTINGS_ROUTE ) );
		$this->assertEquals( 403, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );
		$response = rest_do_request( new WP_REST_Request( 'POST', self::SETTINGS_ROUTE ) );
		$this->assertEquals( 200, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );
	}

	public function test_update_settings_enables_manual_capture() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_manual_capture_enabled', true );

		rest_do_request( $request );

		$this->assertEquals( 'no', $this->get_gateway()->get_option( 'capture' ) );
	}

	public function test_update_settings_disables_manual_capture() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_manual_capture_enabled', false );

		rest_do_request( $request );

		$this->assertEquals( 'yes', $this->get_gateway()->get_option( 'capture' ) );
	}

	public function test_update_settings_does_not_toggle_is_manual_capture_enabled_if_not_supplied() {
		$status_before_request = $this->get_gateway()->get_option( 'manual_capture' );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );

		rest_do_request( $request );

		$this->assertEquals( $status_before_request, $this->get_gateway()->get_option( 'manual_capture' ) );
	}

	public function test_update_settings_returns_error_on_non_bool_is_manual_capture_enabled_value() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_manual_capture_enabled', 'foo' );

		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_update_settings_saves_debug_log() {
		$this->get_gateway()->update_option( 'logging', 'no' );
		$this->assertEquals( 'no', $this->get_gateway()->get_option( 'logging' ) );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_debug_log_enabled', true );

		rest_do_request( $request );

		$this->assertEquals( 'yes', $this->get_gateway()->get_option( 'logging' ) );
	}

	public function test_update_settings_saves_test_mode() {
		$this->get_gateway()->update_option( 'testmode', 'no' );
		$this->assertEquals( 'no', $this->get_gateway()->get_option( 'testmode' ) );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_test_mode_enabled', true );

		rest_do_request( $request );

		$this->assertEquals( 'yes', $this->get_gateway()->get_option( 'testmode' ) );
	}

	public function test_update_settings_saves_account_statement_descriptor() {
		$this->get_gateway()->update_option( 'statement_descriptor', 'foo' );

		$new_account_descriptor = 'new account descriptor';

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'statement_descriptor', $new_account_descriptor );

		rest_do_request( $request );

		$this->assertEquals( $new_account_descriptor, $this->get_gateway()->get_option( 'statement_descriptor' ) );
	}

	public function test_update_settings_saves_payment_request_button_theme() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'payment_request_button_locations', [ 'cart', 'checkout' ] );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( [ 'cart', 'checkout' ], $this->get_gateway()->get_option( 'payment_request_button_locations' ) );
	}

	public function test_update_settings_saves_payment_request_button_size() {
		$this->get_gateway()->update_option( 'payment_request_button_size', 'default' );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'payment_request_button_size', 'medium' );

		rest_do_request( $request );

		$this->assertEquals( 'medium', $this->get_gateway()->get_option( 'payment_request_button_size' ) );
	}

	public function test_update_settings_saves_payment_request_button_type() {
		$this->get_gateway()->update_option( 'payment_request_button_type', 'buy' );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'payment_request_button_type', 'book' );

		rest_do_request( $request );

		$this->assertEquals( 'book', $this->get_gateway()->get_option( 'payment_request_button_type' ) );
	}

	public function test_update_settings_does_not_save_account_statement_descriptor_if_not_supplied() {
		$status_before_request = $this->get_gateway()->get_option( 'statement_descriptor' );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );

		rest_do_request( $request );

		$this->assertEquals( $status_before_request, $this->get_gateway()->get_option( 'statement_descriptor' ) );
	}

	public function test_update_settings_enables_saved_cards() {
		$this->get_gateway()->update_option( 'saved_cards', 'no' );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_saved_cards_enabled', true );

		rest_do_request( $request );

		$this->assertEquals( 'yes', $this->get_gateway()->get_option( 'saved_cards' ) );
	}

	public function test_update_settings_disables_saved_cards() {
		$this->get_gateway()->update_option( 'saved_cards', 'yes' );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_saved_cards_enabled', false );

		rest_do_request( $request );

		$this->assertEquals( 'no', $this->get_gateway()->get_option( 'saved_cards' ) );
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
