<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory for the official Stripe PHP SDK client.
 *
 * Returns a `\Stripe\StripeClient` configured with the active secret key, the
 * pinned API version, and the partner / app info Stripe uses to identify this
 * plugin in dashboards and traffic analytics.
 *
 * @since 10.7.0
 */
class WC_Stripe_Client {

	/**
	 * The Stripe partner ID assigned to the WooCommerce Stripe Gateway plugin.
	 */
	const PARTNER_ID = 'pp_partner_EYuSt9peR0WTMg';

	/**
	 * Cached SDK client.
	 *
	 * @var \Stripe\StripeClient|null
	 */
	private static $client = null;

	/**
	 * The secret key the cached client was built with. Lets us rebuild the
	 * client when the key changes (mode toggle, OAuth reconnect, tests).
	 *
	 * @var string
	 */
	private static $cached_secret_key = '';

	/**
	 * Returns a configured `\Stripe\StripeClient`.
	 *
	 * Lazily constructs the client and caches it per-secret-key.
	 */
	public static function get(): \Stripe\StripeClient {
		$secret_key = WC_Stripe_API::get_secret_key();

		if ( null === self::$client || self::$cached_secret_key !== $secret_key ) {
			\Stripe\Stripe::setAppInfo(
				'WooCommerce Stripe Gateway',
				WC_STRIPE_VERSION,
				'https://woocommerce.com/products/stripe/',
				self::PARTNER_ID
			);

			// Route SDK traffic through `wp_safe_remote_request` so existing
			// `pre_http_request` mocks, third-party WP HTTP filters, and the
			// `wp_safe_remote_*` allowlist all continue to apply.
			\Stripe\ApiRequestor::setHttpClient( new WC_Stripe_SDK_WP_Http_Client() );

			self::$client = new \Stripe\StripeClient(
				[
					'api_key'        => $secret_key,
					'stripe_version' => WC_Stripe_API::STRIPE_API_VERSION,
				]
			);

			self::$cached_secret_key = $secret_key;
		}

		return self::$client;
	}

	/**
	 * Resets the cached client. Mainly intended for tests and for callers that
	 * mutate the secret key out-of-band.
	 */
	public static function reset(): void {
		self::$client            = null;
		self::$cached_secret_key = '';
	}
}
