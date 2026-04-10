<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class to handle Stripe settings.
 *
 * All setter methods update in-memory state only. Call save() to persist changes to the database.
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
	 * Whether the option update hook has been registered.
	 *
	 * @var bool
	 */
	private static bool $hook_registered = false;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$settings = get_option( self::SETTINGS_OPTION, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$this->settings = $settings;

		// Invalidate the singleton when the option is updated or deleted externally.
		if ( ! self::$hook_registered ) {
			add_action( 'update_option_' . self::SETTINGS_OPTION, [ __CLASS__, 'reset' ] );
			add_action( 'delete_option_' . self::SETTINGS_OPTION, [ __CLASS__, 'reset' ] );
			self::$hook_registered = true;
		}
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
	 * Reset the singleton instance so the next call to get_instance() re-reads from the DB.
	 * Use when the settings option is updated outside this class.
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	// ── enabled ──────────────────────────────────────────────────────────

	/**
	 * Whether the Stripe gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return 'yes' === ( $this->settings['enabled'] ?? '' );
	}

	/**
	 * Set the `enabled` setting.
	 *
	 * @param string $value 'yes' or 'no'.
	 */
	public function set_enabled( string $value ): void {
		$this->settings['enabled'] = $value;
	}

	// ── testmode ─────────────────────────────────────────────────────────

	/**
	 * Whether test mode is active (legacy `testmode` key).
	 *
	 * @return bool
	 */
	public function is_testmode(): bool {
		return 'yes' === ( $this->settings['testmode'] ?? '' );
	}

	/**
	 * Set the `testmode` setting.
	 *
	 * @param string $value 'yes' or 'no'.
	 */
	public function set_testmode( string $value ): void {
		$this->settings['testmode'] = $value;
	}

	// ── logging ──────────────────────────────────────────────────────────

	/**
	 * Get the value of the `logging` setting.
	 *
	 * @return string
	 */
	public function get_logging(): string {
		return $this->settings['logging'] ?? '';
	}

	// ── keys ─────────────────────────────────────────────────────────────

	/**
	 * Get the publishable key.
	 *
	 * @return string
	 */
	public function get_publishable_key(): string {
		return $this->settings['publishable_key'] ?? '';
	}

	/**
	 * Set the publishable key.
	 *
	 * @param string $value The publishable key.
	 */
	public function set_publishable_key( string $value ): void {
		$this->settings['publishable_key'] = $value;
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
	 * Set the secret key.
	 *
	 * @param string $value The secret key.
	 */
	public function set_secret_key( string $value ): void {
		$this->settings['secret_key'] = $value;
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
	 * Set the test publishable key.
	 *
	 * @param string $value The test publishable key.
	 */
	public function set_test_publishable_key( string $value ): void {
		$this->settings['test_publishable_key'] = $value;
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
	 * Set the test secret key.
	 *
	 * @param string $value The test secret key.
	 */
	public function set_test_secret_key( string $value ): void {
		$this->settings['test_secret_key'] = $value;
	}

	// ── statement descriptor ─────────────────────────────────────────────

	/**
	 * Get the statement descriptor.
	 *
	 * @return string
	 */
	public function get_statement_descriptor(): string {
		return $this->settings['statement_descriptor'] ?? '';
	}

	// ── express checkout button ──────────────────────────────────────────

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
	 * Pages where the express checkout buttons should be displayed (legacy key).
	 *
	 * @return array<int, string>
	 */
	public function get_express_checkout_button_locations(): array {
		if ( ! isset( $this->settings['payment_request_button_locations'] ) ) {
			return [ 'product', 'cart' ];
		}

		if ( ! is_array( $this->settings['payment_request_button_locations'] ) ) {
			return [];
		}

		return $this->settings['payment_request_button_locations'];
	}

	/**
	 * Set the payment request button locations (legacy key).
	 *
	 * @param array<int, string> $value The value to set.
	 */
	public function set_payment_request_button_locations( array $value ): void {
		$this->settings['payment_request_button_locations'] = $value;
	}

	/**
	 * Get the express checkout button locations (new key).
	 *
	 * @return array<int, string>
	 */
	public function get_new_express_checkout_button_locations(): array {
		if ( ! isset( $this->settings['express_checkout_button_locations'] ) ) {
			return [];
		}

		if ( ! is_array( $this->settings['express_checkout_button_locations'] ) ) {
			return [];
		}

		return $this->settings['express_checkout_button_locations'];
	}

	/**
	 * Set the express checkout button locations (new key).
	 *
	 * @param array<int, string> $value The value to set.
	 */
	public function set_new_express_checkout_button_locations( array $value ): void {
		$this->settings['express_checkout_button_locations'] = $value;
	}

	// ── UPE ──────────────────────────────────────────────────────────────

	/**
	 * Get the value of the `upe_checkout_experience_accepted_payments` setting.
	 *
	 * @return array<int, string>
	 */
	public function get_upe_checkout_experience_accepted_payments(): array {
		$value = $this->settings['upe_checkout_experience_accepted_payments'] ?? null;

		if ( ! is_array( $value ) || [] === $value ) {
			return [ WC_Stripe_Payment_Methods::CARD ];
		}

		return $value;
	}

	/**
	 * Get the UPE checkout experience enabled flag.
	 *
	 * @return string
	 */
	public function get_upe_checkout_experience_enabled(): string {
		return $this->settings['upe_checkout_experience_enabled'] ?? '';
	}

	/**
	 * Set the UPE checkout experience enabled flag.
	 *
	 * @param string $value The value to set.
	 */
	public function set_upe_checkout_experience_enabled( string $value ): void {
		$this->settings['upe_checkout_experience_enabled'] = $value;
	}

	/**
	 * Get the value of the `stripe_upe_payment_method_order` setting.
	 *
	 * @return array<int, string>
	 */
	public function get_stripe_upe_payment_method_order(): array {
		$value = $this->settings['stripe_upe_payment_method_order'] ?? [];
		return is_array( $value ) ? $value : [];
	}

	/**
	 * Set the `stripe_upe_payment_method_order` setting.
	 *
	 * @param array<int, string> $value The value to set.
	 */
	public function set_stripe_upe_payment_method_order( array $value ): void {
		$this->settings['stripe_upe_payment_method_order'] = $value;
	}

	// ── webhooks ─────────────────────────────────────────────────────────

	/**
	 * Get the value of the `webhook_secret` setting.
	 *
	 * @return string
	 */
	public function get_webhook_secret(): string {
		return $this->settings['webhook_secret'] ?? '';
	}

	/**
	 * Set the `webhook_secret` setting.
	 *
	 * @param string $value The webhook secret.
	 */
	public function set_webhook_secret( string $value ): void {
		$this->settings['webhook_secret'] = $value;
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
	 * Set the `test_webhook_secret` setting.
	 *
	 * @param string $value The test webhook secret.
	 */
	public function set_test_webhook_secret( string $value ): void {
		$this->settings['test_webhook_secret'] = $value;
	}

	/**
	 * Get the webhook data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_webhook_data(): array {
		return is_array( $this->settings['webhook_data'] ?? null ) ? $this->settings['webhook_data'] : [];
	}

	/**
	 * Set the webhook data.
	 *
	 * @param array<string, mixed> $value The webhook data.
	 */
	public function set_webhook_data( array $value ): void {
		$this->settings['webhook_data'] = $value;
	}

	/**
	 * Get the test webhook data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_test_webhook_data(): array {
		return is_array( $this->settings['test_webhook_data'] ?? null ) ? $this->settings['test_webhook_data'] : [];
	}

	/**
	 * Set the test webhook data.
	 *
	 * @param array<string, mixed> $value The test webhook data.
	 */
	public function set_test_webhook_data( array $value ): void {
		$this->settings['test_webhook_data'] = $value;
	}

	// ── connection ───────────────────────────────────────────────────────

	/**
	 * Get the value of the `connection_type` setting.
	 *
	 * @return string
	 */
	public function get_connection_type(): string {
		return $this->settings['connection_type'] ?? '';
	}

	/**
	 * Set the `connection_type` setting.
	 *
	 * @param string $value The connection type.
	 */
	public function set_connection_type( string $value ): void {
		$this->settings['connection_type'] = $value;
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
	 * Set the `test_connection_type` setting.
	 *
	 * @param string $value The test connection type.
	 */
	public function set_test_connection_type( string $value ): void {
		$this->settings['test_connection_type'] = $value;
	}

	/**
	 * Get the refresh token.
	 *
	 * @return string
	 */
	public function get_refresh_token(): string {
		return $this->settings['refresh_token'] ?? '';
	}

	/**
	 * Set the refresh token.
	 *
	 * @param string $value The refresh token.
	 */
	public function set_refresh_token( string $value ): void {
		$this->settings['refresh_token'] = $value;
	}

	/**
	 * Get the test refresh token.
	 *
	 * @return string
	 */
	public function get_test_refresh_token(): string {
		return $this->settings['test_refresh_token'] ?? '';
	}

	/**
	 * Set the test refresh token.
	 *
	 * @param string $value The test refresh token.
	 */
	public function set_test_refresh_token( string $value ): void {
		$this->settings['test_refresh_token'] = $value;
	}

	// ── PMC ──────────────────────────────────────────────────────────────

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
	 * Set the `skip_pmc_express_checkout_defaults` setting.
	 *
	 * @param string $value 'yes' or 'no'.
	 */
	public function set_skip_pmc_express_checkout_defaults( string $value ): void {
		$this->settings['skip_pmc_express_checkout_defaults'] = $value;
	}

	// ── payment request / express checkout ────────────────────────────────

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
	 * Whether express checkout is enabled.
	 *
	 * @return bool
	 */
	public function is_express_checkout_enabled(): bool {
		return 'yes' === ( $this->settings['express_checkout'] ?? '' );
	}

	// ── optimized checkout ───────────────────────────────────────────────

	/**
	 * Whether the optimized checkout element is enabled.
	 *
	 * @return bool
	 */
	public function is_optimized_checkout_enabled(): bool {
		return 'yes' === ( $this->settings['optimized_checkout_element'] ?? 'no' );
	}

	/**
	 * Set the `optimized_checkout_element` setting.
	 *
	 * @param string $value 'yes' or 'no'.
	 */
	public function set_optimized_checkout_element( string $value ): void {
		$this->settings['optimized_checkout_element'] = $value;
	}

	// ── capture ──────────────────────────────────────────────────────────

	/**
	 * Whether automatic capture is enabled.
	 *
	 * @return bool
	 */
	public function is_capture_enabled(): bool {
		return 'yes' === ( $this->settings['capture'] ?? 'yes' );
	}

	/**
	 * Whether the short statement descriptor is enabled.
	 *
	 * @return bool
	 */
	public function is_short_statement_descriptor_enabled(): bool {
		return 'yes' === ( $this->settings['is_short_statement_descriptor_enabled'] ?? 'no' );
	}

	// ── saved cards / 3DS ────────────────────────────────────────────────

	/**
	 * Whether saved cards are enabled.
	 *
	 * @return bool
	 */
	public function is_saved_cards_enabled(): bool {
		return 'yes' === ( $this->settings['saved_cards'] ?? 'no' );
	}

	/**
	 * Whether 3D Secure is enabled.
	 *
	 * @return bool
	 */
	public function is_three_d_secure_enabled(): bool {
		return 'yes' === ( $this->settings['three_d_secure'] ?? '' );
	}

	// ── Apple Pay ────────────────────────────────────────────────────────

	/**
	 * Whether the Apple Pay domain has been set.
	 *
	 * @return bool
	 */
	public function is_apple_pay_domain_set(): bool {
		return 'yes' === ( $this->settings['apple_pay_domain_set'] ?? '' );
	}

	/**
	 * Set the `apple_pay_domain_set` setting.
	 *
	 * @param string $value 'yes' or 'no'.
	 */
	public function set_apple_pay_domain_set( string $value ): void {
		$this->settings['apple_pay_domain_set'] = $value;
	}

	/**
	 * Get the Apple Pay verified domain.
	 *
	 * @return string
	 */
	public function get_apple_pay_verified_domain(): string {
		return $this->settings['apple_pay_verified_domain'] ?? '';
	}

	/**
	 * Set the Apple Pay verified domain.
	 *
	 * @param string $value The domain name.
	 */
	public function set_apple_pay_verified_domain( string $value ): void {
		$this->settings['apple_pay_verified_domain'] = $value;
	}

	// ── other getters ────────────────────────────────────────────────────

	/**
	 * Get the gateway title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->settings['title'] ?? '';
	}

	/**
	 * Get the legacy method order.
	 *
	 * @return array<int, string>
	 */
	public function get_stripe_legacy_method_order(): array {
		return $this->settings['stripe_legacy_method_order'] ?? [];
	}

	/**
	 * Whether keys are configured for the given mode.
	 *
	 * @param bool $test_mode Whether to check test keys.
	 * @return bool
	 */
	public function are_keys_set( bool $test_mode = false ): bool {
		if ( $test_mode ) {
			return '' !== $this->get_test_publishable_key() && '' !== $this->get_test_secret_key();
		}
		return '' !== $this->get_publishable_key() && '' !== $this->get_secret_key();
	}

	// ── generic access ───────────────────────────────────────────────────

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
	 * Set a setting value by key (in memory only — call save() to persist).
	 *
	 * Prefer using a specific setter when one exists.
	 *
	 * @param string $key   The setting key.
	 * @param mixed  $value The value to set.
	 */
	public function set( string $key, $value ): void {
		$this->settings[ $key ] = $value;
	}

	/**
	 * Remove a setting key (in memory only — call save() to persist).
	 *
	 * @param string $key The setting key.
	 */
	public function delete( string $key ): void {
		unset( $this->settings[ $key ] );
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

	/**
	 * Persist the current in-memory settings to the database.
	 */
	public function save(): void {
		update_option( self::SETTINGS_OPTION, $this->settings );
	}
}
