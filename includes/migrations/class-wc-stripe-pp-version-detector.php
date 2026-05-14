<?php
/**
 * Detects the installed (or previously installed) major version of the
 * Payment Plugins for Stripe (PP) plugin.
 *
 * @package WooCommerce_Stripe/Migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Three-layer detection in priority order:
 *   1. PP's own version option `stripe_wc_version` (most reliable; persists after PP is uninstalled
 *      because PP has no uninstall.php).
 *   2. Plugin file header parse via `get_plugin_data()` (works while PP files are on disk).
 *   3. Option-shape sniff (last-resort fingerprint of PP 3.X's option layout).
 *
 * @since 9.7.0
 */
class WC_Stripe_PP_Version_Detector {

	/**
	 * Plugin entry file relative to WP_PLUGIN_DIR. PP's WordPress.org slug is `woo-stripe-payment`
	 * (the pre-rebrand name); the product is marketed as "Payment Plugins for Stripe" but the
	 * folder and slug remain `woo-stripe-payment`. Entry file: `stripe-payments.php`.
	 *
	 * @var string
	 */
	const PP_PLUGIN_FILE = 'woo-stripe-payment/stripe-payments.php';

	/**
	 * PP's own version-storage option key (PP's `WC_Stripe_Constants::VERSION_KEY`).
	 * Written by `WC_Stripe_Install::install()` and `WC_Stripe_Update::do_db_update()` in PP.
	 * Verified against PP v3.3.106 at `includes/class-wc-stripe-constants.php`.
	 *
	 * @var string
	 */
	const PP_VERSION_OPTION = 'stripe_wc_version';

	/**
	 * Sentinel returned when no PP version can be determined.
	 *
	 * @var string
	 */
	const VERSION_UNKNOWN = 'unknown';

	/**
	 * Returns the PP major version as a string: e.g. '3', '4', or 'unknown'.
	 *
	 * @return string
	 */
	public static function detect_major_version(): string {
		$version = self::from_version_option();

		if ( null === $version ) {
			$version = self::from_plugin_header();
		}

		if ( null === $version ) {
			$version = self::from_option_shape();
		}

		if ( null === $version || '' === $version ) {
			return self::VERSION_UNKNOWN;
		}

		$parts = explode( '.', $version );
		$major = isset( $parts[0] ) ? (int) $parts[0] : 0;

		if ( $major <= 0 ) {
			return self::VERSION_UNKNOWN;
		}

		return (string) $major;
	}

	/**
	 * Returns the full version string for forensic context (not just the major component).
	 *
	 * @return string|null Full version string when available, null otherwise.
	 */
	public static function detect_full_version(): ?string {
		return self::from_version_option() ?? self::from_plugin_header() ?? self::from_option_shape();
	}

	/**
	 * Layer 1: PP's own version option.
	 *
	 * @return string|null
	 */
	private static function from_version_option(): ?string {
		$version = get_option( self::PP_VERSION_OPTION, '' );
		if ( ! is_string( $version ) || '' === $version ) {
			return null;
		}
		return $version;
	}

	/**
	 * Layer 2: Plugin file header parse via WordPress core.
	 *
	 * @return string|null
	 */
	private static function from_plugin_header(): ?string {
		$plugin_file = WP_PLUGIN_DIR . '/' . self::PP_PLUGIN_FILE;

		if ( ! file_exists( $plugin_file ) ) {
			return null;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data    = get_plugin_data( $plugin_file, false, false );
		$version = isset( $data['Version'] ) ? (string) $data['Version'] : '';

		return '' !== $version ? $version : null;
	}

	/**
	 * Layer 3: Option-shape sniff. Last-resort fingerprint of PP 3.X.
	 *
	 * PP 3.X uses `woocommerce_stripe_api_settings` with a `mode` key. PP 4.X may consolidate
	 * or rename this — this method must be reassessed when 4.X ships.
	 *
	 * @return string|null
	 */
	private static function from_option_shape(): ?string {
		$api_settings = get_option( 'woocommerce_stripe_api_settings', null );

		if ( is_array( $api_settings ) && array_key_exists( 'mode', $api_settings ) ) {
			// Confident enough for major-version mapping. Returns synthetic 3.0.0 so consumers
			// that need a full version string don't get an empty answer.
			return '3.0.0';
		}

		return null;
	}
}
