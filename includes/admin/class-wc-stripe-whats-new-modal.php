<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a "What's new" modal to admins after this plugin updates.
 *
 * On the next admin page load following a version bump, surfaces the
 * changelog entries for the new version, sourced from readme.txt.
 *
 * Disable via: add_filter( 'wc_stripe_whats_new_modal_enabled', '__return_false' ).
 *
 * @since 10.7.0
 */
class WC_Stripe_Whats_New_Modal {
	/**
	 * Transient key used to flag that an update has just completed and the
	 * modal should be shown on the next admin page load.
	 */
	const PENDING_TRANSIENT = 'wc_stripe_whats_new_modal_pending';

	/**
	 * User meta key recording the last version for which the current admin
	 * dismissed the modal. Per-user so that one admin closing the modal does
	 * not suppress it for everyone else on the site.
	 */
	const DISMISSED_USER_META = 'wc_stripe_whats_new_modal_dismissed_version';

	/**
	 * AJAX action used by the modal to persist dismissal.
	 */
	const DISMISS_AJAX_ACTION = 'wc_stripe_dismiss_whats_new_modal';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_stripe_updated', [ $this, 'flag_pending_modal' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_modal' ] );
		add_action( 'admin_footer', [ $this, 'maybe_render_container' ] );
		add_action( 'wp_ajax_' . self::DISMISS_AJAX_ACTION, [ $this, 'handle_dismiss' ] );
	}

	/**
	 * Records that the modal should appear on the next admin page load.
	 * Hooked to `woocommerce_stripe_updated`, which fires once per version
	 * bump on the first admin request after an update.
	 *
	 * Skips fresh installs: `woocommerce_stripe_updated` also fires the very
	 * first time the plugin runs (when `wc_stripe_version` doesn't yet exist),
	 * but there's nothing "new" to surface on a brand-new install — and the
	 * modal would otherwise pop on first admin load and block automated
	 * setups (e.g. Playwright e2e flows).
	 *
	 * @return void
	 */
	public function flag_pending_modal() {
		// `wc_stripe_version` is read by `WC_Stripe::install()` BEFORE this
		// action fires and updated AFTER, so a `false` here unambiguously
		// means a first-ever install rather than an upgrade.
		if ( false === get_option( 'wc_stripe_version' ) ) {
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

		$dismissed_version = get_user_meta( get_current_user_id(), self::DISMISSED_USER_META, true );
		if ( WC_STRIPE_VERSION === $dismissed_version ) {
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
				'fullChangelogUrl'  => 'https://wordpress.org/plugins/woocommerce-gateway-stripe/#developers',
				'dismissAjaxUrl'    => admin_url( 'admin-ajax.php' ),
				'dismissAjaxAction' => self::DISMISS_AJAX_ACTION,
				'dismissNonce'      => wp_create_nonce( self::DISMISS_AJAX_ACTION ),
			]
		);

		// `wcstripe/tracking` reads these globals to auto-tag every event. The
		// modal can fire on any admin page, not just Stripe-specific ones, so
		// localize them here too — `wp_localize_script` scopes to this script
		// handle, so this doesn't conflict with the Stripe settings pages.
		wp_localize_script(
			'wc-stripe-whats-new-modal',
			'wc_stripe_settings_params',
			[
				'plugin_version' => WC_STRIPE_VERSION,
				'is_test_mode'   => class_exists( 'WC_Stripe_Mode' ) && WC_Stripe_Mode::is_test(),
			]
		);

		wp_enqueue_script( 'wc-stripe-whats-new-modal' );
		wp_enqueue_style( 'wc-stripe-whats-new-modal' );

		$this->record_whats_new_modal_event( 'wcstripe_whats_new_modal_enqueued' );
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
			return; // @phpstan-ignore-line — defensive return; wp_send_json_error always exits in production AJAX, but a non-throwing test die handler would otherwise fall through to check_ajax_referer.
		}

		check_ajax_referer( self::DISMISS_AJAX_ACTION, 'nonce' );

		// Per-user dismissal only. The site-wide PENDING_TRANSIENT is left in
		// place so that other admins still see the modal once after the
		// update; it will expire on its own.
		update_user_meta( get_current_user_id(), self::DISMISSED_USER_META, WC_STRIPE_VERSION );

		$dwell_ms = isset( $_POST['dwell_ms'] ) ? absint( wp_unslash( $_POST['dwell_ms'] ) ) : 0;
		$source   = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';

		$this->record_whats_new_modal_event(
			'wcstripe_whats_new_modal_dismissed',
			[
				'dwell_ms' => $dwell_ms,
				'source'   => $source,
			]
		);

		wp_send_json_success();
	}

	/**
	 * Records a Tracks event for the modal, automatically tagging it with the
	 * plugin version and current Stripe mode. Silently no-ops when
	 * `wc_admin_record_tracks_event()` is unavailable (e.g. older WooCommerce
	 * installs or test environments).
	 *
	 * @param string              $name  Event name.
	 * @param array<string,mixed> $props Event properties.
	 *
	 * @return void
	 */
	protected function record_whats_new_modal_event( string $name, array $props = [] ) {
		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}

		$props['stripe_version'] = WC_STRIPE_VERSION;
		if ( class_exists( 'WC_Stripe_Mode' ) ) {
			$props['is_test_mode'] = WC_Stripe_Mode::is_test() ? 'yes' : 'no';
		}

		try {
			wc_admin_record_tracks_event( $name, $props );
		} catch ( \Throwable $e ) {
			// Best-effort telemetry: never break admin flows on a Tracks failure.
			unset( $e );
		}
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
		$readme_path = $this->get_readme_path();
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

	/**
	 * Returns the absolute path to readme.txt. Overridable via subclass for
	 * tests so the production parsing logic can be exercised against a fixture.
	 *
	 * @return string
	 */
	protected function get_readme_path(): string {
		return WC_STRIPE_PLUGIN_PATH . '/readme.txt';
	}
}
