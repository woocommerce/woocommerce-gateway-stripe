<?php
/**
 * Plain WordPress fixture bootstrap for WooCommerce Stripe benchmarks.
 *
 * Require this file from a WP-CLI eval-file or benchmark workload after WordPress,
 * WooCommerce, and WooCommerce Stripe are loaded. This helper intentionally avoids
 * PHPUnit globals and test-runner internals so fixture setup can run before timing
 * starts in an isolated benchmark runtime.
 *
 * @package WooCommerce\Stripe\Tests\Benchmarks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! class_exists( 'WC_Stripe_Benchmark_Fixture_Bootstrap' ) ) {
	/**
	 * Builds deterministic WooCommerce/Stripe state for benchmark workloads.
	 */
	class WC_Stripe_Benchmark_Fixture_Bootstrap {
		/**
		 * Default fixture arguments.
		 *
		 * @var array<string, mixed>
		 */
		private const DEFAULT_ARGS = [
			'currency'                 => 'USD',
			'product_name'             => 'Stripe Benchmark Product',
			'product_sku'              => 'stripe-benchmark-product',
			'product_price'            => '10.00',
			'cart_quantity'            => 1,
			'checkout_page_title'      => 'Checkout',
			'cart_page_title'          => 'Cart',
			'stripe_publishable_key'   => '',
			'stripe_secret_key'        => '',
			'stripe_webhook_secret'    => '',
			'ece_locations'            => [ 'product', 'cart', 'checkout' ],
			'optimized_checkout'       => false,
			'accepted_payment_methods' => [ 'card', 'link' ],
		];

		/**
		 * Bootstrap fixture state and assert it is ready for benchmark timing.
		 *
		 * @param array<string, mixed> $args Fixture overrides.
		 * @return array<string, mixed> Fixture state for workload logging.
		 */
		public static function bootstrap( array $args = [] ): array {
			$args = array_merge( self::DEFAULT_ARGS, self::env_args(), $args );

			self::assert_runtime_loaded();
			self::ensure_woocommerce_install_state();
			self::ensure_store_settings( (string) $args['currency'] );

			$cart_page_id     = self::ensure_page( 'woocommerce_cart_page_id', (string) $args['cart_page_title'], '[woocommerce_cart]' );
			$checkout_page_id = self::ensure_page( 'woocommerce_checkout_page_id', (string) $args['checkout_page_title'], '[woocommerce_checkout]' );
			$shipping_zone    = self::ensure_flat_rate_shipping();
			$product_id       = self::ensure_simple_product(
				(string) $args['product_name'],
				(string) $args['product_sku'],
				(string) $args['product_price']
			);

			self::configure_stripe_settings( $args );
			self::setup_cart( $product_id, (int) $args['cart_quantity'] );

			$state = [
				'product_id'       => $product_id,
				'cart_page_id'     => $cart_page_id,
				'checkout_page_id' => $checkout_page_id,
				'shipping_zone_id' => $shipping_zone['zone_id'],
				'shipping_rate_id' => $shipping_zone['rate_id'],
				'cart_quantity'    => (int) $args['cart_quantity'],
				'currency'         => (string) $args['currency'],
				'ece_locations'    => array_values( (array) $args['ece_locations'] ),
			];

			self::assert_preflight( $state );

			return $state;
		}

		/**
		 * Assert the fixture is ready. Call this immediately before benchmark timing.
		 *
		 * @param array<string, mixed> $state Fixture state from bootstrap().
		 * @return void
		 */
		public static function assert_preflight( array $state ): void {
			self::assert_runtime_loaded();

			$product = wc_get_product( (int) $state['product_id'] );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: benchmark product is missing, not purchasable, or out of stock.' );
			}

			$checkout_page_id = (int) $state['checkout_page_id'];
			$checkout_page    = get_post( $checkout_page_id );
			if ( ! $checkout_page || 'publish' !== $checkout_page->post_status ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: checkout page is missing or not published.' );
			}

			if ( ! WC()->cart || WC()->cart->is_empty() ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: WooCommerce cart is empty.' );
			}

			$packages = WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
			$rates    = [];
			foreach ( $packages as $package ) {
				$rates = array_merge( $rates, (array) ( $package['rates'] ?? [] ) );
			}

			if ( empty( $rates ) ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: no WooCommerce shipping rates are available for the benchmark cart.' );
			}

			$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
			$required        = [ 'enabled', 'testmode', 'test_publishable_key', 'test_secret_key' ];
			foreach ( $required as $key ) {
				if ( empty( $stripe_settings[ $key ] ) ) {
					throw new RuntimeException( sprintf( 'Benchmark fixture setup failed: Stripe setting "%s" is empty.', $key ) );
				}
			}

			if ( 'yes' !== $stripe_settings['enabled'] || 'yes' !== $stripe_settings['testmode'] || 'yes' !== ( $stripe_settings['payment_request'] ?? '' ) ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: Stripe gateway and express checkout must be enabled in test mode.' );
			}

			$locations = (array) ( $stripe_settings['payment_request_button_locations'] ?? [] );
			if ( ! in_array( 'checkout', $locations, true ) ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: Stripe express checkout must be enabled for checkout.' );
			}

			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
			if ( empty( $available_gateways['stripe'] ) ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: Stripe is not available as a checkout gateway.' );
			}
		}

		/**
		 * Assert required plugins/classes are loaded.
		 *
		 * @return void
		 */
		private static function assert_runtime_loaded(): void {
			$required_classes = [
				'WooCommerce'       => class_exists( 'WooCommerce' ),
				'WC_Product_Simple' => class_exists( 'WC_Product_Simple' ),
				'WC_Stripe'         => class_exists( 'WC_Stripe' ),
				'WC_Stripe_Helper'  => class_exists( 'WC_Stripe_Helper' ),
			];

			foreach ( $required_classes as $label => $loaded ) {
				if ( ! $loaded ) {
					throw new RuntimeException( sprintf( 'Benchmark fixture setup failed: required runtime class "%s" is not loaded.', $label ) );
				}
			}

			if ( ! function_exists( 'WC' ) || ! WC() ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: WooCommerce runtime is not initialized.' );
			}
		}

		/**
		 * Ensure WooCommerce has the minimum install flags/pages needed by checkout.
		 *
		 * @return void
		 */
		private static function ensure_woocommerce_install_state(): void {
			if ( class_exists( 'WC_Install' ) ) {
				WC_Install::create_tables();
				WC_Install::create_roles();
			}

			if ( function_exists( 'wc_update_product_lookup_tables_is_running' ) && ! wc_update_product_lookup_tables_is_running() ) {
				delete_transient( 'wc_product_loop' );
			}
		}

		/**
		 * Ensure deterministic store settings.
		 *
		 * @param string $currency Store currency.
		 * @return void
		 */
		private static function ensure_store_settings( string $currency ): void {
			update_option( 'woocommerce_store_address', '60 29th Street' );
			update_option( 'woocommerce_store_address_2', '#343' );
			update_option( 'woocommerce_store_city', 'San Francisco' );
			update_option( 'woocommerce_default_country', 'US:CA' );
			update_option( 'woocommerce_store_postcode', '94110' );
			update_option( 'woocommerce_currency', $currency );
			update_option( 'woocommerce_product_type', 'both' );
			update_option( 'woocommerce_allow_tracking', 'no' );
			update_option( 'woocommerce_coming_soon', 'no' );
		}

		/**
		 * Ensure a WooCommerce page option points to a published page.
		 *
		 * @param string $option_name WooCommerce page option name.
		 * @param string $title       Page title.
		 * @param string $content     Page content.
		 * @return int Page ID.
		 */
		private static function ensure_page( string $option_name, string $title, string $content ): int {
			$page_id = absint( get_option( $option_name ) );
			$page    = $page_id ? get_post( $page_id ) : null;

			if ( $page && 'page' === $page->post_type && 'publish' === $page->post_status ) {
				return $page_id;
			}

			$page_id = wp_insert_post(
				[
					'post_title'   => $title,
					'post_name'    => sanitize_title( $title ),
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_content' => $content,
				],
				true
			);

			if ( is_wp_error( $page_id ) ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: could not create ' . $title . ' page. ' . $page_id->get_error_message() );
			}

			update_option( $option_name, (int) $page_id );

			return (int) $page_id;
		}

		/**
		 * Ensure the benchmark product exists and is purchasable.
		 *
		 * @param string $name  Product name.
		 * @param string $sku   Product SKU.
		 * @param string $price Product price.
		 * @return int Product ID.
		 */
		private static function ensure_simple_product( string $name, string $sku, string $price ): int {
			$product_id = wc_get_product_id_by_sku( $sku );
			$product    = $product_id ? wc_get_product( $product_id ) : null;

			if ( ! $product ) {
				$product = new WC_Product_Simple();
				$product->set_sku( $sku );
			}

			$product->set_name( $name );
			$product->set_regular_price( $price );
			$product->set_price( $price );
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_virtual( false );
			$product->set_downloadable( false );
			$product->save();

			return (int) $product->get_id();
		}

		/**
		 * Ensure a flat-rate shipping method is available for checkout.
		 *
		 * @return array{zone_id:int, rate_id:string}
		 */
		private static function ensure_flat_rate_shipping(): array {
			if ( ! class_exists( 'WC_Shipping_Zones' ) || ! class_exists( 'WC_Shipping_Zone' ) ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: WooCommerce shipping zones are not available.' );
			}

			$zone_id = 0;
			foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
				if ( 'Benchmark Everywhere' === ( $zone['zone_name'] ?? '' ) ) {
					$zone_id = (int) $zone['zone_id'];
					break;
				}
			}

			$shipping_zone = $zone_id ? new WC_Shipping_Zone( $zone_id ) : new WC_Shipping_Zone();
			if ( ! $zone_id ) {
				$shipping_zone->set_zone_name( 'Benchmark Everywhere' );
				$shipping_zone->save();
			}

			$instance_id = 0;
			foreach ( $shipping_zone->get_shipping_methods() as $method ) {
				if ( 'flat_rate' === $method->id ) {
					$instance_id = (int) $method->get_instance_id();
					break;
				}
			}

			if ( ! $instance_id ) {
				$instance_id = (int) $shipping_zone->add_shipping_method( 'flat_rate' );
			}

			update_option(
				'woocommerce_flat_rate_' . $instance_id . '_settings',
				[
					'title'      => 'Benchmark flat rate',
					'tax_status' => 'taxable',
					'cost'       => '10',
				]
			);

			WC_Cache_Helper::get_transient_version( 'shipping', true );

			return [
				'zone_id' => $shipping_zone->get_id(),
				'rate_id' => 'flat_rate:' . $instance_id,
			];
		}

		/**
		 * Configure Stripe test and express checkout settings.
		 *
		 * @param array<string, mixed> $args Fixture args.
		 * @return void
		 */
		private static function configure_stripe_settings( array $args ): void {
			$locations                = array_values( (array) $args['ece_locations'] );
			$accepted_payment_methods = array_values( (array) $args['accepted_payment_methods'] );

			$settings = array_merge(
				WC_Stripe_Helper::get_stripe_settings(),
				[
					'enabled'                                   => 'yes',
					'title'                                     => 'Credit Card (Stripe)',
					'description'                               => 'Pay with your credit card via Stripe.',
					'api_credentials'                           => '',
					'testmode'                                  => 'yes',
					'test_publishable_key'                      => (string) $args['stripe_publishable_key'],
					'test_secret_key'                           => (string) $args['stripe_secret_key'],
					'publishable_key'                           => '',
					'secret_key'                                => '',
					'webhook'                                   => '',
					'test_webhook_secret'                       => (string) $args['stripe_webhook_secret'],
					'webhook_secret'                            => '',
					'inline_cc_form'                            => 'no',
					'statement_descriptor'                      => '',
					'short_statement_descriptor'                => '',
					'capture'                                   => 'yes',
					'payment_request'                           => 'yes',
					'payment_request_button_type'               => 'buy',
					'payment_request_button_theme'              => 'dark',
					'payment_request_button_locations'          => $locations,
					'payment_request_button_size'               => 'default',
					'express_checkout'                          => 'yes',
					'express_checkout_button_type'              => 'buy',
					'express_checkout_button_theme'             => 'dark',
					'express_checkout_button_locations'         => $locations,
					'express_checkout_button_size'              => 'default',
					'saved_cards'                               => 'yes',
					'logging'                                   => 'no',
					'upe_checkout_experience_enabled'           => 'yes',
					'upe_checkout_experience_accepted_payments' => $accepted_payment_methods,
					'optimized_checkout_element'                => ! empty( $args['optimized_checkout'] ) ? 'yes' : 'no',
				]
			);

			WC_Stripe_Helper::update_main_stripe_settings( $settings );

			if ( function_exists( 'woocommerce_gateway_stripe' ) && class_exists( 'WC_Stripe' ) ) {
				$closure = Closure::bind(
					function () {
						$this->stripe_gateway = null;
					},
					woocommerce_gateway_stripe(),
					WC_Stripe::class
				);
				$closure();
			}

			if ( WC()->payment_gateways() ) {
				WC()->payment_gateways()->payment_gateways = [];
				WC()->payment_gateways()->init();
			}
		}

		/**
		 * Set up a deterministic WooCommerce cart/session.
		 *
		 * @param int $product_id Product ID.
		 * @param int $quantity   Cart quantity.
		 * @return void
		 */
		private static function setup_cart( int $product_id, int $quantity ): void {
			if ( function_exists( 'wc_load_cart' ) ) {
				wc_load_cart();
			}

			if ( ! WC()->session && class_exists( 'WC_Session_Handler' ) ) {
				WC()->session = new WC_Session_Handler();
				WC()->session->init();
			}

			if ( ! WC()->cart && class_exists( 'WC_Cart' ) ) {
				WC()->cart = new WC_Cart();
			}

			if ( ! WC()->customer && class_exists( 'WC_Customer' ) ) {
				WC()->customer = new WC_Customer( 0, true );
			}

			WC()->cart->empty_cart();
			$cart_key = WC()->cart->add_to_cart( $product_id, max( 1, $quantity ) );
			if ( ! $cart_key ) {
				throw new RuntimeException( 'Benchmark fixture setup failed: could not add benchmark product to cart.' );
			}

			WC()->cart->calculate_totals();
		}

		/**
		 * Read optional Stripe settings from environment variables.
		 *
		 * @return array<string, string>
		 */
		private static function env_args(): array {
			return [
				'stripe_publishable_key' => self::env( 'STRIPE_PUBLISHABLE_KEY', 'pk_test_benchmark_fixture' ),
				'stripe_secret_key'      => self::env( 'STRIPE_SECRET_KEY', 'sk_test_benchmark_fixture' ),
				'stripe_webhook_secret'  => self::env( 'STRIPE_WEBHOOK_SECRET', '' ),
			];
		}

		/**
		 * Get an environment value.
		 *
		 * @param string $name    Variable name.
		 * @param string $default Default value.
		 * @return string
		 */
		private static function env( string $name, string $default ): string {
			$value = getenv( $name );
			return false === $value ? $default : (string) $value;
		}
	}
}
