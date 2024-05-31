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
	 * @var WC_Stripe_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Constructor
	 *
	 * @param WC_Stripe_Account $account Stripe account
	 */
	public function __construct( WC_Stripe_Account $account, WC_Stripe_Payment_Gateway $gateway ) {
		$this->account = $account;
		$this->gateway = $gateway;
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wc_stripe_gateway_admin_options_wrapper', [ $this, 'admin_options' ] );
		add_action( 'woocommerce_order_item_add_action_buttons', [ $this, 'hide_refund_button_for_uncaptured_orders' ] );

		// Priority 5 so we can manipulate the registered gateways before they are shown.
		add_action( 'woocommerce_admin_field_payment_gateways', [ $this, 'hide_gateways_on_settings_page' ], 5 );

		add_action( 'admin_init', [ $this, 'maybe_update_account_data' ] );
	}

	/**
	* This replaces the refund button with a disabled 'Refunding unavailable' button in the same place for orders that have been authorized but not captured.
	*
	* A help tooltip explains that refunds are not available for orders which have not been captured yet.
	*
	* @param WC_Order $order The order that is being viewed.
	*/
	public function hide_refund_button_for_uncaptured_orders( $order ) {
		$intent = $this->gateway->get_intent_from_order( $order );

		if ( $intent && 'requires_capture' === $intent->status ) {
			$no_refunds_button  = __( 'Refunding unavailable', 'woocommerce-gateway-stripe' );
			$no_refunds_tooltip = __( 'Refunding via Stripe is unavailable because funds have not been captured for this order. Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-stripe' );
			echo '<style>.button.refund-items { display: none; }</style>';
			echo '<span class="button button-disabled">' . $no_refunds_button . wc_help_tip( $no_refunds_tooltip ) . '</span>';
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

		echo '<h2>' . esc_html( $gateway->get_method_title() );
		wc_back_link( __( 'Return to payments', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';

		$settings = get_option( WC_Stripe_Connect::SETTINGS_OPTION, [] );

		$account_data_exists = ( ! empty( $settings['publishable_key'] ) && ! empty( $settings['secret_key'] ) ) || ( ! empty( $settings['test_publishable_key'] ) && ! empty( $settings['test_secret_key'] ) );
		echo $account_data_exists ? '<div id="wc-stripe-account-settings-container"></div>' : '<div id="wc-stripe-new-account-container"></div>';
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

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/upe_settings.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'woocommerce_stripe_admin',
			plugins_url( 'build/upe_settings.js', WC_STRIPE_MAIN_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_register_style(
			'woocommerce_stripe_admin',
			plugins_url( 'build/upe_settings.css', WC_STRIPE_MAIN_FILE ),
			[ 'wc-components' ],
			$script_asset['version']
		);

		$oauth_url = woocommerce_gateway_stripe()->connect->get_oauth_url();
		if ( is_wp_error( $oauth_url ) ) {
			$oauth_url = '';
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
			'stripe_oauth_url'          => $oauth_url,
			'show_customization_notice' => get_option( 'wc_stripe_show_customization_notice', 'yes' ) === 'yes' ? true : false,
			'is_test_mode'              => $this->gateway->is_in_test_mode(),
			'plugin_version'            => WC_STRIPE_VERSION,
			'account_country'           => $this->account->get_account_country(),
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

	/**
	 * Updates the Stripe account data on the settings page.
	 *
	 * Some plugin settings (eg statement descriptions) require the latest update-to-date data from the Stripe Account to display
	 * correctly. This function clears the account cache when the settings page is loaded to ensure the latest data is displayed.
	 */
	public function maybe_update_account_data() {

		// Exit early if we're not on the payments settings page.
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] || 'checkout' !== $_GET['tab'] ) {
			return;
		}

		if ( ! isset( $_GET['section'] ) || 'stripe' !== $_GET['section'] ) {
			return;
		}

		if ( ! WC_Stripe::get_instance()->connect->is_connected() ) {
			return [];
		}

		$this->account->clear_cache();
	}
}
