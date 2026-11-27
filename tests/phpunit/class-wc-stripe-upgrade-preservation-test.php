<?php

require_once __DIR__ . '/helpers/class-wc-stripe-upgrade-http-fake.php';

/**
 * Exercises install() and subsequent settings reads against historical
 * stored states. Migration unit tests alone cannot observe interactions between them.
 *
 * Run: npm run test:php -- --filter WC_Stripe_Upgrade_Preservation_Test
 * Fixtures and intentional changes live together in fixtures/upgrades.php;
 * migration unit tests cover the detailed eligibility matrices.
 * This simulates request state in one process, not installation of old binaries,
 * the full WordPress lifecycle, checkout, rollback or multisite.
 */
class WC_Stripe_Upgrade_Preservation_Test extends WP_UnitTestCase {
	private const BOOKKEEPING_OPTIONS = [
		'wc_stripe_version',
		'wc_stripe_whats_new_note_version',
		'wc_stripe_show_style_notice',
		'wc_stripe_show_sca_notice',
		'wc_stripe_show_stripe_first_method_notice',
		'wc_stripe_show_ocs_ap_banner',
		'wc_stripe_show_ap_only_banner',
		'wc_stripe_show_ocs_only_banner',
		'wc_stripe_ocs_ap_default_on_migration_ran',
		'wc_stripe_express_checkout_cpm_location_migrated',
		'wc_stripe_restored_adaptive_pricing_after_amount_mismatch',
		'wc_stripe_failed_refund_email_additional_content_cleared',
		'wc_stripe_removed_duplicate_webhook_keys',
		'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated',
		// Webhook monitoring is diagnostic state, not a merchant preference.
		'wc_stripe_wh_monitor_began_at',
		'wc_stripe_wh_last_success_at',
		'wc_stripe_wh_last_failure_at',
		'wc_stripe_wh_last_error',
		'wc_stripe_wh_live_pending_webhooks',
		'wc_stripe_wh_test_monitor_began_at',
		'wc_stripe_wh_test_last_success_at',
		'wc_stripe_wh_test_last_failure_at',
		'wc_stripe_wh_test_last_error',
		'wc_stripe_wh_test_pending_webhooks',
	];

	private $http;
	private array $saved_properties = [];
	private array $request_hooks    = [];

	public function set_up() {
		parent::set_up();
		foreach ( [
			[ WC_Stripe::get_instance(), 'stripe_gateway' ],
			[ WC_Stripe_API::class, 'instance' ],
			[ WC_Stripe_API::class, 'secret_key' ],
			[ WC_Stripe_Payment_Method_Configurations::class, 'primary_configuration' ],
			[ WC_Stripe_Database_Cache::class, 'in_memory_cache' ],
			[ WC_Stripe_Hook_Manager::class, 'instance' ],
		] as [ $target, $name ] ) {
			$property = new ReflectionProperty( $target, $name );
			$property->setAccessible( true );
			$object                   = is_object( $target ) ? $target : null;
			$this->saved_properties[] = [ $property, $object, $property->getValue( $object ) ];
			$property->setValue( $object, 'in_memory_cache' === $name ? [] : null );
		}
		update_option( 'active_plugins', [ plugin_basename( WC_STRIPE_MAIN_FILE ) ] );
		update_option( 'woocommerce_allow_tracking', 'no' );
		update_option( 'woocommerce_currency', 'USD' );
	}

	public function tear_down() {
		if ( $this->http ) {
			remove_filter( 'pre_http_request', [ $this->http, 'intercept' ], 1 );
		}
		parent::tear_down();
		foreach ( $this->saved_properties as [ $property, $object, $value ] ) {
			$property->setValue( $object, $value );
		}
	}

	public function provide_upgrade_profiles(): array {
		return require __DIR__ . '/fixtures/upgrades.php';
	}

