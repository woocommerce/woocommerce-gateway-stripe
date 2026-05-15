<?php
/**
 * Get Stripe Settings ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9.

/**
 * Registers the woocommerce-gateway-stripe/get-settings ability.
 *
 * Returns the merchant's Stripe gateway settings snapshot — which payment
 * methods are enabled, test/live mode, manual capture, saved cards,
 * Optimized Checkout, Express Checkout config, and debug logging. The
 * highest-information local read in the plugin.
 *
 * Important caveat: the backing controller calls
 * `$this->gateway->get_upe_enabled_payment_method_ids( true )` which forces
 * a refresh against Stripe's Payment Method Configurations API on every
 * invocation. This is a read but it incurs an outbound HTTP call and Stripe
 * rate-limit consumption.
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Settings extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-settings';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe gateway settings', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Returns the current Stripe gateway settings snapshot — enabled payment method IDs, test/live mode, manual capture, saved cards, Optimized Checkout, Express Checkout config, Adaptive Pricing, Amazon Pay, and debug logging. Note: each call may trigger a refresh against the Stripe Payment Method Configurations API.',
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [],
				'additionalProperties' => false,
			],
			'execute_callback'    => [ self::class, 'execute' ],
			'permission_callback' => [ WC_Stripe_Abilities_Registrar::class, 'can_manage_woocommerce' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => false,
				],
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
				],
			],
		];
	}

	public static function execute( $input = null ) {
		unset( $input );

		return self::delegate_to_rest_controller(
			'WC_REST_Stripe_Settings_Controller',
			'GET',
			'/wc/v3/wc_stripe/settings'
		);
	}
}
