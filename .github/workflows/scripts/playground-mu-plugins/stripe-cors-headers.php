<?php
/**
 * Plugin Name: Stripe CORS Header Pass-through (Playground only)
 * Description: Opts the Stripe Authorization header through Playground's CORS proxy. Loaded only inside the Playground sandbox; never shipped with the plugin.
 *
 * Playground's CORS proxy (cors.wordpress.net/proxy.php) strips Authorization
 * headers by default. Stripe REST endpoints require Authorization: Bearer ...,
 * so without this filter every wp_safe_remote_post() to api.stripe.com hits
 * the proxy, loses its auth header, and returns 401 — surfaced in the plugin
 * UI as "test API keys are no longer valid." Setting
 * X-Cors-Proxy-Allowed-Request-Headers explicitly opts the listed headers
 * through.
 *
 * Header value MUST be lowercase. The proxy's PHP filter lowercases before
 * comparing, but Playground's service-worker layer
 * (fetch-with-cors-proxy.ts) does a case-sensitive
 * `.includes('authorization')` to decide whether to set
 * `credentials: 'include'` on the proxy retry. Without that flag the browser
 * drops the JS-set Authorization header before it ever leaves the sandbox,
 * and Stripe responds "you did not provide an API key."
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'http_request_args',
	function ( $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.stripe.com/' ) ) {
			return $args;
		}

		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}

		$args['headers']['X-Cors-Proxy-Allowed-Request-Headers'] = 'authorization, idempotency-key, stripe-version, stripe-account';

		return $args;
	},
	10,
	2
);
