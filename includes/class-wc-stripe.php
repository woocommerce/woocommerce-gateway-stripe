<?php

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe class.
 */
class WC_Stripe {

	/**
	 * The option name for the Stripe gateway settings.
	 *
	 * @deprecated 8.7.0
	 */
	const STRIPE_GATEWAY_SETTINGS_OPTION_NAME = 'woocommerce_stripe_settings';

	/**
	 * The *Singleton* instance of this class
	 *
	 * @var WC_Stripe
	 */
	private static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Stripe The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Stripe Connect API
	 *
	 * @var WC_Stripe_Connect_API
	 */
	private $api;

	/**
	 * Stripe Connect
	 *
	 * @var WC_Stripe_Connect
	 */
	public $connect;

	/**
	 * Stripe Payment Request configurations.
	 *
	 * @var null
	 *
	 * @deprecated 10.4.0 Use express_checkout_configuration instead. This will be removed in a future release.
	 */
	public $payment_request_configuration;

	/**
	 * Stripe Express Checkout configurations.
	 *
	 * @var WC_Stripe_Express_Checkout_Element
	 */
	public $express_checkout_configuration;

	/**
	 * Stripe Account.
	 *
	 * @var WC_Stripe_Account
	 */
	public $account;

	/**
	 * The main Stripe gateway instance. Use get_main_stripe_gateway() to access it.
	 *
	 * @var null|WC_Stripe_Payment_Gateway
	 */
	protected $stripe_gateway = null;

	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 *
	 * @return void
	 */
	public function __clone() {}

	/**
	 * Private unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	public function __wakeup() {}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'install' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_stripe_settings' ], 15 );

		$this->init();

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Init the plugin after plugins_loaded so environment variables are set.
	 *
	 * @since 1.0.0
	 * @version 5.0.0
	 */
	public function init() {
		if ( is_admin() ) {
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-privacy.php';
			new WC_Stripe_Privacy();
		}

		if ( file_exists( WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-feature-flags.php' ) ) {
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-feature-flags.php';
		}

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-upe-compatibility.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-co-branded-cc-compatibility.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-exception.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-payment-cancelled-exception.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-logger.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-helper.php';
		include_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-order-helper.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-database-cache.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-payment-method-configurations.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-database-cache-prefetch.php';
		include_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-api.php';
		include_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-mode.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-subscriptions-helper.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/trait-wc-stripe-subscriptions-utilities.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/trait-wc-stripe-subscriptions.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/trait-wc-stripe-pre-orders.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-subscriptions-legacy-sepa-token-update.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/abstracts/abstract-wc-stripe-payment-gateway.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/abstracts/abstract-wc-stripe-payment-gateway-voucher.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-action-scheduler-service.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-webhook-state.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-webhook-handler.php';
		new WC_Stripe_Webhook_Handler();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/trait-wc-stripe-fingerprint.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/interface-wc-stripe-payment-method-comparison.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-cc-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-ach-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-acss-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-sepa-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-link-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-cash-app-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-bacs-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-becs-debit-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-amazon-pay-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-klarna-payment-token.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-apple-pay-registration.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-status.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-gateway.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-gateway-stripe.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/constants/class-wc-stripe-currency-code.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/constants/class-wc-stripe-country-code.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/constants/class-wc-stripe-payment-methods.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/constants/class-wc-stripe-intent-status.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-cc.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-ach.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-alipay.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-bacs-debit.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-becs-debit.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-blik.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-giropay.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-ideal.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-klarna.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-affirm.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-afterpay-clearpay.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-bancontact.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-boleto.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-oxxo.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-eps.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-sepa.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-p24.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-sofort.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-multibanco.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-link.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-cash-app-pay.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-wechat-pay.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-acss.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-amazon-pay.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-upe-payment-method-oc.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-express-checkout-element.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-express-checkout-helper.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-express-checkout-ajax-handler.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-methods/class-wc-stripe-express-checkout-custom-fields.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-woo-compat-utils.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/connect/class-wc-stripe-connect.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/connect/class-wc-stripe-connect-api.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-order-handler.php';
		new WC_Stripe_Order_Handler();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/payment-tokens/class-wc-stripe-payment-tokens.php';
		new WC_Stripe_Payment_Tokens();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-customer.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-intent-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-inbox-notes.php';
		new WC_Stripe_Inbox_Notes();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-upe-compatibility-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-allowed-payment-request-button-types-update.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-sepa-tokens-for-other-methods-settings-update.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-migrate-payment-request-data-to-express-checkout-data.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-account.php';

		new Allowed_Payment_Request_Button_Types_Update();
		new Migrate_Payment_Request_Data_To_Express_Checkout_Data();
		new Sepa_Tokens_For_Other_Methods_Settings_Update();

		$this->api     = new WC_Stripe_Connect_API();
		$this->connect = new WC_Stripe_Connect( $this->api );
		$this->account = new WC_Stripe_Account( $this->connect, 'WC_Stripe_API' );

		// Initialize Express Checkout after translations are loaded
		add_action( 'init', [ $this, 'init_express_checkout' ], 11 );

		$intent_controller = new WC_Stripe_Intent_Controller();
		$intent_controller->init_hooks();

		$checkout_sessions_ajax_handler = new WC_Stripe_Checkout_Sessions_Ajax_Handler();
		$checkout_sessions_ajax_handler->init_hooks();

		if ( is_admin() ) {
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-admin-notices.php';
			new WC_Stripe_Admin_Notices();

			// Kept loadable so the deprecated `WC_Stripe_Settings_Controller` shim is reachable for external code.
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-settings-controller.php';

			if ( isset( $_GET['area'] ) && in_array( $_GET['area'], [ 'express_checkout', 'payment_requests' ], true ) ) {
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-express-checkout-controller.php';
				new WC_Stripe_Express_Checkout_Controller();
			} elseif ( isset( $_GET['area'] ) && 'amazon_pay' === $_GET['area'] && WC_Stripe_Feature_Flags::is_amazon_pay_available() ) {
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-amazon-pay-controller.php';
				new WC_Stripe_Amazon_Pay_Controller();
			} else {
				$this->register_settings_admin_hooks();
			}

			require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-payment-gateways-controller.php';
			new WC_Stripe_Payment_Gateways_Controller( $this->account );

			new WC_Stripe_Plugins_Page_Controller( $this->account );

			if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-subscription-detached-bulk-action.php';
				new WC_Stripe_Subscription_Detached_Bulk_Action();
			}
		}

		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );
		add_filter( 'pre_update_option_woocommerce_stripe_settings', [ $this, 'gateway_settings_update' ], 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( WC_STRIPE_MAIN_FILE ), [ $this, 'plugin_action_links' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		add_action( 'update_option_woocommerce_gateway_order', [ $this, 'set_stripe_gateways_in_list' ] );

		// Update the email field position.
		if ( ! is_admin() ) {
			add_filter( 'woocommerce_billing_fields', [ $this, 'checkout_update_email_field_priority' ], 50 );
		}

		// Modify emails emails.
		add_filter( 'woocommerce_email_classes', [ $this, 'add_emails' ], 20 );

		if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
			add_filter( 'woocommerce_get_sections_checkout', [ $this, 'filter_gateway_order_admin' ] );
		}

		new WC_Stripe_UPE_Compatibility_Controller();

		// Initialize the class for updating subscriptions' Legacy SEPA payment methods.
		add_action( 'init', [ $this, 'initialize_subscriptions_updater' ] );
		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );

