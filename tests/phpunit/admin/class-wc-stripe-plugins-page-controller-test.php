<?php

/**
 * WC_Stripe_Plugins_Page_Controller_Test class
 *
 * @package WooCommerce_Stripe/Tests/WP_UnitTestCase
 */
class WC_Stripe_Plugins_Page_Controller_Test extends WP_UnitTestCase {

	/**
	 * Test suite tear down.
	 *
	 * Ensure that scripts/styles enqueued during one test do not leak into the next.
	 *
	 * @inheritDoc
	 */
	public function tearDown(): void {
		wp_dequeue_script( 'wc-stripe-plugins-page' );
		wp_dequeue_style( 'wc-stripe-plugins-page' );
		wp_deregister_script( 'wc-stripe-plugins-page' );
		wp_deregister_style( 'wc-stripe-plugins-page' );

		wp_dequeue_script( 'thickbox' );
		wp_dequeue_style( 'thickbox' );

		delete_site_transient( 'update_plugins' );

		parent::tearDown();
	}

	protected function get_mock_controller(): WC_Stripe_Plugins_Page_Controller {
		$account_mock = $this->getMockBuilder( WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->getMock();

		return new WC_Stripe_Plugins_Page_Controller( $account_mock );
	}

	/**
	 * Tests that `enqueue_scripts` only registers/enqueues the plugins page assets
	 * on the plugins.php admin screen and is a no-op elsewhere.
	 *
	 * @dataProvider provide_enqueue_scripts_hook_suffixes
	 *
	 * @param string|null $hook_suffix    The admin page hook suffix passed by WordPress.
	 * @param bool        $should_enqueue Whether the assets should be registered/enqueued.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts( $hook_suffix, bool $should_enqueue ): void {
		$controller = $this->get_mock_controller();

		$controller->enqueue_scripts( $hook_suffix );

		$this->assertSame( $should_enqueue, wp_script_is( 'wc-stripe-plugins-page', 'registered' ) );
		$this->assertSame( $should_enqueue, wp_script_is( 'wc-stripe-plugins-page', 'enqueued' ) );
		$this->assertSame( $should_enqueue, wp_style_is( 'wc-stripe-plugins-page', 'registered' ) );
		$this->assertSame( $should_enqueue, wp_style_is( 'wc-stripe-plugins-page', 'enqueued' ) );
	}

	/**
	 * Data provider for `test_enqueue_scripts`.
	 *
	 * @return array<string, array{0: string|null, 1: bool}>
	 */
	public function provide_enqueue_scripts_hook_suffixes(): array {
		return [
			'null hook suffix is a no-op'                  => [ null, false ],
			'unrelated admin page does not enqueue assets' => [ 'admin.php', false ],
			'plugins.php registers and enqueues assets'    => [ 'plugins.php', true ],
		];
	}

	/**
	 * Tests that thickbox is enqueued on plugins.php so the plugin information
	 * modal opened by the "Release notes" link can render.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts_loads_thickbox_on_plugins_php(): void {
		$controller = $this->get_mock_controller();

		$controller->enqueue_scripts( 'plugins.php' );

		$this->assertTrue( wp_script_is( 'thickbox', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'thickbox', 'enqueued' ) );
	}

	/**
	 * Tests that the "Release Notes" plugin row meta link is appended only for
	 * the WooCommerce Stripe plugin file, and points at the changelog tab of
	 * the WordPress plugin information modal.
	 *
	 * @return void
	 */
	public function test_add_release_notes_link_appends_link_for_stripe_plugin_only(): void {
		$controller = $this->get_mock_controller();

		$other_plugin_links = $controller->add_release_notes_link( [ 'docs' => '<a>Docs</a>' ], 'some-other/some-other.php' );
		$this->assertSame( [ 'docs' => '<a>Docs</a>' ], $other_plugin_links );

		$stripe_links = $controller->add_release_notes_link( [], plugin_basename( WC_STRIPE_MAIN_FILE ) );
		$this->assertArrayHasKey( 'wc_stripe_release_notes', $stripe_links );

		$link_html = $stripe_links['wc_stripe_release_notes'];
		$this->assertStringContainsString( '>Release notes<', $link_html );
		$this->assertStringContainsString( 'thickbox', $link_html );
		$this->assertStringContainsString( 'open-plugin-details-modal', $link_html );
		$this->assertStringContainsString( 'tab=plugin-information', $link_html );
		$this->assertStringContainsString( 'plugin=woocommerce-gateway-stripe', $link_html );
		$this->assertStringContainsString( 'section=changelog', $link_html );
		$this->assertStringContainsString( 'TB_iframe=true', $link_html );
	}

	/**
	 * Tests that the "Release notes" link is omitted when WordPress has staged
	 * an available update for the Stripe plugin, so it does not duplicate the
	 * core "View details" link and does not surface notes for a not-yet-installed version.
	 *
	 * @return void
	 */
	public function test_add_release_notes_link_skipped_when_update_is_pending(): void {
		$basename = plugin_basename( WC_STRIPE_MAIN_FILE );

		set_site_transient(
			'update_plugins',
			(object) [
				'response' => [
					$basename => (object) [ 'new_version' => '999.0.0' ],
				],
			]
		);

		$controller = $this->get_mock_controller();
		$result     = $controller->add_release_notes_link( [], $basename );

		$this->assertArrayNotHasKey( 'wc_stripe_release_notes', $result );
	}

	/**
	 * Tests that `add_release_notes_link()` honors its `array` return type when
	 * another `plugin_row_meta` filter callback hands it a non-array value, so
	 * the method does not fatal under a misbehaving filter chain.
	 *
	 * @dataProvider provide_non_array_links
	 *
	 * @param mixed $links Value a misbehaving upstream filter could return.
	 *
	 * @return void
	 */
	public function test_add_release_notes_link_normalizes_non_array_links( $links ): void {
		$controller = $this->get_mock_controller();

		$other_plugin = $controller->add_release_notes_link( $links, 'some-other/some-other.php' );
		$this->assertIsArray( $other_plugin );
		$this->assertArrayNotHasKey( 'wc_stripe_release_notes', $other_plugin );

		$stripe = $controller->add_release_notes_link( $links, plugin_basename( WC_STRIPE_MAIN_FILE ) );
		$this->assertIsArray( $stripe );
		$this->assertArrayHasKey( 'wc_stripe_release_notes', $stripe );
	}

	/**
	 * Data provider for `test_add_release_notes_link_normalizes_non_array_links`.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_non_array_links(): array {
		return [
			'null'         => [ null ],
			'false'        => [ false ],
			'empty string' => [ '' ],
			'string'       => [ 'unexpected' ],
			'integer'      => [ 0 ],
		];
	}

	/**
	 * Tests that the changelog link parameters are localized for the JS
	 * post-update enhancement. Guards the contract between the controller
	 * and `initAppendChangelogLink` so renaming or dropping a key surfaces here.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts_localizes_changelog_link_params(): void {
		$controller = $this->get_mock_controller();

		$controller->enqueue_scripts( 'plugins.php' );

		$inline_data = wp_scripts()->get_data( 'wc-stripe-plugins-page', 'data' );
		$this->assertIsString( $inline_data );

		$this->assertStringContainsString( '"plugin_slug":"woocommerce-gateway-stripe"', $inline_data );
		$this->assertStringContainsString( 'tab=plugin-information', $inline_data );
		$this->assertStringContainsString( 'plugin=woocommerce-gateway-stripe', $inline_data );
		$this->assertStringContainsString( 'section=changelog', $inline_data );
		$this->assertStringContainsString( 'TB_iframe=true', $inline_data );
	}
}
