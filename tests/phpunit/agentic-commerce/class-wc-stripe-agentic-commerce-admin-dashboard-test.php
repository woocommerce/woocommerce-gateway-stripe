<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Admin_Dashboard
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Commerce_Admin_Dashboard_Test
 *
 * Tests the admin dashboard class for Agentic Commerce.
 */
class WC_Stripe_Agentic_Commerce_Admin_Dashboard_Test extends WP_UnitTestCase {

	/**
	 * Dashboard instance under test.
	 *
	 * @var WC_Stripe_Agentic_Commerce_Admin_Dashboard
	 */
	private WC_Stripe_Agentic_Commerce_Admin_Dashboard $dashboard;

	/**
	 * Set up test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Admin_Dashboard' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Admin_Dashboard class not loaded' );
		}

		$this->dashboard = new WC_Stripe_Agentic_Commerce_Admin_Dashboard();
	}

	/**
	 * Clean up options after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( WC_Stripe_Agentic_Commerce_Admin_Dashboard::LAST_SYNC_OPTION );
		delete_option( WC_Stripe_Agentic_Commerce_Admin_Dashboard::SYNC_HISTORY_OPTION );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// render_status_badge
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider badge_status_provider
	 */
	public function test_render_status_badge_outputs_expected_class( string $status, string $expected_class ): void {
		ob_start();
		$this->dashboard->render_status_badge( $status );
		$output = ob_get_clean();

		$this->assertStringContainsString( $expected_class, $output );
	}

	/**
	 * Data provider for badge status tests.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function badge_status_provider(): array {
		return [
			'succeeded'             => [ 'succeeded', 'success' ],
			'pending'               => [ 'pending', 'info' ],
			'failed'                => [ 'failed', 'error' ],
			'succeeded_with_errors' => [ 'succeeded_with_errors', 'warning' ],
		];
	}

	/**
	 * Unknown statuses should still render a badge element.
	 *
	 * @return void
	 */
	public function test_render_status_badge_unknown_status_renders_generic_badge(): void {
		ob_start();
		$this->dashboard->render_status_badge( 'some_unknown_status' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wc-stripe-agentic-badge', $output );
	}

	// -------------------------------------------------------------------------
	// render_sync_status — no history
	// -------------------------------------------------------------------------

	/**
	 * With no last sync option, the status card should show a "no syncs yet" message.
	 *
	 * @return void
	 */
	public function test_render_sync_status_no_history_shows_placeholder(): void {
		delete_option( WC_Stripe_Agentic_Commerce_Admin_Dashboard::LAST_SYNC_OPTION );

		ob_start();
		$this->dashboard->render_sync_status();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No syncs yet', $output );
	}

	// -------------------------------------------------------------------------
	// render_sync_status — with history
	// -------------------------------------------------------------------------

	/**
	 * With a last sync result saved, the status card renders details.
	 *
	 * @return void
	 */
	public function test_render_sync_status_with_history_shows_details(): void {
		update_option(
			WC_Stripe_Agentic_Commerce_Admin_Dashboard::LAST_SYNC_OPTION,
			[
				'timestamp'     => time() - 300,
				'products'      => 42,
				'status'        => 'succeeded',
				'file_id'       => 'file_abc123',
				'import_set_id' => 'impset_xyz',
				'error'         => '',
			]
		);

		ob_start();
		$this->dashboard->render_sync_status();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'success', $output );
		$this->assertStringContainsString( 'impset_xyz', $output );
		$this->assertStringContainsString( '42', $output );
	}

	/**
	 * An error in the last sync should render an error notice with the message.
	 *
	 * @return void
	 */
	public function test_render_sync_status_with_error_shows_error_notice(): void {
		update_option(
			WC_Stripe_Agentic_Commerce_Admin_Dashboard::LAST_SYNC_OPTION,
			[
				'timestamp'     => time() - 60,
				'products'      => 0,
				'status'        => 'failed',
				'file_id'       => '',
				'import_set_id' => '',
				'error'         => 'Stripe API key not configured',
			]
		);

		ob_start();
		$this->dashboard->render_sync_status();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Stripe API key not configured', $output );
		$this->assertStringContainsString( 'notice-error', $output );
	}

	// -------------------------------------------------------------------------
	// render_sync_history
	// -------------------------------------------------------------------------

	/**
	 * With an empty history option, the table should show a placeholder message.
	 *
	 * @return void
	 */
	public function test_render_sync_history_empty_shows_placeholder(): void {
		delete_option( WC_Stripe_Agentic_Commerce_Admin_Dashboard::SYNC_HISTORY_OPTION );

		ob_start();
		$this->dashboard->render_sync_history();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No sync history available', $output );
	}

	/**
	 * History entries are rendered in the table.
	 *
	 * @return void
	 */
	public function test_render_sync_history_shows_entries(): void {
		update_option(
			WC_Stripe_Agentic_Commerce_Admin_Dashboard::SYNC_HISTORY_OPTION,
			[
				[
					'timestamp'     => time() - 900,
					'products'      => 100,
					'status'        => 'succeeded',
					'file_id'       => 'file_1',
					'import_set_id' => 'impset_1',
					'error'         => '',
				],
				[
					'timestamp'     => time() - 300,
					'products'      => 0,
					'status'        => 'failed',
					'file_id'       => '',
					'import_set_id' => '',
					'error'         => 'Connection error',
				],
			]
		);

		ob_start();
		$this->dashboard->render_sync_history();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'impset_1', $output );
		$this->assertStringContainsString( 'widefat', $output );
		// Failed row should contain an error indicator.
		$this->assertStringContainsString( 'Connection error', $output );
	}

	/**
	 * Only the 20 most recent entries are shown.
	 *
	 * @return void
	 */
	public function test_render_sync_history_limits_to_20_entries(): void {
		$history = [];
		for ( $i = 1; $i <= 30; $i++ ) {
			$history[] = [
				'timestamp'     => time() - ( $i * 60 ),
				'products'      => $i * 10,
				'status'        => 'succeeded',
				'file_id'       => "file_{$i}",
				'import_set_id' => "impset_{$i}",
				'error'         => '',
			];
		}

		update_option( WC_Stripe_Agentic_Commerce_Admin_Dashboard::SYNC_HISTORY_OPTION, $history );

		ob_start();
		$this->dashboard->render_sync_history();
		$output = ob_get_clean();

		// Entry 1 (oldest) should not appear; entry 11+ (most recent 20) should appear.
		// Entry 1 (oldest, beyond the 20-entry window) should not appear.
		$this->assertStringNotContainsString( 'impset_1"', $output );
		// Entry 11 (the 20th most recent) and entry 30 (newest) should appear.
		$this->assertStringContainsString( 'impset_11', $output );
		$this->assertStringContainsString( 'impset_30', $output );
	}

	// -------------------------------------------------------------------------
	// render_next_sync
	// -------------------------------------------------------------------------

	/**
	 * When Action Scheduler is not available, render_next_sync outputs nothing.
	 *
	 * @return void
	 */
	public function test_render_next_sync_without_action_scheduler_outputs_nothing(): void {
		// as_next_scheduled_action is not available in unit test bootstrap.
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler is available; skip this test.' );
		}

		ob_start();
		$this->dashboard->render_next_sync();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}
}
