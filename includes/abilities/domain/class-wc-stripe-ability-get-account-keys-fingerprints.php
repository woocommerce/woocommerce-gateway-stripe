<?php
/**
 * Get Stripe Account Keys Fingerprints ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9.

/**
 * Registers the woocommerce-gateway-stripe/get-account-keys-fingerprints ability.
 *
 * Returns masked fingerprints (first 10 chars + 50 asterisks + last 2
 * chars) of the stored publishable / secret / webhook secrets for both
 * test and live modes. The full secret is never returned. Empty slots
 * return an empty string so the agent can tell which keys are configured.
 *
 * The masked prefix still reveals the Stripe key type (pk_live_, sk_test_,
 * whsec_, etc.). Agents should not echo these into low-trust contexts.
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Account_Keys_Fingerprints extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-account-keys-fingerprints';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe account key fingerprints', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Returns masked fingerprints of the stored Stripe API keys (publishable, secret, webhook secret) for both test and live modes. Each value is the first 10 characters + 50 asterisks + last 2 characters; the full secret is never returned. Empty slots return an empty string.',
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
		unset( $input );

		return self::delegate_to_rest_controller(
			'WC_REST_Stripe_Account_Keys_Controller',
			'GET',
			'/wc/v3/wc_stripe/account_keys'
		);
	}
}
