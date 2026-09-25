<?php

/**
 * WC_Stripe_Payments_UI_Controller_Test class
 *
 * @package WooCommerce_Stripe/Tests/WP_UnitTestCase
 */
class WC_Stripe_Payments_UI_Controller_Test extends WP_UnitTestCase {

	/**
	 * Test suite tear down.
	 *
	 * Ensure that the following items are cleaned up after each test:
	 * - Enqueued scripts and styles
	 * - Registered menus via the various related globals
	 */
	public function tearDown(): void {
		wp_dequeue_script( 'wc-stripe-admin-payments' );
		wp_dequeue_style( 'wc-stripe-admin-payments' );
		wp_deregister_script( 'wc-stripe-admin-payments' );
		wp_deregister_style( 'wc-stripe-admin-payments' );

		global $menu, $submenu, $admin_page_hooks, $_registered_pages, $_parent_pages, $_wp_submenu_nopriv;
		$menu               = [];
		$submenu            = [];
		$admin_page_hooks   = [];
		$_registered_pages  = [];
		$_parent_pages      = [];
		$_wp_submenu_nopriv = [];

		parent::tearDown();
	}

	/**
	 * Reads one of the controller's private slug/capability constants.
	 *
	 * @param string $const_name The constant name.
	 * @return string
	 */
	private function get_controller_const( string $const_name ): string {
		return WC_Stripe_Test_Helper::get_class_const_value( WC_Stripe_Payments_UI_Controller::class, $const_name, 'string' );
	}

	/**
	 * Registers top-level Payments menus as WooCommerce Core and WooPayments would.
	 *
	 * @param string[] $parent_menu_slugs The top-level menu slugs to register.
	 * @return void
	 */
	private function register_parent_menus( array $parent_menu_slugs ): void {
		foreach ( $parent_menu_slugs as $parent_menu_slug ) {
			add_menu_page( 'Payments', 'Payments', 'manage_woocommerce', $parent_menu_slug );
		}
	}

