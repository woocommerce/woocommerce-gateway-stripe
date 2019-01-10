<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that represents admin notices.
 *
 * @since 4.1.0
 */
class WC_Stripe_Admin_Notices {
	/**
	 * Notices (array)
	 * @var array
	 */
	public $notices = array();

	/**
	 * Constructor
	 *
	 * @since 4.1.0
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_loaded', array( $this, 'hide_notices' ) );
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
		$this->notices[ $slug ] = array(
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		);
	}

	/**
	 * Display any notices we've collected thus far.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Main Stripe payment method.
		$this->stripe_check_environment();

		// All other payment methods.
		$this->payment_methods_check_environment();

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo '<div class="' . esc_attr( $notice['class'] ) . '" style="position:relative;">';

			if ( $notice['dismissible'] ) {
				?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-stripe-hide-notice', $notice_key ), 'wc_stripe_hide_notices_nonce', '_wc_stripe_notice_nonce' ) ); ?>" class="woocommerce-message-close notice-dismiss" style="position:absolute;right:1px;padding:9px;text-decoration:none;"></a>
				<?php
			}

			echo '<p>';
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo '</p></div>';
		}
	}

	/**
	 * List of available payment methods.
	 *
	 * @since 4.1.0
	 * @return array
	 */
	public function get_payment_methods() {
		return array(
			'Alipay'     => 'WC_Gateway_Stripe_Alipay',
			'Bancontact' => 'WC_Gateway_Stripe_Bancontact',
			'EPS'        => 'WC_Gateway_Stripe_EPS',
			'Giropay'    => 'WC_Gateway_Stripe_Giropay',
			'iDeal'      => 'WC_Gateway_Stripe_Ideal',
			'Multibanco' => 'WC_Gateway_Stripe_Multibanco',
			'P24'        => 'WC_Gateway_Stripe_p24',
			'SEPA'       => 'WC_Gateway_Stripe_Sepa',
			'SOFORT'     => 'WC_Gateway_Stripe_Sofort',
		);
	}

	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the environment changes after activation. Also handles upgrade routines.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function stripe_check_environment() {
		$show_style_notice  = get_option( 'wc_stripe_show_style_notice' );
		$show_ssl_notice    = get_option( 'wc_stripe_show_ssl_notice' );
		$show_keys_notice   = get_option( 'wc_stripe_show_keys_notice' );
		$show_phpver_notice = get_option( 'wc_stripe_show_phpver_notice' );
		$show_wcver_notice  = get_option( 'wc_stripe_show_wcver_notice' );
		$show_curl_notice   = get_option( 'wc_stripe_show_curl_notice' );
		$options            = get_option( 'woocommerce_stripe_settings' );
		$testmode           = ( isset( $options['testmode'] ) && 'yes' === $options['testmode'] ) ? true : false;
		$test_pub_key       = isset( $options['test_publishable_key'] ) ? $options['test_publishable_key'] : '';
		$test_secret_key    = isset( $options['test_secret_key'] ) ? $options['test_secret_key'] : '';
		$live_pub_key       = isset( $options['publishable_key'] ) ? $options['publishable_key'] : '';
		$live_secret_key    = isset( $options['secret_key'] ) ? $options['secret_key'] : '';

		if ( isset( $options['enabled'] ) && 'yes' === $options['enabled'] ) {
			if ( empty( $show_style_notice ) ) {
				/* translators: 1) int version 2) int version */
				$message = __( 'WooCommerce Stripe - We recently made changes to Stripe that may impact the appearance of your checkout. If your checkout has changed unexpectedly, please follow these <a href="https://docs.woocommerce.com/document/stripe/#section-45" target="_blank">instructions</a> to fix.', 'woocommerce-gateway-stripe' );

				$this->add_admin_notice( 'style', 'error', $message, true );

				return;
			}

			if ( empty( $show_phpver_notice ) ) {
				if ( version_compare( phpversion(), WC_STRIPE_MIN_PHP_VER, '<' ) ) {
					/* translators: 1) int version 2) int version */
					$message = __( 'WooCommerce Stripe - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe' );

					$this->add_admin_notice( 'phpver', 'error', sprintf( $message, WC_STRIPE_MIN_PHP_VER, phpversion() ), true );

					return;
				}
			}

			if ( empty( $show_wcver_notice ) ) {
				if ( version_compare( WC_VERSION, WC_STRIPE_MIN_WC_VER, '<' ) ) {
					/* translators: 1) int version 2) int version */
					$message = __( 'WooCommerce Stripe - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe' );

					$this->add_admin_notice( 'wcver', 'notice notice-warning', sprintf( $message, WC_STRIPE_MIN_WC_VER, WC_VERSION ), true );

					return;
				}
			}

			if ( empty( $show_curl_notice ) ) {
				if ( ! function_exists( 'curl_init' ) ) {
					$this->add_admin_notice( 'curl', 'notice notice-warning', __( 'WooCommerce Stripe - cURL is not installed.', 'woocommerce-gateway-stripe' ), true );
				}
			}

			if ( empty( $show_keys_notice ) ) {
				$secret = WC_Stripe_API::get_secret_key();

				if ( empty( $secret ) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'stripe' === $_GET['section'] ) ) {
					$setting_link = $this->get_setting_link();
					/* translators: 1) link */
					$this->add_admin_notice( 'keys', 'notice notice-warning', sprintf( __( 'Stripe is almost ready. To get started, <a href="%s">set your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), $setting_link ), true );
				}

				// Check if keys are entered properly per live/test mode.
				if ( $testmode ) {
					if (
						! empty( $test_pub_key ) && ! preg_match( '/^pk_test_/', $test_pub_key )
						|| ( ! empty( $test_secret_key ) && ! preg_match( '/^sk_test_/', $test_secret_key )
						&& ! empty( $test_secret_key ) && ! preg_match( '/^rk_test_/', $test_secret_key ) ) ) {
						$setting_link = $this->get_setting_link();
						/* translators: 1) link */
						$this->add_admin_notice( 'keys', 'notice notice-error', sprintf( __( 'Stripe is in test mode however your test keys may not be valid. Test keys start with pk_test and sk_test or rk_test. Please go to your settings and, <a href="%s">set your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), $setting_link ), true );
					}
				} else {
					if (
						! empty( $live_pub_key ) && ! preg_match( '/^pk_live_/', $live_pub_key )
						|| ( ! empty( $live_secret_key ) && ! preg_match( '/^sk_live_/', $live_secret_key )
						&& ! empty( $live_secret_key ) && ! preg_match( '/^rk_live_/', $live_secret_key ) ) ) {
						$setting_link = $this->get_setting_link();
						/* translators: 1) link */
						$this->add_admin_notice( 'keys', 'notice notice-error', sprintf( __( 'Stripe is in live mode however your test keys may not be valid. Live keys start with pk_live and sk_live or rk_live. Please go to your settings and, <a href="%s">set your Stripe account keys</a>.', 'woocommerce-gateway-stripe' ), $setting_link ), true );
					}
				}
			}

			if ( empty( $show_ssl_notice ) ) {
				// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
				if ( ! wc_checkout_is_https() ) {
					/* translators: 1) link */
					$this->add_admin_notice( 'ssl', 'notice notice-warning', sprintf( __( 'Stripe is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'woocommerce-gateway-stripe' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ), true );
				}
			}
		}
	}

	/**
	 * Environment check for all other payment methods.
	 *
	 * @since 4.1.0
	 */
	public function payment_methods_check_environment() {
		$payment_methods = $this->get_payment_methods();

		foreach ( $payment_methods as $method => $class ) {
			$show_notice = get_option( 'wc_stripe_show_' . strtolower( $method ) . '_notice' );
			$gateway     = new $class();

			if ( 'yes' !== $gateway->enabled || 'no' === $show_notice ) {
				continue;
			}

			if ( ! in_array( get_woocommerce_currency(), $gateway->get_supported_currency() ) ) {
				/* translators: %1$s Payment method, %2$s List of supported currencies */
				$this->add_admin_notice( $method, 'notice notice-error', sprintf( __( '%1$s is enabled - it requires store currency to be set to %2$s', 'woocommerce-gateway-stripe' ), $method, implode( ', ', $gateway->get_supported_currency() ) ), true );
			}
		}
	}

	/**
	 * Hides any admin notices.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function hide_notices() {
		if ( isset( $_GET['wc-stripe-hide-notice'] ) && isset( $_GET['_wc_stripe_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( $_GET['_wc_stripe_notice_nonce'], 'wc_stripe_hide_notices_nonce' ) ) {
				wp_die( __( 'Action failed. Please refresh the page and retry.', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
			}

			$notice = wc_clean( $_GET['wc-stripe-hide-notice'] );

			switch ( $notice ) {
				case 'style':
					update_option( 'wc_stripe_show_style_notice', 'no' );
					break;
				case 'phpver':
					update_option( 'wc_stripe_show_phpver_notice', 'no' );
					break;
				case 'wcver':
					update_option( 'wc_stripe_show_wcver_notice', 'no' );
					break;
				case 'curl':
					update_option( 'wc_stripe_show_curl_notice', 'no' );
					break;
				case 'ssl':
					update_option( 'wc_stripe_show_ssl_notice', 'no' );
					break;
				case 'keys':
					update_option( 'wc_stripe_show_keys_notice', 'no' );
					break;
				case 'Alipay':
					update_option( 'wc_stripe_show_alipay_notice', 'no' );
					break;
				case 'Bancontact':
					update_option( 'wc_stripe_show_bancontact_notice', 'no' );
					break;
				case 'EPS':
					update_option( 'wc_stripe_show_eps_notice', 'no' );
					break;
				case 'Giropay':
					update_option( 'wc_stripe_show_giropay_notice', 'no' );
					break;
				case 'iDeal':
					update_option( 'wc_stripe_show_ideal_notice', 'no' );
					break;
				case 'Multibanco':
					update_option( 'wc_stripe_show_multibanco_notice', 'no' );
					break;
				case 'P24':
					update_option( 'wc_stripe_show_p24_notice', 'no' );
					break;
				case 'SEPA':
					update_option( 'wc_stripe_show_sepa_notice', 'no' );
					break;
				case 'SOFORT':
					update_option( 'wc_stripe_show_sofort_notice', 'no' );
					break;
			}
		}
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
}

new WC_Stripe_Admin_Notices();
