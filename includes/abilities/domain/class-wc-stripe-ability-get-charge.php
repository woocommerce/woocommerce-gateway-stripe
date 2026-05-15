<?php
/**
 * Get Stripe Charge (by ID) ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9.

/**
 * Registers the woocommerce-gateway-stripe/get-charge ability.
 *
 * Single-charge lookup by Stripe charge ID. Parity with WooPayments'
 * get-charge ability. Backs onto WC_Stripe_API::retrieve("charges/{$id}").
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Charge extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-charge';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe charge by ID', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Returns a single Stripe charge by ID. Response is the raw Stripe charge object including payment_method_details, billing_details, and receipt_email.',
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'required'             => [ 'charge_id' ],
				'properties'           => [
					'charge_id' => [
						'type'        => 'string',
						'pattern'     => '^ch_[A-Za-z0-9_]+$',
						'description' => __( 'Stripe charge ID (ch_xxx).', 'woocommerce-gateway-stripe' ),
					],
				],
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
		$input = is_array( $input ) ? $input : [];

		if ( ! isset( $input['charge_id'] )
			|| ! is_string( $input['charge_id'] )
			|| '' === $input['charge_id']
		) {
			return new WP_Error(
				'wc_stripe_missing_charge_id',
				__( 'A charge_id is required to fetch a Stripe charge.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 400 ]
			);
		}

		return self::retrieve_from_stripe( 'charges/' . rawurlencode( $input['charge_id'] ) );
	}
}
