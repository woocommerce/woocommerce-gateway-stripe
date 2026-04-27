<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a "What's new" modal to admins after a standalone update of this plugin.
 *
 * Part of the Radical Speed Month (RSM) experiment: when only this extension is
 * updated (i.e. not bundled with other plugins in the same upgrader run), the
 * next admin page load surfaces the changelog entries for the new version,
 * sourced from readme.txt.
 *
 * Disable via: add_filter( 'wc_stripe_whats_new_modal_enabled', '__return_false' ).
 *
 * @since 10.7.0
 */
class WC_Stripe_Whats_New_Modal {
	/**
	 * Transient key used to flag that a standalone update has just completed and
	 * the modal should be shown on the next admin page load.
	 */
	const PENDING_TRANSIENT = 'wc_stripe_whats_new_modal_pending';

	/**
	 * Option key recording the last version for which the user dismissed the
	 * modal. Suppresses re-display for the same version.
	 */
	const DISMISSED_OPTION = 'wc_stripe_whats_new_modal_dismissed_version';

	/**
	 * AJAX action used by the modal to persist dismissal.
	 */
	const DISMISS_AJAX_ACTION = 'wc_stripe_dismiss_whats_new_modal';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'upgrader_process_complete', [ $this, 'maybe_flag_standalone_update' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_modal' ] );
		add_action( 'admin_footer', [ $this, 'maybe_render_container' ] );
		add_action( 'wp_ajax_' . self::DISMISS_AJAX_ACTION, [ $this, 'handle_dismiss' ] );
	}

