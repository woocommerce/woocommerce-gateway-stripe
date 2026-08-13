<?php
/**
 * Abstract base class for Stripe ability definitions.
 *
 * @package WooCommerce_Stripe
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName -- Abstract class file follows the plugin's `abstract-*` convention.

/**
 * Shared helpers for Stripe ability definitions.
 *
 * Mirrors the shape of WooCommerce Core's
 * `Internal\Abilities\Domain\AbstractDomainAbility` (introduced in WC 10.9)
 * without coupling this plugin to that class — Woo Core's lives under
 * `Internal\`, which we treat as off-limits for cross-plugin reuse.
 *
 * Lives in `includes/abilities/` rather than the plugin's usual
 * `includes/abstracts/` directory because every consumer is itself in
 * `includes/abilities/domain/`; co-locating the base with its subdomain keeps
 * the abilities module self-contained.
 *
 * @since 10.8.0
 *
 * @internal Subject to change without notice between releases.
 */
abstract class WC_Stripe_Ability_Base {

	/**
	 * Ability category slug shared across every Stripe Domain ability.
	 *
	 * The `woocommerce` category is owned and registered by WooCommerce
	 * Core (10.9+); plugin ownership lives in the ability namespace
	 * (`woocommerce-gateway-stripe/*`), not the category. Single source of
	 * truth — Domain classes reference this via `self::CATEGORY_SLUG`.
	 */
	public const CATEGORY_SLUG = 'woocommerce';

	/**
	 * Execute a backing REST controller route and return its unwrapped response.
	 *
	 * Used by Shape-2 abilities whose backing is a plugin-local REST
	 * controller (the Tier 1 abilities in the RSM-108 audit). Builds a
	 * `WP_REST_Request`, dispatches via `rest_do_request()`, and unwraps
	 * either a `WP_REST_Response` or a raw array return shape.
	 *
	 * @param string $controller_class Fully-qualified backing controller class
	 *                                 (informational; produces a clean error
	 *                                 when the class is not loaded).
	 * @param string $method           HTTP method (GET, POST, PUT, DELETE).
	 * @param string $route            Resolved WP REST route path.
	 * @param array  $params           Request parameters.
	 * @param bool   $return_response  When true, return the WP_REST_Response so
	 *                                 callers can read response headers (e.g.
	 *                                 X-WP-Total for paginated envelopes).
	 *
	 * @phpstan-return ($return_response is true ? array|WP_REST_Response|WP_Error : array|WP_Error)
	 * @return array|WP_REST_Response|WP_Error
	 */
	protected static function delegate_to_rest_controller(
		string $controller_class,
		string $method,
		string $route,
		array $params = [],
		bool $return_response = false
	) {
		if ( ! class_exists( $controller_class ) ) {
			return new WP_Error(
				'wc_stripe_missing_controller',
				sprintf(
					/* translators: %s: fully-qualified class name of the missing REST controller. */
					__( 'REST controller %s is not loaded.', 'woocommerce-gateway-stripe' ),
					$controller_class
				),
				[ 'status' => 500 ]
			);
		}

		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response instanceof WP_REST_Response ) {
			if ( $response->is_error() ) {
				// @phpstan-ignore-next-line return.type (WP_REST_Response::as_error() always returns a WP_Error when is_error() is true.)
				return $response->as_error();
			}
			if ( $return_response ) {
				return $response;
			}
			return $response->get_data();
		}

