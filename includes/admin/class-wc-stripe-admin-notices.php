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
	 *
	 * @var array
	 */
	public $notices = [];

	/**
	 * Constructor
	 *
	 * @since 4.1.0
	 */
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'wp_loaded', [ $this, 'hide_notices' ] );
		add_action( 'woocommerce_stripe_updated', [ $this, 'stripe_updated' ] );
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
		$this->notices[ $slug ] = [
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		];
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
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-stripe-hide-notice', $notice_key ), 'wc_stripe_hide_notices_nonce', '_wc_stripe_notice_nonce' ) ); ?>" class="woocommerce-message-close notice-dismiss" style="position:relative;float:right;padding:9px 0px 9px 9px 9px;text-decoration:none;"></a>
				<?php
			}

			echo '<p>';
			echo wp_kses(
				$notice['message'],
				[
					'a' => [
						'href'   => [],
						'target' => [],
					],
				]
			);
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
		return [
			'alipay'     => 'WC_Gateway_Stripe_Alipay',
			'bancontact' => 'WC_Gateway_Stripe_Bancontact',
			'eps'        => 'WC_Gateway_Stripe_EPS',
			'giropay'    => 'WC_Gateway_Stripe_Giropay',
			'ideal'      => 'WC_Gateway_Stripe_Ideal',
			'multibanco' => 'WC_Gateway_Stripe_Multibanco',
			'p24'        => 'WC_Gateway_Stripe_p24',
			'sepa'       => 'WC_Gateway_Stripe_Sepa',
			'sofort'     => 'WC_Gateway_Stripe_Sofort',
			'boleto'     => 'WC_Gateway_Stripe_Boleto',
			'oxxo'       => 'WC_Gateway_Stripe_Oxxo',
		];
	}

	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the environment changes after activation. Also handles upgrade routines.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function stripe_check_environment() {
		$show_style_notice   = get_option( 'wc_stripe_show_style_notice' );
		$show_ssl_notice     = get_option( 'wc_stripe_show_ssl_notice' );
		$show_keys_notice    = get_option( 'wc_stripe_show_keys_notice' );
		$show_3ds_notice     = get_option( 'wc_stripe_show_3ds_notice' );
		$show_phpver_notice  = get_option( 'wc_stripe_show_phpver_notice' );
		$show_wcver_notice   = get_option( 'wc_stripe_show_wcver_notice' );
		$show_curl_notice    = get_option( 'wc_stripe_show_curl_notice' );
		$show_sca_notice     = get_option( 'wc_stripe_show_sca_notice' );
		$changed_keys_notice = get_option( 'wc_stripe_show_changed_keys_notice' );
		$options             = get_option( 'woocommerce_stripe_settings' );
		$testmode            = ( isset( $options['testmode'] ) && 'yes' === $options['testmode'] ) ? true : false;
		$test_pub_key        = isset( $options['test_publishable_key'] ) ? $options['test_publishable_key'] : '';
		$test_secret_key     = isset( $options['test_secret_key'] ) ? $options['test_secret_key'] : '';
		$live_pub_key        = isset( $options['publishable_key'] ) ? $options['publishable_key'] : '';
		$live_secret_key     = isset( $options['secret_key'] ) ? $options['secret_key'] : '';
		$three_d_secure      = isset( $options['three_d_secure'] ) && 'yes' === $options['three_d_secure'];

		if ( isset( $options['enabled'] ) && 'yes' === $options['enabled'] ) {
			if ( empty( $show_3ds_notice ) && $three_d_secure ) {
				$url = 'https://stripe.com/docs/payments/3d-secure#three-ds-radar';

				$message = sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
					__( 'WooCommerce Stripe - We see that you had the "Require 3D secure when applicable" setting turned on. This setting is not available here anymore, because it is now replaced by Stripe Radar. You can learn more about it %1$shere%2$s ', 'woocommerce-gateway-stripe' ),
					'<a href="' . $url . '" target="_blank">',
					'</a>'
				);

				$this->add_admin_notice( '3ds', 'notice notice-warning', $message, true );
			}

			if ( empty( $show_style_notice ) ) {
				$message = sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
					__( 'WooCommerce Stripe - We recently made changes to Stripe that may impact the appearance of your checkout. If your checkout has changed unexpectedly, please follow these %1$sinstructions%2$s to fix.', 'woocommerce-gateway-stripe' ),
					'<a href="https://woocommerce.com/document/stripe/#new-checkout-experience" target="_blank">',
					'</a>'
				);

				$this->add_admin_notice( 'style', 'notice notice-warning', $message, true );

				return;
			}

			// @codeCoverageIgnoreStart
			if ( empty( $show_phpver_notice ) ) {
				if ( version_compare( phpversion(), WC_STRIPE_MIN_PHP_VER, '<' ) ) {
					/* translators: 1) int version 2) int version */
					$message = __( 'WooCommerce Stripe - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe' );

					$this->add_admin_notice( 'phpver', 'error', sprintf( $message, WC_STRIPE_MIN_PHP_VER, phpversion() ), true );

					return;
				}
			}

			if ( empty( $show_wcver_notice ) ) {
				if ( WC_Stripe_Helper::is_wc_lt( WC_STRIPE_FUTURE_MIN_WC_VER ) ) {
					/* translators: 1) int version 2) int version */
					$message = __( 'WooCommerce Stripe - This is the last version of the plugin compatible with WooCommerce %1$s. All future versions of the plugin will require WooCommerce %2$s or greater.', 'woocommerce-gateway-stripe' );
					$this->add_admin_notice( 'wcver', 'notice notice-warning', sprintf( $message, WC_VERSION, WC_STRIPE_FUTURE_MIN_WC_VER ), true );
				}
			}

			if ( empty( $show_curl_notice ) ) {
				if ( ! function_exists( 'curl_init' ) ) {
					$this->add_admin_notice( 'curl', 'notice notice-warning', __( 'WooCommerce Stripe - cURL is not installed.', 'woocommerce-gateway-stripe' ), true );
				}
			}

			// @codeCoverageIgnoreEnd
			if ( empty( $show_keys_notice ) ) {
				$secret = WC_Stripe_API::get_secret_key();
				// phpcs:ignore
				$should_show_notice_on_page = ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 0 === strpos( $_GET['section'], 'stripe' ) );

				if ( empty( $secret ) && $should_show_notice_on_page ) {
					$setting_link = $this->get_setting_link();

					$notice_message = sprintf(
					/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
						__( 'Stripe is almost ready. To get started, %1$sset your Stripe account keys%2$s.', 'woocommerce-gateway-stripe' ),
						'<a href="' . $setting_link . '">',
						'</a>'
					);
					$this->add_admin_notice( 'keys', 'notice notice-warning', $notice_message, true );
				}

				// Check if keys are entered properly per live/test mode.
				if ( $testmode ) {
					$is_test_pub_key    = ! empty( $test_pub_key ) && preg_match( '/^pk_test_/', $test_pub_key );
					$is_test_secret_key = ! empty( $test_secret_key ) && preg_match( '/^[rs]k_test_/', $test_secret_key );
					if ( ! $is_test_pub_key || ! $is_test_secret_key ) {
						$setting_link = $this->get_setting_link();

						$notice_message = sprintf(
						/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
							__( 'Stripe is in test mode however your test keys may not be valid. Test keys start with pk_test and sk_test or rk_test. Please go to your settings and, %1$sset your Stripe account keys%2$s.', 'woocommerce-gateway-stripe' ),
							'<a href="' . $setting_link . '">',
							'</a>'
						);

						$this->add_admin_notice( 'keys', 'notice notice-error', $notice_message, true );
					}
				} else {
					$is_live_pub_key    = ! empty( $live_pub_key ) && preg_match( '/^pk_live_/', $live_pub_key );
					$is_live_secret_key = ! empty( $live_secret_key ) && preg_match( '/^[rs]k_live_/', $live_secret_key );
					if ( ! $is_live_pub_key || ! $is_live_secret_key ) {
						$setting_link = $this->get_setting_link();

						$message = sprintf(
						/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
							__( 'Stripe is in live mode however your live keys may not be valid. Live keys start with pk_live and sk_live or rk_live. Please go to your settings and, %1$sset your Stripe account keys%2$s.', 'woocommerce-gateway-stripe' ),
							'<a href="' . $setting_link . '">',
							'</a>'
						);

						$this->add_admin_notice( 'keys', 'notice notice-error', $message, true );
					}
				}

				// Check if Stripe Account data was successfully fetched.
				$account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
				if ( ! empty( $secret ) && empty( $account_data ) ) {
					$setting_link = $this->get_setting_link();

					$message = sprintf(
					/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
						__( 'Your customers cannot use Stripe on checkout, because we couldn\'t connect to your account. Please go to your settings and, %1$sset your Stripe account keys%2$s.', 'woocommerce-gateway-stripe' ),
						'<a href="' . $setting_link . '">',
						'</a>'
					);

					$this->add_admin_notice( 'keys', 'notice notice-error', $message, true );
				}
			}

			if ( empty( $show_ssl_notice ) ) {
				// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
				if ( ! wc_checkout_is_https() ) {
					$message = sprintf(
					/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
						__( 'Stripe is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid %1$sSSL certificate%2$s.', 'woocommerce-gateway-stripe' ),
						'<a href="https://en.wikipedia.org/wiki/Transport_Layer_Security" target="_blank">',
						'</a>'
					);

					$this->add_admin_notice( 'ssl', 'notice notice-warning', $message, true );
				}
			}

			if ( empty( $show_sca_notice ) ) {
				$message = sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
					__( 'Stripe is now ready for Strong Customer Authentication (SCA) and 3D Secure 2! %1$sRead about SCA%2$s.', 'woocommerce-gateway-stripe' ),
					'<a href="https://woocommerce.com/posts/introducing-strong-customer-authentication-sca/" target="_blank">',
					'</a>'
				);

				$this->add_admin_notice( 'sca', 'notice notice-success', $message, true );
			}

			if ( 'yes' === $changed_keys_notice ) {
				$message = sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
					__( 'The public and/or secret keys for the Stripe gateway have been changed. This might cause errors for existing customers and saved payment methods. %1$sClick here to learn more%2$s.', 'woocommerce-gateway-stripe' ),
					'<a href="https://woocommerce.com/document/stripe-fixing-customer-errors" target="_blank">',
					'</a>'
				);

				$this->add_admin_notice( 'changed_keys', 'notice notice-warning', $message, true );
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
			$show_notice = get_option( 'wc_stripe_show_' . $method . '_notice' );
			$gateway     = new $class();

			if ( 'yes' !== $gateway->enabled || 'no' === $show_notice ) {
				continue;
			}

			if ( ! in_array( get_woocommerce_currency(), $gateway->get_supported_currency(), true ) ) {
				/* translators: 1) Payment method, 2) List of supported currencies */
				$this->add_admin_notice( $method, 'notice notice-error', sprintf( __( '%1$s is enabled - it requires store currency to be set to %2$s', 'woocommerce-gateway-stripe' ), $gateway->get_method_title(), implode( ', ', $gateway->get_supported_currency() ) ), true );
			}
		}

		if ( ! WC_Stripe_Feature_Flags::is_upe_preview_enabled() || ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return;
		}

		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $method_class ) {
			if ( WC_Stripe_UPE_Payment_Method_CC::class === $method_class ) {
				continue;
			}
			$method      = $method_class::STRIPE_ID;
			$show_notice = get_option( 'wc_stripe_show_' . $method . '_upe_notice' );
			$upe_method  = new $method_class();
			if ( ! $upe_method->is_enabled() || 'no' === $show_notice ) {
				continue;
			}
			if ( ! in_array( get_woocommerce_currency(), $upe_method->get_supported_currencies(), true ) ) {
				/* translators: %1$s Payment method, %2$s List of supported currencies */
				$this->add_admin_notice( $method . '_upe', 'notice notice-error', sprintf( __( '%1$s is enabled - it requires store currency to be set to %2$s', 'woocommerce-gateway-stripe' ), $upe_method->get_label(), implode( ', ', $upe_method->get_supported_currencies() ) ), true );
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
			if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wc_stripe_notice_nonce'] ) ), 'wc_stripe_hide_notices_nonce' ) ) {
				wp_die( __( 'Action failed. Please refresh the page and retry.', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
			}

			$notice = wc_clean( wp_unslash( $_GET['wc-stripe-hide-notice'] ) );

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
				case '3ds':
					update_option( 'wc_stripe_show_3ds_notice', 'no' );
					break;
				case 'alipay':
					update_option( 'wc_stripe_show_alipay_notice', 'no' );
					break;
				case 'bancontact':
					update_option( 'wc_stripe_show_bancontact_notice', 'no' );
					break;
				case 'eps':
					update_option( 'wc_stripe_show_eps_notice', 'no' );
					break;
				case 'giropay':
					update_option( 'wc_stripe_show_giropay_notice', 'no' );
					break;
				case 'ideal':
					update_option( 'wc_stripe_show_ideal_notice', 'no' );
					break;
				case 'multibanco':
					update_option( 'wc_stripe_show_multibanco_notice', 'no' );
					break;
				case 'p24':
					update_option( 'wc_stripe_show_p24_notice', 'no' );
					break;
				case 'sepa':
					update_option( 'wc_stripe_show_sepa_notice', 'no' );
					break;
				case 'sofort':
					update_option( 'wc_stripe_show_sofort_notice', 'no' );
					break;
				case 'sca':
					update_option( 'wc_stripe_show_sca_notice', 'no' );
					break;
				case 'changed_keys':
					update_option( 'wc_stripe_show_changed_keys_notice', 'no' );
					break;
				default:
					if ( false !== strpos( $notice, '_upe' ) ) {
						update_option( 'wc_stripe_show_' . $notice . '_notice', 'no' );
					}
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
		return esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' ) );
	}

	/**
	 * Saves options in order to hide notices based on the gateway's version.
	 *
	 * @since 4.3.0
	 */
	public function stripe_updated() {
		$previous_version = get_option( 'wc_stripe_version' );

		// Only show the style notice if the plugin was installed and older than 4.1.4.
		if ( empty( $previous_version ) || version_compare( $previous_version, '4.1.4', 'ge' ) ) {
			update_option( 'wc_stripe_show_style_notice', 'no' );
		}

		// Only show the SCA notice on pre-4.3.0 installs.
		if ( empty( $previous_version ) || version_compare( $previous_version, '4.3.0', 'ge' ) ) {
			update_option( 'wc_stripe_show_sca_notice', 'no' );
		}
	}
}

new WC_Stripe_Admin_Notices();
