<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use Automattic\WooCommerce\Enums\ProductTaxStatus;
use WC_Stripe_UPE_Payment_Gateway;
use WC_Gateway_Stripe_Alipay;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Stripe_Express_Checkout_Helper;
use WC_Stripe_Helper;
use WC_Subscription;
use WC_Subscriptions_Cart;
use WC_Subscriptions_Product;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Product;
use WP_UnitTestCase;

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Helper.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Express_Checkout_Helper
 *
 * WC_Stripe_Express_Checkout_Helper_Test class.
 */
class WC_Stripe_Express_Checkout_Helper_Test extends WP_UnitTestCase {
	private $shipping_zone;
	private $shipping_method;
	private $products;

	public function set_up() {
		parent::set_up();

		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	public function tear_down() {
		if ( $this->shipping_zone ) {
			delete_option( $this->shipping_method->get_instance_option_key() );
			$this->shipping_zone->delete();
		}

		parent::tear_down();
	}

	public function set_up_shipping_methods() {
		// Add a shipping zone.
		$this->shipping_zone = new WC_Shipping_Zone();
		$this->shipping_zone->set_zone_name( 'Worldwide' );
		$this->shipping_zone->set_zone_order( 1 );
		$this->shipping_zone->save();

		$flat_rate_id          = $this->shipping_zone->add_shipping_method( 'flat_rate' );
		$this->shipping_method = WC_Shipping_Zones::get_shipping_method( $flat_rate_id );
		$option_key            = $this->shipping_method->get_instance_option_key();
		$options['cost']       = '5';
		update_option( $option_key, $options );
	}

	/**
	 * Test should_show_express_checkout_button, tax logic.
	 *
	 * @param array  $cart_contents Cart contents.
	 * @param bool   $is_pay_for_order Whether this is a Pay for Order page.
	 * @param bool   $taxes_enabled Whether taxes are enabled.
	 * @param string $tax_based_on Tax based on setting.
	 * @param mixed  $filter_value Value for the filter `wc_stripe_should_hide_express_checkout_button_based_on_tax_setup`.
	 * @param bool   $expected Expected result of `should_show_express_checkout_button`.
	 * @return void
	 *
	 * @dataProvider provide_test_hides_ece_if_cannot_compute_taxes
	 */
	public function test_hides_ece_if_cannot_compute_taxes(
		$cart_contents,
		$is_pay_for_order,
		$taxes_enabled,
		$tax_based_on,
		$filter_value,
		$expected
	) {
		$this->set_up_shipping_methods();
		$this->create_products_for_test_hides_ece_if_cannot_compute_taxes();

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		if ( ! is_null( $filter_value ) ) {
			add_filter(
				'wc_stripe_should_hide_express_checkout_button_based_on_tax_setup',
				function () use ( $filter_value ) {
					return $filter_value;
				}
			);
		} else {
			remove_filter( 'wc_stripe_should_hide_express_checkout_button_based_on_tax_setup', '__return_true' );
		}

		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'is_product',
				'allowed_items_in_cart',
				'should_show_ece_on_cart_page',
				'should_show_ece_on_checkout_page',
			],
			[ $gateway ]
		);

