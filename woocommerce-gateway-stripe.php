<?php
/**
 * Plugin Name: WooCommerce Stripe Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-stripe/
 * Description: Take credit card payments on your store using Stripe.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Version: 8.0.1
 * Requires at least: 6.1
 * Tested up to: 6.4.3
 * WC requires at least: 8.2
 * WC tested up to: 8.6.1
 * Text Domain: woocommerce-gateway-stripe
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_STRIPE_VERSION', '8.0.1' ); // WRCS: DEFINED_VERSION.
define( 'WC_STRIPE_MIN_PHP_VER', '7.3.0' );
define( 'WC_STRIPE_MIN_WC_VER', '7.4' );
define( 'WC_STRIPE_FUTURE_MIN_WC_VER', '7.5' );
define( 'WC_STRIPE_MAIN_FILE', __FILE__ );
define( 'WC_STRIPE_ABSPATH', __DIR__ . '/' );
define( 'WC_STRIPE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_STRIPE_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 */
function woocommerce_stripe_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Stripe requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-stripe' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 4.4.0
 */
function woocommerce_stripe_wc_not_supported() {
	/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Stripe requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'woocommerce-gateway-stripe' ), esc_html( WC_STRIPE_MIN_WC_VER ), esc_html( WC_VERSION ) ) . '</strong></p></div>';
}

