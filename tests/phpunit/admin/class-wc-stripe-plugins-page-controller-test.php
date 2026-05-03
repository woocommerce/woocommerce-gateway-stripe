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
}
