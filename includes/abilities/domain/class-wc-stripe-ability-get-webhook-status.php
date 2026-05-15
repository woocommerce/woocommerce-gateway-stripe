<?php
/**
 * Get Stripe Webhook Status ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9.

/**
 * Registers the woocommerce-gateway-stripe/get-webhook-status ability.
 *
 * Answers "is my Stripe webhook healthy?" with a single status code and a
 * human-readable message. Backs onto
 * WC_REST_Stripe_Account_Controller::get_webhook_status_message via the
 * abstract base's delegate_to_rest_controller() helper.
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Webhook_Status extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-webhook-status';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe webhook status', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				'Returns the current Stripe webhook status code and a human-readable message. Zero-argument read; surfaces last-success / last-failure plumbing via the configured WC_Stripe_Webhook_State helpers.',
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
			'WC_REST_Stripe_Account_Controller',
			'GET',
			'/wc/v3/wc_stripe/account/webhook-status-message'
		);
	}
}
