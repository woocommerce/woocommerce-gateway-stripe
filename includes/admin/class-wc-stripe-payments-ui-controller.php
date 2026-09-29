<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Payments -> Stripe admin submenu and renders the Stripe Payments UI.
 *
 * @since 11.1.0
 */
final class WC_Stripe_Payments_UI_Controller {

	/**
	 * Menu slug shared by the top-level menu and its first submenu item.
	 *
	 * @var string
	 */
	private const PAYMENTS_MENU_SLUG = 'wc-stripe-payments';

	/**
	 * The slug of the Payments menu added by WooPayments.
	 *
	 * @var string
	 */
	private const WOOPAYMENTS_PAYMENTS_MENU_SLUG = 'wc-payments';

	/**
	 * The slug of the Payments menu added by WooCommerce Core.
	 *
	 * @var string
	 */
	private const WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG = 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM';

	/**
	 * Capability required to view the page.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_woocommerce';

	/**
	 * Hook suffix for our Payments menu item. Used to gate asset loading.
	 *
	 * @var string|null
	 */
	private $admin_page_hook = null;

	/**
	 * Registers the admin hooks for the Stripe Payments UI.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register menus late so we can pick up which Payments menu is active.
		add_action( 'admin_menu', [ $this, 'register_stripe_payments_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Registers the Stripe Payments submenu.
	 *
	 * @return void
	 */
	public function register_stripe_payments_menu(): void {
		$payments_menu_slug = $this->get_payments_menu_slug();

		if ( null === $payments_menu_slug ) {
			return;
		}

		$admin_page_hook = add_submenu_page(
			$payments_menu_slug,
			'Stripe',
			'Stripe',
			self::CAPABILITY,
			self::PAYMENTS_MENU_SLUG,
			[ $this, 'render_page' ]
		);

		if ( false !== $admin_page_hook ) {
			$this->admin_page_hook = $admin_page_hook;

			// Only try to override the submenu if we successfully added our own submenu.
			if ( self::WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG === $payments_menu_slug ) {
				$this->shift_and_rename_payments_payments_submenu_item();
			}
		}
	}

	/**
	 * Gets the slug of the Payments menu that is active.
	 * It can come from WooCommerce Core or WooCommerce Payments.
	 *
	 * @return string|null
	 */
	private function get_payments_menu_slug(): ?string {
		$woo_core_payments_menu_url = menu_page_url( self::WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG, false );

		if ( '' !== $woo_core_payments_menu_url ) {
			return self::WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG;
		}

		$woo_payments_menu_url = menu_page_url( self::WOOPAYMENTS_PAYMENTS_MENU_SLUG, false );

		if ( '' !== $woo_payments_menu_url ) {
			return self::WOOPAYMENTS_PAYMENTS_MENU_SLUG;
		}

		return null;
	}

	/**
	 * Helper method to remove and re-insert the Payments -> Payments submenu item.
	 */
	private function shift_and_rename_payments_payments_submenu_item(): void {
		$payments_payments_submenu_item = remove_submenu_page( self::WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG, self::WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG );

		if ( false === $payments_payments_submenu_item ) {
			return;
		}

		try {
			$capability = $payments_payments_submenu_item[1];

			add_submenu_page(
				self::WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG,
				__( 'Add a provider', 'woocommerce-gateway-stripe' ),
				__( 'Add a provider', 'woocommerce-gateway-stripe' ),
				$capability,
				self::WOOCOMMERCE_CORE_PAYMENTS_MENU_SLUG,
				'',
				// The top-level Payments link opens whichever submenu item is first, and that must stay the provider list.
				0
			);
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}

	/**
	 * Renders the container the React app mounts into.
	 *
	 * @return void
	 */
	public function render_page(): void {
		echo '<div class="wrap"><div id="wc-stripe-payments-container"></div></div>';
	}

	/**
	 * Registers and enqueues the payments UI assets when needed.
	 *
	 * @param string|null $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix = null ) {
		if ( empty( $this->admin_page_hook ) || $hook_suffix !== $this->admin_page_hook ) {
			return;
		}

		add_filter(
			'admin_body_class',
			function ( $classes ) {
				$classes .= ' wc-stripe-payments-admin ';
				return $classes;
			}
		);

		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/payments-admin.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'wc-stripe-admin-payments',
			plugins_url( 'build/payments-admin.js', WC_STRIPE_MAIN_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_register_style(
			'wc-stripe-admin-payments',
			plugins_url( 'build/payments-admin.css', WC_STRIPE_MAIN_FILE ),
			[ 'wc-components' ],
			$script_asset['version']
		);

		wp_set_script_translations(
			'wc-stripe-admin-payments',
			'woocommerce-gateway-stripe'
		);

		wp_localize_script(
			'wc-stripe-admin-payments',
			'wc_stripe_admin_payments_params',
			$this->get_script_params()
		);

		wp_enqueue_script( 'wc-stripe-admin-payments' );
		wp_enqueue_style( 'wc-stripe-admin-payments' );
	}

	/**
	 * Builds the params for the Payments UI.
	 *
	 * The minor-unit currency lists are passed through rather than duplicated in
	 * JS so PHP stays the single source of truth for Stripe's exponent rules.
	 *
	 * @return array
	 */
	private function get_script_params() {
		return [
			'locale'                 => str_replace( '_', '-', get_user_locale() ),
			'noDecimalCurrencies'    => WC_Stripe_Currency_Code::NO_DECIMAL_CURRENCY_CODES,
			'threeDecimalCurrencies' => WC_Stripe_Currency_Code::THREE_DECIMAL_CURRENCY_CODES,
		];
	}
}
