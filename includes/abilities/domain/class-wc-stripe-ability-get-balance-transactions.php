<?php
/**
 * Get Stripe Balance Transactions ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9.

/**
 * Registers the woocommerce-gateway-stripe/get-balance-transactions ability.
 *
 * Lists Stripe balance transactions (charges, refunds, payouts,
 * adjustments) — answers "what cleared into or out of my Stripe balance
 * recently?". Complements get-payouts when reconciling specific payout
 * amounts. Backs onto WC_Stripe_API::retrieve("balance_transactions?...").
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
						'enum'        => [
							'adjustment',
							'advance',
							'advance_funding',
							'anticipation_repayment',
							'application_fee',
							'application_fee_refund',
							'charge',
							'connect_collection_transfer',
							'contribution',
							'issuing_authorization_hold',
							'issuing_authorization_release',
							'issuing_dispute',
							'issuing_transaction',
							'obligation_inbound',
							'obligation_outbound',
							'obligation_reversal_inbound',
							'obligation_reversal_outbound',
							'obligation_payout',
							'obligation_payout_failure',
							'payment',
							'payment_failure_refund',
							'payment_refund',
							'payment_reversal',
							'payout',
							'payout_cancel',
							'payout_failure',
							'refund',
							'refund_failure',
							'reserve_transaction',
							'reserved_funds',
							'stripe_fee',
							'stripe_fx_fee',
							'tax_fee',
							'topup',
							'topup_reversal',
							'transfer',
							'transfer_cancel',
							'transfer_failure',
							'transfer_refund',
						],
						'description' => __( 'Filter to balance transactions of a specific type. See Stripe BalanceTransaction.type for the canonical enum.', 'woocommerce-gateway-stripe' ),
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
