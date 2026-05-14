<?php
/**
 * Get Agentic Commerce Settings ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9.

/**
 * Registers the woocommerce-gateway-stripe/get-agentic-commerce-settings ability.
 *
 * Answers "is Agentic Commerce enabled and is the webhook secret
 * configured?" — returns the merchant toggle and a Stripe-style masked
 * webhook secret indicator. The real secret is never returned: stored
 * values are replaced with the placeholder
 * `whsec_********************************`, empty slots return ''.
 *
 * Conditionally registered: only added to ABILITY_CLASSES when
 * WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() is true.
 *
 * @internal
 */
class WC_Stripe_Ability_Get_Agentic_Commerce_Settings extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-agentic-commerce-settings';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Agentic Commerce settings', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Returns the merchant Agentic Commerce toggle and a masked webhook secret indicator. The webhook secret value is replaced with `whsec_********************************` when a secret is stored, or an empty string when not configured. The real secret is never returned.',
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [],
				'additionalProperties' => false,
			],
			'execute_callback'    => [ self::class, 'execute' ],
			'permission_callback' => [ WC_Stripe_Abilities_Registrar::class, 'can_manage_woocommerce' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
				],
			],
		];
	}

	public static function execute( $input = null ) {
		unset( $input );

		return self::delegate_to_rest_controller(
			'WC_REST_Stripe_Agentic_Commerce_Controller',
			'GET',
			'/wc/v3/wc_stripe/agentic-commerce/settings'
		);
	}
}
