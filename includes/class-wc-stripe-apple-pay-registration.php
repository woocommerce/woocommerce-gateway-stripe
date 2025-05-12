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

	const DOMAIN_ASSOCIATION_FILE_NAME = 'apple-developer-merchantid-domain-association';
	const DOMAIN_ASSOCIATION_FILE_DIR  = '.well-known';

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
	 * Current domain name.
	 *
	 * @var bool
	 */
	private $domain_name;

	/**
	 * Stores Apple Pay domain verification issues.
	 *
	 * @var string
	 */
	public $apple_pay_verify_notice;

	public function __construct() {
		add_action( 'init', [ $this, 'add_domain_association_rewrite_rule' ] );
		add_action( 'admin_init', [ $this, 'verify_domain_on_domain_name_change' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_filter( 'query_vars', [ $this, 'whitelist_domain_association_query_param' ], 10, 1 );
		add_action( 'parse_request', [ $this, 'parse_domain_association_request' ], 10, 1 );

		add_action( 'woocommerce_stripe_updated', [ $this, 'verify_domain_if_configured' ] );
		add_action( 'add_option_woocommerce_stripe_settings', [ $this, 'verify_domain_on_new_settings' ], 10, 2 );
		add_action( 'update_option_woocommerce_stripe_settings', [ $this, 'verify_domain_on_updated_settings' ], 10, 2 );

		$this->stripe_settings         = WC_Stripe_Helper::get_stripe_settings();
		$this->domain_name             = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : str_replace( array( 'https://', 'http://' ), '', get_site_url() ); // @codingStandardsIgnoreLine
		$this->apple_pay_domain_set    = 'yes' === $this->get_option( 'apple_pay_domain_set', 'no' );
		$this->apple_pay_verify_notice = '';
	}

	/**
	 * Gets the Stripe settings.
	 *
	 * @since 4.0.6
	 * @param string         $setting
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
	 * Whether the gateway and Express Checkout Buttons (prerequisites for Apple Pay) are enabled.
	 *
	 * @since 4.5.4
	 * @return string Whether Apple Pay required settings are enabled.
	 */
	private function is_enabled() {
		$stripe_enabled = 'yes' === $this->get_option( 'enabled', 'no' );

		$gateway                        = WC_Stripe::get_instance()->get_main_stripe_gateway();
		$payment_request_button_enabled = $gateway->is_payment_request_enabled();

		return $stripe_enabled && $payment_request_button_enabled;
	}

	/**
	 * Gets the Stripe secret key for the current mode.
	 *
	 * @since 4.5.3
	 * @version 4.9.0
	 * @return string Secret key.
	 */
	private function get_secret_key() {
		if ( 'yes' === $this->get_option( 'testmode', 'no' ) ) {
			return $this->get_option( 'test_secret_key' );
		}

		return $this->get_option( 'secret_key' );
	}

	/**
	 * Trigger Apple Pay registration upon domain name change.
	 *
	 * Note: This will also cover the case where Apple Pay is enabled
	 * for the first time for the current domain.
	 *
	 * @since 4.9.0
	 */
	public function verify_domain_on_domain_name_change() {
		if ( $this->domain_name !== $this->get_option( 'apple_pay_verified_domain' ) ) {
			$this->verify_domain_if_configured();
		}
	}

	/**
	 * Vefifies if hosted domain association file is up to date
	 * with the file from the plugin directory.
	 *
	 * @since 4.9.0
	 * @return bool Whether file is up to date or not.
	 */
	private function verify_hosted_domain_association_file_is_up_to_date() {
		// Contents of domain association file from plugin dir.
		$new_contents = @file_get_contents( WC_STRIPE_PLUGIN_PATH . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME ); // @codingStandardsIgnoreLine
		// Get file contents from local path and remote URL and check if either of which matches.
		$fullpath        = untrailingslashit( ABSPATH ) . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
		$local_contents  = @file_get_contents( $fullpath ); // @codingStandardsIgnoreLine
		$url             = get_site_url() . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
		$response        = @wp_remote_get( $url ); // @codingStandardsIgnoreLine
		$remote_contents = @wp_remote_retrieve_body( $response ); // @codingStandardsIgnoreLine

		return $local_contents === $new_contents || $remote_contents === $new_contents;
	}

	/**
	 * Copies and overwrites domain association file.
	 *
	 * @since 4.9.0
	 * @return null|string Error message.
	 */
	private function copy_and_overwrite_domain_association_file() {
		$well_known_dir = untrailingslashit( ABSPATH ) . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR;
		$fullpath       = $well_known_dir . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;

		if ( ! file_exists( $well_known_dir ) ) {
			if ( ! @mkdir( $well_known_dir, 0755 ) ) { // @codingStandardsIgnoreLine
				return __( 'Unable to create domain association folder to domain root.', 'woocommerce-gateway-stripe' );
			}
		}

		if ( ! @copy( WC_STRIPE_PLUGIN_PATH . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME, $fullpath ) ) { // @codingStandardsIgnoreLine
			return __( 'Unable to copy domain association file to domain root.', 'woocommerce-gateway-stripe' );
		}
	}

	/**
	 * Updates the Apple Pay domain association file.
	 * Reports failure only if file isn't already being served properly.
	 *
	 * @since 4.9.0
	 */
	public function update_domain_association_file() {
		if ( $this->verify_hosted_domain_association_file_is_up_to_date() ) {
			return;
		}

		$error_message = $this->copy_and_overwrite_domain_association_file();

		if ( isset( $error_message ) ) {
			$url = get_site_url() . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
			WC_Stripe_Logger::log(
				'Error: ' . $error_message . ' ' .
				/* translators: expected domain association file URL */
				sprintf( __( 'To enable Apple Pay, domain association file must be hosted at %s.', 'woocommerce-gateway-stripe' ), $url )
			);
		} else {
			WC_Stripe_Logger::log( __( 'Domain association file updated.', 'woocommerce-gateway-stripe' ) );
		}
	}

	/**
	 * Adds a rewrite rule for serving the domain association file from the proper location.
	 */
	public function add_domain_association_rewrite_rule() {
		$regex    = '^\\' . self::DOMAIN_ASSOCIATION_FILE_DIR . '\/' . self::DOMAIN_ASSOCIATION_FILE_NAME . '$';
		$redirect = 'index.php?' . self::DOMAIN_ASSOCIATION_FILE_NAME . '=1';

		add_rewrite_rule( $regex, $redirect, 'top' );
	}

	/**
	 * Add to the list of publicly allowed query variables.
	 *
	 * @param  array $query_vars - provided public query vars.
	 * @return array Updated public query vars.
	 */
	public function whitelist_domain_association_query_param( $query_vars ) {
		$query_vars[] = self::DOMAIN_ASSOCIATION_FILE_NAME;
		return $query_vars;
	}

	/**
	 * Serve domain association file when proper query param is provided.
	 *
	 * @param WP WordPress environment object.
	 */
	public function parse_domain_association_request( $wp ) {
		if (
			! isset( $wp->query_vars[ self::DOMAIN_ASSOCIATION_FILE_NAME ] ) ||
			'1' !== $wp->query_vars[ self::DOMAIN_ASSOCIATION_FILE_NAME ]
		) {
			return;
		}

		$path = WC_STRIPE_PLUGIN_PATH . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
		header( 'Content-Type: text/plain;charset=utf-8' );
		echo esc_html( file_get_contents( $path ) );
		exit;
	}

	/**
	 * Makes request to register the domain with Stripe.
	 *
	 * @param string $secret_key
	 * @throws Exception If domain registration request fails.
	 * @since 3.1.0
	 * @version 4.9.0
	 */
	private function make_domain_registration_request( $secret_key ) {
		if ( empty( $secret_key ) ) {
			throw new Exception( __( 'Unable to verify domain - missing secret key.', 'woocommerce-gateway-stripe' ) );
		}

		$endpoint = 'https://api.stripe.com/v1/payment_method_domains';

		$data = [
			'domain_name' => $this->domain_name,
		];

		$headers = [
			'User-Agent'    => 'WooCommerce Stripe',
			'Authorization' => 'Bearer ' . $secret_key,
		];

		$response = wp_remote_post(
			$endpoint,
			[
				'headers' => $headers,
				'body'    => http_build_query( $data ),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			/* translators: error message */
			throw new Exception( sprintf( __( 'Unable to verify domain - %s', 'woocommerce-gateway-stripe' ), $response->get_error_message() ) );
		}

		$parsed_response         = json_decode( $response['body'] );
		$apple_pay_verify_notice = $parsed_response->apple_pay->status_details->error_message ?? '';
		if ( ! empty( $apple_pay_verify_notice ) ) {
			$this->apple_pay_verify_notice = $apple_pay_verify_notice;

			/* translators: error message */
			throw new Exception( sprintf( __( 'Unable to verify domain - %s', 'woocommerce-gateway-stripe' ), $apple_pay_verify_notice ) );
		}
	}

	/**
	 * Processes a payment method domain verification.
	 *
	 * @since 3.1.0
	 * @version 4.5.4
	 *
	 * @param string $secret_key
	 *
	 * @return bool Whether domain verification succeeded.
	 */
	public function register_domain( $secret_key ) {
		try {
			$this->make_domain_registration_request( $secret_key );

			// No errors to this point, verification success!
			$this->stripe_settings['apple_pay_verified_domain'] = $this->domain_name;
			$this->stripe_settings['apple_pay_domain_set']      = 'yes';
			$this->apple_pay_domain_set                         = true;

			WC_Stripe_Helper::update_main_stripe_settings( $this->stripe_settings );

			WC_Stripe_Logger::log( 'Your domain has been verified with Apple Pay!' );

			return true;

		} catch ( Exception $e ) {
			$this->stripe_settings['apple_pay_verified_domain'] = $this->domain_name;
			$this->stripe_settings['apple_pay_domain_set']      = 'no';
			$this->apple_pay_domain_set                         = false;

			WC_Stripe_Helper::update_main_stripe_settings( $this->stripe_settings );

			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Process the Apple Pay domain verification if proper settings are configured.
	 *
	 * @since 4.5.4
	 * @version 4.9.0
	 */
	public function verify_domain_if_configured() {
		$secret_key = $this->get_secret_key();

		if ( ! $this->is_enabled() || empty( $secret_key ) ) {
			return;
		}

		if ( ! $this->is_available() ) {
			return;
		}

		// Ensure that domain association file will be served.
		flush_rewrite_rules();

		// The rewrite rule method doesn't work if permalinks are set to Plain.
		// Create/update domain association file by copying it from the plugin folder as a fallback.
		$this->update_domain_association_file();

		// Register the domain with Apple Pay.
		$verification_complete = $this->register_domain( $secret_key );

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
		$this->verify_domain_on_updated_settings( [], $settings );
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
		$this->stripe_settings = $settings;

		// If secret key was different, then we might need to verify again.
		if ( $this->get_secret_key() !== $prev_secret_key ) {
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
		$allowed_html                      = [
			'a' => [
				'href'  => [],
				'title' => [],
			],
		];
		$verification_failed_without_error = __( 'Apple Pay domain verification failed.', 'woocommerce-gateway-stripe' );
		$verification_failed_with_error    = __( 'Apple Pay domain verification failed with the following error:', 'woocommerce-gateway-stripe' );
		?>
		<div class="error stripe-apple-pay-message">
			<?php if ( $empty_notice ) : ?>
				<p><?php echo esc_html( $verification_failed_without_error ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html( $verification_failed_with_error ); ?></p>
				<p><i><?php echo wp_kses( make_clickable( esc_html( $this->apple_pay_verify_notice ) ), $allowed_html ); ?></i></p>
			<?php endif; ?>
			<p>
				<?php
					printf(
						/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
						esc_html__( 'Please check the %1$slogs%2$s for more details on this issue. Logging must be enabled to see recorded logs.', 'woocommerce-gateway-stripe' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '">',
						'</a>'
					);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Returns whether Apple Pay can be registered.
	 *
	 * @since 7.6.0
	 *
	 * @return boolean
	 */
	private function is_available(): bool {
		$cached_account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country     = $cached_account_data['country'] ?? null;

		// Stripe Elements doesn’t support Apple Pay for Stripe accounts in India.
		// https://docs.stripe.com/stripe-js/elements/payment-request-button?client=html#prerequisites
		return 'IN' !== $account_country;
	}
}

new WC_Stripe_Apple_Pay_Registration();
