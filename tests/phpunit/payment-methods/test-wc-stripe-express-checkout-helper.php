<?php

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Helper.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Express_Checkout_Helper_Test
 */

/**
 * WC_Stripe_Express_Checkout_Helper_Test class.
 */
class WC_Stripe_Express_Checkout_Helper_Test extends WP_UnitTestCase {
	private $shipping_zone;
	private $shipping_method;

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
	 */
	public function test_hides_ece_if_cannot_compute_taxes() {
		$this->set_up_shipping_methods();

		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'is_product',
				'allowed_items_in_cart',
				'should_show_ece_on_cart_page',
				'should_show_ece_on_checkout_page',
				'is_pay_for_order_page',
			]
		);
		$wc_stripe_ece_helper_mock->expects( $this->any() )->method( 'is_product' )->willReturn( false );
		$wc_stripe_ece_helper_mock->expects( $this->any() )->method( 'allowed_items_in_cart' )->willReturn( true );
		$wc_stripe_ece_helper_mock->expects( $this->any() )->method( 'should_show_ece_on_cart_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->expects( $this->any() )->method( 'should_show_ece_on_checkout_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->expects( $this->any() )->method( 'is_pay_for_order_page' )->willReturnOnConsecutiveCalls( true, false );
		$wc_stripe_ece_helper_mock->testmode = true;
		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}
		$original_gateways                         = WC()->payment_gateways()->payment_gateways;
		WC()->payment_gateways()->payment_gateways = [
			'stripe' => new WC_Gateway_Stripe(),
		];

		// Create virtual product and add to cart.
		$virtual_product = WC_Helper_Product::create_simple_product();
		$virtual_product->set_virtual( true );
		$virtual_product->save();

		WC()->session->init();
		WC()->cart->add_to_cart( $virtual_product->get_id(), 1 );

		// Do not hide if Pay for Order page.
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		$this->assertTrue( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Hide if cart has virtual product and tax is based on shipping or billing address.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'billing' );
		$this->assertFalse( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		update_option( 'woocommerce_tax_based_on', 'shipping' );
		$this->assertFalse( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Do not hide if taxes are not enabled.
		update_option( 'woocommerce_calc_taxes', 'no' );
		$this->assertTrue( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Do not hide if taxes are not based on customer billing or shipping address.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'base' );
		$this->assertTrue( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Do not hide if cart requires shipping.
		update_option( 'woocommerce_tax_based_on', 'billing' );
		$shippable_product = WC_Helper_Product::create_simple_product();
		WC()->cart->add_to_cart( $shippable_product->get_id(), 1 );
		$this->assertTrue( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Restore original gateways.
		WC()->payment_gateways()->payment_gateways = $original_gateways;
	}

	/**
	 * Test should_show_express_checkout_button, gateway logic.
	 */
	public function test_hides_ece_if_stripe_gateway_unavailable() {
		$this->set_up_shipping_methods();

		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'is_product',
				'allowed_items_in_cart',
				'should_show_ece_on_cart_page',
				'should_show_ece_on_checkout_page',
			]
		);
		$wc_stripe_ece_helper_mock->expects( $this->any() )->method( 'is_product' )->willReturn( false );
		$wc_stripe_ece_helper_mock->expects( $this->any() )->method( 'allowed_items_in_cart' )->willReturn( true );
		$wc_stripe_ece_helper_mock->expects( $this->any() )->method( 'should_show_ece_on_cart_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->expects( $this->any() )->method( 'should_show_ece_on_checkout_page' )->willReturn( true );
		$wc_stripe_ece_helper_mock->testmode = true;
		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}
		$original_gateways = WC()->payment_gateways()->payment_gateways;

		// Hide if 'stripe' gateway is unavailable.
		update_option( 'woocommerce_calc_taxes', 'no' );
		WC()->payment_gateways()->payment_gateways = [
			'stripe'        => new WC_Gateway_Stripe(),
			'stripe_alipay' => new WC_Gateway_Stripe_Alipay(),
		];
		$this->assertTrue( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		unset( WC()->payment_gateways()->payment_gateways['stripe'] );
		$this->assertFalse( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Restore original gateways.
		WC()->payment_gateways()->payment_gateways = $original_gateways;
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
		$wc_stripe_ece_helper = new WC_Stripe_Express_Checkout_Helper();
		$checkout_data        = $wc_stripe_ece_helper->get_checkout_data();
		$this->assertEmpty( $checkout_data['default_shipping_option'] );
	}

	/**
	 * Test for is_authentication_required().
	 */
	public function test_is_authentication_required() {
		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'is_account_creation_possible',
			]
		);
		$wc_stripe_ece_helper_mock->expects( $this->any() )
			->method( 'is_account_creation_possible' )
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
		$wc_stripe_ece_helper_mock = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[
				'has_subscription_product',
			]
		);
		$wc_stripe_ece_helper_mock->expects( $this->any() )
			->method( 'has_subscription_product' )
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
			]
		);
		$wc_stripe_ece_helper_mock2->expects( $this->any() )
			->method( 'has_subscription_product' )
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
}
