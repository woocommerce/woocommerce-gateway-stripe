<?php

use Automattic\WooCommerce\Enums\ProductType;

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Ajax_Handler.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Express_Checkout_Ajax_Handler
 *
 * WC_Stripe_Express_Checkout_Ajax_Handler_Test class.
 */
class WC_Stripe_Express_Checkout_Ajax_Handler_Test extends WP_UnitTestCase {

	/**
	 * Express checkout helper instance.
	 *
	 * @var WC_Stripe_Express_Checkout_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Ajax handler instance.
	 *
	 * @var WC_Stripe_Express_Checkout_Ajax_Handler
	 */
	private $ajax_handler;

	public function set_up() {
		parent::set_up();

		$stripe_settings                         = WC_Stripe::get_instance()->get_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		WC_Stripe::get_instance()->update_settings( $stripe_settings );

		$this->express_checkout_helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->getMock();
		$this->ajax_handler            = new WC_Stripe_Express_Checkout_Ajax_Handler( $this->express_checkout_helper );
	}

	/**
	 * Test modify_country_locale_for_express_checkout method.
	 *
	 * @dataProvider provide_test_modify_country_locale_for_express_checkout
	 */
	public function test_modify_country_locale_for_express_checkout( $is_express_context, $base_locale, $expected_state_required ) {
		$this->express_checkout_helper->expects( $this->any() )
			->method( 'is_express_checkout_context' )
			->willReturn( $is_express_context );

		$result = $this->ajax_handler->modify_country_locale_for_express_checkout( $base_locale );

		$this->assertEquals( $expected_state_required, $result['AF']['state']['required'] );
		// Countries with states should remain unchanged.
		$this->assertTrue( $result['US']['state']['required'] );
	}

	/**
	 * Data provider for test_modify_country_locale_for_express_checkout.
	 *
	 * @return array
	 */
	public function provide_test_modify_country_locale_for_express_checkout() {
		$base_locale = [
			'US' => [
				'state' => [
					'required' => true,
				],
			],
			'GB' => [
				'state' => [
					'required' => true,
				],
			],
			'AF' => [
				'state' => [
					'required' => true,
				],
			],
			'RO' => [
				'state' => [
					'required' => true,
				],
			],
		];

		return [
			'Not express checkout context - locale unchanged'                         => [
				'is_express_context'      => false,
				'input_locale'            => $base_locale,
				'expected_state_required' => true,
			],
			'Express checkout context - locale modified for countries without states' => [
				'is_express_context'      => true,
				'input_locale'            => $base_locale,
				'expected_state_required' => false,
			],
		];
	}