		$wc_stripe_ece_helper_mock->method( 'is_product' )->willReturn( false );
		$wc_stripe_ece_helper_mock->method( 'allowed_items_in_cart' )->willReturn( true );
		$wc_stripe_ece_helper_mock->method( 'should_show_ece_on_cart_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->method( 'should_show_ece_on_checkout_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->testmode = true;
		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		// Ensure that the 'stripe' gateway is available.
		$original_gateways                         = WC()->payment_gateways()->payment_gateways;
		WC()->payment_gateways()->payment_gateways = [
			'stripe' => new WC_Stripe_UPE_Payment_Gateway(),
		];

		if ( $is_pay_for_order ) {
			$_GET['pay_for_order'] = 1;
		}

		update_option( 'woocommerce_calc_taxes', $taxes_enabled ? 'yes' : 'no' ); // Should be overriden by product tax status.
		update_option( 'woocommerce_tax_based_on', $tax_based_on );

		WC()->session->init();
		WC()->cart->empty_cart();

		foreach ( $cart_contents as $product_key ) {
			$product = $this->products[ $product_key ];
			WC()->cart->add_to_cart( $product->get_id(), 1 );
		}

		$this->assertEquals( $expected, $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Restore original settings.
		unset( $_GET['pay_for_order'] );
		WC()->cart->empty_cart();
		WC()->session->cleanup_sessions();
		WC()->payment_gateways()->payment_gateways = $original_gateways;

		update_option( 'woocommerce_calc_taxes', 'yes' );
	}

	/**
	 * Create products for test_hides_ece_if_cannot_compute_taxes.
	 */
	private function create_products_for_test_hides_ece_if_cannot_compute_taxes() {
		if (
			isset( $this->products['virtual_nontaxable'] ) &&
			isset( $this->products['virtual_taxable'] ) &&
			isset( $this->products['shippable_taxable'] )
		) {
			return;
		}

		$virtual_nontaxable_product = WC_Helper_Product::create_simple_product();
		$virtual_nontaxable_product->set_virtual( true );
		$virtual_nontaxable_product->set_tax_status( ProductTaxStatus::NONE );
		$virtual_nontaxable_product->save();

		$virtual_taxable_product = WC_Helper_Product::create_simple_product();
		$virtual_taxable_product->set_virtual( true );
		$virtual_taxable_product->set_tax_status( ProductTaxStatus::TAXABLE );
		$virtual_taxable_product->save();

		$shippable_taxable_product = WC_Helper_Product::create_simple_product();
		$shippable_taxable_product->set_virtual( false );
		$shippable_taxable_product->set_tax_status( ProductTaxStatus::TAXABLE );
		$shippable_taxable_product->save();
		$this->products = [
			'virtual_nontaxable' => $virtual_nontaxable_product,
			'virtual_taxable'    => $virtual_taxable_product,
			'shippable_taxable'  => $shippable_taxable_product,
		];
	}

	/**
	 * Provider for `test_hides_ece_if_cannot_compute_taxes`.
	 *
	 * @return array
	 */
	public function provide_test_hides_ece_if_cannot_compute_taxes() {
		$hide = false;
		$show = true;
		return [
			'Hide if cart has virtual product and tax is based on billing address.' => [
				'cart contents'    => [ 'virtual_taxable', 'virtual_nontaxable' ],
				'is pay for order' => false,
				'taxes enabled'    => true,
				'tax based on'     => 'billing',
				'filter value'     => null,
				'expected'         => $hide,
			],
			'Do not hide if cart has virtual product and tax is based on billing address, but taxes are now disabled.' => [
				'cart contents'    => [ 'virtual_taxable', 'virtual_nontaxable' ],
				'is pay for order' => false,
				'taxes enabled'    => false,
				'tax based on'     => 'billing',
				'filter value'     => null,
				'expected'         => $show,
			],
			'Do not hide if cart has virtual product and tax is based on billing address, but filter forces to show.' => [
				'cart contents'    => [ 'virtual_taxable', 'virtual_nontaxable' ],
				'is pay for order' => false,
				'taxes enabled'    => true,
				'tax based on'     => 'billing',
				'filter value'     => false,
				'expected'         => $show,
			],
			'Do not hide if Pay for Order page.'     => [
				'cart contents'    => [ 'virtual_taxable' ],
				'is pay for order' => true,
				'taxes enabled'    => true,
				'tax based on'     => 'billing',
				'filter value'     => null,
				'expected'         => $show,
			],
			'Do not hide if taxes are not enabled.'  => [
				'cart contents'    => [ 'virtual_nontaxable' ],
				'is pay for order' => false,
				'taxes enabled'    => false,
				'tax based on'     => 'billing',
				'filter value'     => null,
				'expected'         => $show,
			],
			'Do not hide if cart has virtual product and tax is based on shipping address.' => [
				'cart contents'    => [ 'virtual_taxable', 'virtual_nontaxable' ],
				'is pay for order' => false,
				'taxes enabled'    => true,
				'tax based on'     => 'shipping',
				'filter value'     => null,
				'expected'         => $show,
			],
			'Do not hide if taxes are not based on customer billing or shipping address.' => [
				'cart contents'    => [ 'virtual_taxable' ],
				'is pay for order' => false,
				'taxes enabled'    => true,
				'tax based on'     => 'base',
				'filter value'     => null,
				'expected'         => $show,
			],
			'Do not hide if cart requires shipping.' => [
				'cart contents'    => [ 'shippable_taxable' ],
				'is pay for order' => false,
				'taxes enabled'    => true,
				'tax based on'     => 'billing',
				'filter value'     => null,
				'expected'         => $show,
			],
		];
	}

	/**
	 * Test should_show_express_checkout_button, gateway logic.
	 */
	public function test_hides_ece_if_stripe_gateway_unavailable() {
		$this->set_up_shipping_methods();

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'is_product',
				'allowed_items_in_cart',
				'should_show_ece_on_cart_page',
				'should_show_ece_on_checkout_page',
			],
			[ $gateway ]
		);
		$wc_stripe_ece_helper_mock->method( 'is_product' )->willReturn( false );
		$wc_stripe_ece_helper_mock->method( 'allowed_items_in_cart' )->willReturn( true );
		$wc_stripe_ece_helper_mock->method( 'should_show_ece_on_cart_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->method( 'should_show_ece_on_checkout_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->testmode = true;
		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}
		$original_gateways = WC()->payment_gateways()->payment_gateways;

