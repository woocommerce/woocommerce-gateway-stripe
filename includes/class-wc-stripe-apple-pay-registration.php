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
	 * Apple Pay Domain Set.
	 *
	 * @var bool
	 */
	public $apple_pay_domain_set;

	/**
	 * Stores Apple Pay domain verification issues.
	 *
	 * @var string
	 */
	public $apple_pay_verify_notice;

	public function __construct() {
		add_action( 'init', array( $this, 'add_domain_association_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'whitelist_domain_association_query_param' ), 10, 1 );
		add_action( 'parse_request', array( $this, 'parse_domain_association_request' ), 10, 1 );

		add_action( 'woocommerce_stripe_updated', array( $this, 'verify_domain_if_configured' ) );
		add_action( 'add_option_woocommerce_stripe_settings', array( $this, 'verify_domain_on_new_settings' ), 10, 2 );
		add_action( 'update_option_woocommerce_stripe_settings', array( $this, 'verify_domain_on_updated_settings' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		$this->stripe_settings         = get_option( 'woocommerce_stripe_settings', array() );
		$this->apple_pay_domain_set    = 'yes' === $this->get_option( 'apple_pay_domain_set', 'no' );
		$this->apple_pay_verify_notice = '';
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
	 * Whether the gateway and Payment Request Button (prerequisites for Apple Pay) are enabled.
	 *
	 * @since 4.5.4
	 * @return string Whether Apple Pay required settings are enabled.
	 */
	private function is_enabled() {
		$stripe_enabled                 = 'yes' === $this->get_option( 'enabled', 'no' );
		$payment_request_button_enabled = 'yes' === $this->get_option( 'payment_request', 'yes' );

		return $stripe_enabled && $payment_request_button_enabled;
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
	 * Adds a rewrite rule for serving the domain association file from the proper location.
	 */
	public function add_domain_association_rewrite_rule() {
		$regex    = '^\.well-known\/apple-developer-merchantid-domain-association$';
		$redirect = 'index.php?apple-developer-merchantid-domain-association=1';

		add_rewrite_rule( $regex, $redirect, 'top' );
	}

	/**
	 * Add to the list of publicly allowed query variables.
	 *
	 * @param  array $query_vars - provided public query vars.
	 * @return array Updated public query vars.
	 */
	public function whitelist_domain_association_query_param( $query_vars ) {
		$query_vars[] = 'apple-developer-merchantid-domain-association';
		return $query_vars;
	}

	/**
	 * Serve domain association file when proper query param is provided.
	 *
	 * @param WP WordPress environment object.
	 */
	public function parse_domain_association_request( $wp ) {
		if (
			! isset( $wp->query_vars['apple-developer-merchantid-domain-association'] ) ||
			'1' !== $wp->query_vars['apple-developer-merchantid-domain-association']
		) {
			return;
		}

		$path = WC_STRIPE_PLUGIN_PATH . '/apple-developer-merchantid-domain-association';
		header( 'Content-Type: application/octet-stream' );
		echo esc_html( file_get_contents( $path ) );
		exit;
	}

	/**
	 * Makes request to register the domain with Stripe/Apple Pay.
	 *
	 * @since 3.1.0
	 * @version 4.5.4
	 * @param string $secret_key
	 */
	private function make_domain_registration_request( $secret_key ) {
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
	 * @version 4.5.4
	 *
	 * @param string $secret_key
	 *
	 * @return bool Whether domain verification succeeded.
	 */
	public function register_domain_with_apple( $secret_key ) {
		try {
			$this->make_domain_registration_request( $secret_key );

			// No errors to this point, verification success!
			$this->stripe_settings['apple_pay_domain_set'] = 'yes';
			$this->apple_pay_domain_set                    = true;

			update_option( 'woocommerce_stripe_settings', $this->stripe_settings );

			WC_Stripe_Logger::log( 'Your domain has been verified with Apple Pay!' );

			return true;

		} catch ( Exception $e ) {
			$this->stripe_settings['apple_pay_domain_set'] = 'no';
			$this->apple_pay_domain_set                    = false;

			update_option( 'woocommerce_stripe_settings', $this->stripe_settings );

			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Process the Apple Pay domain verification if proper settings are configured.
	 *
	 * @since 4.5.4
	 * @version 4.5.4
	 */
	public function verify_domain_if_configured() {
		$secret_key = $this->get_secret_key();

		if ( ! $this->is_enabled() || empty( $secret_key ) ) {
			return;
		}

		// Ensure that domain association file will be served.
		flush_rewrite_rules();

		// Register the domain with Apple Pay.
		$verification_complete = $this->register_domain_with_apple( $secret_key );

		// Show/hide notes if necessary.
		WC_Stripe_Inbox_Notes::notify_on_apple_pay_domain_verification( $verification_complete );
	}

	/**
	 * Conditionally process the Apple Pay domain verification after settings are initially set.
	 *
	 * @since 4.5.4
	 * @version 4.5.4
	 */
	public function verify_domain_on_new_settings( $option, $settings ) {
		$this->verify_domain_on_updated_settings( array(), $settings );
	}

	/**
	 * Conditionally process the Apple Pay domain verification after settings are updated.
	 *
	 * @since 4.5.3
	 * @version 4.5.4
	 */
	public function verify_domain_on_updated_settings( $prev_settings, $settings ) {
		// Grab previous state and then update cached settings.
		$this->stripe_settings = $prev_settings;
		$prev_secret_key       = $this->get_secret_key();
		$prev_is_enabled       = $this->is_enabled();
		$this->stripe_settings = $settings;

		// If Stripe or Payment Request Button wasn't enabled (or secret key was different) then might need to verify now.
		if ( ! $prev_is_enabled || ( $this->get_secret_key() !== $prev_secret_key ) ) {
			$this->verify_domain_if_configured();
		}
	}

	/**
	 * Display any admin notices to the user.
	 *
	 * @since 4.0.6
	 */
	public function admin_notices() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$empty_notice = empty( $this->apple_pay_verify_notice );
		if ( $empty_notice && ( $this->apple_pay_domain_set || empty( $this->secret_key ) ) ) {
			return;
		}

		/**
		 * Apple pay is enabled by default and domain verification initializes
		 * when setting screen is displayed. So if domain verification is not set,
		 * something went wrong so lets notify user.
		 */
		$allowed_html                      = array(
			'a' => array(
				'href'  => array(),
				'title' => array(),
			),
		);
		$verification_failed_without_error = __( 'Apple Pay domain verification failed.', 'woocommerce-gateway-stripe' );
		$verification_failed_with_error    = __( 'Apple Pay domain verification failed with the following error:', 'woocommerce-gateway-stripe' );
		$check_log_text                    = sprintf(
			/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			esc_html__( 'Please check the %1$slogs%2$s for more details on this issue. Logging must be enabled to see recorded logs.', 'woocommerce-gateway-stripe' ),
			'<a href="' . admin_url( 'admin.php?page=wc-status&tab=logs' ) . '">',
			'</a>'
		);

		?>
		<div class="error stripe-apple-pay-message">
			<?php if ( $empty_notice ) : ?>
				<p><?php echo esc_html( $verification_failed_without_error ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html( $verification_failed_with_error ); ?></p>
				<p><i><?php echo wp_kses( make_clickable( esc_html( $this->apple_pay_verify_notice ) ), $allowed_html ); ?></i></p>
			<?php endif; ?>
			<p><?php echo $check_log_text; ?></p>
		</div>
		<?php
	}
}

new WC_Stripe_Apple_Pay_Registration();
