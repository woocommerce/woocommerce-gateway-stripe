<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controls whether we're on the settings page and enqueues the JS code.
 *
 * @since 5.4.1
 */
class WC_Stripe_Settings_Controller {
	/**
	 * The Stripe account instance.
	 *
	 * @var WC_Stripe_Account
	 */
	private $account;

	/**
	 * The Stripe gateway instance.
	 *
	 * @var WC_Stripe_Payment_Gateway|null
	 */
	private $gateway;

	/**
	 * Constructor
	 *
	 * @param WC_Stripe_Account $account Stripe account
	 * @param WC_Stripe_Payment_Gateway|null $gateway Stripe gateway
	 */
	public function __construct( WC_Stripe_Account $account, ?WC_Stripe_Payment_Gateway $gateway = null ) {
		$this->account = $account;
		$this->gateway = $gateway;

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wc_stripe_gateway_admin_options_wrapper', [ $this, 'admin_options' ] );
		add_action( 'woocommerce_order_item_add_action_buttons', [ $this, 'hide_refund_button_for_uncaptured_orders' ] );

		// Priority 5 so we can manipulate the registered gateways before they are shown.
		add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'hide_gateways_on_settings_page' ], 5 );

		add_action( 'update_option_woocommerce_gateway_order', [ $this, 'set_stripe_gateways_in_list' ] );

		// Add AJAX handler for OAuth URLs
		add_action( 'wp_ajax_wc_stripe_get_oauth_urls', [ $this, 'ajax_get_oauth_urls' ] );
	}

	/**
	 * Fetches the Stripe gateway instance.
	 */
	private function get_gateway() {
		if ( ! $this->gateway ) {
			$this->gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
		}

		return $this->gateway;
	}

	/**
	 * Sets the Stripe gateways in the 'woocommerce_gateway_order' option which contains the list of all the gateways.
	 * This function is called when the 'woocommerce_gateway_order' option is updated.
	 * Adding the Stripe gateway to the option is needed to display them in the checkout page.
	 *
	 * @param array $ordering The current ordering of the gateways.
	 */
	public function set_stripe_gateways_in_list( $ordering ) {
		// Prevent unnecessary recursion, 'add_stripe_methods_in_woocommerce_gateway_order' saves the same option that triggers this callback.
		remove_action( 'update_option_woocommerce_gateway_order', [ $this, 'set_stripe_gateways_in_list' ] );

		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order();

		add_action( 'update_option_woocommerce_gateway_order', [ $this, 'set_stripe_gateways_in_list' ] );
	}

	/**
	* This replaces the refund button with a disabled 'Refunding unavailable' button in the same place for orders that have been authorized but not captured.
	*
	* A help tooltip explains that refunds are not available for orders which have not been captured yet.
	*
	* @param WC_Order $order The order that is being viewed.
	*/
	public function hide_refund_button_for_uncaptured_orders( $order ) {
		try {
			$intent = $this->get_gateway()->get_intent_from_order( $order );

			if ( $intent && WC_Stripe_Intent_Status::REQUIRES_CAPTURE === $intent->status ) {
				$no_refunds_button  = __( 'Refunding unavailable', 'woocommerce-gateway-stripe' );
				$no_refunds_tooltip = __( 'Refunding via Stripe is unavailable because funds have not been captured for this order. Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-stripe' );
				echo '<style>.button.refund-items { display: none; }</style>';
				echo '<span class="button button-disabled">' . esc_html( $no_refunds_button ) . wp_kses_post( wc_help_tip( $no_refunds_tooltip ) ) . '</span>';
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::log( 'Error getting intent from order: ' . $e->getMessage() );
		}
	}

	/**
	 * Prints the admin options for the gateway.
	 * Remove this action once we're fully migrated to UPE and move the wrapper in the `admin_options` method of the UPE gateway.
	 *
	 * @param WC_Stripe_Payment_Gateway $gateway the Stripe gateway.
	 */
	public function admin_options( WC_Stripe_Payment_Gateway $gateway ) {
		global $hide_save_button;

		$hide_save_button = true;
		$return_url       = admin_url( 'admin.php?page=wc-settings&tab=checkout' );
		$header          = $gateway->get_method_title();
		$return_text     = __( 'Return to payments', 'woocommerce-gateway-stripe' );

		WC_Stripe_Helper::render_admin_header( $header, $return_text, $return_url );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$account_data_exists = ( ! empty( $settings['publishable_key'] ) && ! empty( $settings['secret_key'] ) ) || ( ! empty( $settings['test_publishable_key'] ) && ! empty( $settings['test_secret_key'] ) );
		echo $account_data_exists ? '<div id="wc-stripe-account-settings-container"></div>' : '<div id="wc-stripe-new-account-container"></div>';
	}

	/**
	 * AJAX handler to get OAuth URLs for the configuration modal
	 */
	public function ajax_get_oauth_urls() {
		// Check nonce and capabilities
		if ( ! check_ajax_referer( 'wc_stripe_get_oauth_urls', 'nonce', false ) ||
			! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'woocommerce-gateway-stripe' ) ] );
			return;
		}

		$oauth_url      = woocommerce_gateway_stripe()->connect->get_oauth_url();
		$test_oauth_url = woocommerce_gateway_stripe()->connect->get_oauth_url( '', 'test' );

		wp_send_json_success(
			[
				'oauth_url'      => is_wp_error( $oauth_url ) ? '' : $oauth_url,
				'test_oauth_url' => is_wp_error( $test_oauth_url ) ? '' : $test_oauth_url,
			]
		);
	}

	/**
	 * Determines if OAuth URLs need to be generated.
	 * URLs are needed for new accounts or accounts not connected via OAuth.
	 *
	 * @return bool True if OAuth URLs are needed
	 */
	public function needs_oauth_urls() {
		$settings      = WC_Stripe_Helper::get_stripe_settings();
		$has_live_keys = ! empty( $settings['publishable_key'] ) && ! empty( $settings['secret_key'] );
		$has_test_keys = ! empty( $settings['test_publishable_key'] ) && ! empty( $settings['test_secret_key'] );

		// If no keys at all, we need OAuth URLs for new account setup
		if ( ! $has_live_keys && ! $has_test_keys ) {
			return true;
		}

		$stripe_connect = woocommerce_gateway_stripe()->connect;

		// Check each mode only if it has keys
		$needs_live_oauth = $has_live_keys && ! $stripe_connect->is_connected_via_oauth( 'live' );
		$needs_test_oauth = $has_test_keys && ! $stripe_connect->is_connected_via_oauth( 'test' );

		return $needs_live_oauth || $needs_test_oauth;
	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts( $hook_suffix ) {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		// TODO: refactor this to a regex approach, we will need to touch `should_enqueue_in_current_tab_section` to support it
		if ( ! ( WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_sepa' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_giropay' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_ideal' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_bancontact' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_eps' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_sofort' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_p24' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_alipay' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_multibanco' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_oxxo' )
			|| WC_Stripe_Helper::should_enqueue_in_current_tab_section( 'checkout', 'stripe_boleto' ) ) ) {
			return;
		}

		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/upe-settings.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'woocommerce_stripe_admin',
			plugins_url( 'build/upe-settings.js', WC_STRIPE_MAIN_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_register_style(
			'woocommerce_stripe_admin',
			plugins_url( 'build/upe-settings.css', WC_STRIPE_MAIN_FILE ),
			[ 'wc-components' ],
			$script_asset['version']
		);

		$oauth_url      = '';
		$test_oauth_url = '';

		// Get URLs at page load only if account doesn't exist or if account exists but not connected via OAuth
		if ( $this->needs_oauth_urls() ) {
			$oauth_url = woocommerce_gateway_stripe()->connect->get_oauth_url();
			$oauth_url = is_wp_error( $oauth_url ) ? '' : $oauth_url;

			$test_oauth_url = woocommerce_gateway_stripe()->connect->get_oauth_url( '', 'test' );
			$test_oauth_url = is_wp_error( $test_oauth_url ) ? '' : $test_oauth_url;
		}

		$message = sprintf(
		/* translators: 1) Html strong opening tag 2) Html strong closing tag */
			esc_html__( '%1$sWarning:%2$s your site\'s time does not match the time on your browser and may be incorrect. Some payment methods depend on webhook verification and verifying webhooks with a signing secret depends on your site\'s time being correct, so please check your site\'s time before setting a webhook secret. You may need to contact your site\'s hosting provider to correct the site\'s time.', 'woocommerce-gateway-stripe' ),
			'<strong>',
			'</strong>'
		);

		$params = [
			'time'                      => time(),
			'i18n_out_of_sync'          => $message,
			'is_upe_checkout_enabled'   => WC_Stripe_Feature_Flags::is_upe_checkout_enabled(),
			'is_ach_enabled'            => WC_Stripe_Feature_Flags::is_ach_lpm_enabled(),
			'is_acss_enabled'           => WC_Stripe_Feature_Flags::is_acss_lpm_enabled(),
			'is_bacs_enabled'           => WC_Stripe_Feature_Flags::is_bacs_lpm_enabled(),
			'is_blik_enabled'           => WC_Stripe_Feature_Flags::is_blik_lpm_enabled(),
			'is_becs_debit_enabled'     => WC_Stripe_Feature_Flags::is_becs_debit_lpm_enabled(),
			'stripe_oauth_url'          => $oauth_url,
			'stripe_test_oauth_url'     => $test_oauth_url,
			'show_customization_notice' => get_option( 'wc_stripe_show_customization_notice', 'yes' ) === 'yes' ? true : false,
			'is_test_mode'              => $this->get_gateway()->is_in_test_mode(),
			'plugin_version'            => WC_STRIPE_VERSION,
			'account_country'           => $this->account->get_account_country(),
			'are_apms_deprecated'       => WC_Stripe_Feature_Flags::are_apms_deprecated(),
			'is_amazon_pay_available'   => WC_Stripe_Feature_Flags::is_amazon_pay_available(),
			'is_oc_available'           => WC_Stripe_Feature_Flags::is_oc_available(),
			'oauth_nonce'               => wp_create_nonce( 'wc_stripe_get_oauth_urls' ),
		];
		wp_localize_script(
			'woocommerce_stripe_admin',
			'wc_stripe_settings_params',
			$params
		);
		wp_set_script_translations(
			'woocommerce_stripe_admin',
			'woocommerce-gateway-stripe'
		);

		wp_enqueue_script( 'woocommerce_stripe_admin' );
		wp_enqueue_style( 'woocommerce_stripe_admin' );
	}

	/**
	 * Removes all Stripe alternative payment methods (eg Bancontact, giropay) on the WooCommerce Settings page.
	 *
	 * Note: This function is hooked onto `woocommerce_admin_field_payment_gateways` which is the hook used
	 * to display the payment gateways on the WooCommerce Settings page.
	 */
	public static function hide_gateways_on_settings_page() {
		$gateways_to_hide = [
			// Hide all UPE payment methods.
			WC_Stripe_UPE_Payment_Method::class,
			// Hide all legacy payment methods.
			WC_Gateway_Stripe_Alipay::class,
			WC_Gateway_Stripe_Sepa::class,
			WC_Gateway_Stripe_Giropay::class,
			WC_Gateway_Stripe_Ideal::class,
			WC_Gateway_Stripe_Bancontact::class,
			WC_Gateway_Stripe_Eps::class,
			WC_Gateway_Stripe_P24::class,
			WC_Gateway_Stripe_Boleto::class,
			WC_Gateway_Stripe_Oxxo::class,
			WC_Gateway_Stripe_Sofort::class,
			WC_Gateway_Stripe_Multibanco::class,
		];

		foreach ( WC()->payment_gateways->payment_gateways as $index => $payment_gateway ) {
			foreach ( $gateways_to_hide as $gateway_to_hide ) {
				if ( $payment_gateway instanceof $gateway_to_hide ) {
					unset( WC()->payment_gateways->payment_gateways[ $index ] );
					break; // Break the inner loop as we've already found a match and removed the gateway
				}
			}
		}
	}
}
