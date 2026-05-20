<?php
/**
 * Get Stripe Dispute (by ID) ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-dispute ability.
 *
 * Single dispute lookup by Stripe dispute ID. Parity with WooPayments'
 * get-dispute ability. Backs onto WC_Stripe_API::retrieve("disputes/{$id}").
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Dispute extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-dispute';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe dispute by ID', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Returns a single Stripe dispute by ID. Response is the raw Stripe dispute object including evidence_details and the associated charge reference.',
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'required'             => [ 'dispute_id' ],
				'properties'           => [
					'dispute_id' => [
						'type'        => 'string',
						'pattern'     => '^(dp|du)_[A-Za-z0-9_]+$',
						'description' => __( 'Stripe dispute ID (dp_xxx or legacy du_xxx).', 'woocommerce-gateway-stripe' ),
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

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Optional ability input.
	 * @return array|WP_Error
	 */
	public static function execute( $input = null ) {
		$input = is_array( $input ) ? $input : [];

		if ( ! isset( $input['dispute_id'] )
			|| ! is_string( $input['dispute_id'] )
			|| '' === $input['dispute_id']
		) {
			return new WP_Error(
				'wc_stripe_missing_dispute_id',
				__( 'A dispute_id is required to fetch a Stripe dispute.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 400 ]
			);
		}

		return self::retrieve_from_stripe( 'disputes/' . rawurlencode( $input['dispute_id'] ) );
	}
}