		// Add a non-taxable product to the cart.
		$product = WC_Helper_Product::create_simple_product();
		$product->set_virtual( false );
		$product->set_tax_status( ProductTaxStatus::NONE );
		$product->save();

		WC()->session->init();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		WC()->payment_gateways()->payment_gateways = [
			'stripe'        => new WC_Stripe_UPE_Payment_Gateway(),
			'stripe_alipay' => new WC_Gateway_Stripe_Alipay(),
		];
		$this->assertTrue( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Hide if 'stripe' gateway is unavailable.
		unset( WC()->payment_gateways()->payment_gateways['stripe'] );
		$this->assertFalse( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Restore original settings.
		WC()->session->cleanup_sessions();
		WC()->cart->empty_cart();
		WC()->payment_gateways()->payment_gateways = $original_gateways;
	}

	/**
	 * Test should_show_express_checkout_button, free trial logic.
	 *
	 * @return void
	 */
	public function test_hides_ece_if_free_trial_requires_shipping() {
		$this->set_up_shipping_methods();

		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'is_product',
				'get_product',
				'allowed_items_in_cart',
				'should_show_ece_on_cart_page',
				'should_show_ece_on_checkout_page',
			],
		);

		$wc_stripe_ece_helper_mock->method( 'is_product' )->willReturn( true );
		$wc_stripe_ece_helper_mock->method( 'allowed_items_in_cart' )->willReturn( true );
		$wc_stripe_ece_helper_mock->method( 'should_show_ece_on_cart_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->method( 'should_show_ece_on_checkout_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->testmode = true;

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		// Ensure that the 'stripe' gateway is available.
		$original_gateways                         = WC()->payment_gateways()->payment_gateways;
		WC()->payment_gateways()->payment_gateways = [
			'stripe' => new WC_Stripe_UPE_Payment_Gateway(),
		];

		update_option( 'woocommerce_calc_taxes', 'no' );

		// Should show, as free virtual products does not require shipping.
		$virtual_product = WC_Helper_Product::create_simple_product();
		$virtual_product->set_virtual( true );
		$virtual_product->set_tax_status( 'none' );
		$virtual_product->set_price( 0 );
		$virtual_product->save();

		WC()->session->init();
		WC()->cart->empty_cart();

		WC()->cart->add_to_cart( $virtual_product->get_id(), 1 );
		$wc_stripe_ece_helper_mock
			->method( 'get_product' )
			->willReturn( $virtual_product );

		$this->assertTrue( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Should hide if the free product requires shipping.
		$shippable_product = WC_Helper_Product::create_simple_product();
		$shippable_product->set_virtual( false );
		$shippable_product->set_tax_status( 'none' );
		$shippable_product->save();

		WC()->session->init();
		WC()->cart->empty_cart();

		WC()->cart->add_to_cart( $shippable_product->get_id(), 1 );
		$wc_stripe_ece_helper_mock
			->method( 'get_product' )
			->willReturn( $shippable_product );

		$this->assertFalse( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Restore original settings.
		WC()->cart->empty_cart();
		WC()->session->cleanup_sessions();
		WC()->payment_gateways()->payment_gateways = $original_gateways;

		update_option( 'woocommerce_calc_taxes', 'yes' );
	}

	/**
	 * Test for get_checkout_data().
	 */
	public function test_get_checkout_data() {
		// Local setup
		update_option( 'woocommerce_checkout_phone_field', 'optional' );
		update_option( 'woocommerce_default_country', 'US' );
		update_option( 'woocommerce_currency', 'USD' );
		WC()->cart->empty_cart();

		$this->set_up_shipping_methods();

		$wc_stripe_ece_helper = new WC_Stripe_Express_Checkout_Helper();
		$checkout_data        = $wc_stripe_ece_helper->get_checkout_data();

		$this->assertNotEmpty( $checkout_data['url'] );
		$this->assertEquals( 'usd', $checkout_data['currency_code'] );
		$this->assertEquals( 'US', $checkout_data['country_code'] );
		$this->assertEquals( 'no', $checkout_data['needs_shipping'] );
		$this->assertFalse( $checkout_data['needs_payer_phone'] );
		$this->assertArrayHasKey( 'id', $checkout_data['default_shipping_option'] );
		$this->assertArrayHasKey( 'displayName', $checkout_data['default_shipping_option'] );
		$this->assertArrayHasKey( 'amount', $checkout_data['default_shipping_option'] );
	}

	/**
	 * Test for get_checkout_data(), no shipping zones.
	 *
	 * This is in a separate test, to avoid problems with cached data.
	 */
	public function test_get_checkout_data_no_shipping_zones() {
		// When no shipping zones are set up, the default shipping option should be empty.
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$wc_stripe_ece_helper = new WC_Stripe_Express_Checkout_Helper();
		$checkout_data        = $wc_stripe_ece_helper->get_checkout_data();
		$this->assertEmpty( $checkout_data['default_shipping_option'] );
	}

	/**
	 * Test for is_authentication_required().
	 */
	public function test_is_authentication_required() {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'is_account_creation_possible',
			],
			[ $gateway ]
		);
		$wc_stripe_ece_helper_mock->method( 'is_account_creation_possible' )
			->willReturnOnConsecutiveCalls( true, false );

		// Guest checkout is enabled.
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );
		$this->assertFalse( $wc_stripe_ece_helper_mock->is_authentication_required() );

