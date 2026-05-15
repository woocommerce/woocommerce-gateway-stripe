<?php
/**
 * Class WC_Stripe_Abilities_Registrar
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

// @phan-file-suppress PhanUndeclaredFunction, PhanUndeclaredClassMethod @phan-suppress-current-line UnusedSuppression -- Abilities API added in WP 6.9 (shipped via WC 10.9); suppression covers older-WC compat runs.

/**
 * Registers Stripe abilities with the WordPress Abilities API.
 *
 * Thin coordinator: holds the ABILITY_CLASSES list and the
 * can_manage_woocommerce() capability helper that mirrors the load-bearing
 * read gate resolved by WC_Stripe_REST_Base_Controller::check_permission().
 *
 * Gated by the `wc_stripe_abilities_enabled` filter (default false).
 *
 * Registration pattern: abilities are registered exclusively via Woo Core's
 * `woocommerce_ability_definition_classes` loader filter (introduced in
 * WC 10.9). On stores running WC < 10.9 the feature silently no-ops — see
 * `woo_abilities_loader_available()`.
 *
 * @since 10.8.0
 *
 * @internal Subject to change without notice between releases.
 */
class WC_Stripe_Abilities_Registrar {

	/**
	 * Ability definition classes registered through the WC 10.9 loader.
	 *
	 * Every Stripe ability is listed here. The `::class` constants are
	 * compile-time strings — referencing them does NOT autoload the
	 * classes. They resolve only when Woo Core's loader iterates the
	 * filter return value on WC 10.9+.
	 *
	 * @var array<int, class-string>
	 */
	private const ABILITY_CLASSES = [
		WC_Stripe_Ability_Get_Account_Summary::class,
		WC_Stripe_Ability_Get_Webhook_Status::class,
		WC_Stripe_Ability_Get_Settings::class,
		WC_Stripe_Ability_Get_Account_Keys_Fingerprints::class,
		WC_Stripe_Ability_Get_Terminal_Locations::class,
		WC_Stripe_Ability_Get_Charges::class,
		WC_Stripe_Ability_Get_Charge::class,
		WC_Stripe_Ability_Get_Payment_Intent::class,
		WC_Stripe_Ability_Get_Disputes::class,
		WC_Stripe_Ability_Get_Dispute::class,
		WC_Stripe_Ability_Get_Payouts::class,
		WC_Stripe_Ability_Get_Balance::class,
		WC_Stripe_Ability_Get_Balance_Transactions::class,
	];

	/**
	 * Ability classes registered only when the Agentic Commerce feature
	 * flag is enabled.
	 *
	 * The backing controller's register_routes() returns early when
	 * WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() is false, so
	 * the underlying REST routes do not exist on those sites. We mirror
	 * that guard at the registrar so we never register an ability whose
	 * backing route is absent.
	 *
	 * @var array<int, class-string>
	 */
	private const AGENTIC_COMMERCE_ABILITY_CLASSES = [
		WC_Stripe_Ability_Get_Agentic_Commerce_Sync_Status::class,
		WC_Stripe_Ability_Get_Agentic_Commerce_Settings::class,
	];

	/**
	 * Whether init() has already wired its filter callbacks.
	 *
	 * Without this guard, repeated calls to init() while the feature filter
	 * is true would each append a fresh add_filter() for the registrar
	 * callback, and Woo Core's loader would emit `_doing_it_wrong` notices
	 * for every already-registered slug when the loader fires.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize the abilities registration.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		/**
		 * Filter whether Stripe's Abilities API registrations are active.
		 *
		 * Disabled by default during rollout. Flip this filter to true to
		 * expose Stripe abilities through the shared MCP adapter and the
		 * WordPress Abilities API REST bridge.
		 *
		 * @since 10.8.0
		 *
		 * @param bool $enabled Whether to register Stripe abilities. Default false.
		 */
		if ( ! apply_filters( 'wc_stripe_abilities_enabled', false ) ) {
			return;
		}

		if ( ! self::woo_abilities_loader_available() ) {
			// Abilities feature requires WC 10.9. Silently no-op on older
			// versions; the feature flag is the rollout safety net, the
			// loader gate is the structural gate.
			return;
		}

		self::$initialized = true;

		add_filter( 'woocommerce_ability_definition_classes', [ __CLASS__, 'append_classes' ] );
	}

	/**
	 * Reset the idempotency guard set by init().
	 *
	 * @internal Test-isolation helper. Not part of the public API.
	 *
	 * @return void
	 */
	public static function reset_initialized_for_testing(): void {
		self::$initialized = false;
	}

	/**
	 * Whether WooCommerce 10.9's AbilitiesLoader is available.
	 *
	 * Used as a hard gate: on WC < 10.9 the abilities feature silently
	 * no-ops. WC 10.9 transitively requires WP 6.9, so
	 * `wp_register_ability()` is implicitly available wherever the loader
	 * exists.
	 *
	 * @return bool
	 */
	private static function woo_abilities_loader_available(): bool {
		return class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' );
	}

	/**
	 * Append Stripe ability classes to Woo Core's loader.
	 *
	 * Filter callback for `woocommerce_ability_definition_classes`. The
	 * Agentic Commerce abilities are only appended when the matching
	 * feature flag is enabled — they back onto routes that don't exist
	 * otherwise.
	 *
	 * Domain classes are resolved via the Composer classmap autoloader; a
	 * stale autoloader (contributor checkout without `composer dump-autoload`)
	 * could otherwise hand Woo Core unresolvable class strings. The
	 * `class_exists()` filter contains that to a quiet drop instead of a
	 * fatal in the loader.
	 *
	 * @param array $classes Class names accumulated by the loader.
	 * @return array
	 */
	public static function append_classes( array $classes ): array {
		$abilities = self::ABILITY_CLASSES;

		if ( class_exists( 'WC_Stripe_Feature_Flags' )
			&& method_exists( 'WC_Stripe_Feature_Flags', 'is_agentic_commerce_enabled' )
			&& WC_Stripe_Feature_Flags::is_agentic_commerce_enabled()
		) {
			$abilities = array_merge( $abilities, self::AGENTIC_COMMERCE_ABILITY_CLASSES );
		}

		$resolved = array_values( array_filter( $abilities, 'class_exists' ) );

		// If the safety net actually dropped anything, log it so a stale
		// Composer autoloader is detectable rather than silently shipping a
		// reduced ability surface.
		if ( count( $resolved ) !== count( $abilities ) && class_exists( 'WC_Stripe_Logger' ) ) {
			$missing = array_values( array_diff( $abilities, $resolved ) );
			WC_Stripe_Logger::error(
				'Abilities Registrar dropped unresolvable Domain classes from the loader filter; run `composer dump-autoload`.',
				[ 'missing' => $missing ]
			);
		}

		return array_merge( $classes, $resolved );
	}

	/**
	 * Permission callback for Stripe read abilities.
	 *
	 * Mirrors WC_Stripe_REST_Base_Controller::check_permission(), which
	 * gates every Stripe REST controller in this plugin behind
	 * `current_user_can( 'manage_woocommerce' )`.
	 *
	 * @return bool
	 */
	public static function can_manage_woocommerce(): bool {
		return current_user_can( 'manage_woocommerce' );
	}
}