		return is_array( $response ) ? $response : [ $response ];
	}

	/**
	 * Call Stripe's API via WC_Stripe_API::retrieve() and normalize the response.
	 *
	 * Used by abilities that read directly from `https://api.stripe.com/v1/$path`.
	 *  Returns the decoded Stripe object or a WP_Error.
	 *
	 * `WC_Stripe_API::retrieve()` returns `null` on every 401 (the
	 * invalid-API-key UI signal), and additionally short-circuits before
	 * any network call once the invalid-key error counter exceeds the
	 * threshold — both paths surface here as a typed
	 * `wc_stripe_api_unauthenticated` `WP_Error` rather than a quiet empty.
	 *
	 * @param string $stripe_api_path Stripe API path (e.g. "charges/ch_xxx" or
	 *                                "charges?limit=10"). Caller is responsible
	 *                                for url-encoding any id segments.
	 *
	 * @return array|WP_Error
	 */
	protected static function retrieve_from_stripe( string $stripe_api_path ) {
		if ( ! class_exists( 'WC_Stripe_API' ) ) {
			return new WP_Error(
				'wc_stripe_not_initialized',
				__( 'Stripe API is not initialized.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 500 ]
			);
		}

		$response = WC_Stripe_API::retrieve( $stripe_api_path );

		if ( null === $response ) {
			return new WP_Error(
				'wc_stripe_api_unauthenticated',
				__( 'Stripe API rejected the stored secret key. Verify the merchant credentials.', 'woocommerce-gateway-stripe' ),
				[ 'status' => 401 ]
			);
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( is_object( $response ) && isset( $response->error ) ) {
			$error_code    = isset( $response->error->code ) ? (string) $response->error->code : 'wc_stripe_api_error';
			$error_message = isset( $response->error->message ) ? (string) $response->error->message : __( 'Stripe API returned an error.', 'woocommerce-gateway-stripe' );

			return new WP_Error( $error_code, $error_message, [ 'status' => 400 ] );
		}

		// Cast stdClass response to array for consistent agent-facing shape.
		if ( is_object( $response ) ) {
			$encoded = wp_json_encode( $response );
			return is_string( $encoded ) ? json_decode( $encoded, true ) : (array) $response;
		}
		return (array) $response;
	}

	/**
	 * Build a query string for Stripe list endpoints from an input array.
	 *
	 * Filters out null / empty-string values, preserves bracket-style nested
	 * filters (e.g. `created[gte]`), and applies Stripe's list pagination
	 * conventions (`limit`, `starting_after`, `ending_before`).
	 *
	 * @param array $params Map of param name to scalar or scalar-keyed-array value.
	 *
	 * @return string Empty string when nothing remains after filtering, else
	 *                a `?key=val&...` suffix.
	 */
	protected static function build_stripe_query_string( array $params ): string {
		$filtered = [];
		foreach ( $params as $key => $value ) {
			if ( null === $value || '' === $value ) {
				continue;
			}
			$filtered[ $key ] = $value;
		}

		if ( empty( $filtered ) ) {
			return '';
		}

		return '?' . http_build_query( $filtered, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Build a Stripe-style `expand[]=...` query fragment.
	 *
	 * Stripe expects `expand[]=foo&expand[]=bar` (brackets-no-index), which
	 * is *not* what `http_build_query()` emits for indexed arrays. Callers
	 * append the fragment to a path (with `?` or `&` separator chosen by
	 * the caller, since the path may already carry an id segment or other
	 * filters).
	 *
	 * Empty / non-string entries are dropped. Returns an empty string when
	 * the resulting list is empty.
	 *
	 * @param array<int, mixed> $expand Whitelisted expand field names.
	 * @return string e.g. "expand[]=customer&expand[]=balance_transaction" or "".
	 */
	protected static function build_expand_query_fragment( array $expand ): string {
		$parts = [];
		foreach ( $expand as $field ) {
			if ( ! is_string( $field ) || '' === $field ) {
				continue;
			}
			$parts[] = 'expand[]=' . rawurlencode( $field );
		}
		return implode( '&', $parts );
	}

	/**
	 * Append an `expand[]=...` fragment to a Stripe API path.
	 *
	 * Picks the right separator (`?` or `&`) based on whether the path
	 * already carries a query string, and skips entirely when no expand
	 * fields are requested.
	 *
	 * @param string            $resource_path e.g. "charges/ch_xxx" or "charges?limit=10".
	 * @param array<int, mixed> $expand        Whitelisted expand field names.
	 * @return string Path suitable for WC_Stripe_API::retrieve().
	 */
	protected static function append_expand_to_path( string $resource_path, array $expand ): string {
		$fragment = self::build_expand_query_fragment( $expand );
		if ( '' === $fragment ) {
			return $resource_path;
		}
		$separator = ( false === strpos( $resource_path, '?' ) ) ? '?' : '&';
		return $resource_path . $separator . $fragment;
	}
}
