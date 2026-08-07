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
	 *
	 * Used as the upper bound when validating freshly-fetched payloads in
	 * WC_Stripe_Remote_Config::apply().
	 *
	 * @var int
	 */
	public const MAX_PAYLOAD_BYTES = 65536;

	/**
	 * Schema of remotely-controllable flags.
	 *
	 * Each entry maps a flag name to:
	 *  - `reader`: informational pointer (greppable) to the call site that
	 *    reads this flag; not invoked by the resolver.
	 *  - `type`:   declared PHP type used by validate_value() to reject
	 *    payloads whose value does not match.
	 *
	 * @var array<string, array{reader: string, type: string}>
	 */
	private const FLAGS = [
		'optimized_checkout' => [
			'reader' => 'WC_Stripe_Feature_Flags::is_oc_offered',
			'type'   => 'bool',
		],
	];

	/**
	 * Whether the given flag name is declared in FLAGS.
	 *
	 * @param string $flag_name Flag name as declared in self::FLAGS.
	 */
	public static function is_known_flag( string $flag_name ): bool {
		return isset( self::FLAGS[ $flag_name ] );
	}

	/**
	 * Whether the remote-config feature is enabled on this site.
	 *
	 * The `wc_stripe_remote_config_enabled` filter is the single opt-out: a
	 * constant would be too easy to bake into fleet-wide wp-config templates,
	 * permanently cutting those sites off from incident mitigations. The filter
	 * default is `false` on development environments (WP_DEBUG sites, or sites
	 * whose `wp_get_environment_type()` is local/development/staging) and
	 * `true` everywhere else.
	 */
	public static function is_remote_config_enabled(): bool {
		$is_dev_environment = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			|| in_array( wp_get_environment_type(), [ 'development', 'staging', 'local' ], true );

		/**
		 * Filters whether the Stripe remote-config channel is enabled.
		 *
		 * @since 10.8.0
		 *
		 * @param bool $enabled Default `false` on development environments, `true` elsewhere.
		 */
		return (bool) apply_filters( 'wc_stripe_remote_config_enabled', ! $is_dev_environment );
	}

	/**
	 * Whether the given value matches the declared type for the named flag.
	 *
	 * Returns false for unknown flags.
	 *
	 * @param string $flag_name Flag name as declared in self::FLAGS.
	 * @param mixed  $value     Candidate value to type-check.
	 */
	public static function validate_value( string $flag_name, $value ): bool {
		if ( ! self::is_known_flag( $flag_name ) ) {
			return false;
		}

		$type = self::FLAGS[ $flag_name ]['type'];
		switch ( $type ) {
			case 'bool':
				return is_bool( $value );
			default:
				return false;
		}
	}
}
