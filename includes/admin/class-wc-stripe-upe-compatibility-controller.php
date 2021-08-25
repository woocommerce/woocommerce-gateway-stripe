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
		add_action( 'admin_notices', [ $this, 'add_upcoming_compatibility_notice' ] );
		add_action( 'admin_init', [ $this, 'add_admin_note' ] );
	}

	/**
	 * I created this as a separate method so it can be mocked in unit tests.
	 *
	 * @retun string
	 */
	public function get_wc_version() {
		return WC_VERSION;
	}

	public function add_upcoming_compatibility_notice() {
		$unsatisfied_requirements = array_filter(
			[
				[
					'name'        => 'WordPress',
					'version'     => get_bloginfo( 'version' ),
					'requirement' => WC_STRIPE_UPE_MIN_WP_VER,
					'message'     => sprintf( __( 'Wordpress %s or greater' ), WC_STRIPE_UPE_MIN_WP_VER ),
				],
				[
					'name'        => 'WooCommerce',
					'version'     => $this->get_wc_version(),
					'requirement' => WC_STRIPE_UPE_MIN_WC_VER,
					'message'     => sprintf( __( 'WooCommerce %s or greater to be installed and active' ), WC_STRIPE_UPE_MIN_WC_VER ),
				],
			],
			function ( $requirement ) {
				return version_compare( $requirement['version'], $requirement['requirement'], '<' );
			}
		);

		if ( count( $unsatisfied_requirements ) === 0 ) {
			return;
		}

		$unsatisfied_requirements_message = join(
			__( ' and ', 'woocommerce-gateway-stripe' ),
			array_map(
				function ( $requirement ) {
					return $requirement['message'];
				},
				$unsatisfied_requirements
			)
		);

		$unsatisfied_requirements_versions = join(
			__( ' and ', 'woocommerce-gateway-stripe' ),
			array_map(
				function ( $requirement ) {
					return $requirement['name'] . ' ' . $requirement['requirement'];
				},
				$unsatisfied_requirements
			)
		);

		echo '<div class="error"><p><strong>';
		echo wp_kses_post(
			sprintf(
				/* translators: $1. Minimum WooCommerce and/or WordPress versions. $2. Current WooCommerce and/or versions. $3 Learn more link. */
				_n(
					'Starting with version 5.6.0, Stripe will require %1$s. Your version of %2$s will no longer be supported. <a href="%3$s" target="_blank">Learn more here</a>.',
					'Starting with version 5.6.0, Stripe will require %1$s. Your versions of %2$s will no longer be supported. <a href="%3$s" target="_blank">Learn more here</a>.',
					count( $unsatisfied_requirements ),
					'woocommerce-gateway-stripe'
				),
				$unsatisfied_requirements_message,
				$unsatisfied_requirements_versions,
				'?TODO'
			)
		);
		echo '</strong></p></div>';
	}

	public function add_admin_note() {
		// admin notes are not supported on older versions of WooCommerce.
		if ( version_compare( $this->get_wc_version(), '4.4.0', '>=' ) ) {
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-compatibility-note.php';
			WC_Stripe_UPE_Compatibility_Note::init();
		}
	}
}
