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
		add_filter( 'plugin_row_meta', [ $this, 'add_release_notes_link' ], 10, 2 );
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

		// Required for the plugin information modal that the "Release notes" link opens.
		add_thickbox();

		wp_enqueue_script( 'wc-stripe-plugins-page' );
		wp_enqueue_style( 'wc-stripe-plugins-page' );
	}

	/**
	 * Returns the WordPress.org plugin slug.
	 *
	 * Hard-coded so the plugin information modal still resolves when the plugin
	 * is installed in a non-standard directory.
	 *
	 * @return string The plugin slug.
	 */
	private function get_plugin_slug(): string {
		return 'woocommerce-gateway-stripe';
	}

	/**
	 * Builds the URL for the WordPress plugin information modal, opened on the changelog tab.
	 *
	 * @return string The thickbox-iframe URL.
	 */
	private function get_changelog_url(): string {
		return self_admin_url(
			'plugin-install.php?tab=plugin-information&plugin=' . $this->get_plugin_slug()
			. '&section=changelog&TB_iframe=true&width=600&height=550'
		);
	}

	private string $stripe_plugin_basename = '';

	private function get_plugin_basename(): string {
		if ( '' === $this->stripe_plugin_basename ) {
			$this->stripe_plugin_basename = plugin_basename( WC_STRIPE_MAIN_FILE );
		}
		return $this->stripe_plugin_basename;
	}

	/**
	 * Appends a "Release Notes" link to the plugin row meta on the plugins admin page.
	 *
	 * The link reuses WordPress' built-in plugin information modal, opened on the
	 * changelog tab via thickbox (already enqueued by `enqueue_scripts`).
	 *
	 * @param mixed  $links Existing plugin row meta links.
	 * @param string $file  Plugin file the row belongs to.
	 * @return array Updated row meta links.
	 */
	public function add_release_notes_link( $links, $file ): array {
		$links = (array) $links;

		if ( $this->get_plugin_basename() !== $file ) {
			return $links;
		}

		// When an update is available, WordPress core already injects its own
		// "View details" link into the plugin row pointing at the same modal.
		// Skip ours to avoid redundancy and to avoid surfacing release notes
		// for a version the user has not installed yet.
		if ( $this->has_pending_update( $file ) ) {
			return $links;
		}

		$plugin_slug = $this->get_plugin_slug();
		$label       = __( 'Release notes', 'woocommerce-gateway-stripe' );

		$links['wc_stripe_release_notes'] = sprintf(
			'<a href="%1$s" class="thickbox open-plugin-details-modal" data-slug="%2$s" data-wc-stripe-tracking="release-notes-link" aria-label="%3$s">%4$s</a>',
			esc_url( $this->get_changelog_url() ),
			esc_attr( $plugin_slug ),
			esc_attr__( 'View the WooCommerce Stripe release notes', 'woocommerce-gateway-stripe' ),
			esc_html( $label )
		);

		return $links;
	}

	/**
	 * Whether WordPress has staged an available update for the given plugin file.
	 *
	 * @param string $file Plugin file relative path.
	 * @return bool
	 */
	private function has_pending_update( string $file ): bool {
		$updates = get_site_transient( 'update_plugins' );
		return isset( $updates->response[ $file ] );
	}

	/**
	 * Localized params used by the post-update changelog link.
	 *
	 * @return array{plugin_slug: string, view_changelog_url: string}
	 */
	private function get_changelog_link_params(): array {
		return [
			'plugin_slug'        => $this->get_plugin_slug(),
			'view_changelog_url' => $this->get_changelog_url(),
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
