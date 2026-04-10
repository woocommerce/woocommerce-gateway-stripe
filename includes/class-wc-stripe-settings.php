<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class to handle Stripe settings.
 */
class WC_Stripe_Settings {
	/**
	 * Option name for storing Stripe settings.
	 *
	 * @var string
	 */
	const SETTINGS_OPTION = 'woocommerce_stripe_settings';

	/**
	 * The *Singleton* instance of this class
	 *
	 * @var WC_Stripe_Settings|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings array.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings = get_option( self::SETTINGS_OPTION, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$this->settings = $settings;
	}

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Stripe_Settings The *Singleton* instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Whether the Stripe gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return 'yes' === ( $this->settings['enabled'] ?? '' );
	}

	/**
	 * Get the value of the `logging` setting.
	 *
	 * @return string
	 */
	public function get_logging(): string {
		return $this->settings['logging'] ?? '';
	}

	/**
	 * Get the publishable key.
	 *
	 * @return string
	 */
	public function get_publishable_key(): string {
		return $this->settings['publishable_key'] ?? '';
	}

	/**
	 * Get the secret key.
	 *
	 * @return string
	 */
	public function get_secret_key(): string {
		return $this->settings['secret_key'] ?? '';
	}

	/**
	 * Get the statement descriptor.
	 *
	 * @return string
	 */
	public function get_statement_descriptor(): string {
		return $this->settings['statement_descriptor'] ?? '';
	}

	/**
	 * Get the test publishable key.
	 *
	 * @return string
	 */
	public function get_test_publishable_key(): string {
		return $this->settings['test_publishable_key'] ?? '';
	}

	/**
	 * Get the test secret key.
	 *
	 * @return string
	 */
	public function get_test_secret_key(): string {
		return $this->settings['test_secret_key'] ?? '';
	}

	/**
	 * Get the express checkout button type.
	 *
	 * @return string
	 */
	public function get_express_checkout_button_type(): string {
		return $this->settings['payment_request_button_type'] ?? 'default';
	}

	/**
	 * Get the express checkout button theme.
	 *
	 * @return string
	 */
	public function get_express_checkout_button_theme(): string {
		return $this->settings['payment_request_button_theme'] ?? 'dark';
	}

	/**
	 * Gets the button height.
	 *
	 * @return string
	 */
	public function get_express_checkout_button_height(): string {
		$height = $this->settings['payment_request_button_size'] ?? 'default';
		if ( 'small' === $height ) {
			return '40';
		}

		if ( 'large' === $height ) {
			return '56';
		}

		return '48';
	}

	/**
	 * Gets the button radius.
	 *
	 * @return string
	 */
	public function get_express_checkout_button_radius(): string {
		$height = $this->settings['payment_request_button_size'] ?? 'default';
		if ( 'small' === $height ) {
			return '2';
		}

		if ( 'large' === $height ) {
			return '6';
		}

		return '4';
	}

	/**
	 * Pages where the express checkout buttons should be displayed.
	 *
	 * @return array<int, string>
	 */
	public function get_express_checkout_button_locations(): array {
		// If the locations have not been set return the default setting.
		if ( ! isset( $this->settings['payment_request_button_locations'] ) ) {
			return [ 'product', 'cart' ];
		}

		// If all locations are removed through the settings UI the location config will be set to
		// an empty string "". If that's the case (and if the settings are not an array for any
		// other reason) we should return an empty array.
		if ( ! is_array( $this->settings['payment_request_button_locations'] ) ) {
			return [];
		}

		return $this->settings['payment_request_button_locations'];
	}

	/**
	 * Set the payment request button locations.
	 *
	 * @param array<int, string> $value The value to set.
	 */
	public function set_payment_request_button_locations( array $value ): void {
		$this->settings['payment_request_button_locations'] = $value;
		update_option( self::SETTINGS_OPTION, $this->settings );
	}

	/**
	 * Get the test mode setting.
	 *
	 * @return string
	 */
	public function get_test_mode(): string {
		return $this->settings['test_mode'] ?? '';
	}

	/**
	 * Get the value of the `upe_checkout_experience_accepted_payments` setting.
	 *
	 * @return array<int, string>
	 */
	public function get_upe_checkout_experience_accepted_payments(): array {
		return ! empty( $this->settings['upe_checkout_experience_accepted_payments'] )
			? $this->settings['upe_checkout_experience_accepted_payments']
			: [ WC_Stripe_Payment_Methods::CARD ];
	}

	/**
	 * Get the value of the `webhook_secret` setting.
	 *
	 * @return string
	 */
	public function get_webhook_secret(): string {
		return $this->settings['webhook_secret'] ?? '';
	}

	/**
	 * Get the value of the `test_webhook_secret` setting.
	 *
	 * @return string
	 */
	public function get_test_webhook_secret(): string {
		return $this->settings['test_webhook_secret'] ?? '';
	}

	/**
	 * Get the value of the `connection_type` setting.
	 *
	 * @return string
	 */
	public function get_connection_type(): string {
		return $this->settings['connection_type'] ?? '';
	}

	/**
	 * Get the value of the `test_connection_type` setting.
	 *
	 * @return string
	 */
	public function get_test_connection_type(): string {
		return $this->settings['test_connection_type'] ?? '';
	}

	/**
	 * Get the value of the `pmc_enabled` setting.
	 *
	 * @return string
	 */
	public function get_pmc_enabled(): string {
		return $this->settings['pmc_enabled'] ?? '';
	}

	/**
	 * Set the `pmc_enabled` setting.
	 *
	 * @param string $value The value to set.
	 */
	public function set_pmc_enabled( string $value ): void {
		$this->settings['pmc_enabled'] = $value;
		update_option( self::SETTINGS_OPTION, $this->settings );
	}

	/**
	 * Get the value of the `payment_request` setting.
	 *
	 * @return string
	 */
	public function get_payment_request(): string {
		return $this->settings['payment_request'] ?? '';
	}

	/**
	 * Get the value of the `express_checkout` setting.
	 *
	 * @return string
	 */
	public function get_express_checkout(): string {
		return $this->settings['express_checkout'] ?? '';
	}

	/**
	 * Get the value of the `skip_pmc_express_checkout_defaults` setting.
	 *
	 * @return string
	 */
	public function get_skip_pmc_express_checkout_defaults(): string {
		return $this->settings['skip_pmc_express_checkout_defaults'] ?? 'no';
	}

	/**
	 * Get the value of the `stripe_upe_payment_method_order` setting.
	 *
	 * @return array<int, string>
	 */
	public function get_stripe_upe_payment_method_order(): array {
		return $this->settings['stripe_upe_payment_method_order'] ?? [];
	}

	/**
	 * Set the `stripe_upe_payment_method_order` setting.
	 *
	 * @param array<int, string> $value The value to set.
	 */
	public function set_stripe_upe_payment_method_order( array $value ): void {
		$this->settings['stripe_upe_payment_method_order'] = $value;
		update_option( self::SETTINGS_OPTION, $this->settings );
	}

	/**
	 * Get a setting value by key.
	 *
	 * @param string $key     The setting key.
	 * @param mixed  $default The default value if the key is not set.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Check if a setting key exists.
	 *
	 * @param string $key The setting key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return isset( $this->settings[ $key ] );
	}
}
