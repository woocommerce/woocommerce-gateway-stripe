<?php

/**
 * WC_Stripe_Finance_UI_Controller_Test class
 *
 * @package WooCommerce_Stripe/Tests/WP_UnitTestCase
 */
class WC_Stripe_Finance_UI_Controller_Test extends WP_UnitTestCase {

	/**
	 * Test suite tear down.
	 *
	 * Ensure that scripts/styles enqueued during one test do not leak into the next,
	 * and that the menu registered here does not persist into unrelated tests.
	 *
	 * @inheritDoc
	 */
	public function tearDown(): void {
		wp_dequeue_script( 'wc-stripe-finance' );
		wp_dequeue_style( 'wc-stripe-finance' );
		wp_deregister_script( 'wc-stripe-finance' );
		wp_deregister_style( 'wc-stripe-finance' );

		global $menu, $submenu, $admin_page_hooks, $_registered_pages;
		$menu              = [];
		$submenu           = [];
		$admin_page_hooks  = [];
		$_registered_pages = [];

		parent::tearDown();
	}

	/**
	 * Registers the menu as an administrator and returns the controller.
	 *
	 * @return WC_Stripe_Finance_UI_Controller
	 */
	private function register_menu_as_admin(): WC_Stripe_Finance_UI_Controller {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$controller = new WC_Stripe_Finance_UI_Controller();
		$controller->register_menu();

		return $controller;
	}

	/**
	 * The top-level menu must be registered under the Stripe-specific slug so it
	 * cannot collide with another plugin's Finance menu.
	 */
	public function test_register_menu_adds_top_level_finance_menu(): void {
		$this->register_menu_as_admin();

		global $menu;

		$slugs = wp_list_pluck( $menu, 2 );

		$expected_menu_slug = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Finance_UI_Controller::class, 'FINANCE_MENU_SLUG', 'string' );
		$this->assertContains( $expected_menu_slug, $slugs );

		$finance_menu = null;
		foreach ( $menu as $item ) {
			if ( ( $item[2] ?? null ) === $expected_menu_slug ) {
				$finance_menu = $item;
				break;
			}
		}

		$this->assertNotNull( $finance_menu );
		$this->assertSame( 'Finance', $finance_menu[0] );

