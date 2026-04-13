<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the exit survey script on the Plugins admin page.
 *
 * @since 10.6.0
 */
class WC_Stripe_Plugins_Page_Controller {

	/**
	 * The Stripe account instance.
	 *
	 * @var WC_Stripe_Account
	 */
	private $account;

	/**
	 * Constructor.
	 *
	 * @param WC_Stripe_Account $account Stripe account.
	 */
	public function __construct( WC_Stripe_Account $account ) {
		$this->account = $account;

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_footer', [ $this, 'render_container' ] );
	}

	/**
	 * Enqueue the plugins page script and styles.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( string $hook_suffix ) {
		if ( 'plugins.php' !== $hook_suffix ) {
			return;
		}

		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/plugins-page.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'wc-stripe-plugins-page',
			plugins_url( 'build/plugins-page.js', WC_STRIPE_MAIN_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_register_style(
			'wc-stripe-plugins-page',
			plugins_url( 'build/plugins-page.css', WC_STRIPE_MAIN_FILE ),
			[ 'wp-components' ],
			$script_asset['version']
		);

		wp_set_script_translations(
			'wc-stripe-plugins-page',
			'woocommerce-gateway-stripe'
		);

		wp_localize_script(
			'wc-stripe-plugins-page',
			'wcStripePluginsPageParams',
			WC_Stripe_Helper::get_exit_survey_params( $this->account )
		);

		wp_enqueue_script( 'wc-stripe-plugins-page' );
		wp_enqueue_style( 'wc-stripe-plugins-page' );
	}

	/**
	 * Render the container div for the React app.
	 *
	 * @return void
	 */
	public function render_container() {
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		echo '<div id="wc-stripe-plugins-page-app"></div>';
	}
}
