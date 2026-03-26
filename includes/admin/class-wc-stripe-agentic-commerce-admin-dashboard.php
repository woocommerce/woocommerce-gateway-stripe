<?php
/**
 * Stripe Agentic Commerce Admin Dashboard
 *
 * Renders the product feed sync monitoring dashboard inside the WooCommerce admin.
 * Provides a status overview, sync history table, error display, and a manual sync button.
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe Agentic Commerce Admin Dashboard
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Admin_Dashboard {

	/**
	 * WordPress admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'wc-stripe-agentic-commerce';

	/**
	 * Admin post action for manual sync.
	 *
	 * @var string
	 */
	const MANUAL_SYNC_ACTION = 'stripe_agentic_manual_sync';

	/**
	 * Nonce action for manual sync.
	 *
	 * @var string
	 */
	const MANUAL_SYNC_NONCE = 'stripe_agentic_manual_sync';

	/**
	 * Option key for the last sync result.
	 *
	 * @var string
	 */
	const LAST_SYNC_OPTION = 'wc_stripe_agentic_last_sync';

	/**
	 * Option key for the sync history.
	 *
	 * @var string
	 */
	const SYNC_HISTORY_OPTION = 'wc_stripe_agentic_sync_history';

	/**
	 * Register WordPress hooks.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'admin_post_' . self::MANUAL_SYNC_ACTION, [ $this, 'handle_manual_sync' ] );
		add_action( 'admin_notices', [ $this, 'render_sync_triggered_notice' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Register the admin page under WooCommerce.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Agentic Commerce — Stripe', 'woocommerce-gateway-stripe' ),
			__( 'Agentic Commerce', 'woocommerce-gateway-stripe' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 10.5.0
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wc-stripe-agentic-dashboard',
			plugins_url( 'assets/css/agentic-commerce-dashboard.css', WC_STRIPE_MAIN_FILE ),
			[],
			WC_STRIPE_VERSION
		);
	}

	/**
	 * Render the dashboard page.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'woocommerce-gateway-stripe' ) );
		}

		$template = WC_STRIPE_PLUGIN_PATH . '/templates/admin/agentic-commerce-dashboard.php';

		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Handle the manual sync form submission.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function handle_manual_sync(): void {
		check_admin_referer( self::MANUAL_SYNC_NONCE );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'woocommerce-gateway-stripe' ) );
		}

		$redirect_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Integration' ) ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'sync_triggered' => '1',
						'sync_error'     => '1',
					],
					$redirect_url
				)
			);
			exit;
		}

		$integration = new WC_Stripe_Agentic_Commerce_Integration();
		$integration->sync_feed();

		wp_safe_redirect( add_query_arg( 'sync_triggered', '1', $redirect_url ) );
		exit;
	}

	/**
	 * Display an admin notice after manual sync is triggered.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function render_sync_triggered_notice(): void {
		if ( ! isset( $_GET['sync_triggered'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'woocommerce_page_' . self::PAGE_SLUG !== $screen->id ) {
			return;
		}

		if ( ! empty( $_GET['sync_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html__( 'Sync failed. Check the WooCommerce logs for details.', 'woocommerce-gateway-stripe' )
				. '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Sync triggered successfully.', 'woocommerce-gateway-stripe' )
				. '</p></div>';
		}
	}

	/**
	 * Render the sync status card.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function render_sync_status(): void {
		$last_sync = get_option( self::LAST_SYNC_OPTION, [] );

		echo '<div class="stripe-agentic-status">';

		if ( empty( $last_sync ) ) {
			echo '<p>' . esc_html__( 'No syncs yet. Feed will sync automatically every 15 minutes.', 'woocommerce-gateway-stripe' ) . '</p>';
			$this->render_action_buttons();
			echo '</div>';
			return;
		}

		$this->render_status_badge( $last_sync['status'] ?? 'unknown' );
		$this->render_sync_details( $last_sync );
		$this->render_errors( $last_sync );
		$this->render_action_buttons();

		echo '</div>';
	}

	/**
	 * Render a status badge for the given sync status string.
	 *
	 * @since 10.5.0
	 * @param string $status Sync status value.
	 * @return void
	 */
	public function render_status_badge( string $status ): void {
		$badges = [
			'succeeded'             => '<span class="wc-stripe-agentic-badge success">&#10003; ' . esc_html__( 'Success', 'woocommerce-gateway-stripe' ) . '</span>',
			'pending'               => '<span class="wc-stripe-agentic-badge info">&#9203; ' . esc_html__( 'Processing', 'woocommerce-gateway-stripe' ) . '</span>',
			'failed'                => '<span class="wc-stripe-agentic-badge error">&#10007; ' . esc_html__( 'Failed', 'woocommerce-gateway-stripe' ) . '</span>',
			'succeeded_with_errors' => '<span class="wc-stripe-agentic-badge warning">&#9888; ' . esc_html__( 'Partial Success', 'woocommerce-gateway-stripe' ) . '</span>',
		];

		echo $badges[ $status ] ?? '<span class="wc-stripe-agentic-badge">' . esc_html__( 'Unknown', 'woocommerce-gateway-stripe' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render the sync details rows (timestamp, products, IDs).
	 *
	 * @since 10.5.0
	 * @param array $last_sync Last sync data from option.
	 * @return void
	 */
	private function render_sync_details( array $last_sync ): void {
		echo '<table class="wc-stripe-agentic-details">';

		if ( ! empty( $last_sync['timestamp'] ) ) {
			$timestamp = (int) $last_sync['timestamp'];
			echo '<tr><th>' . esc_html__( 'Last Sync', 'woocommerce-gateway-stripe' ) . '</th>'
				. '<td>' . esc_html( human_time_diff( $timestamp ) . ' ' . __( 'ago', 'woocommerce-gateway-stripe' ) )
				. ' <small>(' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ) . ')</small>'
				. '</td></tr>';
		}

		if ( isset( $last_sync['products'] ) ) {
			echo '<tr><th>' . esc_html__( 'Products Synced', 'woocommerce-gateway-stripe' ) . '</th>'
				. '<td>' . esc_html( number_format_i18n( (int) $last_sync['products'] ) ) . '</td></tr>';
		}

		if ( ! empty( $last_sync['import_set_id'] ) ) {
			echo '<tr><th>' . esc_html__( 'ImportSet ID', 'woocommerce-gateway-stripe' ) . '</th>'
				. '<td><code>' . esc_html( $last_sync['import_set_id'] ) . '</code></td></tr>';
		}

		if ( ! empty( $last_sync['file_id'] ) ) {
			echo '<tr><th>' . esc_html__( 'File ID', 'woocommerce-gateway-stripe' ) . '</th>'
				. '<td><code>' . esc_html( $last_sync['file_id'] ) . '</code></td></tr>';
		}

		echo '</table>';

		$this->render_next_sync();
	}

	/**
	 * Display error information if the last sync had errors.
	 *
	 * @since 10.5.0
	 * @param array $last_sync Last sync data from option.
	 * @return void
	 */
	private function render_errors( array $last_sync ): void {
		if ( empty( $last_sync['error'] ) ) {
			return;
		}

		echo '<div class="notice notice-error inline">';
		echo '<p><strong>' . esc_html__( 'Last Sync Error:', 'woocommerce-gateway-stripe' ) . '</strong></p>';
		echo '<p>' . esc_html( $last_sync['error'] ) . '</p>';

		if ( ! empty( $last_sync['import_set_id'] ) ) {
			echo '<p><small>' . esc_html__( 'ImportSet ID:', 'woocommerce-gateway-stripe' ) . ' '
				. esc_html( $last_sync['import_set_id'] ) . '</small></p>';
			echo '<p><a href="https://dashboard.stripe.com/data-management/import-sets" target="_blank" rel="noopener noreferrer">'
				. esc_html__( 'View in Stripe Dashboard', 'woocommerce-gateway-stripe' )
				. ' &#8594;</a></p>';
		}

		echo '</div>';
	}

	/**
	 * Render the action buttons (Sync Now).
	 *
	 * @since 10.5.0
	 * @return void
	 */
	private function render_action_buttons(): void {
		echo '<div class="wc-stripe-agentic-actions">';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="wc-stripe-agentic-sync-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::MANUAL_SYNC_ACTION ) . '">';
		wp_nonce_field( self::MANUAL_SYNC_NONCE );

		echo '<button type="submit" class="button button-primary" id="wc-stripe-agentic-sync-btn">'
			. esc_html__( 'Sync Now', 'woocommerce-gateway-stripe' )
			. '</button>';

		echo '</form>';

		echo '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '" class="button">'
			. esc_html__( 'View Logs', 'woocommerce-gateway-stripe' )
			. '</a>';

		echo '</div>';

		echo '<script>
			document.getElementById("wc-stripe-agentic-sync-form").addEventListener("submit", function() {
				var btn = document.getElementById("wc-stripe-agentic-sync-btn");
				btn.disabled = true;
				btn.textContent = "' . esc_js( __( 'Syncing…', 'woocommerce-gateway-stripe' ) ) . '";
			});
		</script>';
	}

	/**
	 * Render the next scheduled sync time.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function render_next_sync(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		$next_sync = as_next_scheduled_action( WC_Stripe_Agentic_Commerce_Integration::SCHEDULED_ACTION );

		if ( $next_sync ) {
			$seconds_until = $next_sync - time();
			if ( $seconds_until > 0 ) {
				$minutes = (int) ceil( $seconds_until / 60 );
				echo '<p class="description">'
					/* translators: %d: number of minutes */
					. esc_html( sprintf( _n( 'Next automatic sync: in %d minute.', 'Next automatic sync: in %d minutes.', $minutes, 'woocommerce-gateway-stripe' ), $minutes ) )
					. '</p>';
			} else {
				echo '<p class="description">' . esc_html__( 'Next automatic sync: imminent.', 'woocommerce-gateway-stripe' ) . '</p>';
			}
		} else {
			echo '<p class="description">' . esc_html__( 'No automatic sync scheduled.', 'woocommerce-gateway-stripe' ) . '</p>';
		}
	}

	/**
	 * Render the sync history table.
	 *
	 * @since 10.5.0
	 * @return void
	 */
	public function render_sync_history(): void {
		$history = get_option( self::SYNC_HISTORY_OPTION, [] );

		if ( empty( $history ) ) {
			echo '<p>' . esc_html__( 'No sync history available.', 'woocommerce-gateway-stripe' ) . '</p>';
			return;
		}

		// Show up to the 20 most recent syncs, newest first.
		$recent = array_reverse( array_slice( $history, -20 ) );

		echo '<table class="widefat striped wc-stripe-agentic-history-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Timestamp', 'woocommerce-gateway-stripe' ) . '</th>';
		echo '<th>' . esc_html__( 'Products', 'woocommerce-gateway-stripe' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'woocommerce-gateway-stripe' ) . '</th>';
		echo '<th>' . esc_html__( 'Import ID', 'woocommerce-gateway-stripe' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $recent as $entry ) {
			$timestamp     = isset( $entry['timestamp'] ) ? (int) $entry['timestamp'] : 0;
			$products      = isset( $entry['products'] ) ? (int) $entry['products'] : 0;
			$status        = $entry['status'] ?? 'unknown';
			$import_set_id = $entry['import_set_id'] ?? '';

			echo '<tr>';
			echo '<td>' . esc_html( $timestamp ? wp_date( 'Y-m-d H:i', $timestamp ) : '—' ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $products ) ) . '</td>';
			echo '<td>';
			$this->render_status_badge( $status );
			if ( ! empty( $entry['error'] ) ) {
				echo ' <span title="' . esc_attr( $entry['error'] ) . '">&#9432;</span>';
			}
			echo '</td>';
			echo '<td><code>' . ( ! empty( $import_set_id ) ? esc_html( $import_set_id ) : '—' ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
