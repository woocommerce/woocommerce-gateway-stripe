<?php
/**
 * Get Stripe Disputes ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-disputes ability.
 *
 * Lists Stripe disputes with filters.
 * Calls the Stripe `disputes` API. This API does not support a status filter,
 * so filtering must be done after fetching data from the API.
 *
 * @see https://docs.stripe.com/api/disputes/list
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Disputes extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-disputes';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe disputes', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Lists Stripe disputes with optional filters: charge, payment_intent, created date range, and Stripe cursor pagination. Stripe does not support a status filter server-side; filter the returned list client-side if needed.',
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
					'limit'          => [
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'default'     => 10,
						'description' => __( 'Maximum number of disputes to return. Defaults to 10; Stripe caps at 100.', 'woocommerce-gateway-stripe' ),
					],
					'starting_after' => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return disputes after this object ID.', 'woocommerce-gateway-stripe' ),
					],
					'ending_before'  => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return disputes before this object ID.', 'woocommerce-gateway-stripe' ),
					],
					'charge'         => [
						'type'        => 'string',
						'pattern'     => '^ch_[A-Za-z0-9_]+$',
						'description' => __( 'Filter to disputes associated with a specific charge (ch_xxx).', 'woocommerce-gateway-stripe' ),
					],
					'payment_intent' => [
						'type'        => 'string',
						'pattern'     => '^pi_[A-Za-z0-9_]+$',
						'description' => __( 'Filter to disputes associated with a specific payment intent (pi_xxx).', 'woocommerce-gateway-stripe' ),
					],
					'created_gte'    => [
						'type'        => 'integer',
						'description' => __( 'Filter to disputes created at or after this Unix timestamp.', 'woocommerce-gateway-stripe' ),
					],
					'created_lte'    => [
						'type'        => 'integer',
						'description' => __( 'Filter to disputes created at or before this Unix timestamp.', 'woocommerce-gateway-stripe' ),
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
			'charge'         => $input['charge'] ?? null,
			'payment_intent' => $input['payment_intent'] ?? null,
		];

		if ( isset( $input['created_gte'] ) ) {
			$params['created[gte]'] = (int) $input['created_gte'];
		}
		if ( isset( $input['created_lte'] ) ) {
			$params['created[lte]'] = (int) $input['created_lte'];
		}

		return self::retrieve_from_stripe( 'disputes' . self::build_stripe_query_string( $params ) );
	}
}
