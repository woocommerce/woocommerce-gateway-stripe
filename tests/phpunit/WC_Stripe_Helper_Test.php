<?php

namespace WooCommerce\Stripe\Tests;

use Automattic\WooCommerce\Enums\OrderStatus;
use stdClass;
use WC_Order;
use WC_Stripe_Currency_Code;
use WC_Stripe_Helper;
use WC_Stripe_Order_Helper;
use WC_Stripe_Payment_Methods;
use WooCommerce\Stripe\Tests\Helpers\UPE_Test_Helper;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Order;

/**
 * These tests make assertions against class WC_Stripe_Helper.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Helper
 *
 * WC_Stripe_Helper_Test class.
 */
class WC_Stripe_Helper_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	public function set_up() {
		parent::set_up();
		$this->upe_helper = new UPE_Test_Helper();
		$this->set_stripe_account_data( [ 'country' => 'US' ] );
	}

	public function test_convert_to_stripe_locale() {
		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'en_GB' );
		$this->assertEquals( 'en-GB', $result );

		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'fr_FR' );
		$this->assertEquals( 'fr', $result );

		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'fr_CA' );
		$this->assertEquals( 'fr-CA', $result );

		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'es_UY' );
		$this->assertEquals( 'es', $result );

		$result = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( 'es_EC' );
		$this->assertEquals( 'es-419', $result );
	}

	public function test_should_enqueue_in_current_tab_section() {
		global $current_tab, $current_section;
		$current_tab     = 'checkout';
		$current_section = 'stripe';

		$result = WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe' );
		$this->assertTrue( $result );

		$result = WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'onboarding', 'stripe' );
		$this->assertFalse( $result );

		unset( $current_tab );
		unset( $current_section );
	}

	public function test_add_payment_method_to_request_array_should_add_source_to_request() {
		$source_id = 'src_mock';
		$request   = WC_Stripe_Helper::add_payment_method_to_request_array( $source_id, [] );

		$this->assertArrayHasKey( 'source', $request, 'Source ID was not added to request array' );
		$this->assertEquals( $source_id, $request['source'] );
	}

	public function test_add_payment_method_to_request_array_should_add_payment_method_to_request() {
		$payment_method_id = 'pm_mock';
		$request           = WC_Stripe_Helper::add_payment_method_to_request_array( $payment_method_id, [] );

		$this->assertArrayHasKey( 'payment_method', $request, 'Payment Method ID was not added to request array' );
		$this->assertEquals( $payment_method_id, $request['payment_method'] );
	}

	public function test_add_payment_method_to_request_array_should_add_card_id_to_request() {
		$payment_method_id = 'card_mock';
		$request           = WC_Stripe_Helper::add_payment_method_to_request_array( $payment_method_id, [] );

		$this->assertArrayHasKey( 'payment_method', $request, 'Card ID was not added to request array' );
		$this->assertEquals( $payment_method_id, $request['payment_method'] );
	}

	public function test_add_payment_method_to_request_array_should_not_add_non_source_or_payment_method_to_request() {
		$not_a_payment_method_id = 'cus_mock';
		$request                 = WC_Stripe_Helper::add_payment_method_to_request_array( $not_a_payment_method_id, [] );

		$this->assertArrayNotHasKey( 'payment_method', $request, 'Payment Method ID was added to request array when it should not have' );
		$this->assertArrayNotHasKey( 'source', $request, 'Source was added to request array when it should not have' );
		$this->assertEmpty( $request, 'Request array is not empty when it should be empty' );
	}

	public function test_is_payment_method_object() {
		$payment_method         = new stdClass();
		$payment_method->object = 'payment_method';
		$this->assertTrue( WC_Stripe_Helper::is_payment_method_object( $payment_method ) );

		$empty = new stdClass();
		$this->assertFalse( WC_Stripe_Helper::is_payment_method_object( $empty ) );

		$not_payment_method         = new stdClass();
		$not_payment_method->object = 'not_payment_method';
		$this->assertFalse( WC_Stripe_Helper::is_payment_method_object( $not_payment_method ) );
	}

	public function test_is_reusable_source() {
		$payment_method         = new stdClass();
		$payment_method->object = 'payment_method';
		$this->assertTrue( WC_Stripe_Helper::is_reusable_payment_method( $payment_method ) );

		$reusable_source        = new stdClass();
		$reusable_source->usage = 'reusable';
		$this->assertTrue( WC_Stripe_Helper::is_reusable_payment_method( $reusable_source ) );

		$empty = new stdClass();
		$this->assertFalse( WC_Stripe_Helper::is_reusable_payment_method( $empty ) );

		$non_reusable_source        = new stdClass();
		$non_reusable_source->usage = 'single_use';
		$this->assertFalse( WC_Stripe_Helper::is_reusable_payment_method( $non_reusable_source ) );
	}

	public function test_is_card_payment_method() {
		$card_payment_method         = new stdClass();
		$card_payment_method->object = 'payment_method';
		$card_payment_method->type   = WC_Stripe_Payment_Methods::CARD;
		$this->assertTrue( WC_Stripe_Helper::is_card_payment_method( $card_payment_method ) );

		$card_source         = new stdClass();
		$card_source->object = 'source';
		$card_source->type   = WC_Stripe_Payment_Methods::CARD;
		$this->assertTrue( WC_Stripe_Helper::is_card_payment_method( $card_source ) );

		$non_card_payment_method         = new stdClass();
		$non_card_payment_method->object = 'payment_method';
		$non_card_payment_method->type   = 'not_card';
		$this->assertFalse( WC_Stripe_Helper::is_card_payment_method( $non_card_payment_method ) );

		$non_card_source         = new stdClass();
		$non_card_source->object = 'source';
		$non_card_source->type   = 'not_card';
		$this->assertFalse( WC_Stripe_Helper::is_card_payment_method( $non_card_source ) );

		$not_payment_method_or_source         = new stdClass();
		$not_payment_method_or_source->object = 'not_payment_method_or_source';
		$this->assertFalse( WC_Stripe_Helper::is_card_payment_method( $not_payment_method_or_source ) );
	}

	public function test_get_payment_method_from_intent() {
		$source         = 'src_mock';
		$payment_method = 'pm_mock';

		$intent_with_source         = new stdClass();
		$intent_with_source->source = $source;
		$this->assertEquals( $source, WC_Stripe_Helper::get_payment_method_from_intent( $intent_with_source ) );

		$intent_with_payment_method                 = new stdClass();
		$intent_with_payment_method->payment_method = $payment_method;
		$this->assertEquals( $payment_method, WC_Stripe_Helper::get_payment_method_from_intent( $intent_with_payment_method ) );

		$intent_with_neither_source_nor_payment_method = new stdClass();
		$this->assertNull( WC_Stripe_Helper::get_payment_method_from_intent( $intent_with_neither_source_nor_payment_method ) );
	}

	public function test_get_legacy_payment_methods() {
		$result = WC_Stripe_Helper::get_legacy_payment_methods();
		$this->assertEquals( [ 'stripe_alipay', 'stripe_bancontact', 'stripe_boleto', 'stripe_eps', 'stripe_giropay', 'stripe_ideal', 'stripe_multibanco', 'stripe_oxxo', 'stripe_p24', 'stripe_sepa' ], array_keys( $result ) );
	}

	public function test_get_legacy_available_payment_method_ids() {
		$result = WC_Stripe_Helper::get_legacy_available_payment_method_ids();
		$this->assertEquals( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::ALIPAY, WC_Stripe_Payment_Methods::BANCONTACT, WC_Stripe_Payment_Methods::BOLETO, WC_Stripe_Payment_Methods::EPS, WC_Stripe_Payment_Methods::GIROPAY, WC_Stripe_Payment_Methods::IDEAL, WC_Stripe_Payment_Methods::MULTIBANCO, WC_Stripe_Payment_Methods::OXXO, WC_Stripe_Payment_Methods::P24, WC_Stripe_Payment_Methods::SEPA ], $result );
	}

	public function test_get_legacy_enabled_payment_methods() {
		// Enable EPS, Giropay and P24 LPM gateways.
		$gateways = WC_Stripe_Helper::get_legacy_payment_methods();
		$gateways['stripe_eps']->enable();
		$gateways['stripe_giropay']->enable();
		$gateways['stripe_p24']->enable();

		$result = WC_Stripe_Helper::get_legacy_enabled_payment_methods();
		$this->assertEquals( [ 'stripe_eps', 'stripe_giropay', 'stripe_p24' ], array_keys( $result ) );
	}

	public function test_get_legacy_enabled_payment_method_ids() {
		// Enable EPS, Giropay and P24 LPM gateways.
		$gateways = WC_Stripe_Helper::get_legacy_payment_methods();
		$gateways['stripe_eps']->enable();
		$gateways['stripe_giropay']->enable();
		$gateways['stripe_p24']->enable();

		$result = WC_Stripe_Helper::get_legacy_enabled_payment_method_ids();
		// In legacy mode (when UPE is disabled), Stripe refers to Card as payment method.
		$this->assertEquals( [ WC_Stripe_Payment_Methods::EPS, WC_Stripe_Payment_Methods::GIROPAY, WC_Stripe_Payment_Methods::P24 ], $result );
	}

	/**
	 * Test for `get_order_by_intent_id`
	 *
	 * @param string $status              The order status to return.
	 * @param bool   $success             Whether the order should be found.
	 * @return void
	 * @dataProvider provide_test_get_order_by_intent_id
	 */
	public function test_get_order_by_intent_id( $status, $success ) {
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		$order = wc_get_order( $order_id );
		$order->set_status( $status );

		$intent_id = 'pi_mock';
		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, $intent_id );
		$order->save_meta_data();

		$order = WC_Stripe_Helper::get_order_by_intent_id( $intent_id );
		if ( $success ) {
			$this->assertInstanceOf( WC_Order::class, $order );
		} else {
			$this->assertFalse( $order );
		}
	}

	/**
	 * Data provider for `test_get_order_by_intent_id`
	 *
	 * @return array
	 */
	public function provide_test_get_order_by_intent_id(): array {
		return [
			'regular table' => [
				'custom orders table' => false,
				'status'              => OrderStatus::COMPLETED,
				'success'             => true,
			],
			'trashed order' => [
				'custom orders table' => false,
				'status'              => OrderStatus::TRASH,
				'success'             => false,
			],
		];
	}

	/**
	 * Test for `get_stripe_amount`
	 *
	 * @param int    $total    The total amount.
	 * @param string $currency The currency.
	 * @param int    $expected The expected amount.
	 * @dataProvider provide_test_get_stripe_amount
	 */
	public function test_get_stripe_amount( int $total, string $currency, int $expected, int $price_decimals_setting = 2 ): void {
		if ( 2 !== $price_decimals_setting ) {
			update_option( 'woocommerce_price_num_decimals', $price_decimals_setting );
		}

		$amount = WC_Stripe_Helper::get_stripe_amount( $total, $currency );
		$this->assertEquals( $expected, $amount );
	}

	/**
	 * Data provider for `test_get_stripe_amount`
	 *
	 * @return array
	 */
	public function provide_test_get_stripe_amount(): array {
		return [
			WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR,
				'expected' => 10000,
			],
			WC_Stripe_Currency_Code::JAPANESE_YEN         => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::JAPANESE_YEN,
				'expected' => 100,
			],
			WC_Stripe_Currency_Code::EURO                 => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::EURO,
				'expected' => 10000,
			],
			WC_Stripe_Currency_Code::BAHRAINI_DINAR       => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::BAHRAINI_DINAR,
				'expected' => 100000,
			],
			WC_Stripe_Currency_Code::BAHRAINI_DINAR . ' (3 decimals)' => [
				'total'                  => 100,
				'currency'               => WC_Stripe_Currency_Code::BAHRAINI_DINAR,
				'expected'               => 100000,
				'price_decimals_setting' => 3,
			],
			WC_Stripe_Currency_Code::JORDANIAN_DINAR      => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::JORDANIAN_DINAR,
				'expected' => 100000,
			],
			WC_Stripe_Currency_Code::BURUNDIAN_FRANC      => [
				'total'    => 100,
				'currency' => WC_Stripe_Currency_Code::BURUNDIAN_FRANC,
				'expected' => 100,
			],
		];
	}

	/**
	 * Test for `payment_method_allows_manual_capture`
	 *
	 * @param string $payment_method The payment method.
	 * @param bool   $expected       Whether manual capture is allowed.
	 * @dataProvider provide_payment_method_allows_manual_capture
	 * @return void
	 */
	public function test_payment_method_allows_manual_capture( $payment_method, $expected ): void {
		$actual = WC_Stripe_Helper::payment_method_allows_manual_capture( $payment_method );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Provider for `test_payment_method_allows_manual_capture`
	 *
	 * @return array
	 */
	public function provide_payment_method_allows_manual_capture(): array {
		return [
			'Card'              => [
				'payment_method' => 'stripe',
				'expected'       => true,
			],
			'Affirm'            => [
				'payment_method' => 'stripe_affirm',
				'expected'       => true,
			],
			'Klarna'            => [
				'payment_method' => 'stripe_klarna',
				'expected'       => true,
			],
			'Afterpay/Clearpay' => [
				'payment_method' => 'stripe_afterpay_clearpay',
				'expected'       => true,
			],
			'EPS'               => [
				'payment_method' => 'stripe_eps',
				'expected'       => false,
			],
			'AmazonPay'         => [
				'payment_method' => 'stripe_amazon_pay',
				'expected'       => true,
			],
		];
	}

	public function provide_is_wallet_payment_method(): array {
		return [
			'Apple Pay'  => [
				'apple_pay',
				false,
			],
			'Google Pay' => [
				'google_pay',
				false,
			],
			'Alipay'     => [
				WC_Stripe_Payment_Methods::ALIPAY,
				false,
			],
			'Klarna'     => [
				WC_Stripe_Payment_Methods::KLARNA,
				false,
			],
			'EPS'        => [
				WC_Stripe_Payment_Methods::EPS,
				false,
			],
			'WeChat'     => [
				WC_Stripe_Payment_Methods::WECHAT_PAY,
				true,
			],
			'Cash App'   => [
				WC_Stripe_Payment_Methods::CASHAPP_PAY,
				true,
			],
		];
	}

	/**
	 * Test for `update_main_stripe_settings`, `get_stripe_settings` and `delete_main_stripe_settings`.
	 *
	 * @return void
	 */
	public function test_handle_main_stripe_settings() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'test' => 'abc' ] );
		$current_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $current_settings['test'], 'abc' );

		WC_Stripe_Helper::delete_main_stripe_settings();
		$current_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( [], $current_settings );
	}

	/**
	 * Test for `get_klarna_preferred_locale`.
	 * @return void
	 */
	public function test_get_klarna_preferred_locale() {
		// Language is supported for the region (same region)
		$store_locale    = 'en_US';
		$billing_country = 'US';
		$expected        = 'en-US';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertSame( $expected, $actual );

		// Language is supported for the region (different region)
		$store_locale    = 'en_US';
		$billing_country = 'DE';
		$expected        = 'en-DE';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertSame( $expected, $actual );

		// Language is supported for the region (different region)
		$store_locale    = 'es_ES';
		$billing_country = 'US';
		$expected        = 'es-US';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertSame( $expected, $actual );

		// Language is not supported for the region
		$store_locale    = 'fr_FR';
		$billing_country = 'US';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertNull( $actual );

		// Region is not supported, with supported locale
		$store_locale    = 'pt_PT';
		$billing_country = 'BR';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertNull( $actual );

		// Region is not supported, with non-supported locale
		$store_locale    = 'tl';
		$billing_country = 'PH';
		$actual          = WC_Stripe_Helper::get_klarna_preferred_locale( $store_locale, $billing_country );
		$this->assertNull( $actual );
	}

	/**
	 * Test for `add_stripe_methods_in_woocommerce_gateway_order`.
	 * @return void
	 */
	public function test_add_stripe_methods_in_woocommerce_gateway_order() {
		// When the option is empty, i.e. fresh install, gateway ordering should still work.
		$stripe_payment_methods = [
			'stripe_klarna',
			'card',
			'stripe_alipay',
		];
		delete_option( 'woocommerce_gateway_order' );
		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order( $stripe_payment_methods );
		$gateway_order = get_option( 'woocommerce_gateway_order', [] );
		$this->assertArrayHasKey( 'stripe_klarna', $gateway_order );
		$this->assertArrayHasKey( 'stripe', $gateway_order );
		$this->assertArrayHasKey( 'stripe_alipay', $gateway_order );
		$this->assertTrue( $gateway_order['stripe_klarna'] < $gateway_order['stripe'] );
		$this->assertTrue( $gateway_order['stripe'] < $gateway_order['stripe_alipay'] );

		// Further updates to gateway ordering should work.
		$stripe_payment_methods = [
			'stripe_klarna',
			'stripe_alipay',
			'card',
		];
		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order( $stripe_payment_methods );
		$gateway_order = get_option( 'woocommerce_gateway_order', [] );
		$this->assertArrayHasKey( 'stripe_klarna', $gateway_order );
		$this->assertArrayHasKey( 'stripe', $gateway_order );
		$this->assertArrayHasKey( 'stripe_alipay', $gateway_order );
		$this->assertTrue( $gateway_order['stripe_klarna'] < $gateway_order['stripe_alipay'] );
		$this->assertTrue( $gateway_order['stripe_alipay'] < $gateway_order['stripe'] );

		// Order with respect to other gateways is retained.
		update_option(
			'woocommerce_gateway_order',
			[
				'cod'           => 1,
				'stripe_klarna' => 2,
				'stripe'        => 3,
				'stripe_alipay' => 4,
				'cheque'        => 5,
			]
		);
		$stripe_payment_methods = [
			'stripe_alipay',
			'stripe_klarna',
			'card',
			'stripe_affirm',
		];
		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order( $stripe_payment_methods );
		$gateway_order = get_option( 'woocommerce_gateway_order', [] );
		$this->assertTrue( $gateway_order['cod'] < $gateway_order['stripe_alipay'] );
		$this->assertTrue( $gateway_order['stripe_alipay'] < $gateway_order['stripe_klarna'] );
		$this->assertTrue( $gateway_order['stripe_klarna'] < $gateway_order['stripe'] );
		$this->assertTrue( $gateway_order['stripe'] < $gateway_order['stripe_affirm'] );
		$this->assertTrue( $gateway_order['stripe_affirm'] < $gateway_order['cheque'] );
	}

	/**
	 * Test for `add_mandate_data`.
	 *
	 * @param string $server_variable_key   The key of the server variable to set.
	 * @param string $server_variable_value The value to set the server variable to.
	 * @param string $expected_ip_address    The expected IP address.
	 * @dataProvider provider_test_add_mandate_data
	 * @return void
	 */
	public function test_add_mandate_data( $server_variable_key, $server_variable_value, $expected_ip_address ) {
		unset( $_SERVER['REMOTE_ADDR'] );
		unset( $_SERVER['HTTP_X_REAL_IP'] );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$_SERVER[ $server_variable_key ] = $server_variable_value;
		$request                         = WC_Stripe_Helper::add_mandate_data( [] );
		$this->assertTrue( isset( $request['mandate_data']['customer_acceptance']['online']['ip_address'] ) );
		$ip_address = $request['mandate_data']['customer_acceptance']['online']['ip_address'];
		$this->assertSame( $expected_ip_address, $ip_address );
	}

	/**
	 * Data provider for `test_add_mandate_data`.
	 *
	 * @return array
	 */
	public function provider_test_add_mandate_data() {
		return [
			[ 'REMOTE_ADDR', '192.168.1.1', '192.168.1.1' ],
			[ 'REMOTE_ADDR', '192.168.1.1, 192.168.1.2, 192.168.1.3', '192.168.1.1' ],
			[ 'HTTP_X_REAL_IP', '192.168.1.1', '192.168.1.1' ],
			[ 'HTTP_X_REAL_IP', '192.168.1.1, 192.168.1.2, 192.168.1.3', '192.168.1.1' ],
			[ 'HTTP_X_FORWARDED_FOR', '192.168.1.1, 192.168.1.2, 192.168.1.3', '192.168.1.1' ],
			[ 'HTTP_X_FORWARDED_FOR', '192.168.1.1', '192.168.1.1' ],
			[ 'HTTP_X_REAL_IP', 'invalid-ip-address', 'invalid-ip-address' ],
			[ 'HTTP_X_REAL_IP', '', '' ],
		];
	}

	/**
	 * Tests for `get_refund_reason_description`.
	 *
	 * @param string $refund_reason_key The refund reason key to test.
	 * @param string $expected          The expected description.
	 * @return void
	 *
	 * @dataProvider provide_test_get_refund_reason_description
	 */
	public function test_get_refund_reason_description( $refund_reason_key, $expected ) {
		$this->assertSame( $expected, WC_Stripe_Helper::get_refund_reason_description( $refund_reason_key ) );
	}

	/**
	 * Data provider for `test_get_refund_reason_description`.
	 *
	 * @return array
	 */
	public function provide_test_get_refund_reason_description() {
		return [
			'The charge has been disputed'                 => [
				'key'      => 'charge_for_pending_refund_disputed',
				'expected' => 'The charge has been disputed',
			],
			'The refund was declined'                      => [
				'key'      => 'declined',
				'expected' => 'The refund was declined',
			],
			'The original payment method has expired or was canceled' => [
				'key'      => 'expired_or_canceled_card',
				'expected' => 'The original payment method has expired or was canceled',
			],
			'We could not process the refund at this time' => [
				'key'      => 'insufficient_funds',
				'expected' => 'We could not process the refund at this time',
			],
			'The original payment method was lost or stolen' => [
				'key'      => 'lost_or_stolen_card',
				'expected' => 'The original payment method was lost or stolen',
			],
			'We stopped processing the refund'             => [
				'key'      => 'merchant_request',
				'expected' => 'We stopped processing the refund',
			],
			'Unknown reason (random)'                      => [
				'key'      => 'random',
				'expected' => 'Unknown reason',
			],
			'Unknown reason (null)'                        => [
				'key'      => null,
				'expected' => 'Unknown reason',
			],
			'Unknown reason (empty)'                       => [
				'key'      => '',
				'expected' => 'Unknown reason',
			],
		];
	}

	/**
	 * Tests for `has_other_bnpl_plugins_active`.
	 *
	 * @param array $payment_gateways The available payment gateways.
	 * @param bool  $expected         The expected result.
	 * @dataProvider provide_test_has_other_bnpl_plugins_active
	 * @return void
	 */
	public function test_has_other_bnpl_plugins_active( $payment_gateways, $expected ) {
		$original_payment_gateways = WC()->payment_gateways->payment_gateways;

		// Mock the available payment gateways.
		WC()->payment_gateways->payment_gateways = $payment_gateways;

		$actual = WC_Stripe_Helper::has_other_bnpl_plugins_active();

		// Clean up.
		WC()->payment_gateways->payment_gateways = $original_payment_gateways;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_has_other_bnpl_plugins_active`.
	 *
	 * @return array
	 */
	public function provide_test_has_other_bnpl_plugins_active() {
		return [
			'has other plugins'           => [
				'payment gateways' => [
					'klarna' => (object) [
						'id'      => 'klarna_payments',
						'enabled' => 'yes',
					],
					'affirm' => (object) [
						'id'      => 'affirm',
						'enabled' => 'yes',
					],
				],
				'expected'         => true,
			],
			'does not have other plugins' => [
				'payment gateways' => [],
				'expected'         => false,
			],
		];
	}

	/**
	 * Tests for `has_gateway_plugin_active`.
	 *
	 * @param string $plugin_id The plugin ID to evaluate.
	 * @param array $payment_gateways The available payment gateways.
	 * @param bool $expected The expected result.
	 * @return void
	 *
	 * @dataProvider provide_has_gateway_plugin_active
	 */
	public function test_has_gateway_plugin_active( $plugin_id, $payment_gateways, $expected ) {
		$original_payment_gateways = WC()->payment_gateways->payment_gateways;

		// Mock the available payment gateways.
		WC()->payment_gateways->payment_gateways = $payment_gateways;

		$actual = WC_Stripe_Helper::has_gateway_plugin_active( $plugin_id );

		// Clean up.
		WC()->payment_gateways->payment_gateways = $original_payment_gateways;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_has_gateway_plugin_active`.
	 *
	 * @return array
	 */
	public function provide_has_gateway_plugin_active() {
		return [
			'has Klarna official plugin active'           => [
				'plugin id'        => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
				'payment gateways' => [
					'klarna' => (object) [
						'id'      => 'klarna_payments',
						'enabled' => 'yes',
					],
				],
				'expected'         => true,
			],
			'does not have Klarna official plugin active' => [
				'plugin id'        => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
				'payment gateways' => [
					'affirm' => (object) [
						'id'      => 'affirm',
						'enabled' => 'yes',
					],
				],
				'expected'         => false,
			],
		];
	}

	/**
	 * Stripe requires price in the smallest dominations aka cents.
	 * This test will see if we're indeed converting the price correctly.
	 */
	public function test_price_conversion_before_send_to_stripe() {
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50, WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 10050, WC_Stripe_Currency_Code::JAPANESE_YEN ) );
		$this->assertEquals( 100, WC_Stripe_Helper::get_stripe_amount( 100.50, WC_Stripe_Currency_Code::JAPANESE_YEN ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50 ) );
		$this->assertIsInt( WC_Stripe_Helper::get_stripe_amount( 100.50, WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR ) );
	}

	/**
	 * We store balance fee/net amounts coming from Stripe.
	 * We need to make sure we format it correctly to be stored in WC.
	 * These amounts are posted in lowest dominations.
	 */
	public function test_format_balance_fee() {
		$balance_fee1           = new stdClass();
		$balance_fee1->fee      = 10500;
		$balance_fee1->net      = 10000;
		$balance_fee1->currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->assertEquals( 105.00, WC_Stripe_Helper::format_balance_fee( $balance_fee1, 'fee' ) );

		$balance_fee2           = new stdClass();
		$balance_fee2->fee      = 10500;
		$balance_fee2->net      = 10000;
		$balance_fee2->currency = WC_Stripe_Currency_Code::JAPANESE_YEN;

		$this->assertEquals( 10500, WC_Stripe_Helper::format_balance_fee( $balance_fee2, 'fee' ) );

		$balance_fee3           = new stdClass();
		$balance_fee3->fee      = 10500;
		$balance_fee3->net      = 10000;
		$balance_fee3->currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->assertEquals( 100.00, WC_Stripe_Helper::format_balance_fee( $balance_fee3, 'net' ) );

		$balance_fee4           = new stdClass();
		$balance_fee4->fee      = 10500;
		$balance_fee4->net      = 10000;
		$balance_fee4->currency = WC_Stripe_Currency_Code::JAPANESE_YEN;

		$this->assertEquals( 10000, WC_Stripe_Helper::format_balance_fee( $balance_fee4, 'net' ) );

		$balance_fee5           = new stdClass();
		$balance_fee5->fee      = 10500;
		$balance_fee5->net      = 10000;
		$balance_fee5->currency = WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR;

		$this->assertEquals( 105.00, WC_Stripe_Helper::format_balance_fee( $balance_fee5 ) );

		$this->assertIsString( WC_Stripe_Helper::format_balance_fee( $balance_fee5 ) );
	}

	/**
	 * Stripe requires statement_descriptor to be no longer than 22 characters.
	 * In addition, it cannot contain <>"' special characters.
	 *
	 * @dataProvider statement_descriptor_sanitation_provider
	 */
	public function test_statement_descriptor_sanitation( $original, $expected ) {
		$this->assertEquals( $expected, WC_Stripe_Helper::clean_statement_descriptor( $original ) );
	}

	public function statement_descriptor_sanitation_provider() {
		return [
			'removes \''             => [ 'Test\'s Store', 'Tests Store' ],
			'removes "'              => [ 'Test " Store', 'Test  Store' ],
			'removes <'              => [ 'Test < Store', 'Test  Store' ],
			'removes >'              => [ 'Test > Store', 'Test  Store' ],
			'removes /'              => [ 'Test / Store', 'Test  Store' ],
			'removes ('              => [ 'Test ( Store', 'Test  Store' ],
			'removes )'              => [ 'Test ) Store', 'Test  Store' ],
			'removes {'              => [ 'Test { Store', 'Test  Store' ],
			'removes }'              => [ 'Test } Store', 'Test  Store' ],
			'removes \\'             => [ 'Test \\ Store', 'Test  Store' ],
			'removes *'              => [ 'Test * Store', 'Test  Store' ],
			'keeps at most 22 chars' => [ 'Test\'s Store > Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and >' => [ 'Test\'s Store > Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and <' => [ 'Test\'s Store < Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and "' => [ 'Test\'s Store " Driving Course Range', 'Tests Store  Driving C' ],
			'removes non-Latin'      => [ 'Test-Storeシ Drהiving?12', 'Test-Store Driving?12' ],
		];
	}

	public function test_turning_on_upe_enables_the_correct_upe_methods_based_on_which_legacy_payment_methods_were_enabled() {
		update_option( 'woocommerce_currency', 'EUR' );
		$this->upe_helper->enable_upe_feature_flag();

		// Enable sepa and iDEAL LPM gateways.
		update_option( 'woocommerce_stripe_sepa_settings', [ 'enabled' => 'yes' ] );
		update_option( 'woocommerce_stripe_ideal_settings', [ 'enabled' => 'yes' ] );
		$this->upe_helper->reload_payment_gateways();

		// Initialize default stripe settings, turn on UPE.
		WC_Stripe_Helper::update_main_stripe_settings( [ 'upe_checkout_experience_enabled' => 'yes' ] );

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertEquals( 'yes', $stripe_settings['enabled'] );
		$this->assertEquals( 'yes', $stripe_settings['upe_checkout_experience_enabled'] );

		// Make sure the SEPA and iDEAL LPMs were disabled.
		$sepa_settings = get_option( 'woocommerce_stripe_sepa_settings' );
		$this->assertEquals( 'no', $sepa_settings['enabled'] );
		$ideal_settings = get_option( 'woocommerce_stripe_ideal_settings' );
		$this->assertEquals( 'no', $ideal_settings['enabled'] );
	}

	public function test_turning_off_upe_enables_the_correct_legacy_payment_methods_based_on_which_upe_payment_methods_were_enabled() {
		$this->upe_helper->enable_upe_feature_flag();

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		update_option( 'woocommerce_currency', 'EUR' );

		// Disable sepa and iDEAL LPM gateways.
		update_option( 'woocommerce_stripe_sepa_settings', [ 'enabled' => 'no' ] );
		update_option( 'woocommerce_stripe_ideal_settings', [ 'enabled' => 'no' ] );

		// Turn UPE on first.
		$stripe_settings['upe_checkout_experience_enabled'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::SEPA_DEBIT, WC_Stripe_Payment_Methods::IDEAL ] );

		// Turn UPE off.
		$stripe_settings['upe_checkout_experience_enabled'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Check that the main 'stripe' gateway was disabled because the 'card' UPE method was not enabled.
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertEquals( 'no', $stripe_settings['enabled'] );
		// Check that the correct LPMs were re-enabled.
		$sepa_settings = get_option( 'woocommerce_stripe_sepa_settings' );
		$this->assertEquals( 'yes', $sepa_settings['enabled'] );
		$ideal_settings = get_option( 'woocommerce_stripe_ideal_settings' );
		$this->assertEquals( 'yes', $ideal_settings['enabled'] );
	}

	/**
	 * Test that {@see WC_Stripe_Helper::is_webhook_url()} works as expected.
	 *
	 * @dataProvider is_webhook_url_provider
	 * @covers WC_Stripe_Helper::is_webhook_url()
	 */
	public function test_is_webhook_url( $url, $webhook_url, $expected_result ) {
		$this->assertEquals( $expected_result, WC_Stripe_Helper::is_webhook_url( $url, $webhook_url ) );
	}

	/**
	 * Data provider for {@see test_is_webhook_url()}.
	 *
	 * @return array
	 */
	public function is_webhook_url_provider() {
		return [
			'webhook URLs with mismatched protocol should match'       => [ 'https://example.com/?wc-api=wc_stripe', 'http://example.com/?wc-api=wc_stripe', true ],
			'webhook URLs with mismatched host should not match'       => [ 'https://example.com/?wc-api=wc_stripe', 'https://test.example.com/?wc-api=wc_stripe', false ],
			'webhook URLs with mismatched path should not match'       => [ 'https://example.com/foo?wc-api=wc_stripe', 'https://example.com/bar?wc-api=wc_stripe', false ],
			'webhook URL with empty path should match'                 => [ 'https://example.com/', 'https://example.com/?wc-api=wc_stripe', true ],
			'webhook URL with empty query string should match'         => [ 'https://example.com/test/', 'https://example.com/test/', true ],
			'webhook URL with empty comparison query should not match' => [ 'https://example.com/test/?foo=bar', 'https://example.com/test/', false ],
			'webhook URL with missing parameter should not match'      => [ 'https://example.com/test/?wc-api=wc_stripe', 'https://example.com/test/?wc-api=wc_stripe&foo=bar', false ],
			'webhook URL with wrong parameter should not match'        => [ 'https://example.com/test/?wc-api=wc_stripe_BAD', 'https://example.com/test/?wc-api=wc_stripe', false ],
			'webhook URL with extra parameters should match'           => [ 'https://example.com/test/?wc-api=wc_stripe&foo=bar', 'https://example.com/test/?wc-api=wc_stripe', true ],
		];
	}

	/**
	 * Data provider for {@see test_get_minimum_amount()}.
	 *
	 * @return array
	 */
	public function provide_test_get_minimum_amount(): array {
		return [
			'USD'              => [ 'USD', 50 ],
			'EUR'              => [ 'EUR', 50 ],
			'GBP'              => [ 'GBP', 30 ],
			'CAD'              => [ 'CAD', 50 ],
			'CHF'              => [ 'CHF', 50 ],
			'CZK'              => [ 'CZK', 1500 ],
			'DKK'              => [ 'DKK', 250 ],
			'HUF'              => [ 'HUF', 17500 ],
			'INR'              => [ 'INR', 50 ],
			'MXN'              => [ 'MXN', 1000 ],
			'MYR'              => [ 'MYR', 200 ],
			'NOK'              => [ 'NOK', 300 ],
			'NZD'              => [ 'NZD', 50 ],
			'PLN'              => [ 'PLN', 200 ],
			'RON'              => [ 'RON', 200 ],
			'SEK'              => [ 'SEK', 300 ],
			'SGD'              => [ 'SGD', 50 ],
			'THB'              => [ 'THB', 1000 ],
			'JPY'              => [ 'JPY', 5000 ],
			'UNKNOWN_CURRENCY' => [ 'UNKNOWN_CURRENCY', 50 ],
			'ZMW - not known'  => [ 'ZMW', 50 ],
		];
	}

	/**
	 * @dataProvider provide_test_get_minimum_amount
	 */
	public function test_get_minimum_amount( string $currency, int $expected ): void {
		$currency_filter = function () use ( $currency ) {
			return $currency;
		};
		add_filter( 'woocommerce_currency', $currency_filter );

		$minimum_amount = WC_Stripe_Helper::get_minimum_amount();

		remove_filter( 'woocommerce_currency', $currency_filter );

		$this->assertEquals( $expected, $minimum_amount );
	}
}
