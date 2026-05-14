<?php
/**
 * Get Agentic Commerce Sync Status ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9.

/**
 * Registers the woocommerce-gateway-stripe/get-agentic-commerce-sync-status ability.
 *
 * Answers "how is the Agentic Commerce product feed doing?" — last sync
 * status, recent history (up to 20 entries), and the next scheduled sync
 * timestamp. Conditionally registered: only added to ABILITY_CLASSES when
 * WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() is true.
 *
 * The backing controller performs an outbound Stripe call to refresh
 * non-terminal ImportSet statuses on each invocation.
 *
 * @internal
 */
class WC_Stripe_Ability_Get_Agentic_Commerce_Sync_Status extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-agentic-commerce-sync-status';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Agentic Commerce sync status', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Returns the current Agentic Commerce product feed sync status: last sync result, up to 20 recent history entries, and the next scheduled sync timestamp. Each invocation may trigger a Stripe API call to refresh any non-terminal ImportSet statuses.',
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
			'/wc/v3/wc_stripe/agentic-commerce/status'
		);
	}
}