		// Initialize the class for handling the status page.
		add_action( 'init', [ $this, 'initialize_status_page' ], 15 );

		add_action( 'init', [ $this, 'initialize_apple_pay_registration' ] );

		// Initialize Agentic Commerce integration.
		add_action( 'woocommerce_init', [ $this, 'initialize_agentic_commerce' ] );

		// Check for payment methods that should be toggled, e.g. unreleased,
		// BNPLs when official plugins are active,
		// cards when the Optimized Checkout is enabled, etc.
		add_action( 'wc_payment_gateways_initialized', [ $this, 'maybe_toggle_payment_methods' ] );

		// Reconfigure webhooks when Adaptive Pricing is enabled in the settings.
		add_action( 'update_option_woocommerce_stripe_settings', [ $this, 'maybe_reconfigure_webhooks_after_adaptive_pricing_enabled' ], 10, 2 );

		add_action( WC_Stripe_Database_Cache::ASYNC_CLEANUP_ACTION, [ WC_Stripe_Database_Cache::class, 'delete_all_stale_entries_async' ], 10, 2 );
		add_action( 'action_scheduler_run_recurring_actions_schedule_hook', [ WC_Stripe_Database_Cache::class, 'maybe_schedule_daily_async_cleanup' ], 10, 0 );

		// Handle the async cache prefetch action.
		add_action( WC_Stripe_Database_Cache_Prefetch::ASYNC_PREFETCH_ACTION, [ WC_Stripe_Database_Cache_Prefetch::get_instance(), 'handle_prefetch_action' ], 10, 1 );
	}

	/**
	 * Initialize the class for handling the Apple Pay registration.
	 */
	public function initialize_apple_pay_registration() {
		new WC_Stripe_Apple_Pay_Registration();
	}

	/**
	 * Initialize Express Checkout after translations are loaded.
	 */
	public function init_express_checkout() {
		// Express checkout configurations.
		$express_checkout_helper              = new WC_Stripe_Express_Checkout_Helper();
		$express_checkout_ajax_handler        = new WC_Stripe_Express_Checkout_Ajax_Handler( $express_checkout_helper );
		$this->express_checkout_configuration = new WC_Stripe_Express_Checkout_Element( $express_checkout_ajax_handler, $express_checkout_helper );
		$this->express_checkout_configuration->init();
	}

	/**
	 * Updates the plugin version in db
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function update_plugin_version() {
		delete_option( 'wc_stripe_version' );
		update_option( 'wc_stripe_version', WC_STRIPE_VERSION );
	}

	/**
	 * Handles upgrade routines.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function install() {
		if ( ! is_plugin_active( plugin_basename( WC_STRIPE_MAIN_FILE ) ) ) {
			return;
		}

		if ( defined( 'IFRAME_REQUEST' ) ) {
			return;
		}

		$previous_version = get_option( 'wc_stripe_version' );

		if ( WC_STRIPE_VERSION === $previous_version ) {
			return;
		}

		do_action( 'woocommerce_stripe_updated' );

		if ( ! defined( 'WC_STRIPE_INSTALLING' ) ) {
			define( 'WC_STRIPE_INSTALLING', true );
		}

		$is_new_install = false === $previous_version;

		if ( $is_new_install ) {
			update_option( 'wc_stripe_amazon_pay_default_on', 'yes' );
			update_option( 'wc_stripe_optimized_checkout_default_on', 'yes' );
		}

		add_woocommerce_inbox_variant();
		$this->update_plugin_version();

		// Add webhook reconfiguration
		$account = self::get_instance()->account;
		$account->maybe_reconfigure_webhooks_on_update();

		// TODO: Remove this when we're reasonably sure most merchants have had their
		// settings updated like this. ~80% of merchants is a good threshold.
		// - @reykjalin
		$this->update_prb_location_settings();

		// Migrate to the new checkout experience.
		$this->migrate_to_new_checkout_experience();

		// Check for subscriptions using legacy SEPA tokens on upgrade.
		// Handled by WC_Stripe_Subscriptions_Legacy_SEPA_Token_Update.
		delete_option( 'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated' );

		// TODO: Remove this call when all the merchants have moved to the new checkout experience.
		// We are calling this function here to make sure that the Stripe methods are added to the `woocommerce_gateway_order` option.
		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order();

		// Try to schedule the daily async cleanup of the Stripe database cache.
		WC_Stripe_Database_Cache::maybe_schedule_daily_async_cleanup();

		// If we have previously disabled settings synchronization, remove the flag after the upgrade,
		// just to make sure we are still ineligible for settings synchronization.
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		if ( isset( $stripe_settings['pmc_enabled'] ) && 'no' === $stripe_settings['pmc_enabled'] ) {
			unset( $stripe_settings['pmc_enabled'] );
			$stripe_settings['skip_pmc_express_checkout_defaults'] = 'yes';
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
			WC_Stripe_Logger::error( 'Settings synchronization eligibility will be re-checked after upgrade' );
		}
	}

	/**
	 * Sets the Stripe gateways in the 'woocommerce_gateway_order' option which contains the list of all the gateways.
	 * This function is called when the 'woocommerce_gateway_order' option is updated.
	 * Adding the Stripe gateway to the option is needed to display them in the checkout page.
	 *
	 * @param array $ordering The current ordering of the gateways.
	 * @return void
	 */
	public function set_stripe_gateways_in_list( $ordering ) {
		// Prevent unnecessary recursion, 'add_stripe_methods_in_woocommerce_gateway_order' saves the same option that triggers this callback.
		remove_action( 'update_option_woocommerce_gateway_order', [ $this, 'set_stripe_gateways_in_list' ] );

		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order();

		add_action( 'update_option_woocommerce_gateway_order', [ $this, 'set_stripe_gateways_in_list' ] );
	}

	/**
	 * Redirects to the Stripe settings page upon plugin activation if the transient is set,
	 * and if not activating multiple plugins at once.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_stripe_settings(): void {
		if ( get_transient( 'wc_stripe_redirect_to_settings' ) ) {
			delete_transient( 'wc_stripe_redirect_to_settings' );

			if ( isset( $_GET['activate'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) );
				exit;
			}
		}
	}

	/**
	 * Migrates to the new checkout experience.
	 *
	 * @since 9.6.0
	 * @version 9.6.0
	 */
	public function migrate_to_new_checkout_experience() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		// If the flag is not set or not set to yes (set to no/disabled), it means the site was using the legacy checkout experience.
		if ( empty( $stripe_settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) || 'yes' !== $stripe_settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) {
			$stripe_settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'yes';
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

			if ( class_exists( 'WC_Tracks' ) ) {
				WC_Tracks::record_event( 'wcstripe_migrated_to_new_checkout_experience' );
			}
		}
	}

	/**
	 * Updates the PRB location settings based on deprecated filters.
	 *
	 * The filters were removed in favor of plugin settings. This function can, and should,
	 * be removed when we're reasonably sure most merchants have had their settings updated
	 * through this function. Maybe ~80% of merchants is a good threshold?
	 *
	 * @since 5.5.0
	 * @version 5.5.0
	 */
	public function update_prb_location_settings() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$prb_locations   = isset( $stripe_settings['express_checkout_button_locations'] )
			? $stripe_settings['express_checkout_button_locations']
			: [];
		if ( ! empty( $stripe_settings ) && empty( $prb_locations ) ) {
			// Use existing payment_request_button_locations if it exists.
			if ( array_key_exists( 'payment_request_button_locations', $stripe_settings ) ) {
				$stripe_settings['express_checkout_button_locations'] = $stripe_settings['payment_request_button_locations'];
				unset( $stripe_settings['payment_request_button_locations'] );
				WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
				return;
			}

			// Fall back to filter defaults only if no existing setting.
			global $post;

			$should_show_on_product_page  = ! apply_filters( 'wc_stripe_hide_payment_request_on_product_page', false, $post );
			$should_show_on_cart_page     = apply_filters( 'wc_stripe_show_payment_request_on_cart', true );
			$should_show_on_checkout_page = apply_filters( 'wc_stripe_show_payment_request_on_checkout', false, $post );

			$new_prb_locations = [];

			if ( $should_show_on_product_page ) {
				$new_prb_locations[] = 'product';
			}

			if ( $should_show_on_cart_page ) {
				$new_prb_locations[] = 'cart';
			}

			if ( $should_show_on_checkout_page ) {
				$new_prb_locations[] = 'checkout';
			}

			$stripe_settings['express_checkout_button_locations'] = $new_prb_locations;
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		}
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = [
			'<a href="admin.php?page=wc-settings&tab=checkout&section=stripe">' . esc_html__( 'Settings', 'woocommerce-gateway-stripe' ) . '</a>',
		];
		return array_merge( $plugin_links, $links );
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 4.3.4
	 * @param  array  $links Original list of plugin links.
	 * @param  string $file  Name of current file.
	 * @return array  $links Update list of plugin links.
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( WC_STRIPE_MAIN_FILE ) === $file ) {
			$row_meta = [
				'docs'    => '<a href="' . esc_url( 'https://woocommerce.com/document/stripe/' ) . '" title="' . esc_attr( __( 'View Documentation', 'woocommerce-gateway-stripe' ) ) . '">' . __( 'Docs', 'woocommerce-gateway-stripe' ) . '</a>',
				'support' => '<a href="' . esc_url( 'https://woocommerce.com/my-account/create-a-ticket?select=18627' ) . '" title="' . esc_attr( __( 'Open a support request at WooCommerce.com', 'woocommerce-gateway-stripe' ) ) . '">' . __( 'Support', 'woocommerce-gateway-stripe' ) . '</a>',
			];
			return array_merge( $links, $row_meta );
		}
		return (array) $links;
	}

	/**
	 * Add the gateways to WooCommerce.
	 *
	 * @since 1.0.0
	 * @version 5.6.0
	 */
	public function add_gateways( $methods ) {
		$main_gateway  = $this->get_main_stripe_gateway();
		$methods[]     = $main_gateway;
		$is_oc_enabled = 'yes' === $main_gateway->get_option( 'optimized_checkout_element', 'no' );

		// The $main_gateway represents the card gateway so we don't want to include it in the list of UPE gateways.
		$upe_payment_methods = $main_gateway->payment_methods;
		unset( $upe_payment_methods['card'] );

		$methods = array_merge( $methods, $upe_payment_methods );

		// When we are in an admin context,
		// 1. Filter out Link and Amazon Pay, as they are only available as express checkout methods,
		// and including them in the list results in warnings about block support
		// when viewing the Express Checkout block in the editor for the cart and checkout pages.
		// 2. Filter out Optimized Checkout payment methods, as they are only available as one payment element under the Stripe payment method block,
		// and including them in the list results in warnings about block support
		// when viewing the Optimized Checkout block in the editor for the cart and checkout pages.
		// 3. Filter out UPE payment methods that are not enabled at checkout, as they are not available in the checkout block
		// and including them in the list results in warnings about block support
		// when viewing the payment methods block in the editor for the cart and checkout pages.
		if ( is_admin() ) {
			$methods = array_filter(
				$methods,
				function ( $method ) use ( $is_oc_enabled ) {
					if ( $method instanceof WC_Stripe_UPE_Payment_Method_Link || $method instanceof WC_Stripe_UPE_Payment_Method_Amazon_Pay ) {
						return false;
					}

					if ( $method instanceof WC_Stripe_UPE_Payment_Method ) {
						if ( $is_oc_enabled ) {
							return false;
						}

						if ( ! $method->is_enabled_at_checkout() ) {
							return false;
						}
					}

					return true;
				}
			);
		}

		return $methods;
	}

	/**
	 * Modifies the order of the gateways displayed in admin.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function filter_gateway_order_admin( $sections ) {
		unset( $sections['stripe'] );
		unset( $sections['stripe_bancontact'] );
		unset( $sections['stripe_sofort'] );
		unset( $sections['stripe_giropay'] );
		unset( $sections['stripe_eps'] );
		unset( $sections['stripe_ideal'] );
		unset( $sections['stripe_p24'] );
		unset( $sections['stripe_alipay'] );
		unset( $sections['stripe_sepa'] );
		unset( $sections['stripe_multibanco'] );

		$sections['stripe']     = 'Stripe';
		$sections['stripe_upe'] = 'Stripe checkout experience';

		$sections['stripe_bancontact'] = __( 'Stripe Bancontact', 'woocommerce-gateway-stripe' );
		$sections['stripe_sofort']     = __( 'Stripe Sofort', 'woocommerce-gateway-stripe' );
		$sections['stripe_giropay']    = __( 'Stripe giropay', 'woocommerce-gateway-stripe' );
		$sections['stripe_eps']        = __( 'Stripe EPS', 'woocommerce-gateway-stripe' );
		$sections['stripe_ideal']      = __( 'Stripe iDEAL', 'woocommerce-gateway-stripe' );
		$sections['stripe_p24']        = __( 'Stripe P24', 'woocommerce-gateway-stripe' );
		$sections['stripe_alipay']     = __( 'Stripe Alipay', 'woocommerce-gateway-stripe' );
		$sections['stripe_sepa']       = __( 'Stripe SEPA Direct Debit', 'woocommerce-gateway-stripe' );
		$sections['stripe_multibanco'] = __( 'Stripe Multibanco', 'woocommerce-gateway-stripe' );

		return $sections;
	}

	/**
	 * Provide default values for missing settings on initial gateway settings save.
	 *
	 * @since 4.5.4
	 * @version 4.5.4
	 *
	 * @param array      $settings New settings to save.
	 * @param array|bool $old_settings Existing settings, if any.
	 * @return array New value but with defaults initially filled in for missing settings.
	 */
	public function gateway_settings_update( $settings, $old_settings ) {
		if ( false === $old_settings ) {
			$gateway      = new WC_Stripe_UPE_Payment_Gateway();
			$fields       = $gateway->get_form_fields();
			$old_settings = array_merge( array_fill_keys( array_keys( $fields ), '' ), wp_list_pluck( $fields, 'default' ) );
			$settings     = array_merge( $old_settings, $settings );
		}

		// Note that we need to run these checks before we call toggle_upe() below.
		$this->maybe_reset_stripe_in_memory_key( $settings, $old_settings );

		return $this->toggle_upe( $settings, $old_settings );
	}

	/**
	 * Runs after Stripe gateway settings option is updated. Reconfigures webhooks only when Adaptive Pricing becomes enabled.
	 * Adaptive Pricing and Optimized Checkout both must be enabled in the new value for webhooks to be reconfigured.
	 *
	 * @param array|false $old_value Previous option value.
	 * @param array       $value     New option value.
	 * @return void
	 */
	public function maybe_reconfigure_webhooks_after_adaptive_pricing_enabled( $old_value, $value ) {
		if ( ! $this->account ) {
			return;
		}

		if ( ! is_array( $value ) ) {
			return;
		}

		$is_oc_enabled = 'yes' === ( $value['optimized_checkout_element'] ?? '' );
		$is_ap_enabled = 'yes' === ( $value['adaptive_pricing'] ?? '' );

		// If Adaptive Pricing or Optimized Checkout is disabled in the new value, do nothing.
		if ( ! $is_ap_enabled || ! $is_oc_enabled ) {
			return;
		}

		$was_oc_enabled = is_array( $old_value ) && 'yes' === ( $old_value['optimized_checkout_element'] ?? '' );
		$was_ap_enabled = is_array( $old_value ) && 'yes' === ( $old_value['adaptive_pricing'] ?? '' );

		// If Adaptive Pricing and Optimized Checkout were both enabled before, do nothing.
		if ( $was_ap_enabled && $was_oc_enabled ) {
			return;
		}

		$this->account->maybe_reconfigure_webhooks_on_update( 'settings' );
	}

	/**
	 * Helper function that ensures we clear the in-memory Stripe API key in {@see WC_Stripe_API}
	 * when we're making a change to our settings that impacts which secret key we should be using.
	 *
	 * @param array $settings     New settings that have just been saved.
	 * @param array $old_settings Old settings that were previously saved.
	 * @return void
	 */
	protected function maybe_reset_stripe_in_memory_key( $settings, $old_settings ) {
		// If we're making a change that impacts which secret key we should be using,
		// we need to clear the static key being used by WC_Stripe_API.
		// Note that this also needs to run before we call toggle_upe() below.
		$should_clear_stripe_api_key = false;

		$settings_to_check = [
			'testmode',
			'secret_key',
			'test_secret_key',
		];

		foreach ( $settings_to_check as $setting_to_check ) {
			if ( isset( $settings[ $setting_to_check ] ) && isset( $old_settings[ $setting_to_check ] ) && $settings[ $setting_to_check ] !== $old_settings[ $setting_to_check ] ) {
				$should_clear_stripe_api_key = true;
				break;
			}
		}

		if ( $should_clear_stripe_api_key ) {
			WC_Stripe_API::set_secret_key( '' );
		}
	}

	/**
	 * Enable or disable UPE.
	 *
	 * When enabling UPE: For each currently enabled Stripe LPM, the corresponding UPE method is enabled.
	 *
	 * When disabling UPE: For each currently enabled UPE method, the corresponding LPM is enabled.
	 *
	 * @param array      $settings New settings to save.
	 * @param array|bool $old_settings Existing settings, if any.
	 * @return array New value but with defaults initially filled in for missing settings.
	 */
	protected function toggle_upe( $settings, $old_settings ) {
		if ( false === $old_settings || ! isset( $old_settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) ) {
			$old_settings = [ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME => 'no' ];
		}
		if ( ! isset( $settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) || $settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] === $old_settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) {
			return $settings;
		}

		if ( 'yes' === $settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) {
			return $this->enable_upe( $settings );
		}

		return $this->disable_upe( $settings );
	}

	protected function enable_upe( $settings ) {
		$settings['upe_checkout_experience_accepted_payments'] = [];

		if ( empty( $settings['upe_checkout_experience_accepted_payments'] ) ) {
			$settings['upe_checkout_experience_accepted_payments'] = [ 'card', 'link' ];
		} else {
			// The 'stripe' gateway must be enabled for UPE if any LPMs were enabled.
			$settings['enabled'] = 'yes';
		}

		return $settings;
	}

	protected function disable_upe( $settings ) {
		$upe_gateway            = new WC_Stripe_UPE_Payment_Gateway();
		$upe_enabled_method_ids = $upe_gateway->get_upe_enabled_payment_method_ids();
		// Disable main Stripe/card LPM if 'card' UPE method wasn't enabled.
		if ( ! in_array( 'card', $upe_enabled_method_ids, true ) ) {
			$settings['enabled'] = 'no';
		}
		// DISABLE ALL UPE METHODS
		if ( ! isset( $settings['upe_checkout_experience_accepted_payments'] ) ) {
			$settings['upe_checkout_experience_accepted_payments'] = [];
		}
		return $settings;
	}

	/**
	 * Adds the failed SCA auth email to WooCommerce.
	 *
	 * @param WC_Email[] $email_classes All existing emails.
	 * @return WC_Email[]
	 */
	public function add_emails( $email_classes ) {
		if ( ! class_exists( 'WC_Email', false ) ) {
			WC_Stripe_Logger::debug( 'WC_Email class not found, skipping email class addition' );
			return $email_classes;
		}

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-failed-authentication.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-failed-renewal-authentication.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-failed-preorder-authentication.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-failed-authentication-retry.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-failed-refund.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-admin-failed-refund.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-customer-failed-refund.php';

		// Add all emails, generated by the gateway.
		$email_classes['WC_Stripe_Email_Failed_Renewal_Authentication']  = new WC_Stripe_Email_Failed_Renewal_Authentication( $email_classes );
		$email_classes['WC_Stripe_Email_Failed_Preorder_Authentication'] = new WC_Stripe_Email_Failed_Preorder_Authentication( $email_classes );
		$email_classes['WC_Stripe_Email_Failed_Authentication_Retry']    = new WC_Stripe_Email_Failed_Authentication_Retry();
		$email_classes['WC_Stripe_Email_Admin_Failed_Refund']            = new WC_Stripe_Email_Admin_Failed_Refund();
		$email_classes['WC_Stripe_Email_Customer_Failed_Refund']         = new WC_Stripe_Email_Customer_Failed_Refund();

		return $email_classes;
	}

	/**
	 * Register REST API routes.
	 *
	 * New endpoints/controllers can be added here.
	 */
	public function register_routes() {
		/** API includes */
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-base-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/abstracts/abstract-wc-stripe-connect-rest-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-account-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-connection-tokens-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-locations-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-orders-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-tokens-controller.php';

		$connection_tokens_controller = new WC_REST_Stripe_Connection_Tokens_Controller( $this->get_main_stripe_gateway() );
		$locations_controller         = new WC_REST_Stripe_Locations_Controller();
		$orders_controller            = new WC_REST_Stripe_Orders_Controller( $this->get_main_stripe_gateway() );
		$stripe_tokens_controller     = new WC_REST_Stripe_Tokens_Controller();
		$stripe_account_controller    = new WC_REST_Stripe_Account_Controller( $this->get_main_stripe_gateway(), $this->account );

		$connection_tokens_controller->register_routes();
		$locations_controller->register_routes();
		$orders_controller->register_routes();
		$stripe_tokens_controller->register_routes();
		$stripe_account_controller->register_routes();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-settings-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-upe-flag-toggle-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-account-keys-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-oc-setting-toggle-controller.php';

		$upe_flag_toggle_controller = new WC_Stripe_REST_UPE_Flag_Toggle_Controller();
		$upe_flag_toggle_controller->register_routes();

		$settings_controller = new WC_REST_Stripe_Settings_Controller( $this->get_main_stripe_gateway() );
		$settings_controller->register_routes();

		$stripe_account_keys_controller = new WC_REST_Stripe_Account_Keys_Controller( $this->account );
		$stripe_account_keys_controller->register_routes();

		$oc_setting_toggle_controller = new WC_Stripe_REST_OC_Setting_Toggle_Controller( $this->get_main_stripe_gateway() );
		$oc_setting_toggle_controller->register_routes();

		$exit_survey_controller = new WC_REST_Stripe_Exit_Survey_Controller();
		$exit_survey_controller->register_routes();

		if ( WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() ) {
			$agentic_commerce_controller = new WC_REST_Stripe_Agentic_Commerce_Controller();
			$agentic_commerce_controller->register_routes();
		}
	}

	/**
	 * Returns the main Stripe payment gateway class instance.
	 *
	 * @return WC_Stripe_UPE_Payment_Gateway
	 */
	public function get_main_stripe_gateway() {
		if ( ! $this->stripe_gateway ) {
			$this->stripe_gateway = new WC_Stripe_UPE_Payment_Gateway();
		}

		return $this->stripe_gateway;
	}

	/**
	 * Move the email field to the top of the Checkout page.
	 *
	 * @param array $fields WooCommerce checkout fields.
	 *
	 * @return array WooCommerce checkout fields.
	 */
	public function checkout_update_email_field_priority( $fields ) {
		$gateway = $this->get_main_stripe_gateway();
		if ( isset( $fields['billing_email'] ) && WC_Stripe_UPE_Payment_Method_Link::is_link_enabled( $gateway ) ) {
			// Update the field priority.
			$fields['billing_email']['priority'] = 1;

			// Add extra `stripe-gateway-checkout-email-field` class.
			$fields['billing_email']['class'][] = 'stripe-gateway-checkout-email-field';
		}

		return $fields;
	}

	/**
	 * Initializes updating subscriptions.
	 */
	public function initialize_subscriptions_updater() {
		// The updater depends on WCS_Background_Repairer. Bail out if class does not exist.
		if ( ! class_exists( 'WCS_Background_Repairer' ) ) {
			return;
		}
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-subscriptions-repairer-legacy-sepa-tokens.php';

		$logger  = wc_get_logger();
		$updater = new WC_Stripe_Subscriptions_Repairer_Legacy_SEPA_Tokens( $logger );

		$updater->init();
		$updater->maybe_update();
	}

	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'woocommerce-gateway-stripe', false, WC_STRIPE_PLUGIN_PATH . '/languages' );
	}

	/**
	 * Initializes the status page.
	 *
	 * @return void
	 */
	public function initialize_status_page() {
		if ( ! is_admin() ) {
			return;
		}

		$wcstripe_status = new WC_Stripe_Status( self::get_main_stripe_gateway(), $this->account );
		$wcstripe_status->init_hooks();
	}

	/**
	 * Initialize Agentic Commerce product feed integration.
	 *
	 * Registers the integration with WooCommerce product feed system and
	 * sets up Action Scheduler for automated sync.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function initialize_agentic_commerce() {
		// Check if required WooCommerce interfaces exist before loading our integration class
		// (which implements IntegrationInterface at the class level and will fatal if it's missing).
		if ( ! interface_exists( \Automattic\WooCommerce\Internal\ProductFeed\Integrations\IntegrationInterface::class ) ) {
			return;
		}

		// Check if required classes exist.
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			return;
		}

		// Check if feature is enabled.
		if ( ! WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() ) {
			return;
		}

		// Create integration instance.
		$integration = new WC_Stripe_Agentic_Commerce_Integration();

		try {
			$product_feed = wc_get_container()->get( \Automattic\WooCommerce\Internal\ProductFeed\ProductFeed::class );
			$product_feed->register_integration( $integration );
		} catch ( \Exception $e ) {
			WC_Stripe_Logger::error(
				'Agentic Commerce: Failed to register integration with WooCommerce product feed',
				[ 'error' => $e->getMessage() ]
			);
			return;
		}

		// Register hooks for scheduled actions.
		$integration->register_hooks();

		// Schedule recurring sync if not already scheduled.
		if ( 'yes' !== get_option( WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_OPTION ) ) {
			$integration->activate();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'stripe agentic-commerce', 'WC_Stripe_Agentic_Commerce_CLI' );
		}

		/**
		 * Fires after Agentic Commerce integration is initialized.
		 *
		 * @since 10.5.0
		 * @param WC_Stripe_Agentic_Commerce_Integration $integration The integration instance.
		 */
		do_action( 'wc_stripe_agentic_commerce_initialized', $integration );
	}

	/**
	 * Toggle payment methods that should be enabled/disabled, e.g. unreleased,
	 * BNPLs when other official plugins are active, etc.
	 *
	 * @param WC_Payment_Gateways $gateways The WooCommerce Payment Gateways instance.
	 *
	 * @return void
	 */
	public function maybe_toggle_payment_methods( WC_Payment_Gateways $gateways ) {
		$gateway = $this->get_main_stripe_gateway();
		if ( ! is_a( $gateway, 'WC_Stripe_UPE_Payment_Gateway' ) ) {
			return;
		}

		$payment_method_ids_to_disable = [];
		$enabled_payment_methods       = $gateway->get_upe_enabled_payment_method_ids();

		// Check for BNPLs that should be deactivated.
		$payment_method_ids_to_disable = array_merge(
			$payment_method_ids_to_disable,
			$this->maybe_deactivate_bnpls( $gateways->payment_gateways, $enabled_payment_methods )
		);

		// Check if Amazon Pay should be deactivated.
		$payment_method_ids_to_disable = array_merge(
			$payment_method_ids_to_disable,
			$this->maybe_deactivate_amazon_pay( $enabled_payment_methods )
		);

		if ( [] === $payment_method_ids_to_disable ) {
			return;
		}

		$gateway->update_enabled_payment_methods(
			array_diff( $enabled_payment_methods, $payment_method_ids_to_disable )
		);
	}

	/**
	 * Deactivate Affirm or Klarna payment methods if other official plugins are active.
	 *
	 * @param array $available_payment_gateways The available payment gateways.
	 * @param array $enabled_payment_methods The enabled payment methods.
	 * @return array The payment method IDs to disable.
	 */
	private function maybe_deactivate_bnpls( $available_payment_gateways, $enabled_payment_methods ) {
		$has_affirm_plugin_active = WC_Stripe_Helper::has_gateway_plugin_active(
			WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
			$available_payment_gateways
		);
		$has_klarna_plugin_active = WC_Stripe_Helper::has_gateway_plugin_active(
			WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
			$available_payment_gateways
		);

		if ( ! $has_affirm_plugin_active && ! $has_klarna_plugin_active ) {
			return [];
		}

		$payment_method_ids_to_disable = [];
		if ( $has_affirm_plugin_active && in_array( WC_Stripe_Payment_Methods::AFFIRM, $enabled_payment_methods, true ) ) {
			$payment_method_ids_to_disable[] = WC_Stripe_Payment_Methods::AFFIRM;
		}
		if ( $has_klarna_plugin_active && in_array( WC_Stripe_Payment_Methods::KLARNA, $enabled_payment_methods, true ) ) {
			$payment_method_ids_to_disable[] = WC_Stripe_Payment_Methods::KLARNA;
		}

		return $payment_method_ids_to_disable;
	}

	/**
	 * Deactivate Amazon Pay if it's not available, i.e. unreleased.
	 *
	 * TODO: Remove this method once Amazon Pay is released.
	 *
	 * @param array $enabled_payment_methods The enabled payment methods.
	 * @return array Amazon Pay payment method ID, if it should be disabled.
	 */
	private function maybe_deactivate_amazon_pay( $enabled_payment_methods ) {
		// Safety guard only. Ideally, we will remove this method once Amazon Pay is released.
		if ( WC_Stripe_Feature_Flags::is_amazon_pay_available() ) {
			// Nothing to do if Amazon Pay is already released.
			return [];
		}

		if ( ! in_array( WC_Stripe_Payment_Methods::AMAZON_PAY, $enabled_payment_methods, true ) ) {
			// Nothing to do if Amazon Pay is not enabled.
			return [];
		}

		// Disable Amazon Pay.
		return [ WC_Stripe_Payment_Methods::AMAZON_PAY ];
	}

	/**
	 * Registers the admin hooks that drive the Stripe settings page UI.
	 *
	 * Inlined from the deprecated `WC_Stripe_Settings_Controller`.
	 */
	private function register_settings_admin_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_settings_admin_scripts' ] );
		add_action( 'wc_stripe_gateway_admin_options_wrapper', [ $this, 'render_settings_admin_options' ] );
		add_action( 'woocommerce_order_item_add_action_buttons', [ $this, 'hide_refund_button_for_uncaptured_orders' ] );

		// Priority 5 so we can manipulate the registered gateways before they are shown.
		add_action( 'woocommerce_admin_field_payment_gateways', [ self::class, 'hide_payment_gateways_on_settings_page' ], 5 );

		add_action( 'wp_ajax_wc_stripe_get_oauth_url', [ $this, 'ajax_get_oauth_url' ] );
	}

	/**
	 * Replaces the refund button with a disabled "Refunding unavailable" button
	 * for orders that have been authorized but not captured.
	 *
	 * @param WC_Order $order The order being viewed.
	 */
	public function hide_refund_button_for_uncaptured_orders( $order ) {
		try {
			$intent = $this->get_main_stripe_gateway()->get_intent_from_order( $order );

			if ( $intent && WC_Stripe_Intent_Status::REQUIRES_CAPTURE === $intent->status ) {
				$no_refunds_button  = __( 'Refunding unavailable', 'woocommerce-gateway-stripe' );
				$no_refunds_tooltip = __( 'Refunding via Stripe is unavailable because funds have not been captured for this order. Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-stripe' );
				echo '<style>.button.refund-items { display: none; }</style>';
				echo '<span class="button button-disabled">' . esc_html( $no_refunds_button ) . wp_kses_post( wc_help_tip( $no_refunds_tooltip ) ) . '</span>';
			}
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Error getting intent from order: ' . $order->get_id(), [ 'error_message' => $e->getMessage() ] );
		}
	}

	/**
	 * Prints the admin options wrapper for the Stripe settings page.
	 *
	 * @param WC_Stripe_Payment_Gateway $gateway The Stripe gateway.
	 */
	public function render_settings_admin_options( WC_Stripe_Payment_Gateway $gateway ) {
		global $hide_save_button;

		$hide_save_button = true;
		$return_url       = admin_url( 'admin.php?page=wc-settings&tab=checkout' );
		$header           = $gateway->get_method_title();
		$return_text      = __( 'Return to payments', 'woocommerce-gateway-stripe' );

		WC_Stripe_Helper::render_admin_header( $header, $return_text, $return_url );

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$account_data_exists = ( ! empty( $settings['publishable_key'] ) && ! empty( $settings['secret_key'] ) ) || ( ! empty( $settings['test_publishable_key'] ) && ! empty( $settings['test_secret_key'] ) );
		echo $account_data_exists ? '<div id="wc-stripe-account-settings-container"></div>' : '<div id="wc-stripe-new-account-container"></div>';
	}

	/**
	 * Determines whether the WooCommerce onboarding "payments" task has been completed.
	 */
	private function is_payments_onboarding_task_completed(): bool {
		$task_list = TaskLists::get_list( 'setup' );
		if ( empty( $task_list ) ) {
			return false;
		}

		$payments_task = $task_list->get_task( 'payments' );
		if ( empty( $payments_task ) ) {
			return false;
		}

		return $payments_task->is_complete();
	}

	/**
	 * AJAX handler that generates a Stripe Connect OAuth URL on demand.
	 */
	public function ajax_get_oauth_url() {
		if ( ! check_ajax_referer( 'wc_stripe_get_oauth_url', 'nonce', false ) ||
			! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'woocommerce-gateway-stripe' ) ] );
			return;
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'test';
		if ( ! in_array( $mode, [ 'live', 'test' ], true ) ) {
			$mode = 'test';
		}

		$oauth_url = $this->connect->get_oauth_url( '', $mode );

		if ( is_wp_error( $oauth_url ) ) {
			wp_send_json_error( [ 'message' => $oauth_url->get_error_message() ] );
			return;
		}

		wp_send_json_success( [ 'oauth_url' => $oauth_url ] );
	}

	/**
	 * Loads the Stripe settings page assets.
	 */
	public function enqueue_settings_admin_scripts( $hook_suffix ) {
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

		$gateway = $this->get_main_stripe_gateway();

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

		$message = sprintf(
			/* translators: 1) Html strong opening tag 2) Html strong closing tag */
			esc_html__( '%1$sWarning:%2$s your site\'s time does not match the time on your browser and may be incorrect. Some payment methods depend on webhook verification and verifying webhooks with a signing secret depends on your site\'s time being correct, so please check your site\'s time before setting a webhook secret. You may need to contact your site\'s hosting provider to correct the site\'s time.', 'woocommerce-gateway-stripe' ),
			'<strong>',
			'</strong>'
		);

		$enabled_payment_methods    = $gateway->get_upe_enabled_payment_method_ids();
		$show_bnpl_promotion_banner = get_option( 'wc_stripe_show_bnpl_promotion_banner', 'yes' ) === 'yes'
			// Show the BNPL promotional banner only if no BNPL payment methods are enabled.
			&& ! array_intersect( WC_Stripe_Payment_Methods::BNPL_PAYMENT_METHODS, $enabled_payment_methods );

		$is_oc_enabled = $gateway->is_oc_enabled();

		$show_oc_promotion_banner = get_option( 'wc_stripe_show_oc_promotion_banner', 'yes' ) === 'yes'
			// Show the OC promotional banner only if OC is disabled
			&& ! $is_oc_enabled;

		$show_stripe_tax_banner = get_option( 'wc_stripe_show_stripe_tax_banner', 'yes' ) === 'yes'
			// Show the Stripe Tax banner only if OC is enabled
			&& $is_oc_enabled;

		$is_ap_available_for_account = WC_Stripe_Helper::is_adaptive_pricing_available_for_account();

		$params = [
			'time'                                  => time(),
			'i18n_out_of_sync'                      => $message,
			'is_upe_checkout_enabled'               => true,
			'show_customization_notice'             => get_option( 'wc_stripe_show_customization_notice', 'yes' ) === 'yes' ? true : false,
			'show_optimized_checkout_notice'        => get_option( 'wc_stripe_show_optimized_checkout_notice', 'yes' ) === 'yes' ? true : false,
			'show_bnpl_promotional_banner'          => $show_bnpl_promotion_banner,
			'show_oc_promotional_banner'            => $show_oc_promotion_banner,
			'show_stripe_tax_banner'                => $show_stripe_tax_banner,
			'is_test_mode'                          => $gateway->is_in_test_mode(),
			'plugin_version'                        => WC_STRIPE_VERSION,
			'account_country'                       => $this->account->get_account_country(),
			'are_apms_deprecated'                   => false,
			'is_amazon_pay_available'               => WC_Stripe_Feature_Flags::is_amazon_pay_available(),
			'is_oc_available'                       => WC_Stripe_Feature_Flags::is_oc_available(),
			'is_oc_enabled'                         => $is_oc_enabled,
			'is_cs_available'                       => WC_Stripe_Feature_Flags::is_checkout_sessions_available() && $is_ap_available_for_account,
			'oc_layout'                             => $gateway->get_validated_option( 'optimized_checkout_layout' ),
			'oauth_nonce'                           => wp_create_nonce( 'wc_stripe_get_oauth_url' ),
			'is_sepa_tokens_for_ideal_enabled'      => 'yes' === $gateway->get_option( 'sepa_tokens_for_ideal', 'no' ),
			'is_sepa_tokens_for_bancontact_enabled' => 'yes' === $gateway->get_option( 'sepa_tokens_for_bancontact', 'no' ),
			'has_affirm_gateway_plugin'             => WC_Stripe_Helper::has_gateway_plugin_active( WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM ),
			'has_klarna_gateway_plugin'             => WC_Stripe_Helper::has_gateway_plugin_active( WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA ),
			'has_other_bnpl_plugins'                => WC_Stripe_Helper::has_other_bnpl_plugins_active(),
			'is_payments_onboarding_task_completed' => $this->is_payments_onboarding_task_completed(),
			'taxes_based_on_billing'                => wc_tax_enabled() && 'billing' === get_option( 'woocommerce_tax_based_on' ),
			'is_card_method_enabled'                => in_array( WC_Stripe_Payment_Methods::CARD, $enabled_payment_methods, true ),
			'is_agentic_commerce_enabled'           => WC_Stripe_Feature_Flags::is_agentic_commerce_enabled(),
			'agentic_commerce_import_sets_url'      => $gateway->is_in_test_mode()
				? 'https://dashboard.stripe.com/test/data-management/import-sets'
				: 'https://dashboard.stripe.com/data-management/import-sets',
			'agentic_commerce_logs_url'             => $this->get_agentic_commerce_logs_url(),
			'show_stripe_first_method_notice'       => WC_Stripe_Helper::should_show_stripe_first_method_notice(),
		];
		$params = array_merge( $params, WC_Stripe_Helper::get_exit_survey_params( $this->account ) );
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
	 * Build a URL to the WooCommerce logs page pre-filtered to the Stripe log source.
	 */
	private function get_agentic_commerce_logs_url(): string {
		return admin_url(
			'admin.php?page=wc-status&tab=logs&source=' . rawurlencode( WC_Stripe_Logger::WC_LOG_FILENAME )
		);
	}

	/**
	 * Removes Stripe alternative payment methods from the WooCommerce Settings page gateway list.
	 *
	 * Hooked to `woocommerce_admin_field_payment_gateways`.
	 */
	public static function hide_payment_gateways_on_settings_page() {
		// Skip in the new payments settings experience (React-based UI).
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) && \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'reactify-classic-payments-settings' ) ) {
			return;
		}

		$gateways_to_hide = [
			WC_Stripe_UPE_Payment_Method::class,
		];

		foreach ( WC()->payment_gateways->payment_gateways as $index => $payment_gateway ) {
			foreach ( $gateways_to_hide as $gateway_to_hide ) {
				if ( $payment_gateway instanceof $gateway_to_hide ) {
					unset( WC()->payment_gateways->payment_gateways[ $index ] );
					break;
				}
			}
		}
	}
}
