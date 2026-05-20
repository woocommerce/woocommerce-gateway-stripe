<?php
/**
 * Get Stripe Payment Intent (by ID) ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-payment-intent ability.
 *
 * Single payment-intent lookup by Stripe ID. Parity with WooPayments'
 * get-payment-intent ability. Backs onto
 * WC_Stripe_API::retrieve("payment_intents/{$id}").
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Payment_Intent extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-payment-intent';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe payment intent by ID', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				"Returns a single Stripe payment intent by ID. Response is the raw Stripe payment_intent object including status, charges, and the attached payment method's billing details.",
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'required'             => [ 'payment_intent_id' ],
				'properties'           => [
					'payment_intent_id' => [
						'type'        => 'string',
						'pattern'     => '^pi_[A-Za-z0-9_]+$',
						'description' => __( 'Stripe payment intent ID (pi_xxx).', 'woocommerce-gateway-stripe' ),
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

		if ( ! isset( $input['payment_intent_id'] )
			|| ! is_string( $input['payment_intent_id'] )
			|| '' === $input['payment_intent_id']
		) {
			return new WP_Error(
				'wc_stripe_missing_payment_intent_id',
				__( 'A payment_intent_id is required to fetch a Stripe payment intent.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 400 ]
			);
		}

		return self::retrieve_from_stripe( 'payment_intents/' . rawurlencode( $input['payment_intent_id'] ) );
	}
}
