<?php

/**
 * @package WooCommerce/Stripe/Tests
 */
class WC_Stripe_Webhook_Settings_Test extends WP_UnitTestCase {
	private $webhook_settings;
	private $endpoint;
	private $requests;
	private $http_filter;
	private $lookup_error;
	private $creation_error;
	private $listed_endpoints;

	public function set_up() {
		parent::set_up();
		$this->webhook_settings = new WC_Stripe_Webhook_Settings( WC_Stripe_API::class );
		$this->requests         = [];
		$this->lookup_error     = null;
		$this->creation_error   = null;
		$this->listed_endpoints = [];
		$this->endpoint         = [
			'id'             => 'we_old',
			'url'            => WC_Stripe_Helper::get_webhook_url(),
			'enabled_events' => WC_Stripe_Webhook_Settings::WEBHOOK_EVENTS,
			'api_version'    => WC_Stripe_API::STRIPE_API_VERSION,
			'status'         => 'enabled',
		];
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'testmode'            => 'yes',
				'secret_key'          => 'sk_live_current',
				'test_secret_key'     => 'sk_test_current',
				'webhook_data'        => [
					'id'  => 'we_old',
					'url' => $this->endpoint['url'],
				],
				'test_webhook_data'   => [
					'id'  => 'we_old',
					'url' => $this->endpoint['url'],
				],
				'webhook_secret'      => 'whsec_old',
				'test_webhook_secret' => 'whsec_old',
			]
		);
		WC_Stripe_API::set_secret_key( 'sk_caller' );
		$this->http_filter = function ( $preempt, $args, $url ) {
			if ( false === strpos( $url, 'https://api.stripe.com/v1/webhook_endpoints' ) ) {
				return $preempt;
			}
			$this->requests[] = [
				'method'   => $args['method'],
				'id'       => basename( $url ),
				'key'      => WC_Stripe_API::get_secret_key(),
				'body'     => $args['body'] ?? [],
				'settings' => WC_Stripe_Helper::get_stripe_settings(),
			];
			if ( 'DELETE' === $args['method'] ) {
				$body = [ 'deleted' => true ];
			} elseif ( 'POST' === $args['method'] ) {
				if ( $this->creation_error ) {
					return $this->creation_error;
				}
				$this->endpoint = array_merge(
					$args['body'],
					[
						'id'     => 'we_new',
						'status' => 'enabled',
					]
				);
				$body           = array_merge( $this->endpoint, [ 'secret' => 'whsec_new' ] );
			} elseif ( 'webhook_endpoints' === basename( $url ) ) {
				$body = [ 'data' => $this->listed_endpoints ];
			} elseif ( $this->lookup_error ) {
				return $this->lookup_error;
			} else {
				$body = $this->endpoint;
			}
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( $body ),
			];
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', $this->http_filter );
		WC_Stripe_API::set_secret_key( '' );
		foreach ( [ 'live', 'test' ] as $mode ) {
			WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Webhook_Settings::WEBHOOK_STATUS_CACHE_KEY, $mode );
		}
		parent::tear_down();
	}

	/** @dataProvider provide_reconciliation_cases */
	public function test_reconciliation( $mode, $change, $replace ) {
		$prefix       = 'test' === $mode ? 'test_' : '';
		$other_prefix = 'test' === $mode ? '' : 'test_';
		$settings     = WC_Stripe_Helper::get_stripe_settings();
		$force        = 'force' === $change;
		switch ( $change ) {
			case 'url':
				$this->endpoint['url'] = 'https://old.example.com/?wc-api=wc_stripe';
				break;
			case 'query':
				$this->endpoint['url'] = strtok( $this->endpoint['url'], '?' );
				break;
			case 'events':
				$this->endpoint['enabled_events'] = [ 'charge.succeeded' ];
				break;
			case 'event order':
				$this->endpoint['enabled_events'] = array_reverse( $this->endpoint['enabled_events'] );
				break;
			case 'version':
				$this->endpoint['api_version'] = '2019-12-03';
				break;
			case 'default version':
				$this->endpoint['api_version'] = null;
				break;
			case 'disabled':
				$this->endpoint['status'] = 'disabled';
				break;
			case 'secret':
				$settings[ $prefix . 'webhook_secret' ] = '';
				break;
			case 'unconfigured':
				$settings[ $prefix . 'webhook_data' ] = [];
				break;
			case 'deleted':
				$this->lookup_error = [
					'response' => [ 'code' => 404 ],
					'body'     => wp_json_encode( [ 'error' => [ 'code' => 'resource_missing' ] ] ),
				];
				break;
		}
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		WC_Stripe_API::set_secret_key( 'sk_caller' );
		WC_Stripe_Database_Cache::set_with_mode( WC_Stripe_Webhook_Settings::WEBHOOK_STATUS_CACHE_KEY, 'disabled', HOUR_IN_SECONDS, $mode );
		$result = $this->webhook_settings->maybe_autoconfigure_webhooks( $mode, $force );
		$this->assertSame( 'sk_caller', WC_Stripe_API::get_secret_key() );
		$stored = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $settings[ $other_prefix . 'webhook_data' ], $stored[ $other_prefix . 'webhook_data' ] );
		$this->assertSame( $settings[ $other_prefix . 'webhook_secret' ], $stored[ $other_prefix . 'webhook_secret' ] );
		if ( $replace ) {
			$this->assertSame( 'we_new', $result->id );
			$this->assertSame( 'whsec_new', $result->secret );
			$this->assertSame(
				[
					'id'  => 'we_new',
					'url' => WC_Stripe_Helper::get_webhook_url(),
				],
				$stored[ $prefix . 'webhook_data' ]
			);
			$this->assertSame( 'whsec_new', $stored[ $prefix . 'webhook_secret' ] );
			$this->assertNull( WC_Stripe_Database_Cache::get_with_mode( WC_Stripe_Webhook_Settings::WEBHOOK_STATUS_CACHE_KEY, $mode ) );
		} else {
			$this->assertNull( $result );
			$this->assertSame( $settings, $stored );
			$this->assertTrue( $this->webhook_settings->is_webhook_enabled( $mode ) );
			$this->assertSame( [ 'GET' ], array_column( $this->requests, 'method' ) );
		}
		foreach ( $this->requests as $request ) {
			$this->assertSame( 'sk_' . $mode . '_current', $request['key'] );
			if ( 'DELETE' === $request['method'] ) {
				$this->assertSame( 'whsec_new', $request['settings'][ $prefix . 'webhook_secret' ] );
				$this->assertSame( 'we_new', $request['settings'][ $prefix . 'webhook_data' ]['id'] );
			}
		}
		if ( $replace && ! in_array( $change, [ 'unconfigured', 'deleted' ], true ) ) {
			$this->assertContains( 'DELETE', array_column( $this->requests, 'method' ) );
		} else {
			$this->assertNotContains( 'DELETE', array_column( $this->requests, 'method' ) );
		}
		$this->requests     = [];
		$this->lookup_error = null;
		$this->assertNull( $this->webhook_settings->maybe_autoconfigure_webhooks( $mode ) );
		$this->assertSame( [ 'GET' ], array_column( $this->requests, 'method' ) );
	}

	public function provide_reconciliation_cases() {
		$cases = [];
		foreach ( [ 'live', 'test' ] as $mode ) {
			foreach ( [ 'healthy', 'event order', 'url', 'query', 'events', 'version', 'default version', 'disabled', 'secret', 'unconfigured', 'deleted', 'force' ] as $change ) {
				$cases[ $mode . ' ' . $change ] = [ $mode, $change, ! in_array( $change, [ 'healthy', 'event order' ], true ) ];
			}
		}
		return $cases;
	}

	/** @dataProvider provide_api_failures */
	public function test_api_failure_preserves_configuration( $failure, $during_creation ) {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		if ( $during_creation ) {
			$this->creation_error = $failure;
		} else {
			$this->lookup_error = $failure;
		}
		$this->expectException( Exception::class );
		try {
			$this->webhook_settings->maybe_autoconfigure_webhooks( 'test', $during_creation );
		} finally {
			$this->assertSame( $settings, WC_Stripe_Helper::get_stripe_settings() );
			$this->assertSame( [ $during_creation ? 'POST' : 'GET' ], array_column( $this->requests, 'method' ) );
			$this->assertSame( 'sk_caller', WC_Stripe_API::get_secret_key() );
		}
	}

	public function provide_api_failures() {
		$cases = [];
		foreach ( [ false, true ] as $during_creation ) {
			foreach ( [ 'authentication_error', 'rate_limit' ] as $error ) {
				$cases[ $error . ( $during_creation ? ' creation' : ' lookup' ) ] = [
					[
						'response' => [ 'code' => 401 ],
						'body'     => wp_json_encode(
							[
								'error' => [
									'code'    => $error,
									'message' => 'API error',
								],
							]
						),
					],
					$during_creation,
				];
			}
			$cases[ 'network ' . (int) $during_creation ]          = [ new WP_Error( 'http_request_failed', 'Network error' ), $during_creation ];
			$cases[ 'invalid response ' . (int) $during_creation ] = [
				[
					'response' => [ 'code' => 200 ],
					'body'     => '{}',
				],
				$during_creation,
			];
		}
		return $cases;
	}


	/** @dataProvider provide_decommission_responses */
	public function test_decommission_retains_credentials_only_on_failure( $response, $cleared ) {
		$previous                     = WC_Stripe_Helper::get_stripe_settings();
		$candidate                    = $previous;
		$candidate['test_secret_key'] = 'sk_test_new';
		$filter                       = static function () use ( $response ) {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( $response ),
			];
		};
		add_filter( 'pre_http_request', $filter, 20 );
		$result = $this->webhook_settings->decommission_for_key_change( $previous, $candidate, [ 'test' ] );
		remove_filter( 'pre_http_request', $filter, 20 );
		$expected_data           = $previous['test_webhook_data'];
		$expected_data['secret'] = 'sk_test_current';
		$this->assertSame( $cleared ? [] : $expected_data, $result['test_webhook_data'] );
		$this->assertSame( $cleared ? '' : 'whsec_old', $result['test_webhook_secret'] );
		$this->assertSame( $previous, WC_Stripe_Helper::get_stripe_settings() );
		$this->assertSame( [ 'DELETE' ], array_column( $this->requests, 'method' ) );
		$this->assertSame( 'sk_test_current', $this->requests[0]['key'] );
		$this->assertSame( 'sk_caller', WC_Stripe_API::get_secret_key() );
	}

	public function provide_decommission_responses() {
		return [
			'deleted'         => [ [ 'deleted' => true ], true ],
			'already missing' => [ [ 'error' => [ 'code' => 'resource_missing' ] ], true ],
			'rate limited'    => [ [ 'error' => [ 'code' => 'rate_limit' ] ], false ],
		];
	}

	public function test_missing_credentials_skip_without_using_the_other_mode() {
		$settings                    = WC_Stripe_Helper::get_stripe_settings();
		$settings['test_secret_key'] = '';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		$this->assertNull( $this->webhook_settings->maybe_autoconfigure_webhooks( 'test' ) );
		$this->assertSame( [], $this->requests );
		$this->expectException( Exception::class );
		$this->webhook_settings->maybe_autoconfigure_webhooks( 'test', true );
	}

	public function test_agentic_api_version() {
		add_filter( 'wc_stripe_is_agentic_commerce_enabled', '__return_true' );
		$result = $this->webhook_settings->maybe_autoconfigure_webhooks( 'test' );
		$this->assertSame( WC_Stripe_API::AGENTIC_COMMERCE_API_VERSION, $result->api_version );
		$this->requests = [];
		$this->assertNull( $this->webhook_settings->maybe_autoconfigure_webhooks( 'test' ) );
		$this->assertSame( [ 'GET' ], array_column( $this->requests, 'method' ) );
	}


	/** @dataProvider provide_cleanup_failures */
	public function test_cleanup_failure_does_not_fail_saved_configuration( $failure ) {
		$filter = static function ( $preempt, $args ) use ( $failure ) {
			if ( 'DELETE' === $args['method'] ) {
				if ( 'exception' === $failure ) {
					throw new Exception( 'Network error' );
				}
				return [
					'response' => [ 'code' => 400 ],
					'body'     => wp_json_encode( [ 'error' => [ 'code' => $failure ] ] ),
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $filter, 20, 2 );
		try {
			$result = $this->webhook_settings->maybe_autoconfigure_webhooks( 'test', true );
		} finally {
			remove_filter( 'pre_http_request', $filter, 20 );
		}
		$this->assertSame( 'whsec_new', $result->secret );
		$this->assertSame( 'we_new', WC_Stripe_Helper::get_stripe_settings()['test_webhook_data']['id'] );
		$this->assertSame( 'whsec_new', WC_Stripe_Helper::get_stripe_settings()['test_webhook_secret'] );
		$this->assertSame( 'sk_caller', WC_Stripe_API::get_secret_key() );
	}

	public function provide_cleanup_failures() {
		return [ [ 'exception' ], [ 'rate_limit' ], [ 'resource_missing' ] ];
	}

	public function test_cleanup_preserves_new_and_unrelated_endpoints() {
		$url                    = WC_Stripe_Helper::get_webhook_url();
		$this->listed_endpoints = [
			[
				'id'  => 'we_old',
				'url' => $url,
			],
			[
				'id'  => 'we_new',
				'url' => $url,
			],
			[
				'id'  => 'we_duplicate',
				'url' => $url . '&foo=bar',
			],
			[
				'id'  => 'we_other',
				'url' => 'https://other.example.com',
			],
			[ 'url' => $url ],
		];
		$this->webhook_settings->maybe_autoconfigure_webhooks( 'test', true );
		$deletions = array_filter(
			$this->requests,
			static function ( $request ) {
				return 'DELETE' === $request['method'];
			}
		);
		$this->assertSame( [ 'we_old', 'we_duplicate' ], array_values( array_column( $deletions, 'id' ) ) );
	}

	/**
	 * Status checks use the matching account key, including legacy webhook credentials.
	 *
	 * @dataProvider provide_webhook_status_credentials
	 */
	public function test_webhook_status_authentication( $mode, $legacy_secret, $account_secret, $expected_secret ) {
		$prefix                               = 'test' === $mode ? 'test_' : '';
		$settings                             = WC_Stripe_Helper::get_stripe_settings();
		$settings['testmode']                 = 'test' === $mode ? 'yes' : 'no';
		$settings[ $prefix . 'secret_key' ]   = $account_secret;
		$settings[ $prefix . 'webhook_data' ] = [ 'id' => 'we_status' ];
		if ( null !== $legacy_secret ) {
			$settings[ $prefix . 'webhook_data' ]['secret'] = $legacy_secret;
		}
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Webhook_Settings::WEBHOOK_STATUS_CACHE_KEY, $mode );
		WC_Stripe_API::set_secret_key( '' );
		$headers = [];
		$filter  = function ( $preempt, $args, $url ) use ( &$headers ) {
			$headers[] = $args['headers']['Authorization'];
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [ 'status' => 'enabled' ] ),
			];
		};
		add_filter( 'pre_http_request', $filter, 20, 3 );
		try {
			$account = new WC_Stripe_Webhook_Settings( WC_Stripe_API::class );
			$this->assertSame( '' !== $expected_secret, $account->is_webhook_enabled() );
			$this->assertSame( '' !== $expected_secret, $account->is_webhook_enabled() );
		} finally {
			remove_filter( 'pre_http_request', $filter, 20 );
		}
		$this->assertSame( '' === $expected_secret ? [] : [ 'Basic ' . base64_encode( $expected_secret . ':' ) ], $headers );
		$this->assertSame( $account_secret, WC_Stripe_API::get_secret_key() );
		WC_Stripe_API::set_secret_key( '' );
	}

	public function provide_webhook_status_credentials() {
		return [
			'live account key' => [ 'live', null, 'sk_live_current', 'sk_live_current' ],
			'test account key' => [ 'test', null, 'sk_test_current', 'sk_test_current' ],
			'empty live copy'  => [ 'live', '', 'sk_live_current', 'sk_live_current' ],
			'empty test copy'  => [ 'test', '', 'sk_test_current', 'sk_test_current' ],
			'legacy live key'  => [ 'live', 'sk_live_old', 'sk_live_current', 'sk_live_old' ],
			'legacy test key'  => [ 'test', 'sk_test_old', 'sk_test_current', 'sk_test_old' ],
			'missing live key' => [ 'live', null, '', '' ],
			'missing test key' => [ 'test', null, '', '' ],
			'empty live keys'  => [ 'live', '', '', '' ],
			'empty test keys'  => [ 'test', '', '', '' ],
		];
	}
}
