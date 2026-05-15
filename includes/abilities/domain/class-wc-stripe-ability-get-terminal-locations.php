<?php
/**
 * Get Stripe Terminal Locations ability.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

// phpcs:disable WordPress.Files.FileName -- Domain class follows the plugin's `class-*` convention with `ability-` infix.
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9.

/**
 * Registers the woocommerce-gateway-stripe/get-terminal-locations ability.
 *
 * Lists Stripe Terminal locations with optional single-location fetch via
 * `location_id`. Semantic-intent grouping: one ability routes through
 * get_location() for single fetch when `location_id` is provided, else
 * lists via get_all_locations(). Pagination params (`limit`,
 * `starting_after`, `ending_before`) pass through to Stripe.
 *
 * Excludes the /store endpoint (which auto-creates a location when none
 * matches the store address — write semantics).
 *
 * @internal
 *
 * @since 10.8.0
 */
class WC_Stripe_Ability_Get_Terminal_Locations extends WC_Stripe_Ability_Base implements AbilityDefinition {

	public static function get_name(): string {
		return 'woocommerce-gateway-stripe/get-terminal-locations';
	}

	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get Stripe Terminal locations', 'woocommerce-gateway-stripe' ),
			'description'         => __(
				"Lists the merchant's configured Stripe Terminal locations. When a `location_id` is provided, returns just that single location. Otherwise returns the full list paginated via Stripe's cursor parameters. Each call hits the Stripe API.",
				'woocommerce-gateway-stripe'
			),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
					'location_id'    => [
						'type'        => 'string',
						'pattern'     => '^tml_[A-Za-z0-9_]+$',
						'description' => __( 'When provided, fetch this single Terminal location instead of listing all.', 'woocommerce-gateway-stripe' ),
					],
					'limit'          => [
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'default'     => 10,
						'description' => __( 'Maximum number of locations to return. Defaults to 10; Stripe caps at 100.', 'woocommerce-gateway-stripe' ),
					],
					'starting_after' => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return locations after this object ID.', 'woocommerce-gateway-stripe' ),
					],
					'ending_before'  => [
						'type'        => 'string',
						'description' => __( 'Stripe cursor — return locations before this object ID.', 'woocommerce-gateway-stripe' ),
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

		// Single-location fetch routes through a different REST endpoint.
		if ( isset( $input['location_id'] )
			&& is_string( $input['location_id'] )
			&& '' !== $input['location_id']
		) {
			return self::delegate_to_rest_controller(
				'WC_REST_Stripe_Locations_Controller',
				'GET',
				'/wc/v3/wc_stripe/terminal/locations/' . rawurlencode( $input['location_id'] )
			);
		}

		$params = [];
		foreach ( [ 'limit', 'starting_after', 'ending_before' ] as $key ) {
			if ( isset( $input[ $key ] ) && '' !== $input[ $key ] ) {
				$params[ $key ] = $input[ $key ];
			}
		}

		return self::delegate_to_rest_controller(
			'WC_REST_Stripe_Locations_Controller',
			'GET',
			'/wc/v3/wc_stripe/terminal/locations',
			$params
		);
	}
}
