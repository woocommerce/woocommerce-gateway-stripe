<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controls the UPE compatibility before we release UPE - adds some notices for the merchant if necessary.
 *
 * @since 5.5.0
 */
class WC_Stripe_UPE_Compatibility_Controller {
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'add_compatibility_notice' ] );
	}

	/**
	 * I created this as a separate method, so it can be mocked in unit tests.
	 *
	 * @retun string
	 */
	public function get_wc_version() {
		return WC_VERSION;
	}

	/**
	 * Adds a compatibility notice in the `wp-admin` area in case the version of WooCommerce or WordPress are (or will be) not supported.
	 */
	public function add_compatibility_notice() {
		/*
		 * The following might be hard to read, but here's what I'm trying to do:
		 * - If WP and WC are both supported -> nothing to do
		 * - If WC is not supported -> construct message saying "Stripe requires WooCommerce 5.4 or greater to be installed and active. Your version of WooCommerce [X.X] is no longer be supported"
		 * - If WP is not supported -> construct message saying "Stripe requires WordPress 5.6 or greater. Your version of WordPress [X.X] is no longer be supported"
		 * - If WC & WP are both not supported -> construct message saying "Stripe requires WordPress 5.6 or greater and WooCommerce 5.4 or greater to be installed and active. Your versions of WordPress [X.X] and WooCommerce [X.X] are no longer be supported"
		 */
		$unsatisfied_requirements = array_filter(
			[
				[
					'name'         => 'WordPress',
					'version'      => get_bloginfo( 'version' ),
					'is_supported' => WC_Stripe_UPE_Compatibility::is_wp_supported(),
					/* translators: %s. WordPress version installed. */
					'message'      => sprintf( __( 'WordPress %s or greater', 'woocommerce-gateway-stripe' ), WC_Stripe_UPE_Compatibility::MIN_WP_VERSION ),
				],
				[
					'name'         => 'WooCommerce',
					'version'      => $this->get_wc_version(),
					'is_supported' => version_compare( $this->get_wc_version(), WC_Stripe_UPE_Compatibility::MIN_WC_VERSION, '>=' ),
					'message'      => sprintf(
					/* translators: %s. WooCommerce version installed. */
						__(
							'WooCommerce %s or greater to be installed and active',
							'woocommerce-gateway-stripe'
						),
						WC_Stripe_UPE_Compatibility::MIN_WC_VERSION
					),
				],
			],
			function ( $requirement ) {
				return ! $requirement['is_supported'];
			}
		);

		if ( count( $unsatisfied_requirements ) === 0 ) {
			return;
		}

		$this->show_current_compatibility_notice( $unsatisfied_requirements );
	}

	private function get_installed_versions_message( $unsatisfied_requirements ) {
		return implode(
			__( ' and ', 'woocommerce-gateway-stripe' ),
			array_map(
				function ( $requirement ) {
					return $requirement['name'] . ' ' . $requirement['version'];
				},
				$unsatisfied_requirements
			)
		);
	}

	private function get_unsatisfied_requirements_message( $unsatisfied_requirements ) {
		return implode(
			__( ' and ', 'woocommerce-gateway-stripe' ),
			array_map(
				function ( $requirement ) {
					return $requirement['message'];
				},
				$unsatisfied_requirements
			)
		);
	}

	private function show_current_compatibility_notice( array $unsatisfied_requirements ) {
		/*
		 * The following might be hard to read, but here's what I'm trying to do:
		 * - If WP and WC are both supported -> nothing to do
		 * - If WC is not supported -> construct message saying "WooCommerce Stripe requires WooCommerce 5.4 or greater to be installed and active. Your version of WooCommerce [X.X] is no longer supported"
		 * - If WP is not supported -> construct message saying "WooCommerce Stripe requires WordPress 5.6 or greater. Your version of WordPress [X.X] is no longer supported"
		 * - If WC & WP are both not supported -> construct message saying "WooCommerce Stripe requires WordPress 5.6 or greater and WooCommerce 5.4 or greater to be installed and active. Your versions of WordPress [X.X] and WooCommerce [X.X] are no longer supported"
		 */
		$unsatisfied_requirements_message = $this->get_unsatisfied_requirements_message( $unsatisfied_requirements );

		$unsatisfied_requirements_versions = $this->get_installed_versions_message( $unsatisfied_requirements );

		echo '<div class="error"><p><strong>';
		echo wp_kses_post(
			sprintf(
			/* translators: $1. Minimum WooCommerce and/or WordPress versions. $2. Current WooCommerce and/or versions. $3 Learn more link. */
				_n(
					'WooCommerce Stripe requires %1$s. Your version of %2$s is no longer supported.',
					'WooCommerce Stripe requires %1$s. Your versions of %2$s are no longer supported.',
					count( $unsatisfied_requirements ),
					'woocommerce-gateway-stripe'
				),
				$unsatisfied_requirements_message,
				$unsatisfied_requirements_versions
			)
		);
		echo '</strong></p></div>';
	}
}