	/**
	 * Registers the WooCommerce Core Payments menu and then the Stripe submenu as an
	 * administrator, and returns the controller.
	 *
	 * @return WC_Stripe_Payments_UI_Controller
	 */
	private function register_menu_as_admin(): WC_Stripe_Payments_UI_Controller {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$this->register_parent_menus( [ $this->get_controller_const( 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG' ) ] );

		$controller = new WC_Stripe_Payments_UI_Controller();
		$controller->register_stripe_payments_menu();

		return $controller;
	}

	/**
	 * The hook suffix WordPress assigns to the Stripe page under the WooCommerce Core Payments menu.
	 *
	 * @return string
	 */
	private function get_own_screen_hook_suffix(): string {
		return get_plugin_page_hookname(
			$this->get_controller_const( 'PAYMENTS_MENU_SLUG' ),
			$this->get_controller_const( 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG' )
		);
	}

	/**
	 * Finds the Stripe entry among a parent menu's submenu items.
	 *
	 * @param string $parent_menu_slug The parent menu slug.
	 * @return array|null The submenu item, or null when it is not registered under that parent.
	 */
	private function find_stripe_submenu_item( string $parent_menu_slug ): ?array {
		global $submenu;

		$stripe_payments_menu_slug = $this->get_controller_const( 'PAYMENTS_MENU_SLUG' );

		foreach ( $submenu[ $parent_menu_slug ] ?? [] as $item ) {
			if ( $stripe_payments_menu_slug === $item[2] ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Test that we register the Stripe submenu under the active Payments menu,
	 * which may be added by WooCommerce Core or WooPayments.
	 *
	 * @dataProvider provide_active_payments_menus
	 *
	 * @param string[] $registered_parent_consts Constant names of the parent menus to register.
	 * @param string   $expected_parent_const    Constant name of the parent expected to host the Stripe page.
	 */
	public function test_register_menu_attaches_to_the_active_payments_menu( array $registered_parent_consts, string $expected_parent_const ): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$this->register_parent_menus( array_map( [ $this, 'get_controller_const' ], $registered_parent_consts ) );

		( new WC_Stripe_Payments_UI_Controller() )->register_stripe_payments_menu();

		$stripe_item = $this->find_stripe_submenu_item( $this->get_controller_const( $expected_parent_const ) );

		$this->assertNotNull( $stripe_item );
		$this->assertSame( 'Stripe', $stripe_item[0] );

		foreach ( array_diff( $registered_parent_consts, [ $expected_parent_const ] ) as $other_parent_const ) {
			$this->assertNull( $this->find_stripe_submenu_item( $this->get_controller_const( $other_parent_const ) ) );
		}
	}

	/**
	 * Data provider for test_register_menu_attaches_to_the_active_payments_menu.
	 *
	 * @return array
	 */
	public function provide_active_payments_menus(): array {
		return [
			'woocommerce core menu only' => [ [ 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG' ], 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG' ],
			'woopayments menu only'      => [ [ 'WOOPAYMENTS_PAYMENTS_MENU_SLUG' ], 'WOOPAYMENTS_PAYMENTS_MENU_SLUG' ],
			'both menus registered'      => [ [ 'WOOPAYMENTS_PAYMENTS_MENU_SLUG', 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG' ], 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG' ],
		];
	}

	/**
	 * Test that we correctly relabel the WooCommerce Core Payments -> Payments submenu item.
	 *
	 * @dataProvider provide_parent_menu_submenu_titles
	 *
	 * @param string   $parent_const    Constant name of the parent menu to register.
	 * @param string[] $expected_titles The expected submenu titles, in menu order.
	 */
	public function test_register_menu_relabels_only_the_core_payments_submenu_item( string $parent_const, array $expected_titles ): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$parent_menu_slug = $this->get_controller_const( $parent_const );
		$this->register_parent_menus( [ $parent_menu_slug ] );

		( new WC_Stripe_Payments_UI_Controller() )->register_stripe_payments_menu();

		global $submenu;
		$this->assertSame( $expected_titles, array_values( array_column( $submenu[ $parent_menu_slug ], 0 ) ) );
	}

	/**
	 * Data provider for {@see test_register_menu_relabels_only_the_core_payments_submenu_item()}.
	 *
	 * @return array
	 */
	public function provide_parent_menu_submenu_titles(): array {
		return [
			'woocommerce core menu' => [ 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG', [ 'Add a provider', 'Stripe' ] ],
			'woopayments menu'      => [ 'WOOPAYMENTS_PAYMENTS_MENU_SLUG', [ 'Payments', 'Stripe' ] ],
		];
	}

	/**
	 * The relabelled entry must still use the same link and capability as the original.
	 */
	public function test_relabelled_core_submenu_item_keeps_its_target_and_capability(): void {
		$this->register_menu_as_admin();

		global $submenu;
		$core_menu_slug = $this->get_controller_const( 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG' );

		$provider_items = array_values(
			array_filter(
				$submenu[ $core_menu_slug ],
				function ( $item ) use ( $core_menu_slug ) {
					return $core_menu_slug === $item[2];
				}
			)
		);

		$this->assertCount( 1, $provider_items );
		$this->assertSame( 'Add a provider', $provider_items[0][0] );
		$this->assertSame( 'manage_woocommerce', $provider_items[0][1] );
	}

	public function test_register_menu_does_nothing_without_a_payments_menu(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$controller = new WC_Stripe_Payments_UI_Controller();
		$controller->register_stripe_payments_menu();

		global $submenu;
		$this->assertSame( [], $submenu );

		$controller->enqueue_scripts( $this->get_own_screen_hook_suffix() );

		$this->assertFalse( wp_script_is( 'wc-stripe-admin-payments', 'enqueued' ) );
	}

	public function test_register_menu_sets_the_submenu_capability(): void {
		$this->register_menu_as_admin();

		$stripe_item = $this->find_stripe_submenu_item( $this->get_controller_const( 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG' ) );

		$this->assertNotNull( $stripe_item );
		$this->assertSame( $this->get_controller_const( 'CAPABILITY' ), $stripe_item[1] );
	}

	/**
	 * Test that we correctly handle users lacking permissions.
	 */
	public function test_register_menu_without_capability_adds_nothing_and_enqueues_nothing(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );

		$core_menu_slug = $this->get_controller_const( 'WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG' );
		$this->register_parent_menus( [ $core_menu_slug ] );

		$logged = null;
		try {
			$error_log_path     = (string) tempnam( sys_get_temp_dir(), 'wc-stripe-payments-ui' );
			$previous_error_log = ini_set( 'error_log', $error_log_path );

			$controller = new WC_Stripe_Payments_UI_Controller();
			$controller->register_stripe_payments_menu();

			$logged = (string) file_get_contents( $error_log_path );
		} finally {
			if ( isset( $previous_error_log ) ) {
				ini_set( 'error_log', $previous_error_log );
			}
			if ( isset( $error_log_path ) ) {
				unlink( $error_log_path );
			}
		}

		$this->assertSame( '', $logged );
		$this->assertNull( $this->find_stripe_submenu_item( $core_menu_slug ) );

		$controller->enqueue_scripts( $this->get_own_screen_hook_suffix() );
		$controller->enqueue_scripts( false );

		$this->assertFalse( wp_script_is( 'wc-stripe-admin-payments', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'wc-stripe-admin-payments', 'enqueued' ) );
	}

	public function test_render_page_outputs_the_mount_container(): void {
		$controller = new WC_Stripe_Payments_UI_Controller();

		ob_start();
		$controller->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="wc-stripe-payments-container"', $output );
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

		// The registered hook suffix is only known after the menus are registered.
		if ( '__own_screen__' === $hook_suffix ) {
			$hook_suffix = $this->get_own_screen_hook_suffix();
		}

		$controller->enqueue_scripts( $hook_suffix );

		$this->assertSame( $should_enqueue, wp_script_is( 'wc-stripe-admin-payments', 'enqueued' ) );
		$this->assertSame( $should_enqueue, wp_style_is( 'wc-stripe-admin-payments', 'enqueued' ) );
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

		$controller->enqueue_scripts( $this->get_own_screen_hook_suffix() );

		$data = wp_scripts()->get_data( 'wc-stripe-admin-payments', 'data' );

		$this->assertStringContainsString( 'wc_stripe_admin_payments_params', (string) $data );
		$this->assertStringContainsString( 'noDecimalCurrencies', (string) $data );
		$this->assertStringContainsString( 'threeDecimalCurrencies', (string) $data );

		foreach ( WC_Stripe_Currency_Code::NO_DECIMAL_CURRENCY_CODES as $code ) {
			$this->assertStringContainsString( $code, (string) $data );
		}
	}

	public function test_init_registers_admin_hooks(): void {
		$controller = new WC_Stripe_Payments_UI_Controller();

		$this->assertFalse( has_action( 'admin_menu', [ $controller, 'register_stripe_payments_menu' ] ) );
		$this->assertFalse( has_action( 'admin_enqueue_scripts', [ $controller, 'enqueue_scripts' ] ) );

		$controller->init();

		$menu_hook_priority = has_action( 'admin_menu', [ $controller, 'register_stripe_payments_menu' ] );
		try {
			$this->assertGreaterThan( 10, $menu_hook_priority );
			$this->assertNotFalse( has_action( 'admin_enqueue_scripts', [ $controller, 'enqueue_scripts' ] ) );
		} finally {
			remove_action( 'admin_menu', [ $controller, 'register_stripe_payments_menu' ], $menu_hook_priority );
			remove_action( 'admin_enqueue_scripts', [ $controller, 'enqueue_scripts' ] );
		}
	}
}
