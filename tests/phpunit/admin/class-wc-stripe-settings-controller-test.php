<?php

/**
 * This test makes assertions against the class WC_Stripe_Settings_Controller.
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_Settings_Controller
 *
 * WC_Stripe_Settings_Controller unit tests.
 */
class WC_Stripe_Settings_Controller_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_Settings_Controller
	 */
	private $controller;

	/**
	 * @var WC_Stripe_Account
	 */
	private $account;

	/**
	 * @var WC_Stripe_UPE_Payment_Gateway
	 */
	private $gateway;

	public function set_up() {
		parent::set_up();

		$this->account = $this->getMockBuilder( 'WC_Stripe_Account' )
									->disableOriginalConstructor()
									->getMock();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-settings-controller.php';
		$this->gateway    = new WC_Stripe_UPE_Payment_Gateway();
		$this->controller = new WC_Stripe_Settings_Controller( $this->account, $this->gateway );
	}

	public function tear_down() {
		WC_Stripe_Helper::delete_main_stripe_settings();

		parent::tear_down();
	}

	/**
	 * Should print a placeholder div with id 'wc-stripe-account-settings-container'
	 */
	public function test_admin_options_when_stripe_is_connected() {
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = 'pk_test_key';
		$stripe_settings['test_secret_key']      = 'sk_test_key';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		ob_start();
		$this->controller->admin_options( $this->gateway );
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-account-settings-container"%a', $output );
	}

	/**
	 * Should print a placeholder div with id 'wc-stripe-new-account-container'
	 */
	public function test_admin_options_when_stripe_is_not_connected() {
		$stripe_settings                         = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['enabled']              = 'yes';
		$stripe_settings['testmode']             = 'yes';
		$stripe_settings['test_publishable_key'] = '';
		$stripe_settings['test_secret_key']      = '';
		$stripe_settings['publishable_key']      = '';
		$stripe_settings['secret_key']           = '';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		ob_start();
		$this->controller->admin_options( $this->gateway );
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aid="wc-stripe-new-account-container"%a', $output );
	}

	/**
	 * Test if `display_order_fee` and `display_order_payout` are called when viewing an order on the admin panel.
	 *
	 * @return void
	 */
	public function test_add_buttons_action_is_called_on_order_admin_page() {
		$order = WC_Helper_Order::create_order();

		$intent_id = 'pi_mock';
		WC_Stripe_Order_Helper::get_instance()->update_stripe_intent_id( $order, $intent_id );
		$order->save_meta_data();

		$intent = (object) [
			'id'     => 'pi_123',
			'status' => WC_Stripe_Intent_Status::REQUIRES_CAPTURE,
		];

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->onlyMethods( [ 'get_intent_from_order' ] )
			->getMock();

		$gateway->expects( $this->once() )
			->method( 'get_intent_from_order' )
			->with( $order )
			->willReturn( $intent );

		$controller = new WC_Stripe_Settings_Controller( $this->account, $gateway );

		ob_start();
		$controller->maybe_hide_refund_button( $order );
		$output = ob_get_clean();
		$this->assertStringContainsString( ' class="button button-disabled"', $output );
	}

	/**
	 * The refund button is replaced with a disabled one only once the payment method's
	 * refund window has elapsed, measured from the order's paid date.
	 *
	 * @param string   $payment_type  The order's Stripe UPE payment type.
	 * @param int|null $paid_days_ago Days ago the order was paid, or null for no paid date.
	 * @param bool     $should_block  Whether the refund button should be replaced.
	 *
	 * @dataProvider provide_hide_refund_button_outside_refund_window
	 */
	public function test_hide_refund_button_outside_refund_window( string $payment_type, ?int $paid_days_ago, bool $should_block ): void {
		$order = WC_Helper_Order::create_order();
		WC_Stripe_Order_Helper::get_instance()->update_stripe_upe_payment_type( $order, $payment_type );
		if ( null !== $paid_days_ago ) {
			$order->set_date_paid( time() - ( $paid_days_ago * DAY_IN_SECONDS ) );
		}
		$order->save();

		ob_start();
		$this->controller->maybe_hide_refund_button( $order );
		$output = ob_get_clean();

		if ( $should_block ) {
			$this->assertStringContainsString( 'button-disabled', $output );
			$this->assertStringContainsString( 'Refund unavailable', $output );
		} else {
			$this->assertSame( '', $output );
		}
	}

	public function provide_hide_refund_button_outside_refund_window(): array {
		return [
			'Klarna beyond 180-day window'         => [ WC_Stripe_Payment_Methods::KLARNA, 200, true ],
			'Klarna within 180-day window'         => [ WC_Stripe_Payment_Methods::KLARNA, 10, false ],
			'Klarna with no paid date (fail open)' => [ WC_Stripe_Payment_Methods::KLARNA, null, false ],
			'Affirm beyond 120-day window'         => [ WC_Stripe_Payment_Methods::AFFIRM, 130, true ],
			'Affirm within 120-day window'         => [ WC_Stripe_Payment_Methods::AFFIRM, 100, false ],
			'Card is never time-blocked'           => [ WC_Stripe_Payment_Methods::CARD, 500, false ],
		];
	}

	/**
	 * @dataProvider provide_test_admin_scripts_checkout_sessions_country_restrictions
	 */
	public function test_admin_scripts_sets_checkout_sessions_availability_with_country_restrictions(
		string $account_country,
		bool $is_checkout_sessions_feature_available,
		bool $expected_checkout_sessions_availability,
		?string $expected_adaptive_pricing_unavailable_reason = null
	): void {
		global $current_tab, $current_section;

		$wp_scripts_backup = $GLOBALS['wp_scripts'];

		try {
			// Avoid stacked `wp_localize_script` output from prior data-provider runs breaking JSON extraction.
			$GLOBALS['wp_scripts'] = new WP_Scripts();

			$current_tab     = 'checkout';
			$current_section = 'stripe';

			// is_checkout_sessions_available() requires PMC, OC, and automatic capture all enabled;
			// toggle PMC to drive the feature on/off for this data-provider row.
			$stripe_settings                               = WC_Stripe_Helper::get_stripe_settings();
			$stripe_settings['pmc_enabled']                = $is_checkout_sessions_feature_available ? 'yes' : 'no';
			$stripe_settings['optimized_checkout_element'] = 'yes';
			$stripe_settings['capture']                    = 'yes';
			$stripe_settings['testmode']                   = 'yes';
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

			$account = $this->getMockBuilder( WC_Stripe_Account::class )
				->disableOriginalConstructor()
				->getMock();
			$account->method( 'get_account_country' )->willReturn( $account_country );
			// Adaptive Pricing availability now also depends on webhooks; enable them so this test
			// isolates the country-restriction logic it covers.
			$account->method( 'is_webhook_enabled' )->willReturn( true );

			$stripe_singleton_account_backup   = WC_Stripe::get_instance()->account;
			WC_Stripe::get_instance()->account = $account;

			$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
				->disableOriginalConstructor()
				->setMethods(
					[
						'get_upe_enabled_payment_method_ids',
						'is_oc_enabled',
						'is_in_test_mode',
						'get_validated_option',
						'get_option',
					]
				)
				->getMock();
			$gateway->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [] );
			$gateway->method( 'is_oc_enabled' )->willReturn( false );
			$gateway->method( 'is_in_test_mode' )->willReturn( false );
			$gateway->method( 'get_validated_option' )->with( 'optimized_checkout_layout' )->willReturn( 'accordion' );
			$gateway->method( 'get_option' )->willReturn( 'no' );

			$controller = new WC_Stripe_Settings_Controller( $account, $gateway );

			$controller->admin_scripts( 'woocommerce_page_wc-settings' );

			$localized_data = wp_scripts()->get_data( 'woocommerce_stripe_admin', 'data' );
			$this->assertIsString( $localized_data );
			$this->assertMatchesRegularExpression(
				'/wc_stripe_settings_params\s*=\s*(\{.*\});/s',
				$localized_data
			);
			preg_match( '/wc_stripe_settings_params\s*=\s*(\{.*\});/s', $localized_data, $matches );
			$params = json_decode( $matches[1], true );

			$this->assertIsArray( $params );
			$expected_cs_param = $expected_checkout_sessions_availability ? '1' : '';
			$this->assertSame( $expected_cs_param, $params['is_cs_available'] );
			$this->assertSame(
				$expected_adaptive_pricing_unavailable_reason,
				$params['adaptive_pricing_unavailable_reason']
			);
			$this->assertSame( 'accordion', $params['oc_layout'] );
		} finally {
			if ( isset( $stripe_singleton_account_backup ) ) {
				WC_Stripe::get_instance()->account = $stripe_singleton_account_backup;
			}
			$GLOBALS['wp_scripts'] = $wp_scripts_backup;
			unset( $current_tab, $current_section );
		}
	}

	public function provide_test_admin_scripts_checkout_sessions_country_restrictions(): array {
		return [
			'US account + feature available'   => [ 'US', true, true, null ],
			'IN account + feature available'   => [ 'IN', true, false, 'account-country' ],
			'DE account + feature available'   => [ 'DE', true, true, null ],
			'US account + feature unavailable' => [ 'US', false, false, 'disabled' ],
		];
	}
}
