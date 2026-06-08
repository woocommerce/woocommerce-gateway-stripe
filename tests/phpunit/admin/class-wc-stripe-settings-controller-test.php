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
			->setMethods( [ 'get_intent_from_order' ] )
			->getMock();

		$gateway->expects( $this->once() )
			->method( 'get_intent_from_order' )
			->with( $order )
			->willReturn( $intent );

		$controller = new WC_Stripe_Settings_Controller( $this->account, $gateway );

		ob_start();
		$controller->hide_refund_button_for_uncaptured_orders( $order );
		$output = ob_get_clean();
		$this->assertStringMatchesFormat( '%aclass="button button-disabled"%a', $output );
	}

	/**
	 * @dataProvider provide_test_admin_scripts_checkout_sessions_country_restrictions
	 */
	public function test_admin_scripts_sets_checkout_sessions_availability_with_country_restrictions(
		string $account_country,
		bool $is_checkout_sessions_feature_available,
		bool $expected_checkout_sessions_availability
	): void {
		global $current_tab, $current_section;

		$wp_scripts_backup = $GLOBALS['wp_scripts'];
		$feature_filter    = static function () use ( $is_checkout_sessions_feature_available ) {
			return $is_checkout_sessions_feature_available;
		};

		try {
			// Avoid stacked `wp_localize_script` output from prior data-provider runs breaking JSON extraction.
			$GLOBALS['wp_scripts'] = new WP_Scripts();

			$current_tab     = 'checkout';
			$current_section = 'stripe';

			// is_checkout_sessions_available() bails out before apply_filters unless these are enabled.
			$stripe_settings                               = WC_Stripe_Helper::get_stripe_settings();
			$stripe_settings['pmc_enabled']                = 'yes';
			$stripe_settings['optimized_checkout_element'] = 'yes';
			$stripe_settings['capture']                    = 'yes';
			$stripe_settings['testmode']                   = 'yes';
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

			$account = $this->getMockBuilder( WC_Stripe_Account::class )
				->disableOriginalConstructor()
				->getMock();
			$account->method( 'get_account_country' )->willReturn( $account_country );

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

			add_filter( 'wc_stripe_is_checkout_sessions_available', $feature_filter );

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
			$this->assertSame( 'accordion', $params['oc_layout'] );
		} finally {
			if ( isset( $stripe_singleton_account_backup ) ) {
				WC_Stripe::get_instance()->account = $stripe_singleton_account_backup;
			}
			$GLOBALS['wp_scripts'] = $wp_scripts_backup;
			remove_filter( 'wc_stripe_is_checkout_sessions_available', $feature_filter );
			unset( $current_tab, $current_section );
		}
	}

	public function provide_test_admin_scripts_checkout_sessions_country_restrictions(): array {
		return [
			'US account + feature available'   => [ 'US', true, true ],
			'IN account + feature available'   => [ 'IN', true, false ],
			'DE account + feature available'   => [ 'DE', true, true ],
			'US account + feature unavailable' => [ 'US', false, false ],
		];
	}
}
