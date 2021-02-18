<?php
/**
 * Plugin Name: WooCommerce Stripe Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-stripe/
 * Description: Take credit card payments on your store using Stripe.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Version: 4.8.0
 * Requires at least: 4.4
 * Tested up to: 5.6
 * WC requires at least: 3.0
 * WC tested up to: 4.9
 * Text Domain: woocommerce-gateway-stripe
 * Domain Path: /languages
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_STRIPE_VERSION', '4.8.0' ); // WRCS: DEFINED_VERSION.
define( 'WC_STRIPE_MIN_PHP_VER', '5.6.0' );
define( 'WC_STRIPE_MIN_WC_VER', '3.0' );
define( 'WC_STRIPE_FUTURE_MIN_WC_VER', '3.3' );
define( 'WC_STRIPE_MAIN_FILE', __FILE__ );
define( 'WC_STRIPE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_STRIPE_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 * @return string
 */
function woocommerce_stripe_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Stripe requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-stripe' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 4.4.0
 * @return string
 */
function woocommerce_stripe_wc_not_supported() {
	/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Stripe requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'woocommerce-gateway-stripe' ), WC_STRIPE_MIN_WC_VER, WC_VERSION ) . '</strong></p></div>';
}

function woocommerce_gateway_stripe() {

	static $plugin;

	if ( ! isset( $plugin ) ) {

		class WC_Stripe {

			/**
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
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
				add_action( 'admin_init', array( $this, 'install' ) );

				$this->init();

				$this->api     = new WC_Stripe_Connect_API();
				$this->connect = new WC_Stripe_Connect( $this->api );

				add_action( 'rest_api_init', array( $this, 'register_connect_routes' ) );
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function init() {
				if ( is_admin() ) {
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-privacy.php';
				}

				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-exception.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-logger.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-helper.php';
				include_once dirname( __FILE__ ) . '/includes/class-wc-stripe-api.php';
				require_once dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-stripe-payment-gateway.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-webhook-handler.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-sepa-payment-token.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-apple-pay-registration.php';
				require_once dirname( __FILE__ ) . '/includes/compat/class-wc-stripe-pre-orders-compat.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-stripe.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-bancontact.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-sofort.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-giropay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-eps.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-ideal.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-p24.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-alipay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-sepa.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-multibanco.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-stripe-payment-request.php';
				require_once dirname( __FILE__ ) . '/includes/compat/class-wc-stripe-subs-compat.php';
				require_once dirname( __FILE__ ) . '/includes/compat/class-wc-stripe-sepa-subs-compat.php';
				require_once dirname( __FILE__ ) . '/includes/connect/class-wc-stripe-connect.php';
				require_once dirname( __FILE__ ) . '/includes/connect/class-wc-stripe-connect-api.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-order-handler.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-payment-tokens.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-customer.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-stripe-intent-controller.php';
				require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-inbox-notes.php';

				if ( is_admin() ) {
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-stripe-admin-notices.php';
				}

				// REMOVE IN THE FUTURE.
				require_once dirname( __FILE__ ) . '/includes/deprecated/class-wc-stripe-apple-pay.php';

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
				add_filter( 'pre_update_option_woocommerce_stripe_settings', array( $this, 'gateway_settings_update' ), 10, 2 );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
				add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

				// Modify emails emails.
				add_filter( 'woocommerce_email_classes', array( $this, 'add_emails' ), 20 );

				if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
					add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_gateway_order_admin' ) );
				}
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

					$this->update_plugin_version();
				}
			}

			/**
			 * Add plugin action links.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function plugin_action_links( $links ) {
				$plugin_links = array(
					'<a href="admin.php?page=wc-settings&tab=checkout&section=stripe">' . esc_html__( 'Settings', 'woocommerce-gateway-stripe' ) . '</a>',
				);
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
					$row_meta = array(
						'docs'    => '<a href="' . esc_url( apply_filters( 'woocommerce_gateway_stripe_docs_url', 'https://docs.woocommerce.com/document/stripe/' ) ) . '" title="' . esc_attr( __( 'View Documentation', 'woocommerce-gateway-stripe' ) ) . '">' . __( 'Docs', 'woocommerce-gateway-stripe' ) . '</a>',
						'support' => '<a href="' . esc_url( apply_filters( 'woocommerce_gateway_stripe_support_url', 'https://woocommerce.com/my-account/create-a-ticket?select=18627' ) ) . '" title="' . esc_attr( __( 'Open a support request at WooCommerce.com', 'woocommerce-gateway-stripe' ) ) . '">' . __( 'Support', 'woocommerce-gateway-stripe' ) . '</a>',
					);
					return array_merge( $links, $row_meta );
				}
				return (array) $links;
			}

			/**
			 * Add the gateways to WooCommerce.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function add_gateways( $methods ) {
				if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
					$methods[] = 'WC_Stripe_Subs_Compat';
					$methods[] = 'WC_Stripe_Sepa_Subs_Compat';
				} else {
					$methods[] = 'WC_Gateway_Stripe';
					$methods[] = 'WC_Gateway_Stripe_Sepa';
				}

				$methods[] = 'WC_Gateway_Stripe_Bancontact';
				$methods[] = 'WC_Gateway_Stripe_Sofort';
				$methods[] = 'WC_Gateway_Stripe_Giropay';
				$methods[] = 'WC_Gateway_Stripe_Eps';
				$methods[] = 'WC_Gateway_Stripe_Ideal';
				$methods[] = 'WC_Gateway_Stripe_P24';
				$methods[] = 'WC_Gateway_Stripe_Alipay';
				$methods[] = 'WC_Gateway_Stripe_Multibanco';

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

				$sections['stripe']            = 'Stripe';
				$sections['stripe_bancontact'] = __( 'Stripe Bancontact', 'woocommerce-gateway-stripe' );
				$sections['stripe_sofort']     = __( 'Stripe SOFORT', 'woocommerce-gateway-stripe' );
				$sections['stripe_giropay']    = __( 'Stripe Giropay', 'woocommerce-gateway-stripe' );
				$sections['stripe_eps']        = __( 'Stripe EPS', 'woocommerce-gateway-stripe' );
				$sections['stripe_ideal']      = __( 'Stripe iDeal', 'woocommerce-gateway-stripe' );
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
			 * @param array $settings New settings to save
			 * @param array|bool $old_settings Existing settings, if any.
			 * @return array New value but with defaults initially filled in for missing settings.
			 */
			public function gateway_settings_update( $settings, $old_settings ) {
				if ( false === $old_settings ) {
					$gateway  = new WC_Gateway_Stripe();
					$fields   = $gateway->get_form_fields();
					$defaults = array_merge( array_fill_keys( array_keys( $fields ), '' ), wp_list_pluck( $fields, 'default' ) );
					return array_merge( $defaults, $settings );
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
				$email_classes['WC_Stripe_Email_Failed_Authentication_Retry'] = new WC_Stripe_Email_Failed_Authentication_Retry( $email_classes );

				return $email_classes;
			}

			/**
			 * Register Stripe connect rest routes.
			 */
			public function register_connect_routes() {

				require_once WC_STRIPE_PLUGIN_PATH . '/includes/abstracts/abstract-wc-stripe-connect-rest-controller.php';
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/connect/class-wc-stripe-connect-rest-oauth-init-controller.php';
				require_once WC_STRIPE_PLUGIN_PATH . '/includes/connect/class-wc-stripe-connect-rest-oauth-connect-controller.php';

				$oauth_init    = new WC_Stripe_Connect_REST_Oauth_Init_Controller( $this->connect, $this->api );
				$oauth_connect = new WC_Stripe_Connect_REST_Oauth_Connect_Controller( $this->connect, $this->api );

				$oauth_init->register_routes();
				$oauth_connect->register_routes();
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
