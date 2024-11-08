<?php

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Helper.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Express_Checkout_Helper
 */

/**
 * WC_Stripe_Express_Checkout_Helper class.
 */
class WC_Stripe_Express_Checkout_Helper_Test extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		// Add a shipping zone.
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Worldwide' );
		$zone->set_zone_order( 1 );
		$zone->save();

		$flat_rate_id    = $zone->add_shipping_method( 'flat_rate' );
		$method          = WC_Shipping_Zones::get_shipping_method( $flat_rate_id );
		$option_key      = $method->get_instance_option_key();
		$options['cost'] = '5';
		update_option( $option_key, $options );
	}

	/**
	 * Test should_show_express_checkout_button, tax logic.
	 */
	public function test_hides_ece_if_cannot_compute_taxes() {
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

		// Create virtual product and add to cart.
		$virtual_product = WC_Helper_Product::create_simple_product();
		$virtual_product->set_virtual( true );
		$virtual_product->save();

		WC()->session->init();
		WC()->cart->add_to_cart( $virtual_product->get_id(), 1 );

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
}
