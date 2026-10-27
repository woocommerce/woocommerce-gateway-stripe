<?php
/**
 * DRAFT scaffold for the experimental WooCommerce payment settings screen.
 *
 * Draft starting point, not a finished feature. It stays off unless
 * WooCommerce's experimental admin-screens feature is on, so it is a no-op on a normal install.
 *
 * References (read these before extending this class):
 * - WooCommerce foundation PR woocommerce/woocommerce#70007 (the cheque example and View Config).
 * - WooPayments PR Automattic/woocommerce-payments#12269 (the external-plugin registration pattern).
 *
 * @package WooCommerce_Stripe/Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers a Stripe page on WooCommerce's experimental payment settings screen.
 *
 * WooCommerce renders one page per core-data entity of kind `woo_settings_payments`. This class
 * supplies the entity (shown at /settings/stripe), the field layout through View Config, and a
 * REST route the entity record loads and saves through.
 *
 * It is split into sections so each person can own one without clashing: each
 * section is a card on the page, built from its own method here and (later) its own field UI in
 * client/settings-screen/sections/. Only the "general" section has real fields today; the rest
 * are empty stubs waiting for an owner.
 */
class WC_Stripe_Settings_Screen {

	/**
	 * Entity kind of WooCommerce's payment settings pages.
	 */
	protected const ENTITY_KIND = 'woo_settings_payments';

	/**
	 * Entity name of the Stripe page. WooCommerce shows it at /settings/stripe.
	 */
	protected const SCREEN_ID = 'stripe';

	/**
	 * Admin page slug of the settings screen. WooCommerce fires `{slug}_init` only on this page.
	 */
	protected const PAGE_SLUG = 'wc-payment-settings-wp-admin';

	/**
	 * Script that registers the entity and its fields in the browser.
	 */
	protected const SCRIPT_HANDLE = 'wc-stripe-settings-screen';

	/**
	 * REST route (under wc/v3) the entity record loads and saves through.
	 */
	protected const REST_ROUTE = '/stripe/settings-dataform';

	/**
	 * WooCommerce's experimental feature flag that turns the whole screen on.
	 */
	protected const FEATURE_FLAG = 'experimental-admin-screens';

