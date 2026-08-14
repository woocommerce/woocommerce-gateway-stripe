<?php

/**
 * Unit tests for WC_Stripe_Restore_Adaptive_Pricing_After_Amount_Mismatch_Update.
 */
class WC_Stripe_Restore_Adaptive_Pricing_After_Amount_Mismatch_Update_Test extends WP_UnitTestCase {

	private const MIGRATION_FLAG_OPTION = 'wc_stripe_restore_adaptive_pricing_after_amount_mismatch_migration_ran';

	private const AMOUNT_MISMATCH_OPTION = 'wc_stripe_adaptive_pricing_session_amount_mismatch_detected';

	private const LOG_MESSAGE = 'Adaptive Pricing re-enabled during plugin update after a previous automatic disable caused by a Checkout Session amount mismatch.';

	/**
	 * Logger instance in place before each test.
	 *
	 * @var WC_Logger
	 */
	private $original_logger;

	public function set_up(): void {
		parent::set_up();

		$this->original_logger = WC_Stripe_Logger::$logger;
		delete_option( self::MIGRATION_FLAG_OPTION );
		delete_option( self::AMOUNT_MISMATCH_OPTION );
		WC_Stripe_Helper::delete_main_stripe_settings();
	}

	public function tear_down(): void {
		WC_Stripe_Logger::$logger = $this->original_logger;
		delete_option( self::MIGRATION_FLAG_OPTION );
		delete_option( self::AMOUNT_MISMATCH_OPTION );
		WC_Stripe_Helper::delete_main_stripe_settings();

		parent::tear_down();
	}

	/**
	 * @dataProvider migration_scenarios_provider
	 *
	 * @param string|false $previous_version Previous plugin version, or false for a new install.
	 * @param string|null  $mismatch_marker Stored mismatch marker, or null when absent.
	 * @param string|null  $migration_flag Stored migration flag, or null when absent.
	 * @param string       $adaptive_pricing Initial Adaptive Pricing setting.
	 * @param string       $expected_adaptive_pricing Expected Adaptive Pricing setting after migration.
	 * @param string|false $expected_marker Expected mismatch marker after migration.
	 * @param string|false $expected_migration_flag Expected migration flag after migration.
	 * @param bool         $expect_log Whether the migration should log the restoration.
	 */
	public function test_migration_scenarios( $previous_version, ?string $mismatch_marker, ?string $migration_flag, string $adaptive_pricing, string $expected_adaptive_pricing, $expected_marker, $expected_migration_flag, bool $expect_log ): void {
		add_option(
			WC_Stripe::SETTINGS_OPTION_NAME,
			[
				'adaptive_pricing'           => $adaptive_pricing,
				'logging'                    => 'yes',
				'optimized_checkout_element' => 'no',
				'pmc_enabled'                => 'yes',
			]
		);

		if ( null !== $mismatch_marker ) {
			update_option( self::AMOUNT_MISMATCH_OPTION, $mismatch_marker, false );
		}
		if ( null !== $migration_flag ) {
			update_option( self::MIGRATION_FLAG_OPTION, $migration_flag, false );
		}

		$logger      = $this->createMock( WC_Logger::class );
		$expectation = $logger->expects( $expect_log ? $this->once() : $this->never() )->method( 'info' );

		if ( $expect_log ) {
			$expectation->with(
				self::LOG_MESSAGE,
				array_merge(
					WC_Stripe_Logger::LOG_CONTEXT,
					[ 'previous_version' => (string) $previous_version ]
				)
			);
		}

		WC_Stripe_Logger::$logger = $logger;

		$migration = new WC_Stripe_Restore_Adaptive_Pricing_After_Amount_Mismatch_Update();
		$migration->maybe_migrate( $previous_version );

		$stored_settings = WC_Stripe::get_instance()->get_settings();

		$this->assertSame( $expected_adaptive_pricing, $stored_settings['adaptive_pricing'] );
		$this->assertSame( 'yes', $stored_settings['pmc_enabled'], 'Unrelated settings must survive the migration.' );
		$this->assertSame( $expected_marker, get_option( self::AMOUNT_MISMATCH_OPTION ) );
		$this->assertSame( $expected_migration_flag, get_option( self::MIGRATION_FLAG_OPTION ) );
	}

