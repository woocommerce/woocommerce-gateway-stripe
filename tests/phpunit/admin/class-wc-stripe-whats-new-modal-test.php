<?php

/**
 * Tests for the WC_Stripe_Whats_New_Modal class.
 */
class WC_Stripe_Whats_New_Modal_Test extends WP_UnitTestCase {
	/**
	 * @var WC_Stripe_Whats_New_Modal
	 */
	private WC_Stripe_Whats_New_Modal $modal;

	public function set_up() {
		parent::set_up();
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-whats-new-modal.php';
		$this->modal = new WC_Stripe_Whats_New_Modal();
	}

	public function tear_down() {
		delete_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT );
		$current_user = get_current_user_id();
		if ( $current_user ) {
			delete_user_meta( $current_user, WC_Stripe_Whats_New_Modal::DISMISSED_USER_META );
		}
		parent::tear_down();
	}

	/**
	 * Sets up the AJAX context so that wp_send_json_* paths throw
	 * WPDieException instead of calling die() and killing the worker.
	 */
	private function set_up_ajax_context(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function ( $message ) {
					throw new WPDieException( is_string( $message ) ? $message : '' );
				};
			}
		);
	}

	/**
	 * Removes the AJAX context filters set up by set_up_ajax_context().
	 */
	private function tear_down_ajax_context(): void {
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
	}

	/**
	 * Builds a fixture-backed subject that exercises the production parsing
	 * logic against a custom readme path via the protected seam.
	 */
	private function make_modal_with_readme( string $readme_path ): WC_Stripe_Whats_New_Modal {
		return new class( $readme_path ) extends WC_Stripe_Whats_New_Modal {
			private string $path;

			public function __construct( string $path ) {
				$this->path = $path;
			}

			protected function get_readme_path(): string {
				return $this->path;
			}
		};
	}

	public function test_parse_changelog_returns_entries_for_current_version(): void {
		$entries = $this->modal->parse_changelog_for_version( WC_STRIPE_VERSION );

		$this->assertIsArray( $entries );

		// The bundled readme.txt may or may not include the constant version
		// (the changelog is updated per release). When it does, every entry
		// must carry text; a tag is optional.
		foreach ( $entries as $entry ) {
			$this->assertArrayHasKey( 'tag', $entry );
			$this->assertArrayHasKey( 'text', $entry );
			$this->assertNotSame( '', $entry['text'] );
		}
	}

	public function test_parse_changelog_returns_empty_when_version_missing(): void {
		$entries = $this->modal->parse_changelog_for_version( '0.0.1' );
		$this->assertSame( [], $entries );
	}

	public function test_parse_changelog_extracts_tagged_entries_from_fixture(): void {
		$tmp_dir    = sys_get_temp_dir() . '/wc-stripe-whats-new-' . wp_generate_password( 6, false );
		$tmp_readme = $tmp_dir . '/readme.txt';
		mkdir( $tmp_dir );

		try {
			file_put_contents(
				$tmp_readme,
				"== Changelog ==\n\n= 99.99.99 - 2099-01-01 =\n* Fix - First fix entry\n* Add - Brand new thing\n* Untagged plain entry\n\n= 99.99.98 - 2098-01-01 =\n* Fix - Older fix\n\n[See changelog](https://example.com)\n"
			);

			$entries = $this->make_modal_with_readme( $tmp_readme )
				->parse_changelog_for_version( '99.99.99' );

			$this->assertCount( 3, $entries );
			$this->assertSame( 'Fix', $entries[0]['tag'] );
			$this->assertSame( 'First fix entry', $entries[0]['text'] );
			$this->assertSame( 'Add', $entries[1]['tag'] );
			$this->assertSame( 'Brand new thing', $entries[1]['text'] );
			$this->assertSame( '', $entries[2]['tag'] );
			$this->assertSame( 'Untagged plain entry', $entries[2]['text'] );
		} finally {
			if ( file_exists( $tmp_readme ) ) {
				unlink( $tmp_readme );
			}
			if ( is_dir( $tmp_dir ) ) {
				rmdir( $tmp_dir );
			}
		}
	}

	/**
	 * @dataProvider provide_flag_standalone_update_cases
	 */
	public function test_maybe_flag_standalone_update( array $hook_extra, $expected_transient ): void {
		$this->modal->maybe_flag_standalone_update( null, $hook_extra );

		$this->assertSame(
			$expected_transient,
			get_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT )
		);
	}

	public function provide_flag_standalone_update_cases(): array {
		$self = plugin_basename( WC_STRIPE_MAIN_FILE );

		return [
			'single plugin via plugins list'  => [
				[
					'action'  => 'update',
					'type'    => 'plugin',
					'plugins' => [ $self ],
				],
				'1',
			],
			'single plugin via plugin key'    => [
				[
					'action' => 'update',
					'type'   => 'plugin',
					'plugin' => $self,
				],
				'1',
			],
			'bulk update with another plugin' => [
				[
					'action'  => 'update',
					'type'    => 'plugin',
					'plugins' => [ $self, 'some-other/plugin.php' ],
				],
				false,
			],
			'different plugin only'           => [
				[
					'action'  => 'update',
					'type'    => 'plugin',
					'plugins' => [ 'some-other/plugin.php' ],
				],
				false,
			],
			'theme upgrade ignored'           => [
				[
					'action' => 'update',
					'type'   => 'theme',
					'themes' => [ 'twentytwentyfour' ],
				],
				false,
			],
			'install action ignored'          => [
				[
					'action' => 'install',
					'type'   => 'plugin',
					'plugin' => $self,
				],
				false,
			],
			'empty plugins array ignored'     => [
				[
					'action'  => 'update',
					'type'    => 'plugin',
					'plugins' => [],
				],
				false,
			],
			'missing action ignored'          => [
				[
					'type'   => 'plugin',
					'plugin' => $self,
				],
				false,
			],
		];
	}

	public function test_should_display_requires_pending_transient(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertFalse( $this->modal->should_display() );

		set_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT, '1', DAY_IN_SECONDS );
		$this->assertTrue( $this->modal->should_display() );
	}

	public function test_should_display_returns_false_when_user_dismissed_current_version(): void {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT, '1', DAY_IN_SECONDS );
		update_user_meta( $user_id, WC_Stripe_Whats_New_Modal::DISMISSED_USER_META, WC_STRIPE_VERSION );

		$this->assertFalse( $this->modal->should_display() );
	}

	public function test_should_display_returns_true_when_a_different_user_dismissed(): void {
		$dismissing_user = $this->factory->user->create( [ 'role' => 'administrator' ] );
		update_user_meta( $dismissing_user, WC_Stripe_Whats_New_Modal::DISMISSED_USER_META, WC_STRIPE_VERSION );

		$other_user = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $other_user );
		set_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT, '1', DAY_IN_SECONDS );

		$this->assertTrue( $this->modal->should_display() );
	}

	public function test_should_display_respects_disable_filter(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		set_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT, '1', DAY_IN_SECONDS );

		add_filter( 'wc_stripe_whats_new_modal_enabled', '__return_false' );
		try {
			$this->assertFalse( $this->modal->should_display() );
		} finally {
			remove_filter( 'wc_stripe_whats_new_modal_enabled', '__return_false' );
		}
	}

	public function test_should_display_requires_manage_woocommerce_capability(): void {
		set_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT, '1', DAY_IN_SECONDS );
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertFalse( $this->modal->should_display() );
	}

	public function test_handle_dismiss_records_user_meta_and_leaves_transient(): void {
		$this->set_up_ajax_context();

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		set_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT, '1', DAY_IN_SECONDS );

		$_POST['nonce']    = wp_create_nonce( WC_Stripe_Whats_New_Modal::DISMISS_AJAX_ACTION );
		$_POST['dwell_ms'] = '4200';
		$_POST['source']   = 'primary_button';

		try {
			ob_start();
			try {
				$this->modal->handle_dismiss();
				$this->fail( 'wp_send_json_success should terminate via WPDieException.' );
			} catch ( WPDieException $exception ) {
				unset( $exception );
			}
			ob_end_clean();
		} finally {
			unset( $_POST['nonce'], $_POST['dwell_ms'], $_POST['source'] );
			$this->tear_down_ajax_context();
		}

		$this->assertSame(
			WC_STRIPE_VERSION,
			get_user_meta( $user_id, WC_Stripe_Whats_New_Modal::DISMISSED_USER_META, true )
		);
		// Transient is intentionally left in place so other admins still see
		// the modal once after the update.
		$this->assertSame( '1', get_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT ) );
	}

	public function test_handle_dismiss_rejects_unauthorized_user(): void {
		$this->set_up_ajax_context();

		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		try {
			ob_start();
			try {
				$this->modal->handle_dismiss();
				$this->fail( 'wp_send_json_error should terminate via WPDieException.' );
			} catch ( WPDieException $exception ) {
				unset( $exception );
			}
			ob_end_clean();
		} finally {
			$this->tear_down_ajax_context();
		}

		$this->assertSame( '', get_user_meta( $user_id, WC_Stripe_Whats_New_Modal::DISMISSED_USER_META, true ) );
	}
}
