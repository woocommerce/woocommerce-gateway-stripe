<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the top-level Finance admin menu and renders the Stripe section.
 *
 * @since 11.0.0
 */
class WC_Stripe_Finance_UI_Controller {

	/**
	 * Menu slug shared by the top-level menu and its first submenu item.
	 *
	 * Distinct from WooPayments' `wc-admin&path=/payments/...` menu so both can
	 * coexist on a store running the two plugins.
	 *
	 * @var string
	 */
	private const MENU_SLUG = 'wc-stripe-finance';

	/**
	 * Capability required to view the page.
	 *
	 * Matches the REST controller's permission check so the UI and the endpoint
	 * it calls cannot disagree.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_woocommerce';

	/**
	 * Hook suffix returned by add_menu_page(), used to gate asset loading.
	 *
	 * Null until register_menu() runs on `admin_menu`.
	 *
	 * @var string|null
	 */
	private $hook_suffix;

	/**
	 * Registers the admin hooks that build the Finance UI.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Registers the Finance menu with Transactions as its default sub-item.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'Finance', 'woocommerce-gateway-stripe' ),
			__( 'Finance', 'woocommerce-gateway-stripe' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_page' ],
			'dashicons-money-alt',
			// WooCommerce occupies 55.5 and 55.6; this keeps Finance alongside
			// them. A fractional position is what stops WordPress overwriting an
			// existing menu that already claimed the same integer slot.
			55.7
		);

		// Reusing the parent slug replaces the submenu entry WordPress would
		// otherwise auto-create as a duplicate of the parent's "Finance" label.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Transactions', 'woocommerce-gateway-stripe' ),
			__( 'Transactions', 'woocommerce-gateway-stripe' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Renders the container the React app mounts into.
	 *
	 * @return void
	 */
	public function render_page() {
		echo '<div class="wrap"><div id="wc-stripe-finance-container"></div></div>';
	}

	/**
	 * Registers and enqueues the finance assets on this page only.
	 *
	 * @param string|null $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix = null ) {
		if ( empty( $this->hook_suffix ) || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$script_asset_path = WC_STRIPE_PLUGIN_PATH . '/build/finance.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_STRIPE_VERSION,
			];

		wp_register_script(
			'wc-stripe-finance',
			plugins_url( 'build/finance.js', WC_STRIPE_MAIN_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_register_style(
			'wc-stripe-finance',
			plugins_url( 'build/finance.css', WC_STRIPE_MAIN_FILE ),
			[ 'wc-components' ],
			$script_asset['version']
		);

		wp_set_script_translations(
			'wc-stripe-finance',
			'woocommerce-gateway-stripe'
		);

		wp_localize_script(
			'wc-stripe-finance',
			'wc_stripe_finance_params',
			$this->get_script_params()
		);

		wp_enqueue_script( 'wc-stripe-finance' );
		wp_enqueue_style( 'wc-stripe-finance' );
	}

	/**
	 * Builds the params for the Finance UI.
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
