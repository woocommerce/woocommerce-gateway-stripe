<?php
/**
 * Plugin Name: WooCommerce Stripe Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-stripe/
 * Description: Take credit card payments on your store using Stripe.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Version: 3.2.2
 * Text Domain: woocommerce-gateway-stripe
 * Domain Path: /languages
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Stripe' ) ) :
	/**
	 * Required minimums and constants
	 */
	define( 'WC_STRIPE_VERSION', '3.2.2' );
	define( 'WC_STRIPE_MIN_PHP_VER', '5.6.0' );
	define( 'WC_STRIPE_MIN_WC_VER', '2.5.0' );
	define( 'WC_STRIPE_MAIN_FILE', __FILE__ );
	define( 'WC_STRIPE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
	define( 'WC_STRIPE_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

	class WC_Stripe {

		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * @var Reference to logging class.
		 */
		private static $log;

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
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {}

		/**
		 * Flag to indicate whether or not we need to load code for / support subscriptions.
		 *
		 * @var bool
		 */
		private $subscription_support_enabled = false;

		/**
		 * Flag to indicate whether or not we need to load support for pre-orders.
		 *
		 * @since 3.0.3
		 *
		 * @var bool
		 */
		private $pre_order_enabled = false;

		/**
		 * Notices (array)
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		private function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 *
		 * @since 1.0.0
		 * @version 4.0.0
		 */
		public function init() {
			require_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-api.php' );

			// Don't hook anything else in the plugin if we're in an incompatible environment
			if ( self::get_environment_warning() ) {
				return;
			}

			require_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-logger.php' );
			require_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-helper.php' );
			require_once( dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-stripe-payment-gateway.php' );
			require_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-webhook-handler.php' );
			require_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-stripe.php' );
			require_once( dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-bancontact.php' );
			require_once( dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-sofort.php' );
			require_once( dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-giropay.php' );
			require_once( dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-ideal.php' );
			require_once( dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-alipay.php' );
			require_once( dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-sepa.php' );
			require_once( dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-stripe-bitcoin.php' );
			require_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-order-handler.php' );
			require_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-payment-tokens.php' );
			require_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-customer.php' );

			// Init the gateway itself
			$this->init_gateways();

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'wp_ajax_stripe_dismiss_request_api_notice', array( $this, 'dismiss_request_api_notice' ) );
			add_action( 'wp_ajax_stripe_dismiss_apple_pay_notice', array( $this, 'dismiss_apple_pay_notice' ) );
			add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_gateway_order_admin' ) );

			require_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-payment-request.php' );
		}

		/**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 */
		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		/**
		 * The backup sanity check, in case the plugin is activated in a weird way,
		 * or the environment changes after activation. Also handles upgrade routines.
		 *
		 * @since 1.0.0
		 * @version 4.0.0
		 */
		public function check_environment() {
			if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_STRIPE_VERSION !== get_option( 'wc_stripe_version' ) ) ) {
				$this->install();

				do_action( 'woocommerce_stripe_updated' );
			}

			$environment_warning = self::get_environment_warning();

			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			}

			$secret = WC_Stripe_API::get_secret_key();

			if ( empty( $secret ) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'stripe' === $_GET['section'] ) ) {
				$setting_link = $this->get_setting_link();
				$this->add_admin_notice( 'prompt_connect', 'notice notice-warning', sprintf( __( 'Stripe is almost ready. To get started, <a href="%s">set your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), $setting_link ) );
			}
		}

		/**
		 * Updates the plugin version in db
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 * @return bool
		 */
		private static function _update_plugin_version() {
			delete_option( 'wc_stripe_version' );
			update_option( 'wc_stripe_version', WC_STRIPE_VERSION );

			return true;
		}

		/**
		 * Dismiss the Google Payment Request API Feature notice.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function dismiss_request_api_notice() {
			update_option( 'wc_stripe_show_request_api_notice', 'no' );
		}

		/**
		 * Dismiss the Apple Pay Feature notice.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function dismiss_apple_pay_notice() {
			update_option( 'wc_stripe_show_apple_pay_notice', 'no' );
		}

		/**
		 * Handles upgrade routines.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function install() {
			if ( ! defined( 'WC_STRIPE_INSTALLING' ) ) {
				define( 'WC_STRIPE_INSTALLING', true );
			}

			$this->_update_plugin_version();
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 *
		 * @since 1.0.0
		 * @version 3.1.0
		 */
		public static function get_environment_warning() {
			if ( version_compare( phpversion(), WC_STRIPE_MIN_PHP_VER, '<' ) ) {
				$message = __( 'WooCommerce Stripe - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe' );

				return sprintf( $message, WC_STRIPE_MIN_PHP_VER, phpversion() );
			}

			if ( ! defined( 'WC_VERSION' ) ) {
				return __( 'WooCommerce Stripe requires WooCommerce to be activated to work.', 'woocommerce-gateway-stripe' );
			}

			if ( version_compare( WC_VERSION, WC_STRIPE_MIN_WC_VER, '<' ) ) {
				$message = __( 'WooCommerce Stripe - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe' );

				return sprintf( $message, WC_STRIPE_MIN_WC_VER, WC_VERSION );
			}

			if ( ! function_exists( 'curl_init' ) ) {
				return __( 'WooCommerce Stripe - cURL is not installed.', 'woocommerce-gateway-stripe' );
			}

			return false;
		}

		/**
		 * Adds plugin action links
		 *
		 * @since 1.0.0
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-gateway-stripe' ) . '</a>',
				'<a href="https://docs.woocommerce.com/document/stripe/">' . __( 'Docs', 'woocommerce-gateway-stripe' ) . '</a>',
				'<a href="https://woocommerce.com/contact-us/">' . __( 'Support', 'woocommerce-gateway-stripe' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

			$section_slug = $use_id_as_section ? 'stripe' : strtolower( 'WC_Gateway_Stripe' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			$show_request_api_notice = get_option( 'wc_stripe_show_request_api_notice' );
			$show_apple_pay_notice   = get_option( 'wc_stripe_show_apple_pay_notice' );

			if ( empty( $show_apple_pay_notice ) ) {
				// @TODO remove this notice in the future.
				?>
				<div class="notice notice-warning wc-stripe-apple-pay-notice is-dismissible"><p><?php echo sprintf( esc_html__( 'New Feature! Stripe now supports %s. Your customers can now purchase your products even faster. Apple Pay has been enabled by default.', 'woocommerce-gateway-stripe' ), '<a href="https://woocommerce.com/apple-pay/">Apple Pay</a>'); ?></p></div>

				<script type="application/javascript">
					jQuery( '.wc-stripe-apple-pay-notice' ).on( 'click', '.notice-dismiss', function() {
						var data = {
							action: 'stripe_dismiss_apple_pay_notice'
						};

						jQuery.post( '<?php echo admin_url( 'admin-ajax.php' ); ?>', data );
					});
				</script>

				<?php
			}

			if ( empty( $show_request_api_notice ) ) {
				// @TODO remove this notice in the future.
				?>
				<div class="notice notice-warning wc-stripe-request-api-notice is-dismissible"><p><?php esc_html_e( 'New Feature! Stripe now supports Google Payment Request. Your customers can now use mobile phones with supported browsers such as Chrome to make purchases easier and faster.', 'woocommerce-gateway-stripe' ); ?></p></div>

				<script type="application/javascript">
					jQuery( '.wc-stripe-request-api-notice' ).on( 'click', '.notice-dismiss', function() {
						var data = {
							action: 'stripe_dismiss_request_api_notice'
						};

						jQuery.post( '<?php echo admin_url( 'admin-ajax.php' ); ?>', data );
					});
				</script>

				<?php
			}

			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 * @version 4.0.0
		 */
		public function init_gateways() {
			if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
				$this->subscription_support_enabled = true;
			}

			if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
				$this->pre_order_enabled = true;
			}

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			require_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-apple-pay.php' );

			load_plugin_textdomain( 'woocommerce-gateway-stripe', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

			$load_addons = (
				$this->subscription_support_enabled
				||
				$this->pre_order_enabled
			);

			if ( $load_addons ) {
				require_once( dirname( __FILE__ ) . '/includes/compat/class-wc-gateway-stripe-addons.php' );
			}
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @since 1.0.0
		 * @version 4.0.0
		 */
		public function add_gateways( $methods ) {
			if ( $this->subscription_support_enabled || $this->pre_order_enabled ) {
				$methods[] = 'WC_Gateway_Stripe_Addons';
			} else {
				$methods[] = 'WC_Gateway_Stripe';
				$methods[] = 'WC_Gateway_Stripe_Bancontact';
				$methods[] = 'WC_Gateway_Stripe_Sofort';
				$methods[] = 'WC_Gateway_Stripe_Giropay';
				$methods[] = 'WC_Gateway_Stripe_Ideal';
				$methods[] = 'WC_Gateway_Stripe_Alipay';
				$methods[] = 'WC_Gateway_Stripe_Sepa';
				$methods[] = 'WC_Gateway_Stripe_Bitcoin';
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
			unset( $sections['stripe_ideal'] );
			unset( $sections['stripe_alipay'] );
			unset( $sections['stripe_sepa'] );
			unset( $sections['stripe_bitcoin'] );

			$sections['stripe']            = 'Stripe';
			$sections['stripe_bancontact'] = 'Stripe Bancontact';
			$sections['stripe_sofort']     = 'Stripe Sofort';
			$sections['stripe_giropay']    = 'Stripe Giropay';
			$sections['stripe_ideal']      = 'Stripe iDeal';
			$sections['stripe_alipay']     = 'Stripe Alipay';
			$sections['stripe_sepa']       = 'Stripe SEPA';
			$sections['stripe_bitcoin']    = 'Stripe Bitcoin';
			
			return $sections;
		}
	}

	WC_Stripe::get_instance();

endif;
