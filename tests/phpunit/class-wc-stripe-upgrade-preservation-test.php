<?php

require_once __DIR__ . '/helpers/class-wc-stripe-upgrade-http-fake.php';

/**
 * Exercises install() and subsequent settings reads against historical
 * stored states. Migration unit tests alone cannot observe interactions between them.
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
		$cases = [];
		foreach ( require __DIR__ . '/fixtures/upgrades/profiles.php' as $name => $fixture ) {
			$cases[ $name ] = [ $fixture ];
		}
		return $cases;
	}

	/** @dataProvider provide_upgrade_profiles */
	public function test_upgrade_preserves_merchant_choices( array $fixture ): void {
		$this->exercise_upgrade( $fixture );
	}

	/** @dataProvider provide_detectable_regressions */
	public function test_harness_detects_regressions( string $regression, string $diagnostic ): void {
		$fixtures = require __DIR__ . '/fixtures/upgrades/profiles.php';
		$runs     = 0;
		$inject   = static function () use ( $regression, &$runs ) {
			if ( 'pmc' === $regression && 1 === ++$runs ) {
				return;
			}
			$settings = WC_Stripe_Helper::get_stripe_settings();
			if ( 'oc' === $regression ) {
				$settings['optimized_checkout_element'] = 'yes';
			} elseif ( 'locations' === $regression ) {
				$settings['express_checkout_button_locations'] = [ 'product', 'cart', 'checkout' ];
			} else {
				WC_Stripe_API::set_secret_key_for_mode();
				WC_Stripe_API::request( [ 'link' => [ 'display_preference' => [ 'preference' => 'on' ] ] ], 'payment_method_configurations/pmc_live_fixture' );
			}
			WC_Stripe_Helper::update_main_stripe_settings( $settings );
		};
		add_action( 'woocommerce_stripe_updated', $inject );
		$this->expectException( PHPUnit\Framework\AssertionFailedError::class );
		$this->expectExceptionMessage( $diagnostic );
		try {
			$this->exercise_upgrade( $fixtures['11.0.1 opt-outs'] );
		} finally {
			remove_action( 'woocommerce_stripe_updated', $inject );
		}
	}

	public function provide_detectable_regressions(): array {
		return [
			'OC re-enabled'                 => [ 'oc', 'optimized_checkout_element' ],
			'button locations reset'        => [ 'locations', 'express_checkout_button_locations' ],
			'unexpected PMC write on rerun' => [ 'pmc', 'forced rerun: unexpected Stripe writes' ],
		];
	}

	private function exercise_upgrade( array $fixture ): void {
		$fixture = json_decode( str_replace( '{{webhook_url}}', WC_Stripe_Helper::get_webhook_url(), wp_json_encode( $fixture, JSON_UNESCAPED_SLASHES ) ), true );
		$this->assertNotSame( WC_STRIPE_VERSION, $fixture['options']['wc_stripe_version'], 'Fixture must exercise an upgrade, not the current-version guard.' );
		$this->http = new WC_Stripe_Upgrade_HTTP_Fake( $fixture );
		add_filter( 'pre_http_request', [ $this->http, 'intercept' ], 1, 3 );
		$this->seed_options( $fixture['options'] );
		$expected            = $this->snapshot();
		$visible             = $fixture['visible'];
		$this->request_hooks = $this->clone_hooks();

		// Gateway construction normally happens on init, before admin_init runs install().
		WC_Stripe::get_instance()->get_main_stripe_gateway();
		WC_Stripe::get_instance()->install();
		$this->assertSame( WC_STRIPE_VERSION, get_option( 'wc_stripe_version' ) );
		$this->assert_stage( $fixture, 'upgrade', $expected, $visible );

		$this->new_request();
		$response = $this->read_settings();
		$this->assert_stage( $fixture, 'settings GET', $expected, $visible );
		$this->assert_visible( $visible, $response, 'settings GET' );

		foreach ( [ 'next request', 'forced rerun' ] as $stage ) {
			$this->new_request();
			if ( 'forced rerun' === $stage ) {
				// Rewinding only the version also tests the migrations' own one-shot guards.
				update_option( 'wc_stripe_version', $fixture['options']['wc_stripe_version'] );
			}
			WC_Stripe::get_instance()->get_main_stripe_gateway();
			WC_Stripe::get_instance()->install();
			$this->assertSame( WC_STRIPE_VERSION, get_option( 'wc_stripe_version' ), $stage );
			$this->new_request();
			$response = $this->read_settings();
			$this->assert_stage( $fixture, $stage, $expected, $visible );
			$this->assert_visible( $visible, $response, $stage );
		}

		if ( '10.7.0' === $fixture['options']['wc_stripe_version'] ) {
			$settings                               = WC_Stripe_Helper::get_stripe_settings();
			$settings['optimized_checkout_element'] = 'no';
			$settings['adaptive_pricing']           = 'no';
			WC_Stripe_Helper::update_main_stripe_settings( $settings );
			$expected['woocommerce_stripe_settings'] = $settings;
			$visible['is_oc_enabled']                = false;
			$visible['is_ap_enabled']                = false;
			$this->new_request();
			update_option( 'wc_stripe_version', '10.7.0' );
			WC_Stripe::get_instance()->get_main_stripe_gateway();
			WC_Stripe::get_instance()->install();
			$this->assertSame( WC_STRIPE_VERSION, get_option( 'wc_stripe_version' ), 'opt-out rerun' );
			$this->new_request();
			$response = $this->read_settings();
			$this->assert_stage( $fixture, 'opt-out rerun', $expected, $visible );
			$this->assert_visible( $visible, $response, 'opt-out rerun' );
		}
	}

	private function assert_stage( array $fixture, string $stage, array &$expected, array &$visible ): void {
		$changes = require __DIR__ . '/fixtures/upgrades/allowed-changes.php';
		foreach ( $changes['settings'] as $key => [ $from, $to ] ) {
			$this->assertSame( null !== $from, array_key_exists( $key, $expected['woocommerce_stripe_settings'] ), "$stage: invalid exception key presence: $key" );
			$this->assertSame( $from, $expected['woocommerce_stripe_settings'][ $key ] ?? null, "$stage: invalid exception prerequisite: $key" );
			$this->assertNotSame( $from, $to, "$stage: unused exception: $key" );
			if ( null === $to ) {
				unset( $expected['woocommerce_stripe_settings'][ $key ] );
			} else {
				$expected['woocommerce_stripe_settings'][ $key ] = $to;
			}
		}
		$visible = array_merge( $visible, $changes['visible'] );
		$this->assertSame( [], $this->http->unexpected, "$stage: unexpected HTTP request" );
		$this->assert_values( $changes['writes'], $this->http->writes(), "$stage: unexpected Stripe writes (or unused HTTP exception)" );
		$this->assert_values( $expected, $this->snapshot(), $stage );
		$this->http->requests = [];
	}

	private function assert_visible( array $expected, array $actual, string $stage ): void {
		foreach ( $expected as $key => $value ) {
			$this->assertSame( $value, $actual[ $key ] ?? null, "$stage: merchant-visible $key" );
		}
	}

	private function assert_values( array $expected, array $actual, string $path ): void {
		foreach ( array_unique( array_merge( array_keys( $expected ), array_keys( $actual ) ) ) as $key ) {
			$label = $path . '.' . $key;
			$this->assertSame( array_key_exists( $key, $expected ), array_key_exists( $key, $actual ), "$label: key presence changed" );
			if ( is_array( $expected[ $key ] ) && is_array( $actual[ $key ] ) ) {
				$this->assert_values( $expected[ $key ], $actual[ $key ], $label );
			} else {
				$this->assertSame( $expected[ $key ], $actual[ $key ], $label );
			}
		}
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