	/**
	 * Registers hooks, but only when the experimental screen is available.
	 */
	public function init_hooks(): void {
		if ( ! $this->is_screen_enabled() ) {
			return;
		}

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'get_entity_view_config_' . self::ENTITY_KIND . '_' . self::SCREEN_ID, [ $this, 'get_view_config' ] );
		// WooCommerce fires this while it enqueues the screen's scripts, only on the screen.
		add_action( self::PAGE_SLUG . '_init', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Whether WooCommerce's experimental payment settings screen is available.
	 *
	 * Gated on WooCommerce's own flag so this scaffold never loads on a normal install.
	 *
	 * @return bool
	 */
	private function is_screen_enabled(): bool {
		return class_exists( '\Automattic\WooCommerce\Admin\Features\Features' )
			&& \Automattic\WooCommerce\Admin\Features\Features::is_enabled( self::FEATURE_FLAG );
	}

	/**
	 * The sections the Stripe page is split into, in display order.
	 *
	 * One section is one card on the page, and one owner. Keep each section's fields
	 * in its own method so two people editing different sections do not touch the same lines. A
	 * section with no fields is skipped, so a stub is safe to leave until someone picks it up.
	 *
	 * @return array<string, array{label: string, fields: array<int, array<string, mixed>>}>
	 */
	public function get_sections(): array {
		return [
			'general'          => [
				'label'  => __( 'General', 'woocommerce-gateway-stripe' ),
				'fields' => $this->get_general_fields(),
			],
			// TODO: owner ____. Move the enable/disable and icons of each method here.
			'payment-methods'  => [
				'label'  => __( 'Payment methods', 'woocommerce-gateway-stripe' ),
				'fields' => [],
			],
			// TODO: owner ____. Apple Pay / Google Pay / Link toggles and button options.
			'express-checkout' => [
				'label'  => __( 'Express checkout', 'woocommerce-gateway-stripe' ),
				'fields' => [],
			],
			// TODO: owner ____. Debug log, webhooks, manual keys.
			'advanced'         => [
				'label'  => __( 'Advanced', 'woocommerce-gateway-stripe' ),
				'fields' => [],
			],
		];
	}

	/**
	 * Fields of the "general" section, the one worked example.
	 *
	 * Each field's `id` must match a key handled in handle_request(). `Edit` names the control the
	 * browser renders; see the cheque example in WooCommerce#70007 for the available types.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_general_fields(): array {
		return [
			[
				'id'    => 'enabled',
				'type'  => 'boolean',
				'Edit'  => 'toggle',
				'label' => __( 'Enable Stripe', 'woocommerce-gateway-stripe' ),
			],
			[
				'id'    => 'title',
				'type'  => 'text',
				'label' => __( 'Title', 'woocommerce-gateway-stripe' ),
			],
			[
				'id'    => 'description',
				'type'  => 'text',
				'label' => __( 'Description', 'woocommerce-gateway-stripe' ),
			],
		];
	}

	/**
	 * Supplies the page layout: one card per section that has fields.
	 *
	 * @param mixed $config View Config object supplied by WooCommerce.
	 * @return mixed
	 */
	public function get_view_config( $config ) {
		if ( ! is_object( $config ) || ! is_callable( [ $config, 'merge' ] ) ) {
			return $config;
		}

		$cards = [];
		foreach ( $this->get_sections() as $id => $section ) {
			if ( empty( $section['fields'] ) ) {
				continue;
			}
			$cards[] = [
				'id'       => $id,
				'label'    => $section['label'],
				'layout'   => [
					'type'          => 'card',
					'isCollapsible' => false,
				],
				'children' => wp_list_pluck( $section['fields'], 'id' ),
			];
		}

		return $config->merge( [ 'form' => [ 'fields' => $cards ] ], 1 );
	}

	/**
	 * Registers the entity's REST route.
	 */
	public function register_routes(): void {
		register_rest_route(
			'wc/v3',
			self::REST_ROUTE . '/(?P<id>' . self::SCREEN_ID . ')',
			[
				'methods'             => [ 'GET', 'POST', 'PUT' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'callback'            => [ $this, 'handle_request' ],
			]
		);
	}

	/**
	 * Only store managers may read or change the settings.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Reads or saves the Stripe settings record for the screen.
	 *
	 * DRAFT: only the general section's fields are mapped. Each section owner extends the read and
	 * the save with their own fields. The record is keyed by `id` so core-data can load and save it.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ) {
		$settings = WC_Stripe_Helper::get_stripe_settings();

		if ( 'GET' !== $request->get_method() ) {
			// core-data only sends the fields that changed, so skip anything absent.
			$enabled = $request->get_param( 'enabled' );
			if ( null !== $enabled ) {
				$settings['enabled'] = $enabled ? 'yes' : 'no';
			}
			foreach ( [ 'title', 'description' ] as $key ) {
				$value = $request->get_param( $key );
				if ( null !== $value ) {
					$settings[ $key ] = wc_clean( $value );
				}
			}
			WC_Stripe_Helper::update_main_stripe_settings( $settings );
		}

		return rest_ensure_response(
			[
				'id'          => self::SCREEN_ID,
				'enabled'     => 'yes' === ( $settings['enabled'] ?? 'no' ),
				'title'       => $settings['title'] ?? '',
				'description' => $settings['description'] ?? '',
			]
		);
	}

	/**
	 * Enqueues the screen script and hands it the entity config.
	 *
	 * The built asset does not exist until the client entry is added to webpack and built; see
	 * docs/payment-settings-screen.md. Because this runs only when the experimental screen is
	 * on, a missing build never affects a normal install.
	 */
	public function enqueue_scripts(): void {
		$fields = [];
		foreach ( $this->get_sections() as $section ) {
			$fields = array_merge( $fields, $section['fields'] );
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			WC_STRIPE_PLUGIN_URL . '/build/settings-screen.js',
			[ 'wp-data', 'wp-editor' ],
			WC_STRIPE_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'wcStripeSettingsScreen',
			[
				'kind'    => self::ENTITY_KIND,
				'name'    => self::SCREEN_ID,
				'baseURL' => '/wc/v3' . self::REST_ROUTE,
				'fields'  => $fields,
			]
		);
	}
}
