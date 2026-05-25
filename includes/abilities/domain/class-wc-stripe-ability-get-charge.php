<?php
/**
 * Get Stripe Charge (by ID) ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-charge ability.
 *
 * Single-charge lookup by Stripe charge ID.
 * Calls Stripe `charges` API.
 *
 * @see https://docs.stripe.com/api/charges/retrieve
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Charge extends WC_Stripe_Ability_Base implements AbilityDefinition {

	/**
	 * Fields the Stripe `GET /v1/charges/{id}` endpoint supports expanding.
	 *
	 * @see https://docs.stripe.com/api/charges/retrieve
	 *
	 * @var array<int, string>
	 */
	public const EXPANDABLE_FIELDS = [
		'balance_transaction',
		'customer',
		'payment_intent',
		'refunds',
		'review',
	];

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-charge';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe charge by ID', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Returns a single Stripe charge by ID. Response is the raw Stripe charge object including payment_method_details, billing_details, and receipt_email. Optionally inflate related objects via `expand`.',
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
					'expand'    => [
						'type'        => 'array',
						'items'       => [
							'type' => 'string',
							'enum' => self::EXPANDABLE_FIELDS,
						],
						'uniqueItems' => true,
						'maxItems'    => 5,
						'description' => __( 'Related objects to inflate inline. Allowed values: balance_transaction, customer, payment_intent, refunds, review.', 'woocommerce-gateway-stripe' ),
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

		$expand = isset( $input['expand'] ) && is_array( $input['expand'] ) ? $input['expand'] : [];
		$path   = self::append_expand_to_path(
			'charges/' . rawurlencode( $input['charge_id'] ),
			$expand
		);

		return self::retrieve_from_stripe( $path );
	}
}
