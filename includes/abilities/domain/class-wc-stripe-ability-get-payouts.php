<?php
/**
 * Get Stripe Payouts ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-payouts ability.
 *
 * Lists Stripe payouts.
 * Calls the Stripe `payouts` API.
 *
 * @see https://docs.stripe.com/api/payouts/list
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Payouts extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-payouts';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe payouts', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Lists Stripe payouts. Filters: status, arrival_date range, created date range, and Stripe cursor pagination.',
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
					'limit'            => [
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'default'     => 10,
						'description' => __( 'Maximum number of payouts to return. Defaults to 10; Stripe caps at 100.', 'woocommerce-gateway-stripe' ),
					],
					'starting_after'   => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return payouts after this object ID.', 'woocommerce-gateway-stripe' ),
					],
					'ending_before'    => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return payouts before this object ID.', 'woocommerce-gateway-stripe' ),
					],
					'status'           => [
						'type'        => 'string',
						'enum'        => [ 'pending', 'paid', 'in_transit', 'canceled', 'failed' ],
						'description' => __( 'Filter to payouts in a given status.', 'woocommerce-gateway-stripe' ),
					],
					'arrival_date_gte' => [
						'type'        => 'integer',
						'description' => __( 'Filter to payouts arriving at or after this Unix timestamp.', 'woocommerce-gateway-stripe' ),
					],
					'arrival_date_lte' => [
						'type'        => 'integer',
						'description' => __( 'Filter to payouts arriving at or before this Unix timestamp.', 'woocommerce-gateway-stripe' ),
					],
					'created_gte'      => [
						'type'        => 'integer',
						'description' => __( 'Filter to payouts created at or after this Unix timestamp.', 'woocommerce-gateway-stripe' ),
					],
					'created_lte'      => [
						'type'        => 'integer',
						'description' => __( 'Filter to payouts created at or before this Unix timestamp.', 'woocommerce-gateway-stripe' ),
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

		$limit = isset( $input['limit'] ) ? (int) $input['limit'] : 10;
		$limit = max( 1, min( 100, $limit ) );

		$params = [
			'limit'          => $limit,
			'starting_after' => $input['starting_after'] ?? null,
			'ending_before'  => $input['ending_before'] ?? null,
			'status'         => $input['status'] ?? null,
		];

		if ( isset( $input['arrival_date_gte'] ) ) {
			$params['arrival_date[gte]'] = (int) $input['arrival_date_gte'];
		}
		if ( isset( $input['arrival_date_lte'] ) ) {
			$params['arrival_date[lte]'] = (int) $input['arrival_date_lte'];
		}
		if ( isset( $input['created_gte'] ) ) {
			$params['created[gte]'] = (int) $input['created_gte'];
		}
		if ( isset( $input['created_lte'] ) ) {
			$params['created[lte]'] = (int) $input['created_lte'];
		}

		return self::retrieve_from_stripe( 'payouts' . self::build_stripe_query_string( $params ) );
	}
}