	/**
	 * Data provider for {@see test_migration_scenarios()}.
	 *
	 * @return array
	 */
	public function migration_scenarios_provider(): array {
		return [
			'mismatch disable is restored on upgrade'        => [
				'previous_version'          => '10.8.5',
				'mismatch_marker'           => 'yes',
				'migration_flag'            => null,
				'adaptive_pricing'          => 'no',
				'expected_adaptive_pricing' => 'yes',
				'expected_marker'           => false,
				'expected_migration_flag'   => 'yes',
				'expect_log'                => true,
			],
			'manual disable is preserved'                    => [
				'previous_version'          => '10.8.5',
				'mismatch_marker'           => null,
				'migration_flag'            => null,
				'adaptive_pricing'          => 'no',
				'expected_adaptive_pricing' => 'no',
				'expected_marker'           => false,
				'expected_migration_flag'   => 'yes',
				'expect_log'                => false,
			],
			'non-mismatch marker is ignored'                 => [
				'previous_version'          => '10.8.5',
				'mismatch_marker'           => 'no',
				'migration_flag'            => null,
				'adaptive_pricing'          => 'no',
				'expected_adaptive_pricing' => 'no',
				'expected_marker'           => 'no',
				'expected_migration_flag'   => 'yes',
				'expect_log'                => false,
			],
			'new install is ignored'                         => [
				'previous_version'          => false,
				'mismatch_marker'           => 'yes',
				'migration_flag'            => null,
				'adaptive_pricing'          => 'no',
				'expected_adaptive_pricing' => 'no',
				'expected_marker'           => 'yes',
				'expected_migration_flag'   => 'yes',
				'expect_log'                => false,
			],
			'migration does not run after flag is set'       => [
				'previous_version'          => '10.9.0',
				'mismatch_marker'           => 'yes',
				'migration_flag'            => 'yes',
				'adaptive_pricing'          => 'no',
				'expected_adaptive_pricing' => 'no',
				'expected_marker'           => 'yes',
				'expected_migration_flag'   => 'yes',
				'expect_log'                => false,
			],
			'unfinished migration retries on a later update' => [
				'previous_version'          => '10.9.0',
				'mismatch_marker'           => 'yes',
				'migration_flag'            => null,
				'adaptive_pricing'          => 'no',
				'expected_adaptive_pricing' => 'yes',
				'expected_marker'           => false,
				'expected_migration_flag'   => 'yes',
				'expect_log'                => true,
			],
		];
	}

	/**
	 * A failed settings write must leave both the safety marker and migration
	 * flag available so a later plugin update can retry safely.
	 */
	public function test_failed_settings_write_remains_retryable(): void {
		add_option(
			WC_Stripe::SETTINGS_OPTION_NAME,
			[
				'adaptive_pricing' => 'no',
				'logging'          => 'yes',
			]
		);
		update_option( self::AMOUNT_MISMATCH_OPTION, 'yes', false );

		$prevent_ap_restore = static function ( $settings ) {
			$settings['adaptive_pricing'] = 'no';
			return $settings;
		};
		add_filter( 'pre_update_option_woocommerce_stripe_settings', $prevent_ap_restore, PHP_INT_MAX );

		$logger = $this->createMock( WC_Logger::class );
		$logger->expects( $this->never() )->method( 'info' );
		WC_Stripe_Logger::$logger = $logger;

		try {
			$migration = new WC_Stripe_Restore_Adaptive_Pricing_After_Amount_Mismatch_Update();
			$migration->maybe_migrate( '10.8.5' );
		} finally {
			remove_filter( 'pre_update_option_woocommerce_stripe_settings', $prevent_ap_restore, PHP_INT_MAX );
		}

		$this->assertSame( 'no', WC_Stripe::get_instance()->get_settings()['adaptive_pricing'] );
		$this->assertSame( 'yes', get_option( self::AMOUNT_MISMATCH_OPTION ) );
		$this->assertFalse( get_option( self::MIGRATION_FLAG_OPTION ) );
	}
}
