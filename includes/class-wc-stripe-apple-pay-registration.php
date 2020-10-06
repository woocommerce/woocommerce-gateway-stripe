<?php
/**
 * Stripe Apple Pay Registration Class.
 *
 * @since 4.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Stripe_Apple_Pay_Registration {
	/**
	 * Enabled.
	 *
	 * @var
	 */
	public $stripe_settings;

	/**
	 * Main Stripe Enabled.
	 *
	 * @var bool
	 */
	public $stripe_enabled;

	/**
	 * Do we accept Payment Request?
	 *
	 * @var bool
	 */
	public $payment_request;

	/**
	 * Apple Pay Domain Set.
	 *
	 * @var bool
	 */
	public $apple_pay_domain_set;

	/**
	 * Secret Key.
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Stores Apple Pay domain verification issues.
	 *
	 * @var string
	 */
	public $apple_pay_verify_notice;

	public function __construct() {
		add_action( 'woocommerce_stripe_updated', array( $this, 'verify_domain_if_needed' ) );
		add_action( 'update_option_woocommerce_stripe_settings', array( $this, 'verify_domain_on_new_secret_key' ), 10, 2 );

		$this->stripe_settings         = get_option( 'woocommerce_stripe_settings', array() );
		$this->stripe_enabled          = $this->get_option( 'enabled' );
		$this->payment_request         = 'yes' === $this->get_option( 'payment_request', 'yes' );
		$this->apple_pay_domain_set    = 'yes' === $this->get_option( 'apple_pay_domain_set', 'no' );
		$this->apple_pay_verify_notice = '';
		$this->secret_key              = $this->get_secret_key();

		if ( empty( $this->stripe_settings ) ) {
			return;
		}

		$this->init_apple_pay();

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Gets the Stripe settings.
	 *
	 * @since 4.0.6
	 * @param string $setting
	 * @param string default
	 * @return string $setting_value
	 */
	public function get_option( $setting = '', $default = '' ) {
		if ( empty( $this->stripe_settings ) ) {
			return $default;
		}

		if ( ! empty( $this->stripe_settings[ $setting ] ) ) {
			return $this->stripe_settings[ $setting ];
		}

		return $default;
	}

	/**
	 * Gets the Stripe secret key for the current mode.
	 *
	 * @since 4.5.3
	 * @return string Secret key.
	 */
	private function get_secret_key() {
		$testmode = 'yes' === $this->get_option( 'testmode', 'no' );
		return $testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
	}

	/**
	 * Initializes Apple Pay process on settings page.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function init_apple_pay() {
		if (
			is_admin() &&
			isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] &&
			isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] &&
			isset( $_GET['section'] ) && 'stripe' === $_GET['section'] &&
			$this->payment_request
		) {
			$this->verify_domain();
		}
	}

	/**
	 * Registers the domain with Stripe/Apple Pay
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param string $secret_key
	 */
	private function register_domain_with_apple( $secret_key = '' ) {
		if ( empty( $secret_key ) ) {
			throw new Exception( __( 'Unable to verify domain - missing secret key.', 'woocommerce-gateway-stripe' ) );
		}

		$endpoint = 'https://api.stripe.com/v1/apple_pay/domains';

		$data = array(
			'domain_name' => $_SERVER['HTTP_HOST'],
		);

		$headers = array(
			'User-Agent'    => 'WooCommerce Stripe Apple Pay',
			'Authorization' => 'Bearer ' . $secret_key,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $headers,
				'body'    => http_build_query( $data ),
			)
		);

		if ( is_wp_error( $response ) ) {
			/* translators: error message */
			throw new Exception( sprintf( __( 'Unable to verify domain - %s', 'woocommerce-gateway-stripe' ), $response->get_error_message() ) );
		}

		if ( 200 !== $response['response']['code'] ) {
			$parsed_response = json_decode( $response['body'] );

			$this->apple_pay_verify_notice = $parsed_response->error->message;

			/* translators: error message */
			throw new Exception( sprintf( __( 'Unable to verify domain - %s', 'woocommerce-gateway-stripe' ), $parsed_response->error->message ) );
		}
	}

	/**
	 * Updates the Apple Pay domain association file.
	 * Reports failure only if file isn't already being served properly.
	 *
	 * @param bool $force True to create the file if it didn't exist, false for just updating the file if needed.
	 *
	 * @version 4.3.0
	 * @since 4.3.0
	 * @return bool True on success, false on failure.
	 */
	public function update_domain_association_file( $force = false ) {
			$path     = untrailingslashit( $_SERVER['DOCUMENT_ROOT'] );
			$dir      = '.well-known';
			$file     = 'apple-developer-merchantid-domain-association';
			$fullpath = $path . '/' . $dir . '/' . $file;

			$existing_contents = @file_get_contents( $fullpath );
			$new_contents = @file_get_contents( WC_STRIPE_PLUGIN_PATH . '/' . $file );
			if ( ( ! $existing_contents && ! $force ) || $existing_contents === $new_contents ) {
				return true;
			}

			$error = null;
			if ( ! file_exists( $path . '/' . $dir ) ) {
				if ( ! @mkdir( $path . '/' . $dir, 0755 ) ) { // @codingStandardsIgnoreLine
					$error = __( 'Unable to create domain association folder to domain root.', 'woocommerce-gateway-stripe' );
				}
			}
			if ( ! @copy( WC_STRIPE_PLUGIN_PATH . '/' . $file, $fullpath ) ) { // @codingStandardsIgnoreLine
				$error = __( 'Unable to copy domain association file to domain root.', 'woocommerce-gateway-stripe' );
			}

			if ( isset( $error ) ) {
				$url            = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $dir . '/' . $file;
				$response       = wp_remote_get( $url );
				$already_hosted = wp_remote_retrieve_body( $response ) === $new_contents;
				if ( ! $already_hosted ) {
					WC_Stripe_Logger::log(
						'Error: ' . $error . ' ' .
						/* translators: expected domain association file URL */
						sprintf( __( 'To enable Apple Pay, domain association file must be hosted at %s.', 'woocommerce-gateway-stripe' ), $url )
					);
				}
				return $already_hosted;
			}

			WC_Stripe_Logger::log( 'Domain association file updated.' );
			return true;
	}

	/**
	 * Processes the Apple Pay domain verification.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function verify_domain() {
		if ( ! $this->update_domain_association_file( true ) ) {
			$this->stripe_settings['apple_pay_domain_set'] = 'no';
			update_option( 'woocommerce_stripe_settings', $this->stripe_settings );
			return;
		}

		try {
			// At this point then the domain association folder and file should be available.
			// Proceed to verify/and or verify again.
			$this->register_domain_with_apple( $this->secret_key );

			// No errors to this point, verification success!
			$this->stripe_settings['apple_pay_domain_set'] = 'yes';
			$this->apple_pay_domain_set                    = true;

			update_option( 'woocommerce_stripe_settings', $this->stripe_settings );

			WC_Stripe_Logger::log( 'Your domain has been verified with Apple Pay!' );

		} catch ( Exception $e ) {
			$this->stripe_settings['apple_pay_domain_set'] = 'no';

			update_option( 'woocommerce_stripe_settings', $this->stripe_settings );

			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Conditionally process the Apple Pay domain verification after a new secret key is set.
	 *
	 * @since 4.5.3
	 * @version 4.5.3
	 */
	public function verify_domain_on_new_secret_key( $prev_settings, $settings ) {
		$this->stripe_settings = $prev_settings;
		$prev_secret_key = $this->get_secret_key();

		$this->stripe_settings = $settings;
		$this->secret_key = $this->get_secret_key();

		if ( ! empty( $this->secret_key ) && $this->secret_key !== $prev_secret_key ) {
			$this->verify_domain();
		}
	}

	/**
	 * Process the Apple Pay domain verification if not already done - otherwise just update the file.
	 *
	 * @since 4.5.3
	 * @version 4.5.3
	 */
	public function verify_domain_if_needed() {
		if ( $this->apple_pay_domain_set ) {
			$this->update_domain_association_file();
		} else {
			$this->verify_domain();
		}
	}

	/**
	 * Display any admin notices to the user.
	 *
	 * @since 4.0.6
	 */
	public function admin_notices() {
		if ( ! $this->stripe_enabled ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( $this->payment_request && ! empty( $this->apple_pay_verify_notice ) ) {
			$allowed_html = array(
				'a' => array(
					'href'  => array(),
					'title' => array(),
				),
			);

			echo '<div class="error stripe-apple-pay-message"><p>' . wp_kses( make_clickable( $this->apple_pay_verify_notice ), $allowed_html ) . '</p></div>';
		}

		/**
		 * Apple pay is enabled by default and domain verification initializes
		 * when setting screen is displayed. So if domain verification is not set,
		 * something went wrong so lets notify user.
		 */
		if ( ! empty( $this->secret_key ) && $this->payment_request && ! $this->apple_pay_domain_set ) {
			/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			echo '<div class="error stripe-apple-pay-message"><p>' . sprintf( __( 'Apple Pay domain verification failed. Please check the %1$slog%2$s to see the issue. (Logging must be enabled to see recorded logs)', 'woocommerce-gateway-stripe' ), '<a href="' . admin_url( 'admin.php?page=wc-status&tab=logs' ) . '">', '</a>' ) . '</p></div>';
		}
	}
}

new WC_Stripe_Apple_Pay_Registration();