		$expected_capability = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Finance_UI_Controller::class, 'CAPABILITY', 'string' );
		$this->assertSame( $expected_capability, $finance_menu[1] );
	}

	/**
	 * Reusing the parent slug for the first submenu is what makes "Payouts" the
	 * default sub-item instead of a duplicated "Finance" entry.
	 */
	public function test_register_menu_makes_stripe_the_default_submenu_item(): void {
		$this->register_menu_as_admin();

		global $submenu;

		$expected_menu_slug = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Finance_UI_Controller::class, 'PAYOUTS_MENU_SLUG', 'string' );

		$this->assertArrayHasKey( $expected_menu_slug, $submenu );
		$this->assertSame( 'Payouts', $submenu[ $expected_menu_slug ][0][0] );
		$this->assertSame( $expected_menu_slug, $submenu[ $expected_menu_slug ][0][2] );
	}

	/**
	 * WordPress always records the menu and gates it at render time on the
	 * capability stored with the entry, so both the menu and its submenu must
	 * carry manage_woocommerce for the page to be inaccessible to other roles.
	 */
	public function test_register_menu_gates_both_entries_on_the_capability(): void {
		$this->register_menu_as_admin();

		global $menu, $submenu;

		$expected_menu_slug = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Finance_UI_Controller::class, 'FINANCE_MENU_SLUG', 'string' );

		$capabilities = [];
		foreach ( $menu as $item ) {
			if ( $expected_menu_slug === $item[2] ) {
				$capabilities[] = $item[1];
			}
		}
		$capabilities[] = $submenu[ $expected_menu_slug ][0][1];

		$expected_capability = WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Finance_UI_Controller::class, 'CAPABILITY', 'string' );
		$this->assertNotEmpty( $capabilities );
		foreach ( $capabilities as $capability ) {
			$this->assertSame( $expected_capability, $capability );
		}

		$subscriber = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$this->assertFalse( current_user_can( $expected_capability ) );
	}

	public function test_render_page_outputs_the_mount_container(): void {
		$controller = new WC_Stripe_Finance_UI_Controller();

		ob_start();
		$controller->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="wc-stripe-finance-container"', $output );
	}

	/**
	 * Assets must load only on this controller's own screen.
	 *
	 * @dataProvider provide_enqueue_scripts_hook_suffixes
	 *
	 * @param string|null $hook_suffix    The admin page hook suffix passed by WordPress.
	 * @param bool        $should_enqueue Whether the assets are expected to be enqueued.
	 */
	public function test_enqueue_scripts( $hook_suffix, bool $should_enqueue ): void {
		$controller = $this->register_menu_as_admin();

		// The registered hook suffix is only known after register_menu() runs.
		if ( '__own_screen__' === $hook_suffix ) {
			$hook_suffix = get_plugin_page_hookname(
				WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Finance_UI_Controller::class, 'FINANCE_MENU_SLUG', 'string' ),
				''
			);
		}

		$controller->enqueue_scripts( $hook_suffix );

		$this->assertSame( $should_enqueue, wp_script_is( 'wc-stripe-finance', 'enqueued' ) );
		$this->assertSame( $should_enqueue, wp_style_is( 'wc-stripe-finance', 'enqueued' ) );
	}

	/**
	 * Data provider for test_enqueue_scripts.
	 *
	 * @return array
	 */
	public function provide_enqueue_scripts_hook_suffixes(): array {
		return [
			'own screen'           => [ '__own_screen__', true ],
			'plugins page'         => [ 'plugins.php', false ],
			'dashboard'            => [ 'index.php', false ],
			'woocommerce settings' => [ 'woocommerce_page_wc-settings', false ],
			'null hook suffix'     => [ null, false ],
			'empty hook suffix'    => [ '', false ],
		];
	}

	/**
	 * The app derives per-currency minor units from these lists, so they must come
	 * through as the PHP constants rather than being re-derived in JS.
	 */
	public function test_enqueue_scripts_localizes_currency_exponent_lists(): void {
		$controller = $this->register_menu_as_admin();

		$controller->enqueue_scripts(
			get_plugin_page_hookname( WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Finance_UI_Controller::class, 'FINANCE_MENU_SLUG', 'string' ), '' )
		);

		$data = wp_scripts()->get_data( 'wc-stripe-finance', 'data' );

		$this->assertStringContainsString( 'noDecimalCurrencies', (string) $data );
		$this->assertStringContainsString( 'threeDecimalCurrencies', (string) $data );

		foreach ( WC_Stripe_Currency_Code::NO_DECIMAL_CURRENCY_CODES as $code ) {
			$this->assertStringContainsString( $code, (string) $data );
		}
	}

	/**
	 * init() is the only place the controller wires itself into WordPress, so the
	 * menu and asset hooks must both be attached there rather than on construction.
	 */
	public function test_init_registers_admin_hooks(): void {
		$controller = new WC_Stripe_Finance_UI_Controller();

		$this->assertFalse( has_action( 'admin_menu', [ $controller, 'register_menu' ] ) );
		$this->assertFalse( has_action( 'admin_enqueue_scripts', [ $controller, 'enqueue_scripts' ] ) );

		$controller->init();

		$this->assertNotFalse( has_action( 'admin_menu', [ $controller, 'register_menu' ] ) );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', [ $controller, 'enqueue_scripts' ] ) );

		remove_action( 'admin_menu', [ $controller, 'register_menu' ] );
		remove_action( 'admin_enqueue_scripts', [ $controller, 'enqueue_scripts' ] );
	}
}
