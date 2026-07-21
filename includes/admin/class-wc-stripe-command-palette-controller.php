<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Stripe admin destinations as WordPress Command Palette commands.
 *
 * @since 10.9.0
 */
class WC_Stripe_Command_Palette_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Registers the palette hook; called once by the bootstrap so
	 * instantiation alone never stacks duplicate callbacks.
	 *
	 * @since 10.9.0
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
	}

	/**
	 * Registers and enqueues the command palette registration script on every admin screen.
	 *
	 * Loaded globally because the palette is mounted by core in the editor,
	 * not on Stripe's own settings pages. This only needs to load it for users who can manage WooCommerce.
	 *
	 * @return void
	 */
	public function admin_scripts(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/command-palette.asset.php';
		$asset_metadata    = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'wc-stripe-command-palette',
			plugins_url( 'build/command-palette.js', WC_STRIPE_MAIN_FILE ),
			$asset_metadata['dependencies'],
			$asset_metadata['version'],
			true
		);
		wp_set_script_translations(
			'wc-stripe-command-palette',
			'woocommerce-gateway-stripe'
		);

		wp_enqueue_script( 'wc-stripe-command-palette' );
	}
}
