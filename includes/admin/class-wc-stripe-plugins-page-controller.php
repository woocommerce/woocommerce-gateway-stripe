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
	 * Slug used to identify this plugin in WordPress update events
	 * and in plugin information modal URLs.
	 */
	const PLUGIN_SLUG = 'woocommerce-gateway-stripe';

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
	 * @param string|null $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix = null ) {
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
			array_merge( $script_asset['dependencies'], [ 'jquery', 'plugin-install' ] ),
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
			array_merge(
				WC_Stripe_Helper::get_exit_survey_params( $this->account ),
				$this->get_changelog_link_params()
			)
		);

		// Required for the plugin information modal that the changelog link opens.
		add_thickbox();

		wp_enqueue_script( 'wc-stripe-plugins-page' );
		wp_enqueue_style( 'wc-stripe-plugins-page' );
	}

	/**
	 * Localized params used by the post-update changelog link.
	 *
	 * @return array{plugin_slug: string, view_changelog_url: string}
	 */
	private function get_changelog_link_params(): array {
		$view_changelog_url = self_admin_url(
			'plugin-install.php?tab=plugin-information&plugin=' . self::PLUGIN_SLUG
			. '&section=changelog&TB_iframe=true&width=600&height=550'
		);

		return [
			'plugin_slug'        => self::PLUGIN_SLUG,
			'view_changelog_url' => $view_changelog_url,
		];
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
