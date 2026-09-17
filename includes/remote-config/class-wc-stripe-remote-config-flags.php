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
	 * Option that force-enables ('yes') or force-disables ('no') the
	 * remote-config channel on this site; any other value falls through to
	 * the environment default. Internal tooling seam (wp-cli, phased rollout,
	 * support) — deliberately not a merchant-facing opt-out: a public escape
	 * hatch would fragment incident coverage and force a patch release for
	 * exactly the sites a remote disable needs to reach.
	 */
	public const ENABLED_OVERRIDE_OPTION = '_wcstripe_remote_config_enabled';

	/**
	 * Whether the remote-config feature is enabled on this site.
	 *
	 * Disabled by default while the rollout is in phase 1; the
	 * ENABLED_OVERRIDE_OPTION option force-enables ('yes') or force-disables
	 * ('no') an individual site. There is intentionally no public filter or
	 * constant.
	 */
	public static function is_remote_config_enabled(): bool {
		$override = get_option( self::ENABLED_OVERRIDE_OPTION, '' );
		if ( 'yes' === $override ) {
			return true;
		}
		if ( 'no' === $override ) {
			return false;
		}

		// Phase 1 of the phased rollout: the code ships with the channel
		// globally disabled and our test sites are enabled by hand via the
		// override option. Later phases flip this default via patch releases —
		// test-mode sites first, then a progressive live ramp — and must
		// re-exclude development environments when they do.
		return false;
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