		// Guest checkout is disabled, and account creation is possible.
		update_option( 'woocommerce_enable_guest_checkout', 'no' );
		$this->assertFalse( $wc_stripe_ece_helper_mock->is_authentication_required() );

		// Guest checkout is disabled, and account creation is not possible.
		update_option( 'woocommerce_enable_guest_checkout', 'no' );
		$this->assertTrue( $wc_stripe_ece_helper_mock->is_authentication_required() );
	}

	/**
	 * Test for is_account_creation_possible().
	 */
	public function test_is_account_creation_possible() {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'has_subscription_product',
			],
			[ $gateway ]
		);
		$wc_stripe_ece_helper_mock->method( 'has_subscription_product' )
			->willReturn( false );

		// Account creation on checkout is enabled.
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
		$this->assertTrue( $wc_stripe_ece_helper_mock->is_account_creation_possible() );

		// Account creation on checkout is disabled.
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
		$this->assertFalse( $wc_stripe_ece_helper_mock->is_account_creation_possible() );

		// Account creation on checkout is enabled for subscriptions, but no subscription in cart.
		update_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions', 'yes' );
		$this->assertFalse( $wc_stripe_ece_helper_mock->is_account_creation_possible() );

		//. Tests for when a subscription product is in the cart.
		$wc_stripe_ece_helper_mock2 = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'has_subscription_product',
			],
			[ $gateway ]
		);
		$wc_stripe_ece_helper_mock2->method( 'has_subscription_product' )
			->willReturn( true );

		// Account creation on checkout is disabled.
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
		update_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions', 'no' );
		$this->assertFalse( $wc_stripe_ece_helper_mock2->is_account_creation_possible() );

		// Account creation on checkout is enabled for subscriptions, with subscription in cart.
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
		update_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions', 'yes' );
		$this->assertTrue( $wc_stripe_ece_helper_mock2->is_account_creation_possible() );
	}

	/**
	 * Test for `get_normalized_postal_code`.
	 *
	 * @param string $postal_code Postal code.
	 * @param string $country Country code.
	 * @param string $expected Expected normalized postal code.
	 * @return void
	 * @dataProvider provide_test_get_normalized_postal_code
	 */
	public function test_get_normalized_postal_code( $postal_code, $country, $expected ) {
		$wc_stripe_ece_helper = new WC_Stripe_Express_Checkout_Helper();
		$this->assertEquals( $expected, $wc_stripe_ece_helper->get_normalized_postal_code( $postal_code, $country ) );
	}

	/**
	 * Provider for `test_get_normalized_postal_code`.
	 *
	 * @return array
	 */
	public function provide_test_get_normalized_postal_code() {
		return [
			'GB country'           => [
				'postal code' => 'SW1A 1AA',
				'country'     => 'GB',
				'expected'    => 'SW1A 1AA',
			],
			'GB country, redacted' => [
				'postal code' => 'SW1A',
				'country'     => 'GB',
				'expected'    => 'SW1A ***',
			],
			'CA country'           => [
				'postal code' => 'K1A   ',
				'country'     => 'CA',
				'expected'    => 'K1A***',
			],
			'US country'           => [
				'postal code' => '12345',
				'country'     => 'US',
				'expected'    => '12345',
			],
		];
	}

	/**
	 * Test for `get_payment_method_title_suffix`
	 *
	 * @return void
	 */
	public function test_get_payment_method_title_suffix() {
		$actual = WC_Stripe_Express_Checkout_Helper::get_payment_method_title_suffix();

		$this->assertEquals( ' (Stripe)', $actual );
	}

	/**
	 * Test is_express_checkout_context method.
	 *
	 * @dataProvider provide_test_is_express_checkout_context
	 */
	public function test_is_express_checkout_context( $is_store_api, $has_express_header, $has_nonce_header, $nonce_valid, $expected ) {
		$helper = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[ 'is_request_to_store_api' ]
		);

		$helper->method( 'is_request_to_store_api' )
			->willReturn( $is_store_api );

		// Set up $_SERVER superglobal for headers
		$original_server = $_SERVER;

		if ( $has_express_header ) {
			$_SERVER['HTTP_X_WCSTRIPE_EXPRESS_CHECKOUT'] = 'true';
		} else {
			unset( $_SERVER['HTTP_X_WCSTRIPE_EXPRESS_CHECKOUT'] );
		}

		if ( $has_nonce_header ) {
			if ( $nonce_valid ) {
				$_SERVER['HTTP_X_WCSTRIPE_EXPRESS_CHECKOUT_NONCE'] = wp_create_nonce( 'wc_store_api_express_checkout' );
			} else {
				$_SERVER['HTTP_X_WCSTRIPE_EXPRESS_CHECKOUT_NONCE'] = 'invalid_nonce';
			}
		} else {
			unset( $_SERVER['HTTP_X_WCSTRIPE_EXPRESS_CHECKOUT_NONCE'] );
		}

		$result = $helper->is_express_checkout_context();
		$this->assertEquals( $expected, $result );

		// Restore original $_SERVER
		$_SERVER = $original_server;
	}

	/**
	 * Data provider for test_is_express_checkout_context.
	 *
	 * @return array
	 */
	public function provide_test_is_express_checkout_context() {
		return [
			'Not Store API request'                 => [
				'is_store_api'       => false,
				'has_express_header' => true,
				'has_nonce_header'   => true,
				'nonce_valid'        => true,
				'expected'           => false,
			],
			'Store API request but no express checkout header' => [
				'is_store_api'       => true,
				'has_express_header' => false,
				'has_nonce_header'   => true,
				'nonce_valid'        => true,
				'expected'           => false,
			],
			'Store API request but no nonce header' => [
				'is_store_api'       => true,
				'has_express_header' => true,
				'has_nonce_header'   => false,
				'nonce_valid'        => false,
				'expected'           => false,
			],
			'Store API request and express header but invalid nonce' => [
				'is_store_api'       => true,
				'has_express_header' => true,
				'has_nonce_header'   => true,
				'nonce_valid'        => false,
				'expected'           => false,
			],
			'All conditions met - valid express checkout context' => [
				'is_store_api'       => true,
				'has_express_header' => true,
				'has_nonce_header'   => true,
				'nonce_valid'        => true,
				'expected'           => true,
			],
		];
	}

	/**
	 * Test is_request_to_store_api method.
	 *
	 * @dataProvider provide_test_is_request_to_store_api
	 */
	public function test_is_request_to_store_api( $rest_route, $expected ) {
		$helper = new WC_Stripe_Express_Checkout_Helper();

		// Set up global WP query vars
		$original_wp = $GLOBALS['wp'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp'] = (object) [
			'query_vars' => [
				'rest_route' => $rest_route,
			],
		];

		$result = $helper->is_request_to_store_api();
		$this->assertEquals( $expected, $result );

		// Restore original global
		if ( $original_wp ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['wp'] = $original_wp;
		} else {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			unset( $GLOBALS['wp'] );
		}
	}

	/**
	 * Data provider for test_is_request_to_store_api.
	 *
	 * @return array
	 */
	public function provide_test_is_request_to_store_api() {
		return [
			'No rest_route set'         => [
				'rest_route' => '',
				'expected'   => false,
			],
			'Store API checkout route'  => [
				'rest_route' => '/wc/store/v1/checkout',
				'expected'   => true,
			],
			'Different Store API route' => [
				'rest_route' => '/wc/store/v1/cart',
				'expected'   => false,
			],
			'Non-Store API route'       => [
				'rest_route' => '/wp/v2/posts',
				'expected'   => false,
			],
		];
	}

	/**
	 * Test for `get_stripe_currency_decimals`.
	 *
	 * @param string $currency Currency code.
	 * @param int    $expected Expected number of decimals.
	 *
	 * @dataProvider provide_test_get_stripe_currency_decimals
	 */
	public function test_get_stripe_currency_decimals( $currency, $expected ) {
		update_option( 'woocommerce_currency', $currency );

		$actual = WC_Stripe_Express_Checkout_Helper::get_stripe_currency_decimals();
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Provider for `test_get_stripe_currency_decimals`.
	 *
	 * @return array
	 */
	public function provide_test_get_stripe_currency_decimals() {
		return [
			// No decimal currencies - should return 0
			'Japanese Yen (no decimals)'      => [
				'currency' => 'JPY',
				'expected' => 0,
			],
			// Three decimal currencies - should return 3
			'Bahraini Dinar (three decimals)' => [
				'currency' => 'BHD',
				'expected' => 3,
			],
			// Default currencies - should return 2
			'US Dollar (default)'             => [
				'currency' => 'USD',
				'expected' => 2,
			],
			'Euro (default)'                  => [
				'currency' => 'EUR',
				'expected' => 2,
			],
		];
	}

	/**
	 * Tests for `get_booking_ids_from_cart`.
	 *
	 * @param array $cart_contents Cart contents.
	 * @param array $expected Expected booking IDs.
	 * @return void
	 *
	 * @dataProvider provide_test_get_booking_ids_from_cart
	 */
	public function test_get_booking_ids_from_cart( $cart_contents, $expected ) {
		WC()->session->init();
		WC()->cart->empty_cart();

		WC()->cart->cart_contents = $cart_contents;

		$helper = new WC_Stripe_Express_Checkout_Helper();
		$actual = $helper->get_booking_ids_from_cart();

		// Clean up.
		WC()->session->cleanup_sessions();
		WC()->cart->empty_cart();

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_get_booking_ids_from_cart`.
	 *
	 * @return array
	 */
	public function provide_test_get_booking_ids_from_cart() {
		$product_1 = WC_Helper_Product::create_simple_product();
		$product_1->save();

		$product_2 = WC_Helper_Product::create_simple_product();
		$product_2->save();

		$product_3 = WC_Helper_Product::create_simple_product();
		$product_3->save();

		return [
			'no products'                => [
				'cart contents' => [],
				'expected'      => [],
			],
			'single product'             => [
				'cart contents' => [
					[
						'product_id' => $product_1->get_id(),
						'booking'    => [
							'_booking_id' => $product_1->get_id(),
						],
					],
				],
				'expected'      => [
					$product_1->get_id(),
				],
			],
			'multiple products'          => [
				'cart contents' => [
					[
						'product_id' => $product_1->get_id(),
						'booking'    => [
							'_booking_id' => $product_1->get_id(),
						],
					],
					[
						'product_id' => $product_2->get_id(),
						'booking'    => [
							'_booking_id' => $product_2->get_id(),
						],
					],
				],
				'expected'      => [
					$product_1->get_id(),
					$product_2->get_id(),
				],
			],
			'multiple products, same ID' => [
				'cart contents' => [
					[
						'product_id' => $product_1->get_id(),
						'booking'    => [
							'_booking_id' => $product_1->get_id(),
						],
					],
					[
						'product_id' => $product_1->get_id(),
						'booking'    => [
							'_booking_id' => $product_1->get_id(),
						],
					],
				],
				'expected'      => [
					$product_1->get_id(),
				],
			],
			'mixed products (booking data not always present)' => [
				'cart contents' => [
					[
						'product_id' => $product_1->get_id(),
						'booking'    => [
							'_booking_id' => $product_1->get_id(),
						],
					],
					[
						'product_id' => $product_2->get_id(),
					],
					[
						'product_id' => $product_3->get_id(),
						'booking'    => [
							'_booking_id' => $product_3->get_id(),
						],
					],
				],
				'expected'      => [
					$product_1->get_id(),
					$product_3->get_id(),
				],
			],
		];
	}

	/**
	 * Test for has_free_trial().
	 *
	 * @param bool            $is_product Whether is product page.
	 * @param \WC_Order|null  $product Product on product page.
	 * @param int             $trial_length Trial length of the product.
	 * @param bool            $is_checkout Whether is checkout page.
	 * @param bool            $cart_contains_free_trial Whether cart contains a product with free trial.
	 * @param bool            $expected Expected result.
	 * @return void
	 * @dataProvider provide_test_has_free_trial
	 */
	public function test_has_free_trial( $is_product, $product, $trial_length, $is_checkout, $cart_contains_free_trial, $expected ) {
		add_filter(
			'woocommerce_is_checkout',
			function () use ( $is_checkout ) {
				return $is_checkout;
			}
		);

		WC_Subscriptions_Cart::set_cart_contains_free_trial( $cart_contains_free_trial );

		WC_Subscriptions_Product::set_is_subscription( true );

		WC_Subscriptions_Product::set_trial_length( $trial_length );

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->onlyMethods( [ 'is_product', 'get_product' ] )
			->getMock();

		$helper->method( 'is_product' )
			->willReturn( $is_product );

		$helper->method( 'get_product' )
			->willReturn( $product );

		$actual = $helper->has_free_trial();

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_has_free_trial`.
	 *
	 * @return array
	 */
	public function provide_test_has_free_trial() {
		$subscription = new WC_Subscription();

		$subscription_with_trial = new WC_Subscription();
		$subscription_with_trial->update_meta_data( 'subscription_trial_length', 14 );
		$subscription_with_trial->save_meta_data();

		return [
			'product page, missing product' => [
				'is_product'               => true,
				'product'                  => null,
				'trial length'             => 0,
				'is checkout'              => false,
				'cart contains free trial' => false,
				'expected'                 => false,
			],
			'product page, no free trial' => [
				'is_product'               => true,
				'product'                  => $subscription,
				'trial length'             => 0,
				'is checkout'              => false,
				'cart contains free trial' => false,
				'expected'                 => false,
			],
			'product page, with free trial' => [
				'is_product'               => true,
				'product'                  => $subscription_with_trial,
				'trial length'             => 14,
				'is checkout'              => false,
				'cart contains free trial' => false,
				'expected'                 => true,
			],
			'cart/checkout page, no free trial' => [
				'is_product'               => false,
				'product'                  => $subscription,
				'trial length'             => 0,
				'is checkout'              => true,
				'cart contains free trial' => false,
				'expected'                 => false,
			],
			'cart/checkout page, with free trial' => [
				'is_product'               => false,
				'product'                  => $subscription_with_trial,
				'trial length'             => 14,
				'is checkout'              => true,
				'cart contains free trial' => true,
				'expected'                 => true,
			],
		];
	}

	/**
	 * Tests for `is_cart`.
	 *
	 * @return void
	 */
	public function test_is_cart(): void {
		add_filter( 'woocommerce_is_cart', '__return_true' );

		$helper = new WC_Stripe_Express_Checkout_Helper();

		$actual = $helper->is_cart();

		// Clean up.
		remove_filter( 'woocommerce_is_cart', '__return_true' );

		$this->assertTrue( $actual );

		$actual = $helper->is_cart();

		$this->assertFalse( $actual );
	}

	/**
	 * Tests for `get_button_locations`.
	 *
	 * @param string $express_checkout_type Express checkout type.
	 * @param array  $settings              Settings array.
	 * @param array  $expected              Expected locations.
	 * @return void
	 *
	 * @dataProvider provide_test_get_button_locations
	 */
	public function test_get_button_locations( string $express_checkout_type, array $settings = [], $expected = [] ): void {
		$helper = new WC_Stripe_Express_Checkout_Helper();
		$helper->stripe_settings = $settings;

		$actual = $helper->get_button_locations( $express_checkout_type );

		$this->assertSame( $expected, $actual );
	}

	public function provide_test_get_button_locations(): array {
		return [
			'payment request, settings exists' => [
				'express checkout type' => 'payment_request',
				'settings'              => [ 'express_checkout_button_locations' => [ 'checkout', 'cart' ] ],
				'expected'              => [ 'checkout', 'cart' ],
			],
			'payment request, settings exists, but not a valid array' => [
				'express checkout type' => 'payment_request',
				'settings'              => [ 'express_checkout_button_locations' => 'invalid_value' ],
				'expected'              => [],
			],
			'payment request, settings do not exist' => [
				'express checkout type' => 'payment_request',
				'settings'              => [],
				'expected'              => [ 'product', 'cart' ],
			],
			'link, settings exists' => [
				'express checkout type' => 'link',
				'settings'              => [ 'express_checkout_button_locations' => [ 'cart' ] ],
				'expected'              => [ 'cart' ],
			],
			'link, settings exists, but not a valid array' => [
				'express checkout type' => 'link',
				'settings'              => [ 'express_checkout_button_locations' => 'invalid_value' ],
				'expected'              => [],
			],
			'link, settings do not exist' => [
				'express checkout type' => 'link',
				'settings'              => [],
				'expected'              => [ 'product', 'cart' ],
			],
			'amazon pay, settings exists' => [
				'express checkout type' => 'amazon_pay',
				'settings'              => [ 'amazon_pay_button_locations' => [ 'checkout' ] ],
				'expected'              => [ 'checkout' ],
			],
			'amazon pay, settings exists, but not a valid array' => [
				'express checkout type' => 'amazon_pay',
				'settings'              => [ 'amazon_pay_button_locations' => 'invalid_value' ],
				'expected'              => [],
			],
			'amazon pay, settings do not exist' => [
				'express checkout type' => 'amazon_pay',
				'settings'              => [],
				'expected'              => [ 'product', 'cart' ],
			],
			'default, settings exists' => [
				'express checkout type' => 'default',
				'settings'              => [ 'express_checkout_button_locations' => [ 'checkout', 'cart' ] ],
				'expected'              => [ 'checkout', 'cart' ],
			],
			'default, settings exists, but not a valid array' => [
				'express checkout type' => 'default',
				'settings'              => [ 'express_checkout_button_locations' => 'invalid_value' ],
				'expected'              => [],
			],
			'default, settings do not exist' => [
				'express checkout type' => 'default',
				'settings'              => [],
				'expected'              => [ 'product', 'cart' ],
			],
		];
	}

	/**
	 * Test that OPC detection logic works correctly.
	 *
	 * @dataProvider provide_opc_detection_scenarios
	 *
	 * @param bool  $is_opc Whether the page is detected as One Page Checkout.
	 * @param array $button_locations Button location settings.
	 * @param bool  $expected Expected result for should_show_express_checkout_button.
	 *
	 * @return void
	 */
	public function test_opc_detection_logic( $is_opc, $button_locations, $expected ) {
		$stripe_settings                                      = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['express_checkout_button_locations'] = $button_locations;
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'is_one_page_checkout',
				'is_product',
				'is_checkout',
				'allowed_items_in_cart',
				'get_product',
			],
			[ $gateway ]
		);

		// Create a mock product.
		$product = WC_Helper_Product::create_simple_product();
		$is_product_page = $is_opc || in_array( 'product', $button_locations, true );

		// Mock the methods.
		$wc_stripe_ece_helper_mock->method( 'is_one_page_checkout' )->willReturn( $is_opc );
		$wc_stripe_ece_helper_mock->method( 'is_product' )->willReturn( $is_product_page );
		$wc_stripe_ece_helper_mock->method( 'is_checkout' )->willReturn( false );
		$wc_stripe_ece_helper_mock->method( 'allowed_items_in_cart' )->willReturn( true );
		$wc_stripe_ece_helper_mock->method( 'get_product' )->willReturn( $is_product_page ? $product : false );

		// Manually set the properties that would be set in the constructor.
		$wc_stripe_ece_helper_mock->stripe_settings = $stripe_settings;
		$wc_stripe_ece_helper_mock->testmode        = true;

		// Ensure that the 'stripe' gateway is available.
		$original_gateways                         = WC()->payment_gateways()->payment_gateways;
		WC()->payment_gateways()->payment_gateways = [
			'stripe' => new WC_Stripe_UPE_Payment_Gateway(),
		];

		// Test the actual OPC logic in should_show_express_checkout_button.
		$result = $wc_stripe_ece_helper_mock->should_show_express_checkout_button();

		$this->assertEquals( $expected, $result );

		// Restore original gateways.
		WC()->payment_gateways()->payment_gateways = $original_gateways;
	}

	/**
	 * Data provider for OPC detection scenarios.
	 *
	 * @return array
	 */
	public function provide_opc_detection_scenarios() {
		return [
			'OPC with checkout enabled'     => [ true, [ 'checkout' ], true ],
			'Non-OPC with checkout enabled' => [ false, [ 'checkout' ], true ],
			'OPC with checkout disabled'    => [ true, [ 'product' ], false ],
			'OPC with both enabled'         => [ true, [ 'checkout', 'product' ], true ],
		];
	}
}