	/** @dataProvider provide_upgrade_profiles */
	public function test_upgrade_preserves_merchant_choices( array $fixture ): void {
		$fixture = json_decode( str_replace( '{{webhook_url}}', WC_Stripe_Helper::get_webhook_url(), wp_json_encode( $fixture, JSON_UNESCAPED_SLASHES ) ), true );
		$this->assertNotSame( WC_STRIPE_VERSION, $fixture['options']['wc_stripe_version'], 'The fixture must actually trigger install().' );
		$this->http = new WC_Stripe_Upgrade_HTTP_Fake( $fixture );
		add_filter( 'pre_http_request', [ $this->http, 'intercept' ], 1, 3 );
		$this->seed_options( $fixture['options'] );
		$expected            = $this->snapshot();
		$this->request_hooks = $this->clone_hooks();

		foreach ( [ 'upgrade', 'rerun' ] as $attempt ) {
			$this->new_request();
			// Rewind only the version: retaining migration flags verifies the one-shot
			// guards instead of letting install() return early on the current version.
			update_option( 'wc_stripe_version', $fixture['options']['wc_stripe_version'] );
			WC_Stripe::get_instance()->get_main_stripe_gateway();
			WC_Stripe::get_instance()->install();
			$this->assertSame( WC_STRIPE_VERSION, get_option( 'wc_stripe_version' ) );
			$expected['woocommerce_stripe_settings'] = 'upgrade' === $attempt ? $fixture['after_install'] : $fixture['after_read'];
			$this->assertSame( $this->sort_keys( $expected ), $this->sort_keys( $this->snapshot() ), "$attempt: saved settings after install" );

			$this->new_request();
			$response                                = $this->read_settings();
			$expected['woocommerce_stripe_settings'] = $fixture['after_read'];
			$this->assertSame( $this->sort_keys( $expected ), $this->sort_keys( $this->snapshot() ), "$attempt: saved settings after GET" );
			$this->assertSame( $this->sort_keys( $fixture['visible'] ), $this->sort_keys( array_intersect_key( $response, $fixture['visible'] ) ), "$attempt: merchant-visible settings" );
			$this->assertSame( [], $this->http->unexpected, "$attempt: unexpected HTTP request" );
			$this->assertSame( $this->sort_keys( 'upgrade' === $attempt ? $fixture['writes'] : [] ), $this->sort_keys( $this->http->writes() ), "$attempt: unexpected Stripe writes" );
			$this->http->requests = [];
		}
	}

	private function sort_keys( array $values ): array {
		// Map key order is immaterial; selection order, types and absent keys are not.
		foreach ( $values as &$value ) {
			if ( is_array( $value ) ) {
				$value = $this->sort_keys( $value );
			}
		}
		if ( array_keys( $values ) !== array_keys( array_values( $values ) ) ) {
			ksort( $values );
		}
		return $values;
	}

	private function read_settings(): array {
		$controller = new WC_REST_Stripe_Settings_Controller( WC_Stripe::get_instance()->get_main_stripe_gateway() );
		$response   = $controller->get_settings();
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	private function snapshot(): array {
		global $wpdb;
		// Caches use wcstripe_cache_ and are intentionally outside the merchant option
		// namespace. New unclassified wc_stripe_/woocommerce_stripe_ rows must fail.
		$names  = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name = %s", $wpdb->esc_like( 'wc_stripe_' ) . '%', $wpdb->esc_like( 'woocommerce_stripe_' ) . '%', $wpdb->esc_like( 'woocommerce_wc_stripe_' ) . '%', 'woocommerce_gateway_order' )
		);
		$result = [];
		foreach ( array_diff( $names, self::BOOKKEEPING_OPTIONS ) as $name ) {
			$result[ $name ] = get_option( $name );
		}
		return $result;
	}

	private function seed_options( array $options ): void {
		global $wpdb;
		foreach ( array_keys( $this->snapshot() ) as $name ) {
			delete_option( $name );
		}
		foreach ( self::BOOKKEEPING_OPTIONS as $name ) {
			delete_option( $name );
		}
		foreach ( [ 'live', 'test' ] as $mode ) {
			foreach ( [ 'account', 'payment_method_configuration', 'webhook_status' ] as $key ) {
				WC_Stripe_Database_Cache::delete_with_mode( $key, $mode );
			}
		}
		delete_option( WC_Stripe_Payment_Method_Configurations::FETCH_COOLDOWN_OPTION_KEY );
		foreach ( $options as $name => $value ) {
			// update_option() would apply today's onboarding defaults and consume
			// migration markers before we can test the historical stored state.
			$wpdb->replace(
				$wpdb->options,
				[
					'option_name'  => $name,
					'option_value' => maybe_serialize( $value ),
					'autoload'     => 'no',
				]
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_cache_delete( $name, 'options' );
		}
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		foreach ( $options as $name => $value ) {
			$this->assertSame( $value, get_option( $name ), "Fixture did not round-trip: $name" );
		}
	}

	private function clone_hooks(): array {
		global $wp_filter;
		return array_map(
			static function ( $hook ) {
				return clone $hook;
			},
			$wp_filter
		);
	}

	private function new_request(): void {
		global $wp_filter;
		// Recreate request-scoped objects without deleting persistent cache rows or
		// migration flags. Restore hooks so abandoned gateways cannot handle writes.
		$wp_filter = array_map(
			static function ( $hook ) {
				return clone $hook;
			},
			$this->request_hooks
		);
		foreach ( $this->saved_properties as [ $property, $object ] ) {
			$property->setValue( $object, 'in_memory_cache' === $property->getName() ? [] : null );
		}
	}
}
