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
	 * Cached Stripe settings.
	 *
	 * @var
	 */
	private $stripe_settings;

	/**
	 * Current domain name.
	 *
	 * @var bool
	 */
	private $domain_name;

	/**
	 * Stores Apple Pay domain registration issues.
	 *
	 * @var string
	 */
	public $apple_pay_registration_notice;

	public function __construct() {
		add_action( 'admin_init', [ $this, 'register_domain_on_domain_name_change' ] );
		add_action( 'update_option_woocommerce_stripe_settings', [ $this, 'register_domain_on_updated_settings' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		$this->domain_name                   = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : str_replace( array( 'https://', 'http://' ), '', get_site_url() ); // @codingStandardsIgnoreLine
		$this->apple_pay_registration_notice = '';
	}

	/**
	 * Gets the Stripe settings.
	 *
	 * @since 4.0.6
	 * @param string $setting
	 * @param string default
	 * @return string $setting_value
	 */
	public function get_option( $setting = '', $default_value = '' ) {
		if ( empty( $this->stripe_settings ) ) {
			$this->stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		}

		if ( ! empty( $this->stripe_settings[ $setting ] ) ) {
			return $this->stripe_settings[ $setting ];
		}

		return $default_value;
	}

	/**
	 * Whether the domain is set.
	 *
	 * @return bool
	 */
	public function is_domain_set() {
		return 'yes' === $this->get_option( 'apple_pay_domain_set', 'no' );
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
	 *
	 * @param array $settings Optional. The Stripe settings to check.
	 *
	 * @return string Secret key.
	 */
	private function get_secret_key( $settings = null ) {
		$key_name = ( 'yes' === $this->get_option( 'testmode', 'no' ) ) ? 'test_secret_key' : 'secret_key';

		if ( ! empty( $settings ) && isset( $settings[ $key_name ] ) ) {
			return $settings[ $key_name ];
		}

		return $this->get_option( $key_name );
	}

	/**
	 * Trigger Apple Pay registration upon domain name change.
	 *
	 * Note: This will also cover the case where Apple Pay is enabled
	 * for the first time for the current domain.
	 *
	 * @since 4.9.0
	 */
	public function register_domain_on_domain_name_change() {
		if ( $this->domain_name !== $this->get_option( 'apple_pay_verified_domain' ) ) {
			$this->register_domain_if_configured();
		}
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
			throw new Exception( __( 'Unable to register domain - missing secret key.', 'woocommerce-gateway-stripe' ) );
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
			throw new Exception( sprintf( __( 'Unable to register domain - %s', 'woocommerce-gateway-stripe' ), $response->get_error_message() ) );
		}

		$parsed_response               = json_decode( $response['body'] );
		$apple_pay_registration_notice = $parsed_response->apple_pay->status_details->error_message ?? '';
		if ( ! empty( $apple_pay_registration_notice ) ) {
			$this->apple_pay_registration_notice = $apple_pay_registration_notice;

			/* translators: error message */
			throw new Exception( sprintf( __( 'Unable to register domain - %s', 'woocommerce-gateway-stripe' ), $apple_pay_registration_notice ) );
		}
	}

	/**
	 * Processes a payment method domain registration.
	 *
	 * @since 3.1.0
	 * @version 4.5.4
	 *
	 * @param string $secret_key
	 *
	 * @return bool Whether domain registration succeeded.
	 */
	public function register_domain( $secret_key ) {
		try {
			$this->make_domain_registration_request( $secret_key );

			// No errors to this point, registration success!
			// Reload the settings, to avoid overwriting old, cached values.
			$settings                              = WC_Stripe_Helper::get_stripe_settings();
			$settings['apple_pay_verified_domain'] = $this->domain_name;
			$settings['apple_pay_domain_set']      = 'yes';
			WC_Stripe_Helper::update_main_stripe_settings( $settings );

			// Update cached settings.
			$this->stripe_settings = $settings;

			WC_Stripe_Logger::log( 'Your domain has been registered with Apple Pay!' );

			return true;

		} catch ( Exception $e ) {
			$settings                              = WC_Stripe_Helper::get_stripe_settings();
			$settings['apple_pay_verified_domain'] = $this->domain_name;
			$settings['apple_pay_domain_set']      = 'no';
			WC_Stripe_Helper::update_main_stripe_settings( $settings );

			// Update cached settings.
			$this->stripe_settings = $settings;

			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Process the Apple Pay domain registration if proper settings are configured.
	 *
	 * @since 4.5.4
	 * @version 4.9.0
	 */
	public function register_domain_if_configured() {
		$secret_key = $this->get_secret_key();

		if ( ! $this->is_enabled() || empty( $secret_key ) ) {
			return;
		}

		if ( ! $this->is_available() ) {
			return;
		}

		// Register the domain with Apple Pay.
		$registration_complete = $this->register_domain( $secret_key );

		// Show/hide notes if necessary.
		WC_Stripe_Inbox_Notes::notify_on_apple_pay_domain_registration( $registration_complete );
	}

	/**
	 * Conditionally process the Apple Pay domain registration after settings are updated.
	 *
	 * @param array $prev_settings The settings before the update.
	 * @param array $settings The settings after the update.
	 *
	 * @return void
	 * @since 4.5.3
	 * @version 4.5.4
	 */
	public function register_domain_on_updated_settings( $prev_settings, $settings ) {
		$prev_secret_key    = $this->get_secret_key( $prev_settings );
		$current_secret_key = $this->get_secret_key( $settings );

		// If secret key was different, then we might need to register again.
		if ( $current_secret_key !== $prev_secret_key ) {
			$this->register_domain_if_configured();
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

		$empty_notice = empty( $this->apple_pay_registration_notice );
		if ( $empty_notice && ( $this->is_domain_set() || empty( $this->get_secret_key() ) ) ) {
			return;
		}

		/**
		 * Apple pay is enabled by default and domain registration initializes
		 * when setting screen is displayed. So if domain registration is not set,
		 * something went wrong so lets notify user.
		 */
		$allowed_html                      = [
			'a' => [
				'href'  => [],
				'title' => [],
			],
		];
		$registration_failed_without_error = __( 'Apple Pay domain registration failed.', 'woocommerce-gateway-stripe' );
		$registration_failed_with_error    = __( 'Apple Pay domain registration failed with the following error:', 'woocommerce-gateway-stripe' );
		?>
		<div class="error stripe-apple-pay-message">
			<?php if ( $empty_notice ) : ?>
				<p><?php echo esc_html( $registration_failed_without_error ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html( $registration_failed_with_error ); ?></p>
				<p><i><?php echo wp_kses( make_clickable( esc_html( $this->apple_pay_registration_notice ) ), $allowed_html ); ?></i></p>
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
