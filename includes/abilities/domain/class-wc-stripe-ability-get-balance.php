<?php
/**
 * Get Stripe Balance ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-balance ability.
 *
 * Get the current balance overview.
 * Calls the Stripe `balance` API.
 *
 * @see https://docs.stripe.com/api/balance/balance_retrieve
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Balance extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-balance';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe balance', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				"Returns the merchant's current Stripe balance — available, pending, instant_available, and connect_reserved amounts per currency. Zero-argument read.",
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

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Optional ability input.
	 * @return array|WP_Error
	 */
	public static function execute( $input = null ) {
		unset( $input );

		return self::retrieve_from_stripe( 'balance' );
	}
}
