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
					'is_test_mode'   => 1,
					'stripe_version' => WC_STRIPE_VERSION,
				],
			],
			'no properties, live mode'                  => [
				'no',
				[],
				[
					'is_test_mode'   => 0,
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
					'is_test_mode'   => 1,
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

	/**
	 * @dataProvider provide_record_direct_event_without_shopper_details_scenarios
	 *
	 * @param string|null $role                   Role of the current user; null for a logged-out visitor.
	 * @param bool        $doing_ajax             Whether the request is an Ajax request rather than a rendered admin page.
	 * @param bool        $expect_request_details Whether the request details should be sent.
	 */
	public function test_record_direct_event_without_shopper_details( ?string $role, bool $doing_ajax, bool $expect_request_details ): void {
		wp_set_current_user( null === $role ? 0 : $this->factory->user->create( [ 'role' => $role ] ) );
		$this->start_capturing_tracks_events();
		$footer_pixel_count = count( WC_Tracks_Footer_Pixel::get_events() );

		$events = $this->record_and_capture_sent_events(
			function () {
				WC_Stripe_Tracks::record_direct_event_without_shopper_details( 'wcstripe_test_event', [ 'foo' => 'bar' ] );
			},
			$doing_ajax
		);

		$this->assertCount( 1, $events );
		$this->assertSame( 'wcadmin_wcstripe_test_event', $events[0]['_en'] );
		$this->assertSame( 'bar', $events[0]['foo'] );
		// A footer pixel is requested by the browser, which sends its own IP and user agent.
		$this->assertCount( $footer_pixel_count, WC_Tracks_Footer_Pixel::get_events() );

		$request_details = [
			'_via_ua' => 'Shopper Browser/1.0',
			'_via_ip' => '203.0.113.7',
			'_lg'     => 'en-GB',
			'_dr'     => 'https://shop.example/checkout/',
			'_dl'     => 'https://shop.example/wp-admin/admin-ajax.php',
		];
		foreach ( $request_details as $property => $value ) {
			if ( $expect_request_details ) {
				$this->assertSame( $value, $events[0][ $property ] ?? null, $property );
			} else {
				$this->assertArrayNotHasKey( $property, $events[0] );
			}
		}
	}

	/**
	 * Data provider for {@see test_record_direct_event_without_shopper_details()}.
	 *
	 * @return array
	 */
	public function provide_record_direct_event_without_shopper_details_scenarios(): array {
		return [
			'logged out, ajax'      => [ null, true, false ],
			'customer, ajax'        => [ 'customer', true, false ],
			'editor, ajax'          => [ 'editor', true, false ],
			'editor, rendered page' => [ 'editor', false, false ],
			'shop manager, ajax'    => [ 'shop_manager', true, true ],
			'administrator, ajax'   => [ 'administrator', true, true ],
		];
	}

	public function test_record_direct_event_without_shopper_details_does_not_record_when_tracking_is_disabled(): void {
		wp_set_current_user( 0 );
		$this->start_capturing_tracks_events();
		add_filter( 'woocommerce_apply_user_tracking', '__return_false' );

		try {
			$events = $this->record_and_capture_sent_events(
				function () {
					WC_Stripe_Tracks::record_direct_event_without_shopper_details( 'wcstripe_test_event', [ 'foo' => 'bar' ] );
				}
			);

			$this->assertSame( [], $events );
		} finally {
			remove_filter( 'woocommerce_apply_user_tracking', '__return_false' );
		}
	}

	/**
	 * Runs the callback as a request with a shopper's request details and returns the query arguments
	 * of every Tracks pixel the server sends. The request details are only visible in the pixel,
	 * because WooCommerce adds them after the event property filters run.
	 *
	 * @param callable $record     Records the events.
	 * @param bool     $doing_ajax Whether to run as an Ajax request rather than a rendered admin page.
	 * @return array[] One array of pixel query arguments per sent event.
	 */
	private function record_and_capture_sent_events( callable $record, bool $doing_ajax = true ): array {
		$original_server = $_SERVER;
		$_SERVER         = array_merge(
			$_SERVER,
			[
				'HTTP_USER_AGENT'      => 'Shopper Browser/1.0',
				'REMOTE_ADDR'          => '203.0.113.7',
				'HTTP_ACCEPT_LANGUAGE' => 'en-GB',
				'HTTP_REFERER'         => 'https://shop.example/checkout/',
				'REQUEST_SCHEME'       => 'https',
				'HTTP_HOST'            => 'shop.example',
				'REQUEST_URI'          => '/wp-admin/admin-ajax.php',
			]
		);

		$events      = [];
		$capture_pix = function ( $preempt, $args, $url ) use ( &$events ) {
			if ( 0 !== strpos( $url, WC_Tracks_Client::PIXEL ) ) {
				return $preempt;
			}

			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$events[] = $query;

			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			];
		};

		// WC_Tracks_Event::record() only sends from the server for Ajax requests; on a rendered admin
		// page it queues the pixel for the footer instead.
		$doing_ajax_filter = $doing_ajax ? '__return_true' : '__return_false';
		add_filter( 'wp_doing_ajax', $doing_ajax_filter );
		add_filter( 'pre_http_request', $capture_pix, 10, 3 );

		try {
			$record();
		} finally {
			remove_filter( 'pre_http_request', $capture_pix, 10 );
			remove_filter( 'wp_doing_ajax', $doing_ajax_filter );
			$_SERVER = $original_server;
		}

		return $events;
	}
}