	/**
	 * Captures plugin updates and, when the run targets only this plugin,
	 * records that the modal should appear on the next admin page load.
	 *
	 * @param WP_Upgrader|mixed $upgrader   The WP_Upgrader instance.
	 * @param array|mixed       $hook_extra Extras passed by the upgrader.
	 *
	 * @return void
	 */
	public function maybe_flag_standalone_update( $upgrader, $hook_extra ) {
		unset( $upgrader );

		if ( ! is_array( $hook_extra ) ) {
			return;
		}

		$action = isset( $hook_extra['action'] ) ? $hook_extra['action'] : '';
		$type   = isset( $hook_extra['type'] ) ? $hook_extra['type'] : '';

		if ( 'update' !== $action || 'plugin' !== $type ) {
			return;
		}

		$updated_plugins = [];
		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$updated_plugins = array_values( array_filter( $hook_extra['plugins'] ) );
		} elseif ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
			$updated_plugins = [ $hook_extra['plugin'] ];
		}

		if ( 1 !== count( $updated_plugins ) ) {
			return;
		}

		if ( plugin_basename( WC_STRIPE_MAIN_FILE ) !== reset( $updated_plugins ) ) {
			return;
		}

		set_transient( self::PENDING_TRANSIENT, '1', DAY_IN_SECONDS );
	}

	/**
	 * Decides whether the modal should be shown on the current request.
	 *
	 * @return bool
	 */
	public function should_display(): bool {
		/**
		 * Filters whether the "What's new" modal should run at all.
		 *
		 * @param bool $enabled Whether the experiment is enabled.
		 */
		if ( ! apply_filters( 'wc_stripe_whats_new_modal_enabled', true ) ) {
			return false;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		if ( ! get_transient( self::PENDING_TRANSIENT ) ) {
			return false;
		}

		if ( get_option( self::DISMISSED_OPTION ) === WC_STRIPE_VERSION ) {
			return false;
		}

		return true;
	}

	/**
	 * Enqueues the modal assets when conditions are met.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function maybe_enqueue_modal( $hook_suffix ) {
		unset( $hook_suffix );

		if ( ! $this->should_display() ) {
			return;
		}

		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/whats-new-modal.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'wc-stripe-whats-new-modal',
			plugins_url( 'build/whats-new-modal.js', WC_STRIPE_MAIN_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_register_style(
			'wc-stripe-whats-new-modal',
			plugins_url( 'build/whats-new-modal.css', WC_STRIPE_MAIN_FILE ),
			[ 'wp-components' ],
			$script_asset['version']
		);

		wp_set_script_translations( 'wc-stripe-whats-new-modal', 'woocommerce-gateway-stripe' );

		$changes = $this->parse_changelog_for_version( WC_STRIPE_VERSION );

		wp_localize_script(
			'wc-stripe-whats-new-modal',
			'wcStripeWhatsNewModalParams',
			[
				'version'           => WC_STRIPE_VERSION,
				'changes'           => $changes,
				'isTestMode'        => class_exists( 'WC_Stripe_Mode' ) && WC_Stripe_Mode::is_test(),
				'fullChangelogUrl'  => 'https://wordpress.org/plugins/woocommerce-gateway-stripe/#developers',
				'dismissAjaxUrl'    => admin_url( 'admin-ajax.php' ),
				'dismissAjaxAction' => self::DISMISS_AJAX_ACTION,
				'dismissNonce'      => wp_create_nonce( self::DISMISS_AJAX_ACTION ),
			]
		);

		wp_enqueue_script( 'wc-stripe-whats-new-modal' );
		wp_enqueue_style( 'wc-stripe-whats-new-modal' );

		$this->record_event(
			'wcstripe_whats_new_modal_enqueued',
			[ 'entry_count' => count( $changes ) ]
		);
	}

	/**
	 * Renders the React mount point in the admin footer.
	 *
	 * @return void
	 */
	public function maybe_render_container() {
		if ( ! wp_script_is( 'wc-stripe-whats-new-modal', 'enqueued' ) ) {
			return;
		}

		echo '<div id="wc-stripe-whats-new-modal-root"></div>';
	}

	/**
	 * Handles the AJAX dismiss request from the modal.
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}

		check_ajax_referer( self::DISMISS_AJAX_ACTION, 'nonce' );

		update_option( self::DISMISSED_OPTION, WC_STRIPE_VERSION, false );
		delete_transient( self::PENDING_TRANSIENT );

		$dwell_ms = isset( $_POST['dwell_ms'] ) ? absint( wp_unslash( $_POST['dwell_ms'] ) ) : 0;
		$source   = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';

		$this->record_event(
			'wcstripe_whats_new_modal_dismissed',
			[
				'dwell_ms' => $dwell_ms,
				'source'   => $source,
			]
		);

		wp_send_json_success();
	}

	/**
	 * Records a Tracks event, automatically tagging it with the plugin version
	 * and current Stripe mode. Silently no-ops when WC_Tracks is unavailable
	 * (e.g. older WooCommerce installs or test environments).
	 *
	 * @param string              $name  Event name.
	 * @param array<string,mixed> $props Event properties.
	 *
	 * @return void
	 */
	protected function record_event( string $name, array $props = [] ) {
		if ( ! class_exists( 'WC_Tracks' ) ) {
			return;
		}

		$props['stripe_version'] = WC_STRIPE_VERSION;
		if ( class_exists( 'WC_Stripe_Mode' ) ) {
			$props['is_test_mode'] = WC_Stripe_Mode::is_test() ? 'yes' : 'no';
		}

		WC_Tracks::record_event( $name, $props );
	}

	/**
	 * Parses readme.txt and returns the changelog entries for the given version.
	 *
	 * Each entry is an associative array with `tag` (e.g. "Fix", "Add") and
	 * `text`. Returns an empty array when the version block cannot be located.
	 *
	 * @param string $version Plugin version to look up (e.g. "10.7.0").
	 *
	 * @return array<int, array{tag: string, text: string}>
	 */
	public function parse_changelog_for_version( string $version ): array {
		$readme_path = WC_STRIPE_PLUGIN_PATH . '/readme.txt';
		if ( ! is_readable( $readme_path ) ) {
			return [];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a bundled plugin file.
		$contents = file_get_contents( $readme_path );
		if ( false === $contents || '' === $contents ) {
			return [];
		}

		$pattern = '/^=\s*' . preg_quote( $version, '/' ) . '\s*-[^=\r\n]*=\s*\R(?P<body>.*?)(?=^=\s*\d+\.\d+\.\d+\s*-|\[See changelog|\z)/ms';

		if ( ! preg_match( $pattern, $contents, $matches ) ) {
			return [];
		}

		$body  = trim( $matches['body'] );
		$items = [];

		$lines = preg_split( '/\R/', $body );
		if ( false === $lines ) {
			return [];
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '*' !== substr( $line, 0, 1 ) ) {
				continue;
			}

			$line = ltrim( $line, "* \t" );

			if ( preg_match( '/^([A-Za-z][A-Za-z ]*?)\s+-\s+(.+)$/', $line, $parts ) ) {
				$items[] = [
					'tag'  => trim( $parts[1] ),
					'text' => trim( $parts[2] ),
				];
				continue;
			}

			$items[] = [
				'tag'  => '',
				'text' => $line,
			];
		}

		return $items;
	}
}
