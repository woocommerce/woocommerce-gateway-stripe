<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema for flags the remote-config channel may influence.
 *
 * Adding a new remotely-controllable flag = add a row to FLAGS and route the
 * call site through WC_Stripe_Remote_Config::resolve().
 *
 * The `reader` field is informational only (greppable pointer to which code
 * reads the flag); the resolver does not invoke it.
 */
class WC_Stripe_Remote_Config_Flags {

	/**
	 * Maximum decoded payload size accepted from the server, in bytes.
	 */
	const MAX_PAYLOAD_BYTES = 65536;

	/**
	 * Schema of remotely-controllable flags.
	 *
	 * @var array<string, array{reader: string, type: string}>
	 */
	const FLAGS = [
		'optimized_checkout' => [
			'reader' => 'WC_Stripe_Feature_Flags::is_oc_available',
			'type'   => 'bool',
		],
	];

	/**
	 * Whether the given flag name is declared in FLAGS.
	 */
	public static function is_known_flag( string $name ): bool {
		return isset( self::FLAGS[ $name ] );
	}

	/**
	 * Whether the remote-config feature is enabled on this site.
	 *
	 * The `WC_STRIPE_DISABLE_REMOTE_CONFIG` constant takes precedence; if not
	 * defined, the `wc_stripe_remote_config_enabled` filter is consulted with a
	 * default of true.
	 */
	public static function is_remote_config_enabled(): bool {
		if ( defined( 'WC_STRIPE_DISABLE_REMOTE_CONFIG' ) && WC_STRIPE_DISABLE_REMOTE_CONFIG ) {
			return false;
		}

		/**
		 * Filters whether the Stripe remote-config channel is enabled.
		 *
		 * @since 10.8.0
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'wc_stripe_remote_config_enabled', true );
	}

	/**
	 * Whether the given value matches the declared type for the named flag.
	 *
	 * Returns false for unknown flags.
	 *
	 * @param mixed $value
	 */
	public static function validate_value( string $name, $value ): bool {
		if ( ! self::is_known_flag( $name ) ) {
			return false;
		}

		$type = self::FLAGS[ $name ]['type'];
		switch ( $type ) {
			case 'bool':
				return is_bool( $value );
			default:
				return false;
		}
	}
}
