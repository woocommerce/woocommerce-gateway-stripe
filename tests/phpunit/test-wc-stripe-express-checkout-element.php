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
	public function set_up() {
		parent::set_up();

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
		$actual = $this->element->get_login_redirect_url( 'http://example.com/redirect' );

		$this->assertSame( 'http://example.com/redirect', $actual );
	}

	/**
	 * Test for `javascript_params`.
	 *
	 * @return void
	 */
	public function test_javascript_params() {
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_123';

		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$ajax_handler = $this->getMockBuilder( WC_Stripe_Express_Checkout_Ajax_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->getMock();

		$element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );

		$actual = $element->javascript_params();

		$this->assertSame( $stripe_settings['test_publishable_key'], $actual['stripe']['publishable_key'] );
	}

	/**
	 * Test for `scripts`.
	 *
	 * @return void
	 * @dataProvider provide_test_scripts
	 */
	public function test_scripts( $page_supported, $should_show, $expected ) {
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_123';

		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$ajax_handler = $this->getMockBuilder( WC_Stripe_Express_Checkout_Ajax_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->setMethods( [ 'is_page_supported', 'should_show_express_checkout_button' ] )
			->getMock();

		$helper->expects( $this->any() )
			->method( 'is_page_supported' )
			->willReturn( $page_supported );

		$helper->expects( $this->any() )
			->method( 'should_show_express_checkout_button' )
			->willReturn( $should_show );

		$element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );

		$element->scripts();
		$actual = wp_script_is( 'wc_stripe_express_checkout', 'enqueued' );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_scripts`.
	 *
	 * @return string[]
	 */
	public function provide_test_scripts() {
		return [
			'page not supported'    => [
				'page supported' => false,
				'should show'    => false,
				'expected'       => false,
			],
			'should not show'       => [
				'page supported' => true,
				'should show'    => false,
				'expected'       => false,
			],
			'successfully rendered' => [
				'page supported' => true,
				'should show'    => true,
				'expected'       => true,
			],
		];
	}

	/**
	 * Test for `add_order_meta`.
	 *
	 * @param string $checkout_type The checkout type.
	 * @param string $expected      The expected payment method title.
	 * @return void
	 * @dataProvider provide_test_add_order_meta
	 */
	public function test_add_order_meta( $checkout_type, $expected ) {
		$order = wc_create_order();

		$_POST['express_checkout_type'] = $checkout_type;
		$_POST['payment_method']        = 'stripe';

		$this->element->add_order_meta( $order->get_id(), [] );
		$order = wc_get_order( $order->get_id() );

		$this->assertSame( $expected, $order->get_payment_method_title() );
	}

	/**
	 * Provider for `test_add_order_meta`.
	 *
	 * @return array[]
	 */
	public function provide_test_add_order_meta() {
		return [
			'apple pay'  => [
				'checkout type' => 'apple_pay',
				'expected'      => 'Apple Pay (Stripe)',
			],
			'google pay' => [
				'checkout type' => 'google_pay',
				'expected'      => 'Google Pay (Stripe)',
			],
		];
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
	 * @param bool $stripe_is_enabled Whether Stripe is enabled.
	 * @param bool $page_supported     Whether the current page is supported.
	 * @param bool $should_show        Whether the button should be shown.
	 * @param string $expected         The expected output.
	 * @return void
	 * @dataProvider provide_test_display_express_checkout_button_html
	 */
	public function test_display_express_checkout_button_html( $stripe_is_enabled, $page_supported, $should_show, $expected ) {
		if ( $stripe_is_enabled ) {
			add_filter(
				'woocommerce_available_payment_gateways',
				function () use ( $stripe_is_enabled ) {
					return [
						'stripe' => new class() extends WC_Payment_Gateway {
							public function __construct() {
								$this->id = 'stripe';
							}
						},
					];
				}
			);
		}

		$ajax_handler = $this->getMockBuilder( WC_Stripe_Express_Checkout_Ajax_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->setMethods( [ 'is_page_supported', 'should_show_express_checkout_button' ] )
			->getMock();

		$helper->expects( $this->any() )
			->method( 'is_page_supported' )
			->willReturn( $page_supported );

		$helper->expects( $this->any() )
			->method( 'should_show_express_checkout_button' )
			->willReturn( $should_show );

		$element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );

		ob_start();

		$element->display_express_checkout_button_html();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( $expected, $output );
	}

	/**
	 * Provider for `test_display_express_checkout_button_html`.
	 *
	 * @return array
	 */
	public function provide_test_display_express_checkout_button_html() {
		return [
			'stripe disabled'     => [
				'stripe is enabled' => false,
				'page supported'    => false,
				'should show ECE'   => false,
				'expected'          => '',
			],
			'page not supported'  => [
				'stripe is enabled' => true,
				'page supported'    => false,
				'should show ECE'   => false,
				'expected'          => '',
			],
			'should not show ECE' => [
				'stripe is enabled' => true,
				'page supported'    => true,
				'should show ECE'   => false,
				'expected'          => '',
			],
			'render successfully' => [
				'stripe is enabled' => true,
				'page supported'    => true,
				'should show ECE'   => true,
				'expected'          => '%aid="wc-stripe-express-checkout-element"%a',
			],
		];
	}

	/**
	 * Test for `display_express_checkout_button_separator_html`.
	 *
	 * @param bool $is_checkout       Whether the current page is checkout.
	 * @param bool $is_cart           Whether the current page is cart.
	 * @param bool $is_order_pay      Whether the current page is order pay.
	 * @param string $button_location The location of the button.
	 * @param string $expected        The expected output.
	 * @return void
	 * @dataProvider provide_test_display_express_checkout_button_separator_html
	 */
	public function test_display_express_checkout_button_separator_html( $is_checkout, $is_cart, $is_order_pay, $button_location, $expected ) {
		add_filter(
			'woocommerce_is_checkout',
			function () use ( $is_checkout ) {
				return $is_checkout;
			}
		);

		if ( $is_cart ) {
			\Automattic\Jetpack\Constants::set_constant( 'WOOCOMMERCE_CART', true );
		} else {
			\Automattic\Jetpack\Constants::clear_single_constant( 'WOOCOMMERCE_CART' );
		}

		add_filter(
			'woocommerce_get_query_vars',
			function () use ( $is_order_pay ) {
				if ( ! $is_order_pay ) {
					return [];
				}

				return [
					'is_order_pay' => $is_order_pay,
				];
			}
		);

		$ajax_handler = $this->getMockBuilder( WC_Stripe_Express_Checkout_Ajax_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_button_locations' ] )
			->getMock();

		$helper->expects( $this->any() )
			->method( 'get_button_locations' )
			->willReturn( [ $button_location ] );

		$element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );

		ob_start();
		$element->display_express_checkout_button_separator_html();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( $expected, $output );
	}

	/**
	 * Provider for `test_display_express_checkout_button_separator_html`.
	 *
	 * @return array
	 */
	public function provide_test_display_express_checkout_button_separator_html() {
		return [
			'not checkout, not cart, not order pay' => [
				'is checkout'     => false,
				'is cart'         => false,
				'is order pay'    => false,
				'button location' => null,
				'expected'        => '',
			],
			'checkout, button not in checkout'      => [
				'is checkout'     => true,
				'is cart'         => false,
				'is order pay'    => false,
				'button location' => 'cart',
				'expected'        => '',
			],
			'cart, button not in cart'              => [
				'is checkout'     => false,
				'is cart'         => true,
				'is order pay'    => false,
				'button location' => 'checkout',
				'expected'        => '',
			],
			'checkout, button in checkout'          => [
				'is checkout'     => true,
				'is cart'         => false,
				'is order pay'    => false,
				'button location' => 'checkout',
				'expected'        => '%aid="wc-stripe-express-checkout-button-separator"%a',
			],
		];
	}

	/**
	 * Test for `add_order_attribution_data`.
	 *
	 * @return void
	 */
	public function test_add_order_attribution_data() {
		$ajax_handler = $this->getMockBuilder( WC_Stripe_Express_Checkout_Ajax_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->getMock();

		$element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );

		ob_start();
		$element->add_order_attribution_inputs();
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-express-checkout__order-attribution-inputs"%a', $output );
	}
}
