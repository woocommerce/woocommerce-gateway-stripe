<?php

namespace WooCommerce\Stripe\Tests\Admin;

use Automattic\WooCommerce\Blocks\Package;
use Exception;
use WooCommerce\Stripe\Tests\Helpers\UPE_Test_Helper;
use WC_Stripe_UPE_Payment_Gateway;
use WC_REST_Stripe_Settings_Controller;
use WC_Stripe;
use WC_Stripe_Feature_Flags;
use WC_Stripe_Helper;
use WC_Stripe_Payment_Methods;
use WooCommerce\Stripe\Tests\WC_Mock_Stripe_API_Unit_Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class WC_REST_Stripe_Settings_Controller_Test
 *
 * WC_REST_Stripe_Settings_Controller_Test unit tests.
 */
class WC_REST_Stripe_Settings_Controller_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Tested REST route.
	 */
	const SETTINGS_ROUTE = '/wc/v3/wc_stripe/settings';

	/**
	 * Controller instance
	 *
	 * @var WC_REST_Stripe_Settings_Controller
	 */
	private $controller;

	/**
	 * UPE test helper instance.
	 *
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	/**
	 * Gateway instance that the controller uses.
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway
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
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		$upe_helper = new UPE_Test_Helper();

		// Enable Amazon Pay
		update_option( WC_Stripe_Feature_Flags::AMAZON_PAY_FEATURE_FLAG_NAME, 'yes' );

		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		self::$gateway = WC()->payment_gateways()->payment_gateways()[ WC_Stripe_UPE_Payment_Gateway::ID ];
	}

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::set_up();

		if ( version_compare( WC_VERSION, '3.4.0', '<' ) ) {
			$this->markTestSkipped( 'The controller is not compatible with older WC versions, due to the missing `update_option` method on the gateway.' );
		}

		$this->upe_helper = new UPE_Test_Helper();
		$this->controller = new WC_REST_Stripe_Settings_Controller( $this->get_gateway() );

		add_action( 'rest_api_init', [ $this, 'deregister_wc_blocks_rest_api' ], 5 );

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );
	}

	public function tear_down() {
		parent::tear_down();

		delete_option( WC_Stripe_Feature_Flags::AMAZON_PAY_FEATURE_FLAG_NAME );
	}

	/**
	 * @dataProvider stripe_payment_method_configurations_provider
	 */
	public function test_get_stripe_payment_method_configurations_settings( $enabled_payment_method_ids, $disabled_payment_method_ids ) {
		$this->mock_payment_method_configurations( $enabled_payment_method_ids, $disabled_payment_method_ids );

		$response = $this->controller->get_settings();
		$this->assertEquals( 200, $response->get_status() );
		foreach ( $enabled_payment_method_ids as $payment_method ) {
			$this->assertContains( $payment_method, $response->get_data()['enabled_payment_method_ids'] );
		}
		foreach ( $disabled_payment_method_ids as $payment_method ) {
			$this->assertNotContains( $payment_method, $response->get_data()['enabled_payment_method_ids'] );
		}
	}

	/**
	 * Test that the update_settings method updates the payment method configurations settings.
	 */
	public function test_update_stripe_payment_method_configurations_settings() {
		// Set up initial state with only card enabled
		$this->mock_payment_method_configurations( [ 'card' ], [ 'amazon_pay', 'google_pay', 'apple_pay' ] );

		// Set pmc_enabled to yes to prevent migration
		$stripe_settings                = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['pmc_enabled'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->expect_payment_method_configurations_update( [ 'amazon_pay', 'card' ] );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'enabled_payment_method_ids', [ 'amazon_pay', 'card' ] );
		$request->set_param( 'is_upe_enabled', true );

		$response = $this->controller->update_settings( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Tests for boolean fields.
	 *
	 * @param string $rest_key    REST API key.
	 * @param string $option_name Option name.
	 * @param bool   $inverse     Whether the option is inverse of the REST key.
	 * @dataProvider boolean_field_provider
	 */
	public function test_boolean_fields( string $rest_key, string $option_name, bool $inverse = false ): void {
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
	 * Tests for enum fields.
	 *
	 * @param string       $rest_key             REST API key.
	 * @param string       $option_name          Option name.
	 * @param string|array $original_valid_value Original valid value.
	 * @param string|array $new_valid_value      New valid value.
	 * @param string|array $new_invalid_value    New invalid value.
	 * @param bool         $is_upe_enabled       Whether UPE is enabled.
	 *
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
					'alipay_payments'     => 'active',
					'ideal_payments'      => 'active',
					'p24_payments'        => 'active',
					'sepa_debit_payments' => 'active',
					'boleto_payments'     => 'active',
					'oxxo_payments'       => 'active',
					'link_payments'       => 'active',
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

	public function test_get_settings_returns_available_payment_method_ids() {
		$expected_method_ids = [
			WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::ACH,
			WC_Stripe_Payment_Methods::ALIPAY,
			WC_Stripe_Payment_Methods::AMAZON_PAY,
			WC_Stripe_Payment_Methods::KLARNA,
			WC_Stripe_Payment_Methods::AFFIRM,
			WC_Stripe_Payment_Methods::AFTERPAY_CLEARPAY,
			WC_Stripe_Payment_Methods::EPS,
			WC_Stripe_Payment_Methods::BANCONTACT,
			WC_Stripe_Payment_Methods::BOLETO,
			WC_Stripe_Payment_Methods::IDEAL,
			WC_Stripe_Payment_Methods::OXXO,
			WC_Stripe_Payment_Methods::SEPA_DEBIT,
			WC_Stripe_Payment_Methods::P24,
			WC_Stripe_Payment_Methods::MULTIBANCO,
			// 'link', // Link is excluded as it is a express method.
			WC_Stripe_Payment_Methods::WECHAT_PAY,
			WC_Stripe_Payment_Methods::CASHAPP_PAY,
			WC_Stripe_Payment_Methods::ACSS_DEBIT,
		];
		$this->mock_payment_method_configurations( $expected_method_ids, [] );

		$response             = $this->rest_get_settings();
		$available_method_ids = $response->get_data()['available_payment_method_ids'];

		$this->assertEquals(
			$expected_method_ids,
			$available_method_ids
		);
		$this->assertNotContains( WC_Stripe_Payment_Methods::BACS_DEBIT, $available_method_ids );
	}

	public function test_get_settings_returns_ordered_payment_method_ids() {
		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
													->disableOriginalConstructor()
													->setMethods(
														[
															'get_cached_account_data',
															'get_account_country',
														]
													)
													->getMock();

		WC_Stripe::get_instance()->account->method( 'get_cached_account_data' )->willReturn(
			[
				'country'      => 'US',
				'capabilities' => [],
			]
		);

		WC_Stripe::get_instance()->account->method( 'get_account_country' )->willReturn( 'US' );

		// Link and Amazon Pay are excluded as they are express methods only.
		$expected_method_ids = [
			WC_Stripe_Payment_Methods::CARD,
			WC_Stripe_Payment_Methods::ACH,
			WC_Stripe_Payment_Methods::ALIPAY,
			WC_Stripe_Payment_Methods::KLARNA,
			WC_Stripe_Payment_Methods::AFFIRM,
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
			WC_Stripe_Payment_Methods::CASHAPP_PAY,
			WC_Stripe_Payment_Methods::ACSS_DEBIT,
		];
		$this->mock_payment_method_configurations( $expected_method_ids, [] );

		$response           = $this->rest_get_settings();
		$ordered_method_ids = $response->get_data()['ordered_payment_method_ids'];

		$this->assertEquals(
			$expected_method_ids,
			$ordered_method_ids
		);
		$this->assertNotContains( WC_Stripe_Payment_Methods::BACS_DEBIT, $ordered_method_ids );
	}

	public function test_get_settings_fails_if_user_cannot_manage_woocommerce() {
		$cb = $this->create_can_manage_woocommerce_cap_override( false );
		add_filter( 'user_has_cap', $cb );
		$request  = new WP_REST_Request( 'GET', self::SETTINGS_ROUTE );
		$response = rest_do_request( $request );
		$this->assertEquals( 403, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );
		$request  = new WP_REST_Request( 'GET', self::SETTINGS_ROUTE );
		$response = rest_do_request( $request );
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

	/**
	 * Tests that Apple Pay and Google Pay can be enabled in the PMC
	 * when payment request is enabled, and card is enabled.
	 */
	public function test_update_settings_enables_apple_pay_google_pay() {
		// Before the update: card and CashApp are enabled, Apple Pay and Google Pay are disabled
		$this->mock_payment_method_configurations(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::CASHAPP_PAY ],
			[ WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ]
		);

		// After the update: card, Apple Pay, and Google Pay are enabled, CashApp is disabled
		$this->expect_payment_method_configurations_update(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ],
			[ WC_Stripe_Payment_Methods::CASHAPP_PAY ]
		);
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		// Disable CashApp, keep card enabled.
		$request->set_param( 'enabled_payment_method_ids', [ WC_Stripe_Payment_Methods::CARD ] );
		$request->set_param( 'is_upe_enabled', true );
		// Enable Apple Pay and Google Pay.
		$request->set_param( 'is_payment_request_enabled', true );

		$response = $this->controller->update_settings( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Tests that Apple Pay and Google Pay can only be enabled in the PMC
	 * when payment request is enabled, and card is enabled.
	 */
	public function test_update_settings_enforces_apple_pay_google_pay_requires_card() {
		// Before the update: card, Apple Pay, and Google Pay are enabled, CashApp is disabled
		$this->mock_payment_method_configurations(
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ],
			[ WC_Stripe_Payment_Methods::CASHAPP_PAY ]
		);

		// After the update: CashApp is enabled, card, Apple Pay, and Google Pay are disabled
		$this->expect_payment_method_configurations_update(
			[ WC_Stripe_Payment_Methods::CASHAPP_PAY ],
			[ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ]
		);

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		// Disable card, enable CashApp.
		$request->set_param( 'enabled_payment_method_ids', [ WC_Stripe_Payment_Methods::CASHAPP_PAY ] );
		$request->set_param( 'is_upe_enabled', true );
		// Enable Apple Pay and Google Pay -- this will be ignored because card is disabled
		$request->set_param( 'is_payment_request_enabled', true );

		$response = $this->controller->update_settings( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Tests for the dismiss notice endpoint.
	 *
	 * @param array $request_params The request parameters.
	 * @param array $expected_option The expected option after the request.
	 * @param array $expected_response The expected response.
	 * @return void
	 *
	 * @dataProvider provide_test_dismiss_notice
	 */
	public function test_dismiss_notice( $request_params, $expected_option, $expected_response ) {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE . '/notice' );
		foreach ( $request_params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );
		if ( count( $expected_option ) > 0 ) {
			foreach ( $expected_option as $option_name => $option_value ) {
				$notice_option = get_option( $option_name );
				$this->assertEquals( $option_value, $notice_option );
			}
		}

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $expected_response, $response->get_data() );
	}

	/**
	 * Provider for test_dismiss_notice.
	 *
	 * @return array
	 */
	public function provide_test_dismiss_notice() {
		return [
			'empty request'                => [
				'request params'    => [],
				'expected option'   => [],
				'expected response' => [],
			],
			'dismiss customization notice' => [
				'request params'    => [
					'wc_stripe_show_customization_notice' => 'no',
				],
				'expected option'   => [
					'wc_stripe_show_customization_notice' => 'no',
				],
				'expected response' => [
					'result' => 'notice dismissed',
				],
			],
			'dismiss BNPL banner'          => [
				'request params'    => [
					'wc_stripe_show_bnpl_promotion_banner' => 'no',
				],
				'expected option'   => [
					'wc_stripe_show_bnpl_promotion_banner' => 'no',
				],
				'expected response' => [
					'result' => 'notice dismissed',
				],
			],
		];
	}

	/**
	 * @dataProvider is_payment_request_enabled_provider
	 */
	public function test_is_payment_request_enabled( $is_enabled, $enabled_payment_method_ids, $disabled_payment_method_ids ) {
		$this->mock_payment_method_configurations(
			$enabled_payment_method_ids,
			$disabled_payment_method_ids
		);
		$request  = new WP_REST_Request( 'GET', self::SETTINGS_ROUTE );
		$response = $this->controller->get_settings( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $is_enabled, $response->get_data()['is_payment_request_enabled'] );
	}

	public function is_payment_request_enabled_provider() {
		return [
			[ true, [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::GOOGLE_PAY ], [] ],
			[ false, [], [ WC_Stripe_Payment_Methods::GOOGLE_PAY, WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::LINK, WC_Stripe_Payment_Methods::AMAZON_PAY ] ],
		];
	}

	/**
	 * Data provider for `test_boolean_fields`.
	 *
	 * @return array
	 */
	public function boolean_field_provider(): array {
		return [
			'is_stripe_enabled'                     => [ 'is_stripe_enabled', 'enabled' ],
			'is_test_mode_enabled'                  => [ 'is_test_mode_enabled', 'testmode' ],
			'is_oc_enabled'                         => [ 'is_oc_enabled', 'optimized_checkout_element' ],
			'is_ap_enabled'                         => [ 'is_ap_enabled', 'adaptive_pricing' ],
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

	public function stripe_payment_method_configurations_provider() {
		return [
			'amazon_pay' => [ [], [ 'amazon_pay' ] ],
			'card'       => [ [], [ 'card', 'link' ] ],
		];
	}

	/**
	 * Data provider for `test_enum_fields`.
	 *
	 * @return array
	 */
	public function enum_field_provider() {
		return [
			'payment_request_button_theme'     => [
				'payment_request_button_theme',
				'express_checkout_button_theme',
				'dark',
				'light',
				'foo',
			],
			'payment_request_button_size'      => [
				'payment_request_button_size',
				'express_checkout_button_size',
				'default',
				'large',
				'foo',
			],
			'payment_request_button_type'      => [
				'payment_request_button_type',
				'express_checkout_button_type',
				'buy',
				'book',
				'foo',
			],
			'payment_request_button_locations' => [
				'payment_request_button_locations',
				'express_checkout_button_locations',
				[ 'cart' ],
				[ 'cart', 'checkout', 'product' ],
				[ 'foo' ],
			],
			'optimized_checkout_layout' => [
				'oc_layout',
				'optimized_checkout_layout',
				'accordion',
				'tabs',
				'foo',
				true, // is_upe_enabled
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

		return $this->controller->get_settings( $request );
	}

	/**
	 * @return WC_Stripe_UPE_Payment_Gateway
	 */
	private function get_gateway() {
		return self::$gateway;
	}
}
