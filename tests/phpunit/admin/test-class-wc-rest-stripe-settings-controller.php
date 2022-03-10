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

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( $rest_key, true );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $inverse ? 'no' : 'yes', $this->get_gateway()->get_option( $option_name ) );

		// Do not update if rest key not present in update request.
		$status_before_request = $this->get_gateway()->get_option( $option_name );

		$request  = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $status_before_request, $this->get_gateway()->get_option( $option_name ) );

		// Return HTTP code 400 if REST value is not boolean.
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( $rest_key, 'foo' );
		$response = rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * @dataProvider enum_field_provider
	 */
	public function test_enum_fields( $rest_key, $option_name, $original_valid_value, $new_valid_value, $new_invalid_value ) {
		// It returns option value under expected key with HTTP code 200.
		$this->get_gateway()->update_option( $option_name, $original_valid_value );
		$response = $this->rest_get_settings();
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $original_valid_value, $response->get_data()[ $rest_key ] );

		// Test update works for values within enum.
		$this->get_gateway()->update_option( $option_name, $original_valid_value );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( $rest_key, $new_valid_value );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $new_valid_value, $this->get_gateway()->get_option( $option_name ) );

		// Do not update if rest key not present in update request.
		$this->get_gateway()->update_option( $option_name, $original_valid_value );

		$status_before_request = $this->get_gateway()->get_option( $option_name );
		$request               = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $status_before_request, $this->get_gateway()->get_option( $option_name ) );

		// Test update fails and returns HTTP code 400 for values outside of enum.
		$this->get_gateway()->update_option( $option_name, $original_valid_value );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( $rest_key, $new_invalid_value );

		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( $original_valid_value, $this->get_gateway()->get_option( $option_name ) );
	}

	/**
	 * @dataProvider statement_descriptor_field_provider
	 */
	public function test_statement_descriptor_fields( $option_name, $max_allowed_length ) {
		// It returns option value under expected key with HTTP code 200.
		$this->get_gateway()->update_option( $option_name, 'foobar' );
		$response = $this->rest_get_settings();
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'foobar', $response->get_data()[ $option_name ] );

		// Test update works for values passing validation.
		$this->get_gateway()->update_option( $option_name, 'foobar' );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		if ( 'short_statement_descriptor' === $option_name ) {
			$request->set_param( 'is_short_statement_descriptor_enabled', true );
		}
		$request->set_param( $option_name, 'quuxcorge' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'quuxcorge', $this->get_gateway()->get_option( $option_name ) );

		// Do not update if rest key not present in update request.
		$this->get_gateway()->update_option( $option_name, 'foobar' );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'foobar', $this->get_gateway()->get_option( $option_name ) );

		// Test update fails and returns HTTP code 400 for non-string values.
		$this->assert_statement_descriptor_update_request_fails_for_value(
			$option_name,
			[]
		);

		// Test update fails and returns HTTP code 400 for values that are too short.
		$this->assert_statement_descriptor_update_request_fails_for_value(
			$option_name,
			'1234'
		);

		// Test update fails and returns HTTP code 400 for values that are too long.
		$this->assert_statement_descriptor_update_request_fails_for_value(
			$option_name,
			str_pad( '', $max_allowed_length + 1, 'a' )
		);

		// Test update fails and returns HTTP code 400 for values that contain no letters.
		$this->assert_statement_descriptor_update_request_fails_for_value(
			$option_name,
			'123456'
		);

		// Test update fails and returns HTTP code 400 for values that contain special characters.
		$this->assert_statement_descriptor_update_request_fails_for_value(
			$option_name,
			'foobar\''
		);
	}

	public function test_short_statement_descriptor_is_not_updated() {
		// It returns option value under expected key with HTTP code 200.
		$this->get_gateway()->update_option( 'short_statement_descriptor', 'foobar' );
		$response = $this->rest_get_settings();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'foobar', $response->get_data()['short_statement_descriptor'] );

		// test update does not fail since is_short_statement_descriptor_enabled is disabled
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_short_statement_descriptor_enabled', false );
		$request->set_param( 'short_statement_descriptor', '123' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'foobar', $this->get_gateway()->get_option( 'short_statement_descriptor' ) );
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
		$response = rest_do_request( new WP_REST_Request( 'POST', self::SETTINGS_ROUTE ) );
		$this->assertEquals( 403, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );
		$response = rest_do_request( new WP_REST_Request( 'POST', self::SETTINGS_ROUTE ) );
		$this->assertEquals( 200, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );
	}

	public function boolean_field_provider() {
		return [
			'is_stripe_enabled'                     => [ 'is_stripe_enabled', 'enabled' ],
			'is_test_mode_enabled'                  => [ 'is_test_mode_enabled', 'testmode' ],
			'is_payment_request_enabled'            => [ 'is_payment_request_enabled', 'payment_request' ],
			'is_manual_capture_enabled'             => [ 'is_manual_capture_enabled', 'capture', true ],
			'is_saved_cards_enabled'                => [ 'is_saved_cards_enabled', 'saved_cards' ],
			'is_separate_card_form_enabled'         => [ 'is_separate_card_form_enabled', 'inline_cc_form', true ],
			'is_short_statement_descriptor_enabled' => [
				'is_short_statement_descriptor_enabled',
				'is_short_statement_descriptor_enabled',
			],
			'is_debug_log_enabled'                  => [ 'is_debug_log_enabled', 'logging' ],
		];
	}

	public function enum_field_provider() {
		return [
			'enabled_payment_method_ids'       => [
				'enabled_payment_method_ids',
				'upe_checkout_experience_accepted_payments',
				[ 'card' ],
				[ 'card', 'giropay' ],
				[ 'foo' ],
			],
			'payment_request_button_theme'     => [
				'payment_request_button_theme',
				'payment_request_button_theme',
				'dark',
				'light',
				'foo',
			],
			'payment_request_button_size'      => [
				'payment_request_button_size',
				'payment_request_button_size',
				'default',
				'large',
				'foo',
			],
			'payment_request_button_type'      => [
				'payment_request_button_type',
				'payment_request_button_type',
				'buy',
				'book',
				'foo',
			],
			'payment_request_button_locations' => [
				'payment_request_button_locations',
				'payment_request_button_locations',
				[ 'cart' ],
				[ 'cart', 'checkout', 'product' ],
				[ 'foo' ],
			],
		];
	}

	public function statement_descriptor_field_provider() {
		return [
			'statement_descriptor'       => [ 'statement_descriptor', 22 ],
			'short_statement_descriptor' => [ 'short_statement_descriptor', 10 ],
		];
	}

	private function assert_statement_descriptor_update_request_fails_for_value( $option_name, $new_invalid_value ) {
		$this->get_gateway()->update_option( $option_name, 'foobar' );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		if ( 'short_statement_descriptor' === $option_name ) {
			$request->set_param( 'is_short_statement_descriptor_enabled', true );
		}
		$request->set_param( $option_name, $new_invalid_value );

		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'foobar', $this->get_gateway()->get_option( $option_name ) );
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
