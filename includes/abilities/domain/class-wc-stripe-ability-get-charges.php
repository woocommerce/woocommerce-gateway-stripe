<?php
/**
 * Get Stripe Charges ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-charges ability.
 *
 * Lists recent Stripe charges with filters (date range, customer,
 * payment_intent, limit).
 * Calls the Stripe `charges` API and returns raw Stripe charge objects
 * (which include customer-shaped data like billing_details and
 * receipt_email).
 *
 * @see https://docs.stripe.com/api/charges/list
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Charges extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-charges';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe charges', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Lists recent Stripe charges with optional filters: customer, payment_intent, created date range, and Stripe cursor pagination (starting_after / ending_before). Returns raw Stripe charge objects which include customer billing details and receipt email — PII-shaped per the Stripe API.',
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
						'description' => __( 'Maximum number of charges to return. Defaults to 10; Stripe caps at 100.', 'woocommerce-gateway-stripe' ),
					],
					'starting_after' => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return charges after this object ID.', 'woocommerce-gateway-stripe' ),
					],
					'ending_before'  => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return charges before this object ID.', 'woocommerce-gateway-stripe' ),
					],
					'customer'       => [
						'type'        => 'string',
						'pattern'     => '^cus_[A-Za-z0-9_]+$',
						'description' => __( 'Filter to charges belonging to a specific Stripe customer (cus_xxx).', 'woocommerce-gateway-stripe' ),
					],
					'payment_intent' => [
						'type'        => 'string',
						'pattern'     => '^pi_[A-Za-z0-9_]+$',
						'description' => __( 'Filter to charges attached to a specific payment intent (pi_xxx).', 'woocommerce-gateway-stripe' ),
					],
					'created_gte'    => [
						'type'        => 'integer',
						'description' => __( 'Filter to charges created at or after this Unix timestamp.', 'woocommerce-gateway-stripe' ),
					],
					'created_lte'    => [
						'type'        => 'integer',
						'description' => __( 'Filter to charges created at or before this Unix timestamp.', 'woocommerce-gateway-stripe' ),
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
			'customer'       => $input['customer'] ?? null,
			'payment_intent' => $input['payment_intent'] ?? null,
		];

		if ( isset( $input['created_gte'] ) ) {
			$params['created[gte]'] = (int) $input['created_gte'];
		}
		if ( isset( $input['created_lte'] ) ) {
			$params['created[lte]'] = (int) $input['created_lte'];
		}

		return self::retrieve_from_stripe( 'charges' . self::build_stripe_query_string( $params ) );
	}
}
