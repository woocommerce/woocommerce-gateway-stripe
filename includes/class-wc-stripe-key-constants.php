<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overrides the stored Stripe secret keys with wp-config.php constants.
 *
 * Applied on the option filters (not the plugin's settings accessors) because
 * WooCommerce core also reads and writes this option directly, and every
 * reader must see the same key. Usage:
 *
 *     define( 'WC_STRIPE_SECRET_KEY', 'rk_live_...' );
 *     define( 'WC_STRIPE_TEST_SECRET_KEY', 'rk_test_...' );
 *
 * @since 11.0.0
 */
class WC_Stripe_Key_Constants {

	/**
	 * Overridable settings fields mapped to their constant. Secret keys only:
	 * publishable keys are not sensitive, and webhook signing secrets are
	 * needed at runtime to verify inbound events.
	 *
	 * @var array<string, string>
	 */
	protected const FIELD_CONSTANTS = [
		'secret_key'      => 'WC_STRIPE_SECRET_KEY',
		'test_secret_key' => 'WC_STRIPE_TEST_SECRET_KEY',
	];

	/**
	 * The *Singleton* instance of this class.
	 *
	 * @var WC_Stripe_Key_Constants|null
	 */
	private static $instance = null;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Stripe_Key_Constants
	 */
	public static function get_instance(): WC_Stripe_Key_Constants {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the singleton's option filters.
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance()->register_filters();
	}

	/**
	 * Hooks the override into every read and write of the Stripe settings option.
	 *
	 * @return void
	 */
	public function register_filters(): void {
		// Priority 999 so the override wins over other filters on this option.
		add_filter( 'option_' . WC_Stripe::SETTINGS_OPTION_NAME, [ $this, 'apply_overrides' ], 999 );
		add_filter( 'pre_update_option_' . WC_Stripe::SETTINGS_OPTION_NAME, [ $this, 'strip_overrides_before_save' ], 999 );
	}

	/**
	 * Removes the option filters.
	 *
	 * @return void
	 */
	public function unregister_filters(): void {
		remove_filter( 'option_' . WC_Stripe::SETTINGS_OPTION_NAME, [ $this, 'apply_overrides' ], 999 );
		remove_filter( 'pre_update_option_' . WC_Stripe::SETTINGS_OPTION_NAME, [ $this, 'strip_overrides_before_save' ], 999 );
	}

	/**
	 * Whether any secret key is currently overridden by a constant.
	 *
	 * @return bool
	 */
	public function has_overrides(): bool {
		return [] !== $this->get_configured_overrides();
	}

	/**
	 * Substitutes the constant-defined keys into the settings on every read.
	 *
	 * @param mixed $settings The stored settings. Not guaranteed to be an array.
	 * @return mixed
	 */
	public function apply_overrides( $settings ) {
		$overrides = $this->get_configured_overrides();

		// Non-array values are normalized by the readers themselves.
		if ( [] === $overrides || ! is_array( $settings ) ) {
			return $settings;
		}

		return array_merge( $settings, $overrides );
	}

	/**
	 * Strips the substituted keys back out before the settings are written,
	 * so a read-modify-write save never persists the constant into the
	 * database. Only exact matches are reverted; a deliberately changed key
	 * is written as-is.
	 *
	 * @param mixed $value The settings about to be written.
	 * @return mixed
	 */
	public function strip_overrides_before_save( $value ) {
		$overrides = $this->get_configured_overrides();

		if ( [] === $overrides || ! is_array( $value ) ) {
			return $value;
		}

		$stored = null;

		foreach ( $overrides as $field => $override_value ) {
			if ( ! isset( $value[ $field ] ) || $value[ $field ] !== $override_value ) {
				continue;
			}

			if ( null === $stored ) {
				$stored = $this->get_stored_settings_unfiltered();
			}

			// Restore rather than blank, so a constant never destroys the stored key.
			$value[ $field ] = isset( $stored[ $field ] ) && is_string( $stored[ $field ] ) ? $stored[ $field ] : '';
		}

		return $value;
	}

	/**
	 * Reads the settings option as stored, bypassing apply_overrides().
	 * The old value pre_update_option filters receive is already filtered,
	 * so it holds the constant, not the stored key.
	 *
	 * @return array
	 */
	private function get_stored_settings_unfiltered(): array {
		remove_filter( 'option_' . WC_Stripe::SETTINGS_OPTION_NAME, [ $this, 'apply_overrides' ], 999 );
		$stored = get_option( WC_Stripe::SETTINGS_OPTION_NAME, [] );
		add_filter( 'option_' . WC_Stripe::SETTINGS_OPTION_NAME, [ $this, 'apply_overrides' ], 999 );

		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Returns the configured overrides as field => key.
	 *
	 * @return array<string, string>
	 */
	private function get_configured_overrides(): array {
		$overrides = [];

		foreach ( static::FIELD_CONSTANTS as $field => $constant ) {
			$value = $this->get_constant_value( $constant );

			// Ignore blank or non-string constants: injecting an empty key
			// would silently disconnect the store.
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$overrides[ $field ] = trim( $value );
			}
		}

		return $overrides;
	}

	/**
	 * Reads a constant's value. Seam for tests, since constants cannot be
	 * undefined once set.
	 *
	 * @param string $constant The constant name.
	 * @return mixed|null Null when the constant is not defined.
	 */
	protected function get_constant_value( string $constant ) {
		return defined( $constant ) ? constant( $constant ) : null;
	}
}
