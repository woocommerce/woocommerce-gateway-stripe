<?php
/**
 * Get Stripe Payout (by ID) ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-payout ability.
 *
 * Single payout lookup by Stripe payout ID.
 * Calls the Stripe `payouts` API.
 *
 * @see https://docs.stripe.com/api/payouts/retrieve
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Payout extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-payout';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe payout by ID', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Returns a single Stripe payout by ID. Response is the raw Stripe payout object including status, arrival_date, amount, currency, and destination summary.',
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'required'             => [ 'payout_id' ],
				'properties'           => [
					'payout_id' => [
						'type'        => 'string',
						'pattern'     => '^po_[A-Za-z0-9_]+$',
						'description' => __( 'Stripe payout ID (po_xxx).', 'woocommerce-gateway-stripe' ),
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

		if ( ! isset( $input['payout_id'] )
			|| ! is_string( $input['payout_id'] )
			|| '' === $input['payout_id']
		) {
			return new WP_Error(
				'wc_stripe_missing_payout_id',
				__( 'A payout_id is required to fetch a Stripe payout.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 400 ]
			);
		}

		return self::retrieve_from_stripe( 'payouts/' . rawurlencode( $input['payout_id'] ) );
	}
}
