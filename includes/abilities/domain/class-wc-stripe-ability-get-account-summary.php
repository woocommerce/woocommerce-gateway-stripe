<?php
/**
 * Get Stripe Account Summary ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.

/**
 * Registers the woocommerce-gateway-stripe/get-account-summary ability.
 *
 * Answers "what's the current state of my Stripe account?" in one zero-arg
 * call — status, country, supported currencies, statement descriptor,
 * test/live mode, and pending/overdue requirements. Delegates to
 * WC_REST_Stripe_Account_Controller::get_account_summary via the abstract
 * base's delegate_to_rest_controller() helper.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active. The
 *           WC_Stripe_Abilities_Registrar short-circuits before Woo Core's
 *           loader would iterate ABILITY_CLASSES on earlier WC versions.
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Account_Summary extends WC_Stripe_Ability_Base implements AbilityDefinition {

	/**
	 * Stable ability ID — the public contract agents call.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-account-summary';
	}

	/**
	 * Build the wp_register_ability() argument array.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe account summary', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				"Returns the current state of the merchant's connected Stripe account — Stripe account ID, status, country, supported currencies, statement descriptor, test/live mode, and any pending or overdue requirements. Zero-argument read; safe summary suitable for agent tools (excludes the raw account object).",
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [],
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'type'                 => 'object',
				'properties'           => [
					'id' => [
						'type'        => [ 'string', 'null' ],
						'description' => __( 'Stripe account ID (e.g. acct_...). Null when no account is connected.', 'woocommerce-gateway-stripe' ),
					],
				],
				'additionalProperties' => true,
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
	 * Execute callback.
	 *
	 * Forwards to WC_REST_Stripe_Account_Controller::get_account_summary
	 * via rest_do_request() so any future controller-side normalization
	 * applies uniformly to REST consumers, MCP agents, and the Abilities
	 * API REST bridge.
	 *
	 * @param mixed $input Optional; ability input. Unused (empty input_schema).
	 * @return array|WP_Error
	 */
	public static function execute( $input = null ) {
		unset( $input );

		return self::delegate_to_rest_controller(
			'WC_REST_Stripe_Account_Controller',
			'GET',
			'/wc/v3/wc_stripe/account/summary'
		);
	}
}
