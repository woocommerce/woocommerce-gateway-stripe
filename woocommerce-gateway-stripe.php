<?php
/*
 * Plugin Name: WooCommerce Stripe Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-stripe/
 * Description: Take credit card payments on your store using Stripe.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Version: 3.1.6
 * Text Domain: woocommerce-gateway-stripe
 * Domain Path: /languages
 *
 * Copyright (c) 2017 WooCommerce
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_STRIPE_VERSION', '3.1.6' );
define( 'WC_STRIPE_MIN_PHP_VER', '5.6.0' );
define( 'WC_STRIPE_MIN_WC_VER', '2.5.0' );
define( 'WC_STRIPE_MAIN_FILE', __FILE__ );
define( 'WC_STRIPE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_STRIPE_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'WC_Stripe' ) ) :

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
		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Don't hook anything else in the plugin if we're in an incompatible environment
			if ( self::get_environment_warning() ) {
				return;
			}

			include_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-api.php' );
			include_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-customer.php' );

			// Init the gateway itself
			$this->init_gateways();

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
			add_filter( 'woocommerce_get_customer_payment_tokens', array( $this, 'woocommerce_get_customer_payment_tokens' ), 10, 3 );
			add_action( 'woocommerce_payment_token_deleted', array( $this, 'woocommerce_payment_token_deleted' ), 10, 2 );
			add_action( 'woocommerce_payment_token_set_default', array( $this, 'woocommerce_payment_token_set_default' ) );
			add_action( 'wp_ajax_stripe_dismiss_request_api_notice', array( $this, 'dismiss_request_api_notice' ) );
			add_action( 'wp_ajax_stripe_dismiss_apple_pay_notice', array( $this, 'dismiss_apple_pay_notice' ) );

			include_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-payment-request.php' );
		}

		/**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
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
		 */
		public function check_environment() {
			if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_STRIPE_VERSION !== get_option( 'woocommerce_stripe_version' ) ) ) {
				$this->install();

				do_action( 'woocommerce_stripe_updated' );
			}

			$environment_warning = self::get_environment_warning();

			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			}

			// Check if secret key present. Otherwise prompt, via notice, to go to
			// setting.
			if ( ! class_exists( 'WC_Stripe_API' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-api.php' );
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
			add_option( 'wc_stripe_version', WC_STRIPE_VERSION );

			return true;
		}

		/**
		 * Dismiss the Google Payment Request API Feature notice.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function dismiss_request_api_notice() {
			add_option( 'wc_stripe_show_request_api_notice', 'no' );
		}

		/**
		 * Dismiss the Apple Pay Feature notice.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function dismiss_apple_pay_notice() {
			add_option( 'wc_stripe_show_apple_pay_notice', 'no' );
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
		 */
		static function get_environment_warning() {
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
				<div class="notice notice-warning wc-stripe-apple-pay-notice is-dismissible"><p><?php esc_html_e( 'New Feature! Stripe now supports Apple Pay. Your customers can now purchase your products even faster. Apple Pay has been enabled by default.', 'woocommerce-gateway-stripe' ); ?></p></div>

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

			if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-stripe.php' );
				include_once( dirname( __FILE__ ) . '/includes/class-wc-stripe-apple-pay.php' );
			} else {
				include_once( dirname( __FILE__ ) . '/includes/legacy/class-wc-gateway-stripe.php' );
				include_once( dirname( __FILE__ ) . '/includes/legacy/class-wc-gateway-stripe-saved-cards.php' );
			}

			load_plugin_textdomain( 'woocommerce-gateway-stripe', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
			
			$load_addons = (
				$this->subscription_support_enabled
				||
				$this->pre_order_enabled
			);

			if ( $load_addons ) {
				require_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-stripe-addons.php' );
			}
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @since 1.0.0
		 */
		public function add_gateways( $methods ) {
			if ( $this->subscription_support_enabled || $this->pre_order_enabled ) {
				$methods[] = 'WC_Gateway_Stripe_Addons';
			} else {
				$methods[] = 'WC_Gateway_Stripe';
			}
			return $methods;
		}

		/**
		 * List of currencies supported by Stripe that has no decimals.
		 *
		 * @return array $currencies
		 */
		public static function no_decimal_currencies() {
			return array(
				'bif', // Burundian Franc
				'djf', // Djiboutian Franc
				'jpy', // Japanese Yen
				'krw', // South Korean Won
				'pyg', // Paraguayan Guaraní
				'vnd', // Vietnamese Đồng
				'xaf', // Central African Cfa Franc
				'xpf', // Cfp Franc
				'clp', // Chilean Peso
				'gnf', // Guinean Franc
				'kmf', // Comorian Franc
				'mga', // Malagasy Ariary
				'rwf', // Rwandan Franc
				'vuv', // Vanuatu Vatu
				'xof', // West African Cfa Franc
			);
		}

		/**
		 * Stripe uses smallest denomination in currencies such as cents.
		 * We need to format the returned currency from Stripe into human readable form.
		 *
		 * @param object $balance_transaction
		 * @param string $type Type of number to format
		 */
		public static function format_number( $balance_transaction, $type = 'fee' ) {
			if ( ! is_object( $balance_transaction ) ) {
				return;
			}

			if ( in_array( strtolower( $balance_transaction->currency ), self::no_decimal_currencies() ) ) {
				if ( 'fee' === $type ) {
					return $balance_transaction->fee;
				}

				return $balance_transaction->net;
			}

			if ( 'fee' === $type ) {
				return number_format( $balance_transaction->fee / 100, 2, '.', '' );
			}

			return number_format( $balance_transaction->net / 100, 2, '.', '' ); 
		}

		/**
		 * Capture payment when the order is changed from on-hold to complete or processing
		 *
		 * @param  int $order_id
		 */
		public function capture_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( 'stripe' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
				$charge   = get_post_meta( $order_id, '_stripe_charge_id', true );
				$captured = get_post_meta( $order_id, '_stripe_charge_captured', true );

				if ( $charge && 'no' === $captured ) {
					$result = WC_Stripe_API::request( array(
						'amount'   => $order->get_total() * 100,
						'expand[]' => 'balance_transaction',
					), 'charges/' . $charge . '/capture' );

					if ( is_wp_error( $result ) ) {
						$order->add_order_note( __( 'Unable to capture charge!', 'woocommerce-gateway-stripe' ) . ' ' . $result->get_error_message() );
					} else {
						$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $result->id ) );
						update_post_meta( $order_id, '_stripe_charge_captured', 'yes' );

						// Store other data such as fees
						update_post_meta( $order_id, 'Stripe Payment ID', $result->id );

						if ( isset( $result->balance_transaction ) && isset( $result->balance_transaction->fee ) ) {
							// Fees and Net needs to both come from Stripe to be accurate as the returned
							// values are in the local currency of the Stripe account, not from WC.
							$fee = ! empty( $result->balance_transaction->fee ) ? self::format_number( $result->balance_transaction, 'fee' ) : 0;
							$net = ! empty( $result->balance_transaction->net ) ? self::format_number( $result->balance_transaction, 'net' ) : 0;
							update_post_meta( $order_id, 'Stripe Fee', $fee );
							update_post_meta( $order_id, 'Net Revenue From Stripe', $net );
						}
					}
				}
			}
		}

		/**
		 * Cancel pre-auth on refund/cancellation
		 *
		 * @param  int $order_id
		 */
		public function cancel_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( 'stripe' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
				$charge   = get_post_meta( $order_id, '_stripe_charge_id', true );

				if ( $charge ) {
					$result = WC_Stripe_API::request( array(
						'amount' => $order->get_total() * 100,
					), 'charges/' . $charge . '/refund' );

					if ( is_wp_error( $result ) ) {
						$order->add_order_note( __( 'Unable to refund charge!', 'woocommerce-gateway-stripe' ) . ' ' . $result->get_error_message() );
					} else {
						$order->add_order_note( sprintf( __( 'Stripe charge refunded (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $result->id ) );
						delete_post_meta( $order_id, '_stripe_charge_captured' );
						delete_post_meta( $order_id, '_stripe_charge_id' );
					}
				}
			}
		}

		/**
		 * Gets saved tokens from API if they don't already exist in WooCommerce.
		 * @param array $tokens
		 * @return array
		 */
		public function woocommerce_get_customer_payment_tokens( $tokens, $customer_id, $gateway_id ) {
			if ( is_user_logged_in() && 'stripe' === $gateway_id && class_exists( 'WC_Payment_Token_CC' ) ) {
				$stripe_customer = new WC_Stripe_Customer( $customer_id );
				$stripe_cards    = $stripe_customer->get_cards();
				$stored_tokens   = array();

				foreach ( $tokens as $token ) {
					$stored_tokens[] = $token->get_token();
				}

				foreach ( $stripe_cards as $card ) {
					if ( ! in_array( $card->id, $stored_tokens ) ) {
						$token = new WC_Payment_Token_CC();
						$token->set_token( $card->id );
						$token->set_gateway_id( 'stripe' );
						$token->set_card_type( strtolower( $card->brand ) );
						$token->set_last4( $card->last4 );
						$token->set_expiry_month( $card->exp_month );
						$token->set_expiry_year( $card->exp_year );
						$token->set_user_id( $customer_id );
						$token->save();
						$tokens[ $token->get_id() ] = $token;
					}
				}
			}
			return $tokens;
		}

		/**
		 * Delete token from Stripe
		 */
		public function woocommerce_payment_token_deleted( $token_id, $token ) {
			if ( 'stripe' === $token->get_gateway_id() ) {
				$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
				$stripe_customer->delete_card( $token->get_token() );
			}
		}

		/**
		 * Set as default in Stripe
		 */
		public function woocommerce_payment_token_set_default( $token_id ) {
			$token = WC_Payment_Tokens::get( $token_id );
			if ( 'stripe' === $token->get_gateway_id() ) {
				$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
				$stripe_customer->set_default_card( $token->get_token() );
			}
		}

		/**
		 * Checks Stripe minimum order value authorized per currency
		 */
		public static function get_minimum_amount() {
			// Check order amount
			switch ( get_woocommerce_currency() ) {
				case 'USD':
				case 'CAD':
				case 'EUR':
				case 'CHF':
				case 'AUD':
				case 'SGD':
					$minimum_amount = 50;
					break;
				case 'GBP':
					$minimum_amount = 30;
					break;
				case 'DKK':
					$minimum_amount = 250;
					break;
				case 'NOK':
				case 'SEK':
					$minimum_amount = 300;
					break;
				case 'JPY':
					$minimum_amount = 5000;
					break;
				case 'MXN':
					$minimum_amount = 1000;
					break;
				case 'HKD':
					$minimum_amount = 400;
					break;
				default:
					$minimum_amount = 50;
					break;
			}

			return $minimum_amount;
		}

		/**
		 * What rolls down stairs
		 * alone or in pairs,
		 * and over your neighbor's dog?
		 * What's great for a snack,
		 * And fits on your back?
		 * It's log, log, log
		 */
		public static function log( $message ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}

			self::$log->add( 'woocommerce-gateway-stripe', $message );
		}
	}

	$GLOBALS['wc_stripe'] = WC_Stripe::get_instance();

endif;