function woocommerce_gateway_stripe() {

	static $plugin;

	if ( ! isset( $plugin ) ) {

		class WC_Stripe {

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
			 * @var WC_Stripe_Payment_Request
			 */
			public $payment_request_configuration;

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
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-privacy.php';
				}

				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-feature-flags.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-upe-compatibility.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-exception.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-logger.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-helper.php';
				include_once dirname( __FILE__ ) . '/includes/class-wc-stripe-api.php';
				require_once dirname( __FILE__ ) . '/includes/compat/trait-wc-stripe-subscriptions-utilities.php';
				require_once dirname( __FILE__ ) . '/includes/compat/trait-wc-stripe-subscriptions.php';
				require_once dirname( __FILE__ ) . '/includes/compat/trait-wc-stripe-pre-orders.php';
				require_once dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-stripe-payment-gateway.php';
				require_once dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-stripe-payment-gateway-voucher.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-webhook-state.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-webhook-handler.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-sepa-payment-token.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-link-payment-token.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-apple-pay-registration.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-stripe.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-gateway.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-cc.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-alipay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-giropay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-ideal.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-bancontact.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-boleto.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-oxxo.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-eps.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-sepa.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-p24.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-sofort.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-upe-payment-method-link.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-bancontact.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-sofort.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-giropay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-eps.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-ideal.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-p24.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-alipay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-sepa.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-multibanco.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-boleto.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-oxxo.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-payment-request.php';
				require_once dirname( __FILE__ ) . '/includes/compat/class-wc-stripe-woo-compat-utils.php';
				require_once dirname( __FILE__ ) . '/includes/connect/class-wc-stripe-connect.php';
				require_once dirname( __FILE__ ) . '/includes/connect/class-wc-stripe-connect-api.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-action-scheduler-service.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-order-handler.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-payment-tokens.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-customer.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-intent-controller.php';
				require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-inbox-notes.php';
				require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-upe-compatibility-controller.php';
				require_once dirname( __FILE__ ) . '/includes/migrations/class-allowed-payment-request-button-types-update.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-account.php';
				new Allowed_Payment_Request_Button_Types_Update();

				$this->api                           = new WC_Stripe_Connect_API();
				$this->connect                       = new WC_Stripe_Connect( $this->api );
				$this->payment_request_configuration = new WC_Stripe_Payment_Request();
				$this->account                       = new WC_Stripe_Account( $this->connect, 'WC_Stripe_API' );

				$intent_controller = new WC_Stripe_Intent_Controller();
				$intent_controller->init_hooks();

				if ( is_admin() ) {
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-admin-notices.php';
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-settings-controller.php';

					if ( WC_Stripe_Feature_Flags::is_upe_preview_enabled() ) {
						require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-old-settings-upe-toggle-controller.php';
						new WC_Stripe_Old_Settings_UPE_Toggle_Controller();
					}

					if ( isset( $_GET['area'] ) && 'payment_requests' === $_GET['area'] ) {
						require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-payment-requests-controller.php';
						new WC_Stripe_Payment_Requests_Controller();
					} else {
						new WC_Stripe_Settings_Controller( $this->account );
					}

					if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
						require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-payment-gateways-controller.php';
						new WC_Stripe_Payment_Gateways_Controller();
					}
				}

				// REMOVE IN THE FUTURE.
				require_once dirname( __FILE__ ) . '/includes/deprecated/class-wc-stripe-apple-pay.php';

				add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );
				add_filter( 'pre_update_option_woocommerce_stripe_settings', [ $this, 'gateway_settings_update' ], 10, 2 );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
				add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

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
				if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					return;
				}

				if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_STRIPE_VERSION !== get_option( 'wc_stripe_version' ) ) ) {
					do_action( 'woocommerce_stripe_updated' );

					if ( ! defined( 'WC_STRIPE_INSTALLING' ) ) {
						define( 'WC_STRIPE_INSTALLING', true );
					}

					add_woocommerce_inbox_variant();
					$this->update_plugin_version();

					// TODO: Remove this when we're reasonably sure most merchants have had their
					// settings updated like this. ~80% of merchants is a good threshold.
					// - @reykjalin
					$this->update_prb_location_settings();
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
				$stripe_settings = get_option( 'woocommerce_stripe_settings', [] );
				$prb_locations   = isset( $stripe_settings['payment_request_button_locations'] )
					? $stripe_settings['payment_request_button_locations']
					: [];
				if ( ! empty( $stripe_settings ) && empty( $prb_locations ) ) {
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

					$stripe_settings['payment_request_button_locations'] = $new_prb_locations;
					update_option( 'woocommerce_stripe_settings', $stripe_settings );
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
				if ( plugin_basename( __FILE__ ) === $file ) {
					$row_meta = [
						'docs'    => '<a href="' . esc_url( apply_filters( 'woocommerce_gateway_stripe_docs_url', 'https://woocommerce.com/document/stripe/' ) ) . '" title="' . esc_attr( __( 'View Documentation', 'woocommerce-gateway-stripe' ) ) . '">' . __( 'Docs', 'woocommerce-gateway-stripe' ) . '</a>',
						'support' => '<a href="' . esc_url( apply_filters( 'woocommerce_gateway_stripe_support_url', 'https://woocommerce.com/my-account/create-a-ticket?select=18627' ) ) . '" title="' . esc_attr( __( 'Open a support request at WooCommerce.com', 'woocommerce-gateway-stripe' ) ) . '">' . __( 'Support', 'woocommerce-gateway-stripe' ) . '</a>',
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
				$main_gateway = $this->get_main_stripe_gateway();
				$methods[]    = $main_gateway;

				// These payment gateways will be visible in the main settings page, if UPE enabled.
				if ( is_a( $main_gateway, 'WC_Stripe_UPE_Payment_Gateway' ) ) {
					// The $main_gateway represents the card gateway so we don't want to include it in the list of UPE gateways.
					$upe_payment_methods = $main_gateway->payment_methods;
					unset( $upe_payment_methods['card'] );

					$methods = array_merge( $methods, $upe_payment_methods );
				} else {
					// These payment gateways will not be included in the gateway list when UPE is enabled:
					$methods[] = WC_Gateway_Stripe_Alipay::class;
					$methods[] = WC_Gateway_Stripe_Sepa::class;
					$methods[] = WC_Gateway_Stripe_Giropay::class;
					$methods[] = WC_Gateway_Stripe_Ideal::class;
					$methods[] = WC_Gateway_Stripe_Bancontact::class;
					$methods[] = WC_Gateway_Stripe_Eps::class;
					$methods[] = WC_Gateway_Stripe_P24::class;
					$methods[] = WC_Gateway_Stripe_Boleto::class;
					$methods[] = WC_Gateway_Stripe_Oxxo::class;

					/** Show Sofort if it's already enabled. Hide from the new merchants and keep it for the old ones who are already using this gateway, until we remove it completely.
					 * Stripe is deprecating Sofort https://support.stripe.com/questions/sofort-is-being-deprecated-as-a-standalone-payment-method.
					 */
					$sofort_settings = get_option( 'woocommerce_stripe_sofort_settings', [] );
					if ( isset( $sofort_settings['enabled'] ) && 'yes' === $sofort_settings['enabled'] ) {
						$methods[] = WC_Gateway_Stripe_Sofort::class;
					}
				}

				// Multibanco will always be added to the gateway list, regardless if UPE is enabled or disabled:
				$methods[] = WC_Gateway_Stripe_Multibanco::class;

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
				if ( WC_Stripe_Feature_Flags::is_upe_preview_enabled() ) {
					unset( $sections['stripe_upe'] );
				}
				unset( $sections['stripe_bancontact'] );
				unset( $sections['stripe_sofort'] );
				unset( $sections['stripe_giropay'] );
				unset( $sections['stripe_eps'] );
				unset( $sections['stripe_ideal'] );
				unset( $sections['stripe_p24'] );
				unset( $sections['stripe_alipay'] );
				unset( $sections['stripe_sepa'] );
				unset( $sections['stripe_multibanco'] );

				$sections['stripe'] = 'Stripe';
				if ( WC_Stripe_Feature_Flags::is_upe_preview_enabled() ) {
					$sections['stripe_upe'] = 'Stripe checkout experience';
				}
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
					$gateway      = new WC_Gateway_Stripe();
					$fields       = $gateway->get_form_fields();
					$old_settings = array_merge( array_fill_keys( array_keys( $fields ), '' ), wp_list_pluck( $fields, 'default' ) );
					$settings     = array_merge( $old_settings, $settings );
				}

				if ( ! WC_Stripe_Feature_Flags::is_upe_preview_enabled() ) {
					return $settings;
				}

				return $this->toggle_upe( $settings, $old_settings );
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

				$payment_gateways = WC_Stripe_Helper::get_legacy_payment_methods();
				foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $method_class ) {
					if ( ! defined( "$method_class::LPM_GATEWAY_CLASS" ) ) {
						continue;
					}

					$lpm_gateway_id = constant( $method_class::LPM_GATEWAY_CLASS . '::ID' );
					if ( isset( $payment_gateways[ $lpm_gateway_id ] ) && $payment_gateways[ $lpm_gateway_id ]->is_enabled() ) {
						// DISABLE LPM
						/**
						 * TODO: This can be replaced with:
						 *
						 *   $payment_gateways[ $lpm_gateway_id ]->update_option( 'enabled', 'no' );
						 *   $payment_gateways[ $lpm_gateway_id ]->enabled = 'no';
						 *
						 * ...once the minimum WC version is 3.4.0.
						 */
						$payment_gateways[ $lpm_gateway_id ]->settings['enabled'] = 'no';
						update_option(
							$payment_gateways[ $lpm_gateway_id ]->get_option_key(),
							apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $payment_gateways[ $lpm_gateway_id ]::ID, $payment_gateways[ $lpm_gateway_id ]->settings ),
							'yes'
						);
						// ENABLE UPE METHOD
						$settings['upe_checkout_experience_accepted_payments'][] = $method_class::STRIPE_ID;
					}

					if ( 'stripe' === $lpm_gateway_id && isset( $this->stripe_gateway ) && $this->stripe_gateway->is_enabled() ) {
						$settings['upe_checkout_experience_accepted_payments'][] = 'card';
					}
				}
				if ( empty( $settings['upe_checkout_experience_accepted_payments'] ) ) {
					$settings['upe_checkout_experience_accepted_payments'] = [ 'card' ];
				} else {
					// The 'stripe' gateway must be enabled for UPE if any LPMs were enabled.
					$settings['enabled'] = 'yes';
				}

				return $settings;
			}

			protected function disable_upe( $settings ) {
				$upe_gateway            = new WC_Stripe_UPE_Payment_Gateway();
				$upe_enabled_method_ids = $upe_gateway->get_upe_enabled_payment_method_ids();
				foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $method_class ) {
					if ( ! defined( "$method_class::LPM_GATEWAY_CLASS" ) || ! in_array( $method_class::STRIPE_ID, $upe_enabled_method_ids, true ) ) {
						continue;
					}
					// ENABLE LPM
					$gateway_class = $method_class::LPM_GATEWAY_CLASS;
					$gateway       = new $gateway_class();
					/**
					 * TODO: This can be replaced with:
					 *
					 *   $gateway->update_option( 'enabled', 'yes' );
					 *
					 * ...once the minimum WC version is 3.4.0.
					 */
					$gateway->settings['enabled'] = 'yes';
					update_option( $gateway->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $gateway::ID, $gateway->settings ), 'yes' );
				}
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
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-failed-authentication.php';
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-failed-renewal-authentication.php';
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-failed-preorder-authentication.php';
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/compat/class-wc-stripe-email-failed-authentication-retry.php';

				// Add all emails, generated by the gateway.
				$email_classes['WC_Stripe_Email_Failed_Renewal_Authentication']  = new WC_Stripe_Email_Failed_Renewal_Authentication( $email_classes );
				$email_classes['WC_Stripe_Email_Failed_Preorder_Authentication'] = new WC_Stripe_Email_Failed_Preorder_Authentication( $email_classes );
				$email_classes['WC_Stripe_Email_Failed_Authentication_Retry']    = new WC_Stripe_Email_Failed_Authentication_Retry( $email_classes );

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
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/connect/class-wc-stripe-connect-rest-oauth-init-controller.php';
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/connect/class-wc-stripe-connect-rest-oauth-connect-controller.php';

				$connection_tokens_controller = new WC_REST_Stripe_Connection_Tokens_Controller( $this->get_main_stripe_gateway() );
				$locations_controller         = new WC_REST_Stripe_Locations_Controller();
				$orders_controller            = new WC_REST_Stripe_Orders_Controller( $this->get_main_stripe_gateway() );
				$stripe_tokens_controller     = new WC_REST_Stripe_Tokens_Controller();
				$oauth_init                   = new WC_Stripe_Connect_REST_Oauth_Init_Controller( $this->connect, $this->api );
				$oauth_connect                = new WC_Stripe_Connect_REST_Oauth_Connect_Controller( $this->connect, $this->api );
				$stripe_account_controller    = new WC_REST_Stripe_Account_Controller( $this->get_main_stripe_gateway(), $this->account );

				$connection_tokens_controller->register_routes();
				$locations_controller->register_routes();
				$orders_controller->register_routes();
				$stripe_tokens_controller->register_routes();
				$oauth_init->register_routes();
				$oauth_connect->register_routes();
				$stripe_account_controller->register_routes();

				if ( WC_Stripe_Feature_Flags::is_upe_preview_enabled() ) {
					require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-settings-controller.php';
					require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-upe-flag-toggle-controller.php';
					require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-account-keys-controller.php';

					$upe_flag_toggle_controller = new WC_Stripe_REST_UPE_Flag_Toggle_Controller();
					$upe_flag_toggle_controller->register_routes();

					$settings_controller = new WC_REST_Stripe_Settings_Controller( $this->get_main_stripe_gateway() );
					$settings_controller->register_routes();

					$stripe_account_keys_controller = new WC_REST_Stripe_Account_Keys_Controller( $this->account );
					$stripe_account_keys_controller->register_routes();
				}
			}

			/**
			 * Returns the main Stripe payment gateway class instance.
			 *
			 * @return WC_Stripe_Payment_Gateway
			 */
			public function get_main_stripe_gateway() {
				if ( ! is_null( $this->stripe_gateway ) ) {
					return $this->stripe_gateway;
				}

				if ( WC_Stripe_Feature_Flags::is_upe_preview_enabled() && WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
					$this->stripe_gateway = new WC_Stripe_UPE_Payment_Gateway();

					return $this->stripe_gateway;
				}

				$this->stripe_gateway = new WC_Gateway_Stripe();

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
				if ( isset( $fields['billing_email'] ) && WC_Stripe_UPE_Payment_Method_Link::is_link_enabled() ) {
					// Update the field priority.
					$fields['billing_email']['priority'] = 1;

					// Add extra `wcpay-checkout-email-field` class.
					$fields['billing_email']['class'][] = 'stripe-gateway-checkout-email-field';

					// Append StripeLink modal trigger button for logged in users.
					$fields['billing_email']['label'] = $fields['billing_email']['label']
						. ' <button class="stripe-gateway-stripelink-modal-trigger"></button>';
				}

				return $fields;
			}
		}

		$plugin = WC_Stripe::get_instance();

	}

	return $plugin;
}

