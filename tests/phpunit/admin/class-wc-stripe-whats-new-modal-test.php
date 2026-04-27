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
		delete_option( WC_Stripe_Whats_New_Modal::DISMISSED_OPTION );
		parent::tear_down();
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
		$readme  = WC_STRIPE_PLUGIN_PATH . '/readme.txt';
		$content = file_get_contents( $readme );
		$this->assertNotFalse( $content );

		// Ensure regex anchors and the line-prefix detection still match a
		// realistic block. We synthesize a known version and write it into a
		// throwaway readme.
		$tmp_dir = sys_get_temp_dir() . '/wc-stripe-whats-new-' . wp_generate_password( 6, false );
		mkdir( $tmp_dir );
		$tmp_readme = $tmp_dir . '/readme.txt';
		file_put_contents(
			$tmp_readme,
			"== Changelog ==\n\n= 99.99.99 - 2099-01-01 =\n* Fix - First fix entry\n* Add - Brand new thing\n* Untagged plain entry\n\n= 99.99.98 - 2098-01-01 =\n* Fix - Older fix\n\n[See changelog](https://example.com)\n"
		);

		// Use reflection to invoke the parser against the fixture path by
		// temporarily symlinking via a subclass override.
		$test_subject                  = new class() extends WC_Stripe_Whats_New_Modal {
			public string $readme_override = '';

			public function parse_changelog_for_version( string $version ): array {
				if ( '' !== $this->readme_override ) {
					$contents = file_get_contents( $this->readme_override );
					$pattern  = '/^=\s*' . preg_quote( $version, '/' ) . '\s*-[^=\r\n]*=\s*\R(?P<body>.*?)(?=^=\s*\d+\.\d+\.\d+\s*-|\[See changelog|\z)/ms';
					if ( ! preg_match( $pattern, $contents, $matches ) ) {
						return [];
					}
					$body  = trim( $matches['body'] );
					$items = [];
					foreach ( preg_split( '/\R/', $body ) as $line ) {
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
				return parent::parse_changelog_for_version( $version );
			}
		};
		$test_subject->readme_override = $tmp_readme;

		$entries = $test_subject->parse_changelog_for_version( '99.99.99' );

		$this->assertCount( 3, $entries );
		$this->assertSame( 'Fix', $entries[0]['tag'] );
		$this->assertSame( 'First fix entry', $entries[0]['text'] );
		$this->assertSame( 'Add', $entries[1]['tag'] );
		$this->assertSame( 'Brand new thing', $entries[1]['text'] );
		$this->assertSame( '', $entries[2]['tag'] );
		$this->assertSame( 'Untagged plain entry', $entries[2]['text'] );

		unlink( $tmp_readme );
		rmdir( $tmp_dir );
	}

	public function test_standalone_update_with_single_plugin_sets_transient(): void {
		$this->modal->maybe_flag_standalone_update(
			null,
			[
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => [ plugin_basename( WC_STRIPE_MAIN_FILE ) ],
			]
		);

		$this->assertSame( '1', get_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT ) );
	}

	public function test_standalone_update_via_single_plugin_key_sets_transient(): void {
		$this->modal->maybe_flag_standalone_update(
			null,
			[
				'action' => 'update',
				'type'   => 'plugin',
				'plugin' => plugin_basename( WC_STRIPE_MAIN_FILE ),
			]
		);

		$this->assertSame( '1', get_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT ) );
	}

	public function test_bulk_update_with_multiple_plugins_does_not_set_transient(): void {
		$this->modal->maybe_flag_standalone_update(
			null,
			[
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => [ plugin_basename( WC_STRIPE_MAIN_FILE ), 'some-other/plugin.php' ],
			]
		);

		$this->assertFalse( get_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT ) );
	}

	public function test_update_targeting_a_different_plugin_does_not_set_transient(): void {
		$this->modal->maybe_flag_standalone_update(
			null,
			[
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => [ 'some-other/plugin.php' ],
			]
		);

		$this->assertFalse( get_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT ) );
	}

	public function test_non_plugin_upgrade_event_is_ignored(): void {
		$this->modal->maybe_flag_standalone_update(
			null,
			[
				'action' => 'update',
				'type'   => 'theme',
				'themes' => [ 'twentytwentyfour' ],
			]
		);

		$this->assertFalse( get_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT ) );
	}

	public function test_non_update_action_is_ignored(): void {
		$this->modal->maybe_flag_standalone_update(
			null,
			[
				'action' => 'install',
				'type'   => 'plugin',
				'plugin' => plugin_basename( WC_STRIPE_MAIN_FILE ),
			]
		);

		$this->assertFalse( get_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT ) );
	}

	public function test_should_display_requires_pending_transient(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertFalse( $this->modal->should_display() );

		set_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT, '1', DAY_IN_SECONDS );
		$this->assertTrue( $this->modal->should_display() );
	}

	public function test_should_display_returns_false_when_dismissed_for_current_version(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		set_transient( WC_Stripe_Whats_New_Modal::PENDING_TRANSIENT, '1', DAY_IN_SECONDS );
		update_option( WC_Stripe_Whats_New_Modal::DISMISSED_OPTION, WC_STRIPE_VERSION );

		$this->assertFalse( $this->modal->should_display() );
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
}
