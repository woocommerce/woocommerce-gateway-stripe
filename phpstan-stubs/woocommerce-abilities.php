<?php
/**
 * Stub for WooCommerce 10.9's Abilities API surface used by Stripe Domain
 * abilities under includes/abilities/. The plugin still supports WC L-2, so
 * we cannot rely on the real interface being loadable during static analysis;
 * this stub keeps PHPStan resolving the symbol without coupling runtime.
 *
 * @package WooCommerce_Stripe
 */

namespace Automattic\WooCommerce\Abilities;

interface AbilityDefinition {

	/**
	 * The stable, public ability ID.
	 */
	public static function get_name(): string;

	/**
	 * Arguments passed to wp_register_ability().
	 *
	 * @return array<string, mixed>
	 */
	public static function get_registration_args(): array;
}
