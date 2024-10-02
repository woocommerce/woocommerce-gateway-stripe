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

		$wc_stripe_connect_mock = $this->getMockBuilder( 'WC_Stripe_Connect' )
			->disableOriginalConstructor()
			->setMethods( [ 'is_connected' ] )
			->getMock();
		$wc_stripe_connect_mock->method( 'is_connected' )->willReturn( true );
		WC_Stripe::get_instance()->connect = $wc_stripe_connect_mock;
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
		update_option( 'woocommerce_tax_based_on', 'billing' );
		$this->assertFalse( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		update_option( 'woocommerce_tax_based_on', 'shipping' );
		$this->assertFalse( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Do not hide if taxes are not based on customer billing or shipping address.
		update_option( 'woocommerce_tax_based_on', 'base' );
		$this->assertTrue( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );

		// Do not hide if cart requires shipping.
		$shippable_product = WC_Helper_Product::create_simple_product();
		WC()->cart->add_to_cart( $shippable_product->get_id(), 1 );
		$this->assertTrue( $wc_stripe_ece_helper_mock->should_show_express_checkout_button() );
	}
}