	/**
	 * Test ajax_add_to_cart sends wp_send_json_error payload on failure.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_add_to_cart_returns_error_for_invalid_product() {
		Ajax_Test_Helper::init_hooks();

		try {
			$security_nonce       = wp_create_nonce( 'wc-stripe-add-to-cart' );
			$_REQUEST['security'] = $security_nonce;
			$_POST['security']    = $security_nonce;
			$_POST['product_id']  = 0;
			$_POST['qty']         = 1;

			WC()->session->init();
			WC()->cart->empty_cart();

			ob_start();
			$this->ajax_handler->ajax_add_to_cart();
			$output = ob_get_clean();

			$response = json_decode( $output, true );
		} finally {
			WC()->cart->empty_cart();
			Ajax_Test_Helper::remove_hooks();
			unset( $_POST['product_id'], $_POST['qty'], $_POST['security'], $_REQUEST['security'] );
		}

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'success', $response );
		$this->assertFalse( $response['success'] );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'message', $response['data'] );
	}

	/**
	 * Test ajax_add_to_cart returns success payload for a supported simple product.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_add_to_cart_returns_success_for_simple_product() {
		Ajax_Test_Helper::init_hooks();

		$product = WC_Helper_Product::create_simple_product();

		$this->express_checkout_helper->expects( $this->once() )
			->method( 'supported_product_types' )
			->willReturn( [ ProductType::SIMPLE ] );

		$display_items = [
			'displayItems' => [
				[
					'label'  => $product->get_name(),
					'amount' => 1000,
				],
			],
			'total'        => [
				'label'  => 'Total',
				'amount' => 1000,
			],
		];

		$this->express_checkout_helper->expects( $this->once() )
			->method( 'build_display_items' )
			->willReturn( $display_items );

		try {
			$security_nonce       = wp_create_nonce( 'wc-stripe-add-to-cart' );
			$_REQUEST['security'] = $security_nonce;
			$_POST['security']    = $security_nonce;
			$_POST['product_id']  = $product->get_id();
			$_POST['qty']         = 1;

			WC()->session->init();
			WC()->cart->empty_cart();

			ob_start();
			$this->ajax_handler->ajax_add_to_cart();
			$output = ob_get_clean();
		} finally {
			WC()->cart->empty_cart();
			Ajax_Test_Helper::remove_hooks();
			unset( $_POST['product_id'], $_POST['qty'], $_POST['security'], $_REQUEST['security'] );
		}

		$response = json_decode( $output, true );

		$this->assertIsArray( $response );
		$this->assertSame( 'success', $response['result'] );
		$this->assertSame( $display_items['displayItems'], $response['displayItems'] );
		$this->assertSame( $display_items['total'], $response['total'] );
	}

	/**
	 * Test ajax_get_selected_product_data taxes the full computed line total (quantity x price + add-ons),
	 * and shows no tax for a non-taxable product.
	 *
	 * The preview must tax the same amount it shows as the line item, matching how WooCommerce taxes
	 * quantity and add-on cost folded into the cart-item price; a tax-exempt product must show no tax.
	 *
	 * @dataProvider provide_full_line_tax_scenarios
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_get_selected_product_data_taxes_full_line( $qty, $addon_value, $taxable, $expected_tax_base, $stubbed_tax, $expected_item_amount, $expected_tax_amount, $expected_total_amount ) {
		Ajax_Test_Helper::init_hooks();
		update_option( 'woocommerce_calc_taxes', 'yes' ); // WC_Product::is_taxable() requires taxes enabled.

		$product = WC_Helper_Product::create_simple_product();
		if ( ! $taxable ) {
			$product->set_tax_status( 'none' );
			$product->save();
		}

		$this->express_checkout_helper->method( 'is_invalid_subscription_product' )->willReturn( false );
		$this->express_checkout_helper->method( 'get_product_price' )->willReturn( 10.0 );
		$this->express_checkout_helper->method( 'get_total_label' )->willReturn( 'Total' );

		if ( $taxable ) {
			$this->express_checkout_helper->expects( $this->once() )
				->method( 'get_taxes_like_cart' )
				->with( $this->anything(), $expected_tax_base )
				->willReturn( [ $stubbed_tax ] );
		} else {
			$this->express_checkout_helper->expects( $this->never() )->method( 'get_taxes_like_cart' );
		}

		try {
			$security_nonce       = wp_create_nonce( 'wc-stripe-get-selected-product-data' );
			$_REQUEST['security'] = $security_nonce;
			$_POST['security']    = $security_nonce;
			$_POST['product_id']  = $product->get_id();
			$_POST['qty']         = $qty;
			$_POST['addon_value'] = $addon_value;

			WC()->session->init();

			ob_start();
			$this->ajax_handler->ajax_get_selected_product_data();
			$output = ob_get_clean();

			$response = json_decode( $output, true );
		} finally {
			Ajax_Test_Helper::remove_hooks();
			unset( $_POST['product_id'], $_POST['qty'], $_POST['addon_value'], $_POST['security'], $_REQUEST['security'] );
		}

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'displayItems', $response, 'response: ' . wp_json_encode( $response ) );
		$this->assertSame( $expected_item_amount, $response['displayItems'][0]['amount'] );

		// Match the tax line by label rather than position, so this is robust to item ordering and
		// can assert a non-taxable product shows no tax line at all.
		$tax_items = array_values(
			array_filter(
				$response['displayItems'],
				static function ( $item ) {
					return isset( $item['label'] ) && __( 'Tax', 'woocommerce-gateway-stripe' ) === $item['label'];
				}
			)
		);
		if ( null !== $expected_tax_amount ) {
			$this->assertCount( 1, $tax_items, 'Exactly one tax line should be shown.' );
			$this->assertSame( $expected_tax_amount, $tax_items[0]['amount'] );
		} else {
			$this->assertCount( 0, $tax_items, 'A non-taxable product must show no tax line.' );
		}
		$this->assertSame( $expected_total_amount, $response['total']['amount'] );
	}

	/**
	 * Data provider for test_ajax_get_selected_product_data_taxes_full_line.
	 *
	 * Product price is stubbed at $10. Columns: quantity, posted add-on value, whether the product is
	 * taxable, expected tax base passed to get_taxes_like_cart() (null when it is skipped), the stubbed
	 * tax it returns (null when skipped), then expected line-item / tax / total amounts in minor units.
	 * A null expected tax means no tax line should be shown.
	 *
	 * @return array
	 */
	public function provide_full_line_tax_scenarios() {
		return [
			'multiple quantity, no add-on'        => [ 3, 0, true, 30.0, 3.0, 3000, 300, 3300 ],
			'single quantity with taxable add-on' => [ 1, 5, true, 15.0, 1.5, 1500, 150, 1650 ],
			'non-taxable product shows no tax'    => [ 1, 5, false, null, null, 1500, null, 1500 ],
		];
	}

