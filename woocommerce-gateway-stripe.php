<?php
/*
 * Plugin Name: WooCommerce Stripe Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-stripe/
 * Description: Take credit card payments on your store using Stripe.
 * Author: Automattic
 * Author URI: http://woothemes.com/
 * Version: 3.0.2
 * Text Domain: woocommerce-gateway-stripe
 * Domain Path: /languages
 *
 * Copyright (c) 2016 Automattic
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
define( 'WC_STRIPE_VERSION', '3.0.2' );
define( 'WC_STRIPE_MIN_PHP_VER', '5.3.0' );
define( 'WC_STRIPE_MIN_WC_VER', '2.5.0' );
define( 'WC_STRIPE_MAIN_FILE', __FILE__ );
define( 'WC_STRIPE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

if ( ! class_exists( 'WC_Stripe' ) ) {

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

	/** @var whether or not we need to load code for / support subscriptions */
	private $subscription_support_enabled = false;

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

		include_once( plugin_basename( 'includes/class-wc-stripe-api.php' ) );
		include_once( plugin_basename( 'includes/class-wc-stripe-customer.php' ) );

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
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
	 */
	public function add_admin_notice( $slug, $class, $message ) {
		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message
		);
	}

	/**
	 * The primary sanity check, automatically disable the plugin on activation if it doesn't
	 * meet minimum requirements.
	 *
	 * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
	 */
	public static function activation_check() {
		$environment_warning = self::get_environment_warning( true );
		if ( $environment_warning ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( $environment_warning );
		}
	}

	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the environment changes after activation.
	 */
	public function check_environment() {
		$environment_warning = self::get_environment_warning();
		if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
		}

		// Check if secret key present. Otherwise prompt, via notice, to go to
		// setting.
		if ( ! class_exists( 'WC_Stripe_API' ) ) {
			include_once( plugin_basename( 'includes/class-wc-stripe-api.php' ) );
		}

		$secret = WC_Stripe_API::get_secret_key();

		if ( empty( $secret ) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'stripe' === $_GET['section'] ) ) {
			$setting_link = $this->get_setting_link();
			$this->add_admin_notice( 'prompt_connect', 'notice notice-warning', sprintf( __( 'Stripe is almost ready. To get started, <a href="%s">set your Stripe account keys</a>.', 'wwoocommerce-gateway-stripe' ), $setting_link ) );
		}
	}

	/**
	 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
	 * found or false if the environment has no problems.
	 */
	static function get_environment_warning( $during_activation = false ) {
		if ( version_compare( phpversion(), WC_STRIPE_MIN_PHP_VER, '<' ) ) {
			if ( $during_activation ) {
				$message = __( 'The plugin could not be activated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe', 'woocommerce-gateway-stripe' );
			} else {
				$message = __( 'The WooCommerce Stripe plugin has been deactivated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe' );
			}
			return sprintf( $message, WC_STRIPE_MIN_PHP_VER, phpversion() );
		}

		if ( version_compare( WC_VERSION, WC_STRIPE_MIN_WC_VER, '<' ) ) {
			if ( $during_activation ) {
				$message = __( 'The plugin could not be activated. The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe', 'woocommerce-gateway-stripe' );
			} else {
				$message = __( 'The WooCommerce Stripe plugin has been deactivated. The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe' );
			}
			return sprintf( $message, WC_STRIPE_MIN_WC_VER, WC_VERSION );
		}

		if ( ! function_exists( 'curl_init' ) ) {
			if ( $during_activation ) {
				return __( 'The plugin could not be activated. cURL is not installed.', 'woocommerce-gateway-stripe' );
			}
			return __( 'The WooCommerce Stripe plugin has been deactivated. cURL is not installed.', 'woocommerce-gateway-stripe' );
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
			'<a href="https://docs.woothemes.com/document/stripe/">' . __( 'Docs', 'woocommerce-gateway-stripe' ) . '</a>',
			'<a href="http://support.woothemes.com/">' . __( 'Support', 'woocommerce-gateway-stripe' ) . '</a>',
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
		$use_id_as_section = version_compare( WC()->version, '2.6', '>=' );

		$section_slug = $use_id_as_section ? 'stripe' : strtolower( 'WC_Gateway_Stripe' );

		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
	}

	/**
	 * Display any notices we've collected thus far (e.g. for connection, disconnection)
	 */
	public function admin_notices() {
		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo "</p></div>";
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

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {
			include_once( plugin_basename( 'includes/class-wc-gateway-stripe.php' ) );
		} else {
			include_once( plugin_basename( 'includes/legacy/class-wc-gateway-stripe.php' ) );
			include_once( plugin_basename( 'includes/legacy/class-wc-gateway-stripe-saved-cards.php' ) );
		}

		load_plugin_textdomain( 'woocommerce-gateway-stripe', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

		if ( $this->subscription_support_enabled ) {
			require_once( plugin_basename( 'includes/class-wc-gateway-stripe-addons.php' ) );
		}
	}

	/**
	 * Add the gateways to WooCommerce
	 *
	 * @since 1.0.0
	 */
	public function add_gateways( $methods ) {
		if ( $this->subscription_support_enabled ) {
			$methods[] = 'WC_Gateway_Stripe_Addons';
		} else {
			$methods[] = 'WC_Gateway_Stripe';
		}
		return $methods;
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'stripe' === $order->payment_method ) {
			$charge   = get_post_meta( $order_id, '_stripe_charge_id', true );
			$captured = get_post_meta( $order_id, '_stripe_charge_captured', true );

			if ( $charge && 'no' === $captured ) {
				$result = WC_Stripe_API::request( array(
					'amount'   => $order->get_total() * 100,
					'expand[]' => 'balance_transaction'
				), 'charges/' . $charge . '/capture' );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to capture charge!', 'woocommerce-gateway-stripe' ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $result->id ) );
					update_post_meta( $order->id, '_stripe_charge_captured', 'yes' );

					// Store other data such as fees
					update_post_meta( $order->id, 'Stripe Payment ID', $result->id );

					if ( isset( $result->balance_transaction ) && isset( $result->balance_transaction->fee ) ) {
						update_post_meta( $order->id, 'Stripe Fee', number_format( $result->balance_transaction->fee / 100, 2, '.', '' ) );
						update_post_meta( $order->id, 'Net Revenue From Stripe', ( $order->order_total - number_format( $result->balance_transaction->fee / 100, 2, '.', '' ) ) );
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

		if ( 'stripe' === $order->payment_method ) {
			$charge   = get_post_meta( $order_id, '_stripe_charge_id', true );

			if ( $charge ) {
				$result = WC_Stripe_API::request( array(
					'amount' => $order->get_total() * 100,
				), 'charges/' . $charge . '/refund' );

				if ( is_wp_error( $result ) ) {
					$order->add_order_note( __( 'Unable to refund charge!', 'woocommerce-gateway-stripe' ) . ' ' . $result->get_error_message() );
				} else {
					$order->add_order_note( sprintf( __( 'Stripe charge refunded (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $result->id ) );
					delete_post_meta( $order->id, '_stripe_charge_captured' );
					delete_post_meta( $order->id, '_stripe_charge_id' );
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
					$token->set_expiry_month( $card->exp_month  );
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

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message );
		}
	}
}

$GLOBALS['wc_stripe'] = WC_Stripe::get_instance();
register_activation_hook( __FILE__, array( 'WC_Stripe', 'activation_check' ) );

}
