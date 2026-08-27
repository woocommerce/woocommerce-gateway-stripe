<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overrides the stored Stripe secret keys with values defined as constants.
 *
 * Merchants define the constants in wp-config.php:
 *
 *     define( 'WC_STRIPE_SECRET_KEY', 'rk_live_...' );
 *     define( 'WC_STRIPE_TEST_SECRET_KEY', 'rk_test_...' );
 *
 * This lets merchants keep the secret key (for example a Restricted API Key)
 * out of the database. The override is applied on the option filters rather
 * than inside the plugin's own settings accessors because WooCommerce core
 * also reads and writes this option directly (WC_Settings_API::init_settings()
 * and process_admin_options()), and every reader must see the same key.
 *
 * @since 11.0.0
 */
class WC_Stripe_Key_Constants {

	/**
	 * The settings fields that can be overridden, mapped to the constant that overrides each.
	 *
	 * Only secret keys are overridable: publishable keys are not sensitive, and
	 * webhook signing secrets are required at runtime to verify inbound events.
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
	 * Removes the option filters. Mainly useful for tests and for reading the raw stored value.
	 *
	 * @return void
	 */
	public function unregister_filters(): void {
		remove_filter( 'option_' . WC_Stripe::SETTINGS_OPTION_NAME, [ $this, 'apply_overrides' ], 999 );
		remove_filter( 'pre_update_option_' . WC_Stripe::SETTINGS_OPTION_NAME, [ $this, 'strip_overrides_before_save' ], 999 );
	}

	/**
	 * Whether any secret key is currently being overridden by a constant.
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

		// A non-array value is normalized by the readers themselves; forcing an
		// array here would change behavior for a store that has never saved settings.
		if ( [] === $overrides || ! is_array( $settings ) ) {
			return $settings;
		}

		return array_merge( $settings, $overrides );
	}

	/**
	 * Strips the substituted keys back out before the settings are written.
	 *
	 * Settings writes are read-modify-write: the caller read the settings through
	 * apply_overrides(), so without this filter the first save would persist the
	 * constant-defined key into the database, defeating the point of keeping it
	 * in wp-config.php. Only a value identical to the constant is reverted, so a
	 * caller that deliberately writes a different key is not interfered with.
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

			// Restore the stored value rather than blanking it, so defining a
			// constant never destroys the key already saved in the database.
			$value[ $field ] = isset( $stored[ $field ] ) && is_string( $stored[ $field ] ) ? $stored[ $field ] : '';
		}

		return $value;
	}

	/**
	 * Reads the settings option as stored, bypassing apply_overrides().
	 *
	 * The 'old value' WordPress passes to pre_update_option filters has already
	 * been through the option filter, so it holds the constant, not the stored
	 * key; this is the only way to recover what is actually in the database.
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
	 * Returns the overrides that are actually configured, as field => key.
	 *
	 * @return array<string, string>
	 */
	private function get_configured_overrides(): array {
		$overrides = [];

		foreach ( static::FIELD_CONSTANTS as $field => $constant ) {
			$value = $this->get_constant_value( $constant );

			// A blank or non-string constant is ignored rather than applied:
			// injecting an empty key would make the store report itself as
			// disconnected with no error pointing at the constant.
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$overrides[ $field ] = trim( $value );
			}
		}

		return $overrides;
	}

	/**
	 * Reads a constant's value.
	 *
	 * Seam for tests: constants cannot be undefined once set, so tests override
	 * this method instead of defining real constants that would leak into every
	 * other test in the process.
	 *
	 * @param string $constant The constant name.
	 * @return mixed|null Null when the constant is not defined.
	 */
	protected function get_constant_value( string $constant ) {
		return defined( $constant ) ? constant( $constant ) : null;
	}
}
