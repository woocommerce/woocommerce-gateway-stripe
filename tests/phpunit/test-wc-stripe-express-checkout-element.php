<?php

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Element.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Express_Checkout_Element
 * /

/**
 * Class WC_Stripe_Express_Checkout_Element_Test
 */
class WC_Stripe_Express_Checkout_Element_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_Express_Checkout_Element
	 */
	private $element;

	/**
	 * Setup test.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$ajax_handler = $this->getMockBuilder( WC_Stripe_Express_Checkout_Ajax_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->getMock();

		$this->element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );
	}

	/**
	 * Test for `get_login_redirect_url`.
	 *
	 * @return void
	 */
	public function test_get_login_redirect_url() {
		$this->element->get_login_redirect_url( 'http://example.com/redirect' );

		$this->assertSame( '', wp_unslash( $_COOKIE['wc_stripe_express_checkout_redirect_url'] ) );
	}

	/**
	 * Test for `javascript_params`.
	 *
	 * @return void
	 */
	public function test_javascript_params() {
		$actual = $this->element->javascript_params();
		$this->assertSame( [], $actual );
	}

	/**
	 * Test for `scripts`.
	 *
	 * @return void
	 */
	public function test_scripts() {
		$this->element->scripts();
	}

	/**
	 * Test for `add_order_meta`.
	 *
	 * @return void
	 */
	public function test_add_order_meta() {
		$order = wc_create_order();

		// Apple Pay.
		$_POST['express_checkout_type'] = 'apple_pay';
		$this->element->add_order_meta( $order->get_id(), [] );
		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'Apple Pay (Stripe)', $order->get_payment_method_title() );

		// Google Pay.
		$_POST['express_checkout_type'] = 'google_pay';
		$this->element->add_order_meta( $order->get_id(), [] );
		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'Google Pay (Stripe)', $order->get_payment_method_title() );
	}

	/**
	 * Test for `filter_gateway_title`.
	 *
	 * @return void
	 */
	public function test_filter_gateway_title() {
		$actual = $this->element->filter_gateway_title( 'test', 'stripe' );
		$this->assertSame( 'test', $actual );
	}

	/**
	 * Test for `display_express_checkout_button_html`.
	 *
	 * @return void
	 */
	public function test_display_express_checkout_button_html() {
		ob_start();

		$this->element->display_express_checkout_button_html();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-express-checkout-element"%a', $output );
	}

	/**
	 * Test for `display_express_checkout_button_separator_html`.
	 *
	 * @return void
	 */
	public function test_display_express_checkout_button_separator_html() {
		ob_start();

		$this->element->display_express_checkout_button_separator_html();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-express-checkout-button-separator"%a', $output );
	}
}
