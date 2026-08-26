<?php

/**
 * These tests make assertions against class WC_Stripe_Express_Checkout_Element.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Express_Checkout_Element
 *
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

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->setConstructorArgs( [ $gateway ] )
			->setMethods( [ 'is_page_supported', 'should_show_express_checkout_button' ] )
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

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->setConstructorArgs( [ $gateway ] )
			->getMock();

		$element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );

		$actual = $element->javascript_params();

		$this->assertSame( $stripe_settings['test_publishable_key'], $actual['stripe']['publishable_key'] );
	}

	/**
	 * Only the Store API nonces may be minted at render time; the wc-ajax
	 * nonces are served on demand so cached pages can't embed expired copies.
	 *
	 * @return void
	 */
	public function test_javascript_params_only_mints_store_api_nonces() {
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_123';

		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$ajax_handler = $this->getMockBuilder( WC_Stripe_Express_Checkout_Ajax_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->setConstructorArgs( [ $gateway ] )
			->getMock();

		$element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );

		$nonces = $element->javascript_params()['nonce'];

		$this->assertSame( [ 'wc_store_api', 'wc_store_api_express_checkout' ], array_keys( $nonces ) );
		$this->assertNotFalse( wp_verify_nonce( $nonces['wc_store_api'], 'wc_store_api' ) );
		$this->assertNotFalse( wp_verify_nonce( $nonces['wc_store_api_express_checkout'], 'wc_store_api_express_checkout' ) );
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

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->setConstructorArgs( [ $gateway ] )
			->setMethods( [ 'is_page_supported', 'should_show_express_checkout_button' ] )
			->getMock();

		$helper->method( 'is_page_supported' )
			->willReturn( $page_supported );

		$helper->method( 'should_show_express_checkout_button' )
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
	 * Build an element with helper mocks for both guards.
	 *
	 * @param bool $page_supported Return value for is_page_supported().
	 * @param bool $should_show    Return value for should_show_express_checkout_button().
	 *
	 * @return WC_Stripe_Express_Checkout_Element
	 */
	private function build_element_with_guards( $page_supported, $should_show ) {
		$ajax_handler = $this->getMockBuilder( WC_Stripe_Express_Checkout_Ajax_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->setConstructorArgs( [ $gateway ] )
			->setMethods( [ 'is_page_supported', 'should_show_express_checkout_button' ] )
			->getMock();

		$helper->method( 'is_page_supported' )
			->willReturn( $page_supported );

		$helper->method( 'should_show_express_checkout_button' )
			->willReturn( $should_show );

		return new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );
	}

	/**
	 * Test that `add_resource_hints` appends Stripe preconnect entries when ECE will render.
	 *
	 * @return void
	 */
	public function test_add_resource_hints_appends_preconnect_when_ece_enabled() {
		$element = $this->build_element_with_guards( true, true );

		$urls = $element->add_resource_hints( [ 'https://example.com' ], 'preconnect' );

		$hrefs = array_map(
			static function ( $entry ) {
				return is_array( $entry ) ? $entry['href'] : $entry;
			},
			$urls
		);

		$this->assertContains( 'https://example.com', $hrefs );
		$this->assertContains( 'https://js.stripe.com', $hrefs );
		$this->assertContains( 'https://m.stripe.network', $hrefs );
		$this->assertContains( 'https://q.stripe.com', $hrefs );
		$this->assertContains( 'https://b.stripecdn.com', $hrefs );

		// js.stripe.com and m.stripe.network must declare crossorigin so the preconnected
		// TLS session is reused by the script/iframe fetch that follows.
		foreach ( $urls as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( in_array( $entry['href'], [ 'https://js.stripe.com', 'https://m.stripe.network', 'https://b.stripecdn.com' ], true ) ) {
				$this->assertSame( 'anonymous', $entry['crossorigin'] );
			}

			if ( 'https://q.stripe.com' === $entry['href'] ) {
				$this->assertArrayNotHasKey( 'crossorigin', $entry );
			}
		}
	}

	/**
	 * Test that `add_resource_hints` is a no-op when the page or guard rules block ECE.
	 *
	 * @param bool $page_supported Return value for is_page_supported().
	 * @param bool $should_show    Return value for should_show_express_checkout_button().
	 *
	 * @return void
	 * @dataProvider provide_test_add_resource_hints_skips_when_unavailable
	 */
	public function test_add_resource_hints_skips_when_unavailable( $page_supported, $should_show ) {
		$element = $this->build_element_with_guards( $page_supported, $should_show );

		$input = [ 'https://example.com' ];
		$urls  = $element->add_resource_hints( $input, 'preconnect' );

		$this->assertSame( $input, $urls );
	}

	/**
	 * Provider for `test_add_resource_hints_skips_when_unavailable`.
	 *
	 * @return array[]
	 */
	public function provide_test_add_resource_hints_skips_when_unavailable() {
		return [
			'page not supported' => [
				'page supported' => false,
				'should show'    => true,
			],
			'guards say no'      => [
				'page supported' => true,
				'should show'    => false,
			],
			'both say no'        => [
				'page supported' => false,
				'should show'    => false,
			],
		];
	}

	/**
	 * Test that `add_resource_hints` only touches the `preconnect` relation type.
	 *
	 * @param string $relation_type The relation type to pass.
	 *
	 * @return void
	 * @dataProvider provide_test_add_resource_hints_ignores_non_preconnect_relations
	 */
	public function test_add_resource_hints_ignores_non_preconnect_relations( $relation_type ) {
		$element = $this->build_element_with_guards( true, true );

		$input = [ 'https://example.com' ];
		$urls  = $element->add_resource_hints( $input, $relation_type );

		$this->assertSame( $input, $urls );
	}

	/**
	 * Provider for `test_add_resource_hints_ignores_non_preconnect_relations`.
	 *
	 * @return array[]
	 */
	public function provide_test_add_resource_hints_ignores_non_preconnect_relations() {
		return [
			'dns-prefetch' => [ 'dns-prefetch' ],
			'prefetch'     => [ 'prefetch' ],
			'prerender'    => [ 'prerender' ],
		];
	}

	/**
	 * Test that `add_preload_resources` appends the ECE bundle entry only when ECE will render.
	 *
	 * @param bool $page_supported Return value for is_page_supported().
	 * @param bool $should_show    Return value for should_show_express_checkout_button().
	 * @param bool $expect_entry   Whether a preload entry should be appended.
	 *
	 * @return void
	 * @dataProvider provide_test_add_preload_resources
	 */
	public function test_add_preload_resources( $page_supported, $should_show, $expect_entry ) {
		$element = $this->build_element_with_guards( $page_supported, $should_show );

		$existing = [
			[
				'href' => 'https://example.com/other.js',
				'as'   => 'script',
			],
		];
		$output   = $element->add_preload_resources( $existing );

		// Pre-existing entries must be preserved regardless of the guard outcome.
		$this->assertSame( $existing[0], $output[0] );

		if ( $expect_entry ) {
			$this->assertCount( 2, $output );
			$bundle_entry = $output[1];
			$this->assertSame( 'script', $bundle_entry['as'] );
			$this->assertMatchesRegularExpression(
				'#/build/express-checkout\.js\?ver=[^&]+$#',
				$bundle_entry['href']
			);
		} else {
			$this->assertSame( $existing, $output );
		}
	}

	/**
	 * Provider for `test_add_preload_resources`.
	 *
	 * @return array[]
	 */
	public function provide_test_add_preload_resources() {
		return [
			'page not supported'    => [
				'page supported' => false,
				'should show'    => true,
				'expect entry'   => false,
			],
			'should not show'       => [
				'page supported' => true,
				'should show'    => false,
				'expect entry'   => false,
			],
			'successfully rendered' => [
				'page supported' => true,
				'should show'    => true,
				'expect entry'   => true,
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
	 * Test for `update_subscription_payment_method_title`.
	 *
	 * @param string $checkout_type The checkout type.
	 * @param string $expected      The expected payment method title.
	 * @return void
	 * @dataProvider provide_test_update_subscription_payment_method_title
	 */
	public function test_update_subscription_payment_method_title( $checkout_type, $expected ) {
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'stripe' );
		$subscription->set_payment_method_title( 'Stripe' );
		$subscription->save();

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $subscription ) {
				return (int) $id === $subscription->get_id() ? $subscription : false;
			}
		);

		$_GET['change_payment_method']  = $subscription->get_id();
		$_POST['express_checkout_type'] = $checkout_type;

		$this->element->update_subscription_payment_method_title();

		$this->assertSame( $expected, $subscription->get_payment_method_title() );

		$this->assertContains( "Payment method updated to {$expected}.", $subscription->get_captured_notes() );

		unset( $_GET['change_payment_method'], $_POST['express_checkout_type'] );
		WC_Subscriptions::$wcs_get_subscription = null;
	}

	/**
	 * Provider for `test_update_subscription_payment_method_title`.
	 *
	 * @return array[]
	 */
	public function provide_test_update_subscription_payment_method_title() {
		return [
			'apple pay'  => [
				'checkout type' => 'apple_pay',
				'expected'      => 'Apple Pay (Stripe)',
			],
			'google pay' => [
				'checkout type' => 'google_pay',
				'expected'      => 'Google Pay (Stripe)',
			],
			'amazon pay' => [
				'checkout type' => 'amazon_pay',
				'expected'      => 'Amazon Pay (Stripe)',
			],
			'link'       => [
				'checkout type' => 'link',
				'expected'      => 'Link',
			],
		];
	}

	/**
	 * Test for `maybe_apply_express_title_after_confirmed_intent` (3DS-redirect path).
	 *
	 * @param string $checkout_type The express checkout type stored on the subscription.
	 * @param string $expected      The expected payment method title.
	 * @return void
	 * @dataProvider provide_test_update_subscription_payment_method_title
	 */
	public function test_maybe_apply_express_title_after_confirmed_intent( $checkout_type, $expected ) {
		$user_id = $this->factory->user->create( [ 'role' => 'customer' ] );
		$token   = WC_Helper_Token::create_token( 'pm_post3ds_card', $user_id );

		$subscription = new WC_Subscription();
		$subscription->set_customer_id( $user_id );
		$subscription->set_payment_method( 'stripe' );
		$subscription->set_payment_method_title( 'Credit Card (Stripe)' );
		$subscription->update_meta_data( '_wc_stripe_express_checkout_type', $checkout_type );
		$subscription->update_meta_data( '_wc_stripe_express_checkout_payment_method_id', 'pm_post3ds_card' );
		$subscription->save();

		$this->element->maybe_apply_express_title_after_confirmed_intent( $subscription );

		$this->assertSame( $expected, $subscription->get_payment_method_title() );
		// Both temporary meta fields should be cleaned up after applying.
		$this->assertSame( '', $subscription->get_meta( '_wc_stripe_express_checkout_type' ) );
		$this->assertSame( '', $subscription->get_meta( '_wc_stripe_express_checkout_payment_method_id' ) );
		// The token persisted alongside the title meta must be linked to the subscription.
		$attached_ids = array_values( $subscription->get_payment_tokens() );
		$this->assertSame( [ $token->get_id() ], $attached_ids );
		// And the corrective note should also be appended on this path.
		$this->assertContains( "Payment method updated to {$expected}.", $subscription->get_captured_notes() );
	}

	/**
	 * Test that `maybe_apply_express_title_after_confirmed_intent` is a no-op without the meta.
	 *
	 * @return void
	 */
	public function test_maybe_apply_express_title_after_confirmed_intent_noop_without_meta() {
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'stripe' );
		$subscription->set_payment_method_title( 'Credit Card (Stripe)' );
		$subscription->save();

		$this->element->maybe_apply_express_title_after_confirmed_intent( $subscription );

		$this->assertSame( 'Credit Card (Stripe)', $subscription->get_payment_method_title() );
	}

	/**
	 * Test for `filter_change_payment_method_note_title` on the no-3DS path, where
	 * the form submission carries `$_POST['express_checkout_type']`.
	 *
	 * Guards that the order-note "to" label written by WCS gets replaced with the
	 * express checkout label instead of the bare "Credit Card" gateway title.
	 *
	 * @param string $checkout_type The express checkout type posted with the form.
	 * @param string $expected      The expected note label substituted by the filter.
	 * @return void
	 * @dataProvider provide_test_update_subscription_payment_method_title
	 */
	public function test_filter_change_payment_method_note_title_uses_post( $checkout_type, $expected ) {
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'stripe' );
		$subscription->save();

		$_POST['express_checkout_type'] = $checkout_type;

		$filtered = $this->element->filter_change_payment_method_note_title( 'Credit Card', 'stripe', $subscription );

		unset( $_POST['express_checkout_type'] );

		$this->assertSame( $expected, $filtered );
	}

	/**
	 * Test for `filter_change_payment_method_note_title` on the post-3DS path, where
	 * $_POST is unavailable and the express type was persisted to subscription meta
	 * before the redirect.
	 *
	 * Guards that the note "to" label is corrected after a 3DS round-trip too.
	 *
	 * @return void
	 */
	public function test_filter_change_payment_method_note_title_falls_back_to_meta() {
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'stripe' );
		$subscription->update_meta_data( '_wc_stripe_express_checkout_type', 'apple_pay' );
		$subscription->save();

		unset( $_POST['express_checkout_type'] );

		$filtered = $this->element->filter_change_payment_method_note_title( 'Credit Card', 'stripe', $subscription );

		$this->assertSame( 'Apple Pay (Stripe)', $filtered );
	}

	/**
	 * Test that `filter_change_payment_method_note_title` is a pass-through when the
	 * target gateway is not Stripe, when no express type signal is present, or when the
	 * signalled type is not an express method.
	 *
	 * Ensures the filter never alters notes for unrelated gateways or non-express
	 * Stripe submissions.
	 *
	 * @return void
	 */
	public function test_filter_change_payment_method_note_title_noop_cases() {
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'stripe' );
		$subscription->save();

		// Non-Stripe gateway: always pass through.
		$_POST['express_checkout_type'] = 'apple_pay';
		$this->assertSame(
			'PayPal',
			$this->element->filter_change_payment_method_note_title( 'PayPal', 'paypal', $subscription )
		);

		// No signal: pass through the original WCS-computed title.
		unset( $_POST['express_checkout_type'] );
		$this->assertSame(
			'Credit Card',
			$this->element->filter_change_payment_method_note_title( 'Credit Card', 'stripe', $subscription )
		);

		// Unrecognized express type: pass through.
		$_POST['express_checkout_type'] = 'paypal';
		try {
			$this->assertSame(
				'Credit Card',
				$this->element->filter_change_payment_method_note_title( 'Credit Card', 'stripe', $subscription )
			);
		} finally {
			unset( $_POST['express_checkout_type'] );
		}
	}

	/**
	 * Test that `update_subscription_payment_method_title` is a no-op when the express
	 * checkout type is missing or unrecognized.
	 *
	 * @return void
	 */
	public function test_update_subscription_payment_method_title_noop_when_no_express_type() {
		$subscription = new WC_Subscription();
		$subscription->set_payment_method( 'stripe' );
		$subscription->set_payment_method_title( 'Stripe' );
		$subscription->save();

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $subscription ) {
				return (int) $id === $subscription->get_id() ? $subscription : false;
			}
		);

		$_GET['change_payment_method'] = $subscription->get_id();
		unset( $_POST['express_checkout_type'] );

		$this->element->update_subscription_payment_method_title();
		try {
			$this->assertSame( 'Stripe', $subscription->get_payment_method_title() );

			// Unrecognized type should also leave the title unchanged.
			$_POST['express_checkout_type'] = 'paypal';
			$this->element->update_subscription_payment_method_title();
			$this->assertSame( 'Stripe', $subscription->get_payment_method_title() );
		} finally {
			unset( $_GET['change_payment_method'], $_POST['express_checkout_type'] );
			WC_Subscriptions::$wcs_get_subscription = null;
		}
	}

	/**
	 * Test for `filter_gateway_title`.
	 *
	 * @param string $title    The title to filter.
	 * @param string $expected The expected title.
	 * @return void
	 * @dataProvider provide_test_filter_gateway_title
	 */
	public function test_filter_gateway_title( $title, $expected ) {
		global $theorder;

		$theorder = WC_Helper_Order::create_order();
		$actual   = $this->element->filter_gateway_title( $title, 'stripe' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Provider for `test_filter_gateway_title`.
	 *
	 * @return array
	 */
	public function provide_test_filter_gateway_title() {
		return [
			'random title' => [
				'title'    => 'test',
				'expected' => 'test',
			],
			'Google Pay'   => [
				'title'    => 'Google Pay (Stripe)',
				'expected' => 'Google Pay (Stripe)',
			],
		];
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

		$helper->method( 'is_page_supported' )
			->willReturn( $page_supported );

		$helper->method( 'should_show_express_checkout_button' )
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
		$is_checkout_filter = function () use ( $is_checkout ) {
			return $is_checkout;
		};
		add_filter( 'woocommerce_is_checkout', $is_checkout_filter );

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

		$helper->method( 'get_button_locations' )
			->willReturn( [ $button_location ] );

		$element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );

		ob_start();
		$element->display_express_checkout_button_separator_html();
		$output = ob_get_clean();

		remove_filter( 'woocommerce_is_checkout', $is_checkout_filter );

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

	/**
	 * Test for `is_product_page_for_ece`.
	 *
	 * The is_product_page_for_ece method determines whether ECE should use product
	 * pricing vs cart pricing. It returns false when:
	 * - Not on a product page
	 * - On a One Page Checkout page with ECE enabled on checkout (should use cart pricing)
	 *
	 * @param bool $is_product               Whether we're on a product page.
	 * @param bool $is_opc                   Whether One Page Checkout is active.
	 * @param bool $show_ece_on_checkout     Whether ECE is enabled on checkout pages.
	 * @param bool $expected_result          Expected return value from is_product_page_for_ece.
	 * @return void
	 * @dataProvider provide_test_is_product_page_for_ece
	 */
	public function test_is_product_page_for_ece( $is_product, $is_opc, $show_ece_on_checkout, $expected_result ) {
		$ajax_handler = $this->getMockBuilder( WC_Stripe_Express_Checkout_Ajax_Handler::class )
			->disableOriginalConstructor()
			->getMock();

		$helper = $this->getMockBuilder( WC_Stripe_Express_Checkout_Helper::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'is_product', 'is_one_page_checkout', 'should_show_ece_on_checkout_page' ] )
			->getMock();

		$helper->method( 'is_product' )
			->willReturn( $is_product );

		$helper->method( 'is_one_page_checkout' )
			->willReturn( $is_opc );

		$helper->method( 'should_show_ece_on_checkout_page' )
			->willReturn( $show_ece_on_checkout );

		$element = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );
		$result  = $element->is_product_page_for_ece();

		$this->assertSame( $expected_result, $result );
	}

	/**
	 * Data provider for test_is_product_page_for_ece.
	 *
	 * @return array
	 */
	public function provide_test_is_product_page_for_ece() {
		return [
			'not on product page'                                => [
				'is_product'           => false,
				'is_opc'               => false,
				'show_ece_on_checkout' => false,
				'expected_result'      => false,
			],
			'product page, not OPC'                              => [
				'is_product'           => true,
				'is_opc'               => false,
				'show_ece_on_checkout' => false,
				'expected_result'      => true,
			],
			'product page, not OPC, ECE on checkout enabled'     => [
				'is_product'           => true,
				'is_opc'               => false,
				'show_ece_on_checkout' => true,
				'expected_result'      => true,
			],
			'product page, OPC active, ECE on checkout disabled' => [
				'is_product'           => true,
				'is_opc'               => true,
				'show_ece_on_checkout' => false,
				'expected_result'      => true,
			],
			'product page, OPC active, ECE on checkout enabled'  => [
				'is_product'           => true,
				'is_opc'               => true,
				'show_ece_on_checkout' => true,
				'expected_result'      => false,
			],
		];
	}

	/**
	 * The Pay for Order page must localize the express checkout payload using the order's
	 * currency, not the store base currency. Otherwise the Apple Pay / Google Pay wallet
	 * sheet shows the wrong currency (and, for zero-decimal currencies, the wrong amount)
	 * whenever the order currency differs from the store base. See STRIPE-1195.
	 *
	 * @param string $store_currency  Store base currency option value.
	 * @param string $order_currency  The order's currency.
	 * @param int    $expected_amount Expected `total.amount` in Stripe's smallest unit.
	 *
	 * @return void
	 * @dataProvider provide_test_localize_pay_for_order_uses_order_currency
	 */
	public function test_localize_pay_for_order_uses_order_currency( $store_currency, $order_currency, $expected_amount ) {
		// Start from a clean script registration so we read only this call's localized data.
		wp_deregister_script( 'wc_stripe_express_checkout' );

		update_option( 'woocommerce_currency', $store_currency );

		// Order total is 50 from the helper; give it a currency that may differ from the store base.
		$order = WC_Helper_Order::create_order( 1, null, [ 'currency' => $order_currency ] );

		$this->element->localize_pay_for_order_page_scripts( $order );

		$params = $this->get_localized_pay_for_order_params();

		$this->assertSame( strtolower( $order_currency ), $params['currency'] );
		$this->assertSame( $expected_amount, $params['total']['amount'] );
	}

	/**
	 * Data provider for `test_localize_pay_for_order_uses_order_currency`.
	 *
	 * @return array
	 */
	public function provide_test_localize_pay_for_order_uses_order_currency() {
		return [
			// Order total 50, two-decimal currency differing from store base -> 5000 (cents).
			'order currency differs from store base' => [
				'store_currency'  => 'GBP',
				'order_currency'  => 'AUD',
				'expected_amount' => 5000,
			],
			// Zero-decimal order currency on a two-decimal store: amount must stay 50, not 5000.
			'zero-decimal order currency'            => [
				'store_currency'  => 'GBP',
				'order_currency'  => 'JPY',
				'expected_amount' => 50,
			],
			// Control: order currency equal to store base still works.
			'order currency equals store base'       => [
				'store_currency'  => 'USD',
				'order_currency'  => 'USD',
				'expected_amount' => 5000,
			],
		];
	}

	/**
	 * Decode the localized `wcStripeExpressCheckoutPayForOrderParams` payload back into an array.
	 *
	 * `wp_localize_script` stores it as `var wcStripeExpressCheckoutPayForOrderParams = {json};`.
	 *
	 * @return array
	 */
	private function get_localized_pay_for_order_params() {
		$data  = wp_scripts()->get_data( 'wc_stripe_express_checkout', 'data' );
		$start = strpos( $data, '{' );
		$end   = strrpos( $data, '}' );
		$json  = substr( $data, $start, $end - $start + 1 );

		return json_decode( $json, true );
	}
}
