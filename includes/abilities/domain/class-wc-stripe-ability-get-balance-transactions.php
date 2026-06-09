<?php
/**
 * Get Stripe Balance Transactions ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-balance-transactions ability.
 *
 * Lists Stripe balance transactions (charges, refunds, payouts,
 * adjustments) — answers "what cleared into or out of my Stripe balance
 * recently?". Complements get-payouts when reconciling specific payout
 * amounts. Calls the Stripe `balance_transactions` API.
 *
 * @see https://docs.stripe.com/api/balance_transactions/retrieve
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Balance_Transactions extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-balance-transactions';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe balance transactions', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Lists Stripe balance transactions (charges, refunds, payouts, adjustments). Filters: type, source object id, payout id, created date range, and Stripe cursor pagination.',
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
						'description' => __( 'Maximum number of balance transactions to return. Defaults to 10; Stripe caps at 100.', 'woocommerce-gateway-stripe' ),
					],
					'starting_after' => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return balance transactions after this object ID.', 'woocommerce-gateway-stripe' ),
					],
					'ending_before'  => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return balance transactions before this object ID.', 'woocommerce-gateway-stripe' ),
					],
					'type'           => [
						'type'        => 'string',
						// BalanceTransaction.type grows quarterly; we validate the shape
						// (lowercase + underscores) and let Stripe authoritatively reject
						// unknown values rather than committing to a frozen enum that drifts.
						'pattern'     => '^[a-z][a-z0-9_]*$',
						'description' => __( 'Filter to balance transactions of a specific type (e.g. charge, refund, payout, adjustment, application_fee, stripe_fee). See Stripe BalanceTransaction.type for the canonical list.', 'woocommerce-gateway-stripe' ),
					],
					'payout'         => [
						'type'        => 'string',
						'pattern'     => '^po_[A-Za-z0-9_]+$',
						'description' => __( 'Filter to balance transactions included in a specific payout (po_xxx).', 'woocommerce-gateway-stripe' ),
					],
					'source'         => [
						'type'        => 'string',
						'pattern'     => '^[a-z]{2,}_[A-Za-z0-9_]+$',
						'description' => __( 'Filter to balance transactions for a specific source object ID (e.g. ch_xxx, re_xxx, txn_xxx).', 'woocommerce-gateway-stripe' ),
					],
					'created_gte'    => [
						'type'        => 'integer',
						'description' => __( 'Filter to balance transactions created at or after this Unix timestamp.', 'woocommerce-gateway-stripe' ),
					],
					'created_lte'    => [
						'type'        => 'integer',
						'description' => __( 'Filter to balance transactions created at or before this Unix timestamp.', 'woocommerce-gateway-stripe' ),
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
			'type'           => $input['type'] ?? null,
			'payout'         => $input['payout'] ?? null,
			'source'         => $input['source'] ?? null,
		];

		if ( isset( $input['created_gte'] ) ) {
			$params['created[gte]'] = (int) $input['created_gte'];
		}
		if ( isset( $input['created_lte'] ) ) {
			$params['created[lte]'] = (int) $input['created_lte'];
		}

		return self::retrieve_from_stripe( 'balance_transactions' . self::build_stripe_query_string( $params ) );
	}
}
