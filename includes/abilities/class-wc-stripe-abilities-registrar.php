<?php
/**
 * Class WC_Stripe_Abilities_Registrar
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

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
		WC_Stripe_Ability_Get_Charges::class,
		WC_Stripe_Ability_Get_Charge::class,
		WC_Stripe_Ability_Get_Payment_Intent::class,
		WC_Stripe_Ability_Get_Disputes::class,
		WC_Stripe_Ability_Get_Dispute::class,
		WC_Stripe_Ability_Get_Payouts::class,
		WC_Stripe_Ability_Get_Payout::class,
		WC_Stripe_Ability_Get_Balance::class,
		WC_Stripe_Ability_Get_Balance_Transactions::class,
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

		if ( ! self::woo_abilities_loader_available() ) {
			// Abilities feature requires WC 10.9. Silently no-op on older
			// versions; the feature flag is the rollout safety net, the
			// loader gate is the structural gate.
			return;
		}

		if ( ! WC_Stripe_Feature_Flags::is_abilities_enabled() ) {
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
	 * Filter callback for `woocommerce_ability_definition_classes`.
	 *
	 * @param array $classes Class names accumulated by the loader.
	 * @return array
	 */
	public static function append_classes( array $classes ): array {
		return array_merge( $classes, self::ABILITY_CLASSES );
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