add_action( 'plugins_loaded', 'woocommerce_gateway_stripe_init' );

function woocommerce_gateway_stripe_init() {
	load_plugin_textdomain( 'woocommerce-gateway-stripe', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_stripe_missing_wc_notice' );
		return;
	}

	if ( version_compare( WC_VERSION, WC_STRIPE_MIN_WC_VER, '<' ) ) {
		add_action( 'admin_notices', 'woocommerce_stripe_wc_not_supported' );
		return;
	}

	woocommerce_gateway_stripe();
}

/**
 * Add woocommerce_inbox_variant for the Remote Inbox Notification.
 *
 * P2 post can be found at https://wp.me/paJDYF-1uJ.
 */
if ( ! function_exists( 'add_woocommerce_inbox_variant' ) ) {
	function add_woocommerce_inbox_variant() {
		$config_name = 'woocommerce_inbox_variant_assignment';
		if ( false === get_option( $config_name, false ) ) {
			update_option( $config_name, wp_rand( 1, 12 ) );
		}
	}
}
register_activation_hook( __FILE__, 'add_woocommerce_inbox_variant' );

function wcstripe_deactivated() {
	// admin notes are not supported on older versions of WooCommerce.
	require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-upe-compatibility.php';
	if ( class_exists( 'WC_Stripe_Inbox_Notes' ) && WC_Stripe_Inbox_Notes::are_inbox_notes_supported() ) {
		// requirements for the note
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-feature-flags.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-availability-note.php';
		WC_Stripe_UPE_Availability_Note::possibly_delete_note();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-stripelink-note.php';
		WC_Stripe_UPE_StripeLink_Note::possibly_delete_note();
	}
}
register_deactivation_hook( __FILE__, 'wcstripe_deactivated' );

// Hook in Blocks integration. This action is called in a callback on plugins loaded, so current Stripe plugin class
// implementation is too late.
add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_stripe_woocommerce_block_support' );

function woocommerce_gateway_stripe_woocommerce_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-blocks-support.php';
		// priority is important here because this ensures this integration is
		// registered before the WooCommerce Blocks built-in Stripe registration.
		// Blocks code has a check in place to only register if 'stripe' is not
		// already registered.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				// I noticed some incompatibility with WP 5.x and WC 5.3 when `_wcstripe_feature_upe_settings` is enabled.
				if ( ! class_exists( 'WC_Stripe_Payment_Request' ) ) {
					return;
				}

				$container = Automattic\WooCommerce\Blocks\Package::container();
				// registers as shared instance.
				$container->register(
					WC_Stripe_Blocks_Support::class,
					function() {
						if ( class_exists( 'WC_Stripe' ) ) {
							return new WC_Stripe_Blocks_Support( WC_Stripe::get_instance()->payment_request_configuration );
						} else {
							return new WC_Stripe_Blocks_Support();
						}
					}
				);
				$payment_method_registry->register(
					$container->get( WC_Stripe_Blocks_Support::class )
				);
			},
			5
		);
	}
}

add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
