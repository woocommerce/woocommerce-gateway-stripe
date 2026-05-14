<?php
/**
 * Class WC_Stripe_PP_Version_Detector_Test
 *
 * @package WooCommerce_Stripe/Tests
 */

require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-pp-version-detector.php';

/**
 * Unit tests for {@see WC_Stripe_PP_Version_Detector}.
 */
class WC_Stripe_PP_Version_Detector_Test extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( WC_Stripe_PP_Version_Detector::PP_VERSION_OPTION );
		delete_option( 'woocommerce_stripe_api_settings' );

		parent::tear_down();
	}

	public function test_returns_unknown_when_no_pp_data_present() {
		$this->assertSame(
			WC_Stripe_PP_Version_Detector::VERSION_UNKNOWN,
			WC_Stripe_PP_Version_Detector::detect_major_version()
		);
	}

	/**
	 * Layer 1: PP's own stored version option is the highest-priority source.
	 *
	 * @dataProvider version_option_provider
	 */
	public function test_detects_major_version_from_version_option( string $stored_version, string $expected_major ) {
		update_option( WC_Stripe_PP_Version_Detector::PP_VERSION_OPTION, $stored_version );

		$this->assertSame( $expected_major, WC_Stripe_PP_Version_Detector::detect_major_version() );
	}

	public function version_option_provider(): array {
		return [
			'3.3.106 (PP current)' => [ '3.3.106', '3' ],
			'3.0.0'                => [ '3.0.0', '3' ],
			'4.0.5 (hypothetical)' => [ '4.0.5', '4' ],
			'10.2.1'               => [ '10.2.1', '10' ],
		];
	}

	public function test_returns_unknown_when_version_option_is_malformed() {
		update_option( WC_Stripe_PP_Version_Detector::PP_VERSION_OPTION, 'not-a-version' );

		$this->assertSame(
			WC_Stripe_PP_Version_Detector::VERSION_UNKNOWN,
			WC_Stripe_PP_Version_Detector::detect_major_version()
		);
	}

	public function test_returns_unknown_when_version_option_is_empty_string() {
		update_option( WC_Stripe_PP_Version_Detector::PP_VERSION_OPTION, '' );

		$this->assertSame(
			WC_Stripe_PP_Version_Detector::VERSION_UNKNOWN,
			WC_Stripe_PP_Version_Detector::detect_major_version()
		);
	}

	/**
	 * Layer 3: option-shape sniff falls through when neither option nor plugin file is available.
	 *
	 * The presence of `woocommerce_stripe_api_settings` with a `mode` key fingerprints PP 3.X.
	 */
	public function test_detects_major_version_from_option_shape_when_other_sources_missing() {
		update_option(
			'woocommerce_stripe_api_settings',
			[
				'mode'                 => 'test',
				'publishable_key_test' => 'pk_test_xxx',
			]
		);

		$this->assertSame( '3', WC_Stripe_PP_Version_Detector::detect_major_version() );
	}

	public function test_option_shape_sniff_ignores_unrelated_option_shapes() {
		update_option(
			'woocommerce_stripe_api_settings',
			[
				// No `mode` key → not the PP 3.X fingerprint.
				'publishable_key_test' => 'pk_test_xxx',
			]
		);

		$this->assertSame(
			WC_Stripe_PP_Version_Detector::VERSION_UNKNOWN,
			WC_Stripe_PP_Version_Detector::detect_major_version()
		);
	}

	public function test_full_version_returns_stored_value() {
		update_option( WC_Stripe_PP_Version_Detector::PP_VERSION_OPTION, '3.3.106' );

		$this->assertSame( '3.3.106', WC_Stripe_PP_Version_Detector::detect_full_version() );
	}

	public function test_full_version_returns_null_when_no_pp_data_present() {
		$this->assertNull( WC_Stripe_PP_Version_Detector::detect_full_version() );
	}

	public function test_full_version_falls_back_to_synthetic_3_0_0_from_option_shape() {
		update_option(
			'woocommerce_stripe_api_settings',
			[
				'mode' => 'live',
			]
		);

		$this->assertSame( '3.0.0', WC_Stripe_PP_Version_Detector::detect_full_version() );
	}

	public function test_version_option_takes_priority_over_option_shape() {
		update_option( WC_Stripe_PP_Version_Detector::PP_VERSION_OPTION, '4.0.5' );
		// Shape would suggest 3.x; option should win.
		update_option( 'woocommerce_stripe_api_settings', [ 'mode' => 'live' ] );

		$this->assertSame( '4', WC_Stripe_PP_Version_Detector::detect_major_version() );
	}
}
