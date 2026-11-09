<?php

/**
 * Tests for WC_Stripe_Tracks.
 *
 * Class WC_Stripe_Tracks_Test.
 */
class WC_Stripe_Tracks_Test extends WP_UnitTestCase {
	use WC_Stripe_Tracks_Capture_Trait;

	/**
	 * @inheritDoc
	 */
	public function set_up() {
		parent::set_up();

		// The settings-update filter merges in defaults on save, which would make the
		// testmode value written by these tests depend on unrelated default settings.
		remove_filter( 'pre_update_option_woocommerce_stripe_settings', [ WC_Stripe::get_instance(), 'gateway_settings_update' ] );
		WC_Stripe_Helper::update_main_stripe_settings( [] );
	}

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		$this->stop_capturing_tracks_events();
		WC_Stripe_Helper::update_main_stripe_settings( [] );
		add_filter( 'pre_update_option_woocommerce_stripe_settings', [ WC_Stripe::get_instance(), 'gateway_settings_update' ], 10, 2 );

		parent::tear_down();
	}

	/**
	 * @dataProvider provide_is_tracking_enabled_scenarios
	 *
	 * @param string    $allow_tracking      Value of the `woocommerce_allow_tracking` option.
	 * @param bool|null $apply_user_tracking Value returned by the `woocommerce_apply_user_tracking` filter. Null means no filter.
	 * @param bool      $expected            Expected result.
	 */
	public function test_is_tracking_enabled( string $allow_tracking, ?bool $apply_user_tracking, bool $expected ): void {
		update_option( 'woocommerce_allow_tracking', $allow_tracking );
		if ( null === $apply_user_tracking ) {
			$user_tracking_filter = null;
		} else {
			$user_tracking_filter = $apply_user_tracking ? '__return_true' : '__return_false';
			add_filter( 'woocommerce_apply_user_tracking', $user_tracking_filter );
		}

		try {
			$this->assertSame( $expected, WC_Stripe_Tracks::is_tracking_enabled() );
		} finally {
			if ( null !== $user_tracking_filter ) {
				remove_filter( 'woocommerce_apply_user_tracking', $user_tracking_filter );
			}
			delete_option( 'woocommerce_allow_tracking' );
		}
	}

	/**
	 * Data provider for {@see test_is_tracking_enabled()}.
	 *
	 * @return array
	 */
	public function provide_is_tracking_enabled_scenarios(): array {
		return [
			'opted in, no filter'        => [ 'yes', null, true ],
			'opted in, filter on'        => [ 'yes', true, true ],
			'opted in and filtered off'  => [ 'yes', false, false ],
			'opted out, no filter'       => [ 'no', null, false ],
			'opted out, filter on'       => [ 'no', true, false ],
			'opted out and filtered off' => [ 'no', false, false ],
		];
	}

	/**
	 * @dataProvider provide_add_default_event_properties_scenarios
	 *
	 * @param string $testmode         Value of the `testmode` setting.
	 * @param array  $event_properties Properties passed in.
	 * @param array  $expected         Expected combined properties.
	 */
	public function test_add_default_event_properties( string $testmode, array $event_properties, array $expected ): void {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'testmode' => $testmode ] );

		$this->assertSame( $expected, WC_Stripe_Tracks::add_default_event_properties( $event_properties ) );
	}

	/**
	 * Data provider for test_add_default_event_properties().
	 *
	 * @return array
	 */
	public function provide_add_default_event_properties_scenarios(): array {
		return [
			'no properties, test mode'                  => [
				'yes',
				[],
				[
					'is_test_mode'   => true,
					'stripe_version' => WC_STRIPE_VERSION,
				],
			],
			'no properties, live mode'                  => [
				'no',
				[],
				[
					'is_test_mode'   => false,
					'stripe_version' => WC_STRIPE_VERSION,
				],
			],
			'extra properties are appended'             => [
				'yes',
				[
					'account_id' => 'acct_123',
					'enabled'    => false,
				],
				[
					'is_test_mode'   => true,
					'stripe_version' => WC_STRIPE_VERSION,
					'account_id'     => 'acct_123',
					'enabled'        => false,
				],
			],
			'supplied properties override the defaults' => [
				'yes',
				[
					'is_test_mode'   => false,
					'stripe_version' => '1.2.3',
					'source'         => 'settings',
				],
				[
					'is_test_mode'   => false,
					'stripe_version' => '1.2.3',
					'source'         => 'settings',
				],
			],
		];
	}

	public function test_record_direct_event_records_the_supplied_properties_when_tracking_is_enabled(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$this->start_capturing_tracks_events();

		WC_Stripe_Tracks::record_direct_event( 'wcstripe_test_event', [ 'foo' => 'bar' ] );

		$events = $this->get_captured_tracks_event_properties( 'wcadmin_wcstripe_test_event' );
		$this->assertCount( 1, $events );
		$this->assertSame( 'bar', $events[0]['foo'] );
		// Defaults are opt-in via add_default_event_properties(), not added implicitly.
		$this->assertArrayNotHasKey( 'stripe_version', $events[0] );
	}

	public function test_record_direct_event_does_not_record_when_tracking_is_disabled(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$this->start_capturing_tracks_events();
		add_filter( 'woocommerce_apply_user_tracking', '__return_false' );

		try {
			WC_Stripe_Tracks::record_direct_event( 'wcstripe_test_event', [ 'foo' => 'bar' ] );

			$this->assertSame( [], $this->get_captured_tracks_event_properties( 'wcadmin_wcstripe_test_event' ) );
		} finally {
			remove_filter( 'woocommerce_apply_user_tracking', '__return_false' );
		}
	}
}
