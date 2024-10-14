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
	public function test_enum_fields( $rest_key, $option_name, $original_valid_value, $new_valid_value, $new_invalid_value, $is_upe_enabled = true ) {
		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_cached_account_data',
				]
			)
			->getMock();
		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn(
			[
				'capabilities' => [
					'bancontact_payments' => 'active',
					'card_payments'       => 'active',
					'eps_payments'        => 'active',
					'alipay_payments'            => 'active',
					'ideal_payments'             => 'active',
					'p24_payments'               => 'active',
					'sepa_debit_payments'        => 'active',
					'boleto_payments'            => 'active',
					'oxxo_payments'              => 'active',
					'link_payments'              => 'active',
				],
			]
		);
		// It returns option value under expected key with HTTP code 200.
		$this->get_gateway()->update_option( $option_name, $original_valid_value );
		$response = $this->rest_get_settings();
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $original_valid_value, $response->get_data()[ $rest_key ] );

		// Test update works for values within enum.
		$this->get_gateway()->update_option( $option_name, $original_valid_value );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_upe_enabled', $is_upe_enabled );
		$request->set_param( $rest_key, $new_valid_value );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $new_valid_value, $this->get_gateway()->get_option( $option_name ) );

		// Do not update if rest key not present in update request.
		$this->get_gateway()->update_option( $option_name, $original_valid_value );

		$status_before_request = $this->get_gateway()->get_option( $option_name );
		$request->set_param( 'is_upe_enabled', $is_upe_enabled );
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $status_before_request, $this->get_gateway()->get_option( $option_name ) );

		// Test update fails and returns HTTP code 400 for values outside of enum.
		$this->get_gateway()->update_option( $option_name, $original_valid_value );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_upe_enabled', $is_upe_enabled );
		$request->set_param( $rest_key, $new_invalid_value );

		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( $original_valid_value, $this->get_gateway()->get_option( $option_name ) );
	}

	public function test_individual_payment_method_settings() {
		// Disable UPE and set up EPS gateway.
		update_option(
			'woocommerce_stripe_settings',
			[
				'enabled'     => 'yes',
				'title'       => 'Credit card',
				'description' => 'Pay with Credit card',
				WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME => 'no',
			]
		);
		$gateways = WC_Stripe_Helper::get_legacy_payment_methods();
		$gateways['stripe_eps']->update_option( 'title', 'EPS' );
		$gateways['stripe_eps']->update_option( 'description', 'Pay with EPS' );

		$response                                = $this->rest_get_settings();
		$individual_payment_method_settings_data = $response->get_data()['individual_payment_method_settings'];

		$this->assertEquals( 200, $response->get_status() );
		$this->arrayHasKey( WC_Stripe_Payment_Methods::EPS, $individual_payment_method_settings_data );
		$this->assertEquals(
			[
				'name'        => 'EPS',
				'description' => 'Pay with EPS',
			],
			$individual_payment_method_settings_data['eps'],
		);

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE . '/payment_method' );
		$request->set_param( 'payment_method_id', WC_Stripe_Payment_Methods::GIROPAY );
		$request->set_param( 'is_enabled', true );
		$request->set_param( 'title', 'Giropay' );
		$request->set_param( 'description', 'Pay with Giropay' );

		$response         = rest_do_request( $request );
		$gateway_settings = get_option( 'woocommerce_stripe_giropay_settings' );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'Giropay', $gateway_settings['title'] );
		$this->assertEquals( 'Pay with Giropay', $gateway_settings['description'] );
	}

	public function test_get_settings_returns_available_payment_method_ids() {
		//link is available only in US
		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
													->disableOriginalConstructor()
													->setMethods(
														[
															'get_cached_account_data',
														]
													)
													->getMock();

		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn(
			[
				'country'      => 'US',
				'capabilities' => [
					'bancontact_payments'        => 'active',
					'card_payments'              => 'active',
					'eps_payments'               => 'active',
					'giropay_payments'           => 'active',
					'ideal_payments'             => 'active',
					'p24_payments'               => 'active',
					'sepa_debit_payments'        => 'active',
					'boleto_payments'            => 'active',
					'oxxo_payments'              => 'active',
					'link_payments'              => 'active',
				],
			]
		);
		$response = $this->rest_get_settings();

		$expected_method_ids  = array_keys( $this->get_gateway()->payment_methods );
		$available_method_ids = $response->get_data()['available_payment_method_ids'];

		$this->assertEquals(
			$expected_method_ids,
			$available_method_ids
		);
	}

	public function test_get_settings_returns_ordered_payment_method_ids() {
		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
													->disableOriginalConstructor()
													->setMethods(
														[
															'get_cached_account_data',
														]
													)
													->getMock();

		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn(
			[
				'country' => 'US',
				'capabilities' => [
					'bancontact_payments'        => 'active',
					'card_payments'              => 'active',
					'eps_payments'               => 'active',
					'giropay_payments'           => 'active',
					'ideal_payments'             => 'active',
					'p24_payments'               => 'active',
					'sepa_debit_payments'        => 'active',
					'boleto_payments'            => 'active',
					'oxxo_payments'              => 'active',
					'link_payments'              => 'active',
				],
			]
		);
		$response = $this->rest_get_settings();

		$expected_methods = $this->get_gateway()->payment_methods;

		unset( $expected_methods['link'] );

		$expected_method_ids = array_keys( $expected_methods );
		$ordered_method_ids  = $response->get_data()['ordered_payment_method_ids'];

		$this->assertEquals(
			$expected_method_ids,
			$ordered_method_ids
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

	public function test_dismiss_customization_notice() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE . '/notice' );
		$request->set_param( 'wc_stripe_show_customization_notice', 'no' );

		$response      = rest_do_request( $request );
		$notice_option = get_option( 'wc_stripe_show_customization_notice' );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'no', $notice_option );
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
				[ WC_Stripe_Payment_Methods::CARD ],
				[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::ALIPAY ],
				[ 'foo' ],
				true,
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
