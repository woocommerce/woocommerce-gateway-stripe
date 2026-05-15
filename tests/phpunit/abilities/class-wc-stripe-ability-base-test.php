<?php
/**
 * Tests for the WC_Stripe_Ability_Base error-path helpers.
 *
 * Covers `delegate_to_rest_controller()` and `retrieve_from_stripe()` branches
 * directly, so a regression that silently returns `null` or swallows a typed
 * error cannot ship green.
 *
 * @package WooCommerce_Stripe
 */

/**
 * Concrete subclass that exposes the protected helpers for direct testing.
 *
 * Lives only in this test file — production code never instantiates it.
 */
// phpcs:disable WordPress.Files.FileName -- Test-only fixture class.
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Fixture and test class colocated for readability.
class WC_Stripe_Ability_Base_Test_Fixture extends WC_Stripe_Ability_Base {
	public static function call_delegate( string $controller_class, string $method = 'GET', string $route = '/__bogus__' ) {
		return self::delegate_to_rest_controller( $controller_class, $method, $route );
	}

	public static function call_retrieve( string $path ) {
		return self::retrieve_from_stripe( $path );
	}

	public static function call_build_query( array $params ): string {
		return self::build_stripe_query_string( $params );
	}
}
// phpcs:enable WordPress.Files.FileName

/**
 * @covers WC_Stripe_Ability_Base
 */
class WC_Stripe_Ability_Base_Test extends WP_UnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		if ( class_exists( 'WC_Stripe_Database_Cache' ) && defined( 'WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY' ) ) {
			WC_Stripe_Database_Cache::delete( WC_Stripe_API::INVALID_API_KEY_ERROR_COUNT_CACHE_KEY );
		}
		parent::tearDown();
	}

	public function test_delegate_returns_wp_error_when_controller_class_missing() {
		$result = WC_Stripe_Ability_Base_Test_Fixture::call_delegate( 'Nonexistent_Controller_Class_Definitely_Not_Loaded' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wc_stripe_missing_controller', $result->get_error_code() );
		$this->assertSame( 500, $result->get_error_data()['status'] ?? null );
	}

	public function test_retrieve_returns_wp_error_when_stripe_api_returns_null() {
		// WC_Stripe_API returns `null` for every 401 from Stripe (the UI relies
		// on null to render the connect-keys state). The ability layer must
		// surface that as a typed WP_Error rather than leaking the null
		// upstream.
		if ( ! class_exists( 'WC_Stripe_API' ) ) {
			$this->markTestSkipped( 'WC_Stripe_API not loaded in this environment.' );
		}

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 401,
						'message' => 'Unauthorized',
					],
					'body'     => '{"error":{"type":"invalid_request_error","message":"Invalid API Key provided"}}',
					'headers'  => [],
					'cookies'  => [],
				];
			}
		);

		$result = WC_Stripe_Ability_Base_Test_Fixture::call_retrieve( 'balance' );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'retrieve_from_stripe() must convert a null WC_Stripe_API response into a WP_Error rather than leaking the null upstream.'
		);
		$this->assertSame( 'wc_stripe_api_unauthenticated', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] ?? null );
	}

	public function test_retrieve_surfaces_stripe_api_error_payload() {
		if ( ! class_exists( 'WC_Stripe_API' ) ) {
			$this->markTestSkipped( 'WC_Stripe_API not loaded in this environment.' );
		}

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 402,
						'message' => 'Payment Required',
					],
					'body'     => '{"error":{"code":"card_declined","message":"Your card was declined."}}',
					'headers'  => [],
					'cookies'  => [],
				];
			}
		);

		$result = WC_Stripe_Ability_Base_Test_Fixture::call_retrieve( 'charges/ch_test_xxx' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'card_declined', $result->get_error_code() );
		$this->assertSame( 'Your card was declined.', $result->get_error_message() );
	}

	public function test_retrieve_returns_array_on_success() {
		if ( ! class_exists( 'WC_Stripe_API' ) ) {
			$this->markTestSkipped( 'WC_Stripe_API not loaded in this environment.' );
		}

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => '{"id":"acct_123","object":"account","email":"test@example.com"}',
					'headers'  => [],
					'cookies'  => [],
				];
			}
		);

		$result = WC_Stripe_Ability_Base_Test_Fixture::call_retrieve( 'account' );

		$this->assertIsArray( $result );
		$this->assertSame( 'acct_123', $result['id'] );
		$this->assertSame( 'account', $result['object'] );
	}

	public function test_build_query_string_filters_null_and_empty_values_and_keeps_brackets() {
		$result = WC_Stripe_Ability_Base_Test_Fixture::call_build_query(
			[
				'limit'          => 10,
				'starting_after' => null,
				'ending_before'  => '',
				'created'        => [ 'gte' => 1700000000 ],
			]
		);

		$this->assertStringStartsWith( '?', $result );
		$this->assertStringContainsString( 'limit=10', $result );
		$this->assertStringNotContainsString( 'starting_after', $result );
		$this->assertStringNotContainsString( 'ending_before', $result );
		// http_build_query encodes brackets; either raw or %5B/%5D is acceptable
		// here since the base method uses PHP_QUERY_RFC3986.
		$this->assertMatchesRegularExpression( '/created(\[|%5B)gte(\]|%5D)=1700000000/', $result );
	}

	public function test_build_query_string_returns_empty_when_nothing_remains() {
		$result = WC_Stripe_Ability_Base_Test_Fixture::call_build_query(
			[
				'limit'          => null,
				'starting_after' => '',
			]
		);

		$this->assertSame( '', $result );
	}
}
