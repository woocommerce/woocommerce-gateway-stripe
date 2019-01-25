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
	 * Testmode.
	 *
	 * @var bool
	 */
	public $testmode;

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
		$this->stripe_settings         = get_option( 'woocommerce_stripe_settings', array() );
		$this->stripe_enabled          = $this->get_option( 'enabled' );
		$this->payment_request         = 'yes' === $this->get_option( 'payment_request', 'yes' );
		$this->apple_pay_domain_set    = 'yes' === $this->get_option( 'apple_pay_domain_set', 'no' );
		$this->apple_pay_verify_notice = '';
		$this->testmode                = 'yes' === $this->get_option( 'testmode', 'no' );
		$this->secret_key              = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );

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
			$this->process_apple_pay_verification();
		}
	}

	/**
	 * Registers the domain with Stripe/Apple Pay
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param string $secret_key
	 */
	private function register_apple_pay_domain( $secret_key = '' ) {
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
	 * Processes the Apple Pay domain verification.
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 */
	public function process_apple_pay_verification() {
		try {
			$path     = untrailingslashit( $_SERVER['DOCUMENT_ROOT'] );
			$dir      = '.well-known';
			$file     = 'apple-developer-merchantid-domain-association';
			$fullpath = $path . '/' . $dir . '/' . $file;

			if ( $this->apple_pay_domain_set && file_exists( $fullpath ) ) {
				return;
			}

			if ( ! file_exists( $path . '/' . $dir ) ) {
				if ( ! @mkdir( $path . '/' . $dir, 0755 ) ) { // @codingStandardsIgnoreLine
					throw new Exception( __( 'Unable to create domain association folder to domain root.', 'woocommerce-gateway-stripe' ) );
				}
			}

			if ( ! file_exists( $fullpath ) ) {
				if ( ! @copy( WC_STRIPE_PLUGIN_PATH . '/' . $file, $fullpath ) ) { // @codingStandardsIgnoreLine
					throw new Exception( __( 'Unable to copy domain association file to domain root.', 'woocommerce-gateway-stripe' ) );
				}
			}

			// At this point then the domain association folder and file should be available.
			// Proceed to verify/and or verify again.
			$this->register_apple_pay_domain( $this->secret_key );

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