	/**
	 * Test ajax_add_to_cart preserves decimal quantities on stores that allow them.
	 *
	 * Stores that sell by fractional units (e.g. fabric by the metre) enable decimal
	 * quantities by filtering `woocommerce_stock_amount`. The express checkout
	 * add-to-cart handler must route the posted quantity through wc_stock_amount()
	 * so those fractional values are kept rather than truncated to an integer.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_add_to_cart_preserves_decimal_quantity() {
		Ajax_Test_Helper::init_hooks();

		// Simulate a decimal-quantity plugin: swap the default integer stock-amount
		// filter for a float one. This mirrors what such plugins actually do
		// (remove_filter intval / add_filter floatval).
		remove_filter( 'woocommerce_stock_amount', 'intval' );
		add_filter( 'woocommerce_stock_amount', 'floatval' );

		$product = WC_Helper_Product::create_simple_product();

		$this->express_checkout_helper->expects( $this->once() )
			->method( 'supported_product_types' )
			->willReturn( [ ProductType::SIMPLE ] );

		$this->express_checkout_helper->expects( $this->once() )
			->method( 'build_display_items' )
			->willReturn(
				[
					'displayItems' => [],
					'total'        => [
						'label'  => 'Total',
						'amount' => 0,
					],
				]
			);

		$cart_quantity = null;
		$cart_count    = 0;
		$output        = '';

		try {
			$security_nonce       = wp_create_nonce( 'wc-stripe-add-to-cart' );
			$_REQUEST['security'] = $security_nonce;
			$_POST['security']    = $security_nonce;
			$_POST['product_id']  = $product->get_id();
			$_POST['qty']         = '0.25';

			WC()->session->init();
			WC()->cart->empty_cart();

			ob_start();
			$this->ajax_handler->ajax_add_to_cart();
			$output = ob_get_clean();

			$cart_items    = WC()->cart->get_cart();
			$cart_count    = count( $cart_items );
			$first_item    = reset( $cart_items );
			$cart_quantity = $first_item ? $first_item['quantity'] : null;
		} finally {
			remove_filter( 'woocommerce_stock_amount', 'floatval' );
			add_filter( 'woocommerce_stock_amount', 'intval' );
			WC()->cart->empty_cart();
			Ajax_Test_Helper::remove_hooks();
			unset( $_POST['product_id'], $_POST['qty'], $_POST['security'], $_REQUEST['security'] );
		}

		$this->assertSame( 1, $cart_count, 'Exactly one item should be added to the cart. Handler output: ' . $output );
		$this->assertEqualsWithDelta( 0.25, $cart_quantity, 0.0001, 'Decimal quantity should be preserved, not truncated to an integer.' );
	}

	/**
	 * Test ajax_add_to_cart does not add a zero/negative quantity. wc_stock_amount()
	 * (unlike the previous absint()) can return a non-positive value, so the handler
	 * clamps it to zero, which WooCommerce then declines to add to the cart.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_add_to_cart_does_not_add_non_positive_quantity() {
		Ajax_Test_Helper::init_hooks();

		$product = WC_Helper_Product::create_simple_product();

		$this->express_checkout_helper->method( 'supported_product_types' )
			->willReturn( [ ProductType::SIMPLE ] );
		$this->express_checkout_helper->method( 'build_display_items' )
			->willReturn(
				[
					'displayItems' => [],
					'total'        => [
						'label'  => 'Total',
						'amount' => 0,
					],
				]
			);

		$cart_count = null;

		try {
			$security_nonce       = wp_create_nonce( 'wc-stripe-add-to-cart' );
			$_REQUEST['security'] = $security_nonce;
			$_POST['security']    = $security_nonce;
			$_POST['product_id']  = $product->get_id();
			$_POST['qty']         = '-1';

			WC()->session->init();
			WC()->cart->empty_cart();

			ob_start();
			$this->ajax_handler->ajax_add_to_cart();
			ob_get_clean();

			$cart_count = WC()->cart->get_cart_contents_count();
		} finally {
			WC()->cart->empty_cart();
			Ajax_Test_Helper::remove_hooks();
			unset( $_POST['product_id'], $_POST['qty'], $_POST['security'], $_REQUEST['security'] );
		}

		$this->assertSame( 0, $cart_count, 'A non-positive quantity should be clamped to zero and not added to the cart.' );
	}

	/**
	 * Test ajax_get_selected_product_data keeps a decimal quantity in its price math.
	 *
	 * This handler feeds the express-checkout button preview ($total = $qty * $price),
	 * so a truncated quantity would show the wrong amount.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_get_selected_product_data_preserves_decimal_quantity() {
		Ajax_Test_Helper::init_hooks();

		// Simulate a decimal-quantity plugin (see note in the add-to-cart test above).
		remove_filter( 'woocommerce_stock_amount', 'intval' );
		add_filter( 'woocommerce_stock_amount', 'floatval' );

		$product = WC_Helper_Product::create_simple_product();

		$this->express_checkout_helper->method( 'is_invalid_subscription_product' )->willReturn( false );
		$this->express_checkout_helper->method( 'get_product_price' )->willReturn( 10.0 );
		$this->express_checkout_helper->method( 'get_taxes_like_cart' )->willReturn( [] );
		$this->express_checkout_helper->method( 'get_total_label' )->willReturn( 'Total' );

		try {
			$security_nonce       = wp_create_nonce( 'wc-stripe-get-selected-product-data' );
			$_REQUEST['security'] = $security_nonce;
			$_POST['security']    = $security_nonce;
			$_POST['product_id']  = $product->get_id();
			$_POST['qty']         = '0.25';

			WC()->session->init();

			ob_start();
			$this->ajax_handler->ajax_get_selected_product_data();
			$output = ob_get_clean();

			$response = json_decode( $output, true );
		} finally {
			remove_filter( 'woocommerce_stock_amount', 'floatval' );
			add_filter( 'woocommerce_stock_amount', 'intval' );
			Ajax_Test_Helper::remove_hooks();
			unset( $_POST['product_id'], $_POST['qty'], $_POST['security'], $_REQUEST['security'] );
		}

		// 0.25 x $10 = $2.50 = 250 minor units; a truncated quantity would yield 0.
		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'displayItems', $response, 'response: ' . wp_json_encode( $response ) );
		$this->assertSame( 250, $response['displayItems'][0]['amount'] );
	}

	/**
	 * Test ajax_get_selected_product_data clamps a zero/negative quantity to zero, so the
	 * preview total can never go negative.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_get_selected_product_data_clamps_non_positive_quantity() {
		Ajax_Test_Helper::init_hooks();

		$product = WC_Helper_Product::create_simple_product();

		$this->express_checkout_helper->method( 'is_invalid_subscription_product' )->willReturn( false );
		$this->express_checkout_helper->method( 'get_product_price' )->willReturn( 10.0 );
		$this->express_checkout_helper->method( 'get_taxes_like_cart' )->willReturn( [] );
		$this->express_checkout_helper->method( 'get_total_label' )->willReturn( 'Total' );

		try {
			$security_nonce       = wp_create_nonce( 'wc-stripe-get-selected-product-data' );
			$_REQUEST['security'] = $security_nonce;
			$_POST['security']    = $security_nonce;
			$_POST['product_id']  = $product->get_id();
			$_POST['qty']         = '-1';

			WC()->session->init();

			ob_start();
			$this->ajax_handler->ajax_get_selected_product_data();
			$output = ob_get_clean();

			$response = json_decode( $output, true );
		} finally {
			Ajax_Test_Helper::remove_hooks();
			unset( $_POST['product_id'], $_POST['qty'], $_POST['security'], $_REQUEST['security'] );
		}

		// -1 is clamped to 0, so the preview shows $0 rather than a negative amount.
		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'displayItems', $response, 'response: ' . wp_json_encode( $response ) );
		$this->assertSame( 0, $response['displayItems'][0]['amount'] );
		$this->assertSame( 0, $response['total']['amount'] );
	}

	/**
	 * Builds an ajax handler backed by a helper that only stubs
	 * is_express_checkout_context(), so the real state/postcode normalization runs.
	 *
	 * @param bool $is_express_context Value is_express_checkout_context() should return.
	 *
	 * @return WC_Stripe_Express_Checkout_Ajax_Handler
	 */
	private function get_ajax_handler_with_real_normalization( $is_express_context ) {
		$helper = $this->createPartialMock(
			WC_Stripe_Express_Checkout_Helper::class,
			[ 'is_express_checkout_context' ]
		);
		$helper->method( 'is_express_checkout_context' )->willReturn( $is_express_context );

		return new WC_Stripe_Express_Checkout_Ajax_Handler( $helper );
	}

	/**
	 * The update-customer route normalizes a redacted Apple Pay / Google Pay
	 * shipping address (long state name + truncated postcode) so the Store API
	 * doesn't reject it, and registers the postcode-validation bypass.
	 */
	public function test_tokenized_cart_normalizes_redacted_shipping_address_on_update_customer() {
		$ajax_handler = $this->get_ajax_handler_with_real_normalization( true );

		$request = new WP_REST_Request( 'POST', '/wc/store/v1/cart/update-customer' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => 'CA',
				'state'    => 'Ontario',
				'postcode' => 'E2L',
				'city'     => 'Toronto',
			]
		);

		try {
			$result = $ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

			$shipping = $request->get_param( 'shipping_address' );

			$this->assertNull( $result );
			$this->assertSame( 'ON', $shipping['state'] );
			// Padded with `0`, not `*`, so it survives WC_Validation::is_postcode()'s char check.
			$this->assertSame( 'E2L000', $shipping['postcode'] );
			$this->assertSame(
				10,
				has_filter( 'woocommerce_validate_postcode', [ $ajax_handler, 'maybe_skip_postcode_validation' ] )
			);
			// Root-cause guard: a `*`-padded value would fail before the bypass filter runs.
			$this->assertTrue( WC_Validation::is_postcode( $shipping['postcode'], 'CA' ) );
		} finally {
			remove_filter( 'woocommerce_validate_postcode', [ $ajax_handler, 'maybe_skip_postcode_validation' ], 10 );
		}
	}

	/**
	 * Outside an express checkout context the request is left untouched and the
	 * postcode-validation bypass is not registered.
	 */
	public function test_tokenized_cart_leaves_request_untouched_when_not_express_context() {
		$ajax_handler = $this->get_ajax_handler_with_real_normalization( false );

		$address = [
			'country'  => 'CA',
			'state'    => 'Ontario',
			'postcode' => 'E2L',
		];
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/cart/update-customer' );
		$request->set_param( 'shipping_address', $address );

		$result = $ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$this->assertNull( $result );
		$this->assertSame( $address, $request->get_param( 'shipping_address' ) );
		$this->assertFalse(
			has_filter( 'woocommerce_validate_postcode', [ $ajax_handler, 'maybe_skip_postcode_validation' ] )
		);
	}

	/**
	 * On routes other than update-customer the postcode is not padded and the
	 * validation bypass is not registered (the location is already set by then).
	 */
	public function test_tokenized_cart_does_not_pad_postcode_on_other_routes() {
		$ajax_handler = $this->get_ajax_handler_with_real_normalization( true );

		$request = new WP_REST_Request( 'POST', '/wc/store/v1/cart/select-shipping-rate' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => 'CA',
				'state'    => 'Ontario',
				'postcode' => 'E2L',
			]
		);

		$ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping = $request->get_param( 'shipping_address' );

		// State is still normalized, but the redacted postcode is left as-is.
		$this->assertSame( 'ON', $shipping['state'] );
		$this->assertSame( 'E2L', $shipping['postcode'] );
		$this->assertFalse(
			has_filter( 'woocommerce_validate_postcode', [ $ajax_handler, 'maybe_skip_postcode_validation' ] )
		);
	}

	/**
	 * Test maybe_skip_postcode_validation.
	 *
	 * @param bool   $valid    Incoming validity.
	 * @param string $postcode Postcode under validation.
	 * @param string $country  Country code.
	 * @param bool   $expected Expected validity.
	 *
	 * @dataProvider provide_test_maybe_skip_postcode_validation
	 */
	public function test_maybe_skip_postcode_validation( $valid, $postcode, $country, $expected ) {
		$this->assertSame(
			$expected,
			$this->ajax_handler->maybe_skip_postcode_validation( $valid, $postcode, $country )
		);
	}

	/**
	 * Data provider for test_maybe_skip_postcode_validation.
	 *
	 * @return array
	 */
	public function provide_test_maybe_skip_postcode_validation() {
		return [
			'CA redacted padded postcode bypasses validation'   => [
				'valid'    => false,
				'postcode' => 'E2L000',
				'country'  => 'CA',
				'expected' => true,
			],
			'GB redacted padded postcode bypasses validation'   => [
				'valid'    => false,
				'postcode' => 'N1 000',
				'country'  => 'GB',
				'expected' => true,
			],
			'CA real postcode keeps original (invalid) result'  => [
				'valid'    => false,
				'postcode' => 'K1A 0B1',
				'country'  => 'CA',
				'expected' => false,
			],
			'CA real postcode keeps original (valid) result'    => [
				'valid'    => true,
				'postcode' => 'K1A 0B1',
				'country'  => 'CA',
				'expected' => true,
			],
			'Other country is unaffected even when ending in 0' => [
				'valid'    => false,
				'postcode' => '12340',
				'country'  => 'US',
				'expected' => false,
			],
		];
	}
}
