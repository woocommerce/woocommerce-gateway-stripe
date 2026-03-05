<?php

namespace WooCommerce\Stripe\Tests;

use WC_Stripe_Connect;
use WC_Stripe_Connect_API;
use WC_Stripe_Database_Cache;
use WP_UnitTestCase;

/**
 * Tests for WC_Stripe_Connect.
 *
 * @package WooCommerce\Stripe\Tests
 */
class WC_Stripe_Connect_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Stripe_Connect_API|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_api;

	/**
	 * @var WC_Stripe_Connect
	 */
	private $connect;

	public function set_up() {
		parent::set_up();

		$this->mock_api = $this->getMockBuilder( WC_Stripe_Connect_API::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_stripe_oauth_init', 'get_stripe_oauth_keys' ] )
			->getMock();

		$this->connect = new WC_Stripe_Connect( $this->mock_api );
	}

	public function tear_down() {
		unset(
			$_GET['wcs_stripe_code'],
			$_GET['wcs_stripe_state'],
			$_GET['wcs_stripe_mode'],
			$_GET['_wpnonce'],
			$_GET['stripe_connect_popup']
		);

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// get_oauth_url tests
	// -------------------------------------------------------------------------

	/**
	 * get_oauth_url() with $new_tab = true should include stripe_connect_popup=1
	 * in the return URL before the _wpnonce is appended.
	 */
	public function test_get_oauth_url_with_new_tab_adds_popup_param() {
		$api_result           = new \stdClass();
		$api_result->oauthUrl = 'https://connect.stripe.com/oauth/v2/authorize?state=abc'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$api_result->state    = 'abc';
		$api_result->type     = 'connect';

		$this->mock_api
			->expects( $this->once() )
			->method( 'get_stripe_oauth_init' )
			->with(
				$this->callback(
					function ( $return_url ) {
						return false !== strpos( $return_url, 'stripe_connect_popup=1' );
					}
				),
				'test'
			)
			->willReturn( $api_result );

		$url = $this->connect->get_oauth_url( '', 'test', true );

		$this->assertNotInstanceOf( \WP_Error::class, $url );
		$this->assertEquals( 'https://connect.stripe.com/oauth/v2/authorize?state=abc', $url );
	}

	/**
	 * get_oauth_url() with $new_tab = false (default) should NOT include stripe_connect_popup param.
	 */
	public function test_get_oauth_url_without_new_tab_does_not_add_popup_param() {
		$api_result           = new \stdClass();
		$api_result->oauthUrl = 'https://connect.stripe.com/oauth/v2/authorize?state=xyz'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$api_result->state    = 'xyz';
		$api_result->type     = 'connect';

		$this->mock_api
			->expects( $this->once() )
			->method( 'get_stripe_oauth_init' )
			->with(
				$this->callback(
					function ( $return_url ) {
						return false === strpos( $return_url, 'stripe_connect_popup' );
					}
				),
				'test'
			)
			->willReturn( $api_result );

		$url = $this->connect->get_oauth_url( '', 'test', false );

		$this->assertNotInstanceOf( \WP_Error::class, $url );
	}

	// -------------------------------------------------------------------------
	// maybe_handle_redirect tests
	// -------------------------------------------------------------------------

	/**
	 * maybe_handle_redirect() with stripe_connect_popup=1 should call
	 * output_popup_completion_page() with the correct arguments.
	 *
	 * Uses a partial mock so output_popup_completion_page() does not call exit().
	 * connect_oauth() is also mocked to avoid the full key-saving flow in tests.
	 */
	public function test_maybe_handle_redirect_popup_calls_completion_page() {
		set_current_screen( 'dashboard' );
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$nonce = wp_create_nonce( 'wcs_stripe_connected' );
		$state = 'test_state_123';

		$_GET['wcs_stripe_code']      = 'test_code_abc';
		$_GET['wcs_stripe_state']     = $state;
		$_GET['wcs_stripe_mode']      = 'test';
		$_GET['_wpnonce']             = $nonce;
		$_GET['stripe_connect_popup'] = '1';

		$connect = $this->getMockBuilder( WC_Stripe_Connect::class )
			->setConstructorArgs( [ $this->mock_api ] )
			->setMethods( [ 'connect_oauth', 'output_popup_completion_page' ] )
			->getMock();

		$connect->method( 'connect_oauth' )
			->willReturn( new \stdClass() );

		$connect
			->expects( $this->once() )
			->method( 'output_popup_completion_page' )
			->with( true, 'test' );

		$connect->maybe_handle_redirect();
	}

	/**
	 * get_popup_completion_html() should return HTML containing the expected JS.
	 *
	 * output_popup_completion_page() is mocked here to avoid exit(); we call
	 * get_popup_completion_html() directly via a partial mock that exposes it.
	 */
	public function test_popup_completion_html_contains_postmessage() {
		$reflection = new \ReflectionClass( $this->connect );
		$method     = $reflection->getMethod( 'get_popup_completion_html' );
		$method->setAccessible( true );

		$html = $method->invoke( $this->connect, true, 'test' );

		$this->assertStringContainsString( 'postMessage', $html );
		$this->assertStringContainsString( 'window.close', $html );
		$this->assertStringContainsString( 'wc_stripe_oauth_connected', $html );
	}

	/**
	 * maybe_handle_redirect() without stripe_connect_popup should perform a normal
	 * wp_safe_redirect (existing path, no regression).
	 *
	 * connect_oauth() is mocked to avoid the full key-saving flow. The
	 * wp_redirect filter throws to intercept the redirect before exit() runs.
	 */
	public function test_maybe_handle_redirect_without_popup_does_normal_redirect() {
		set_current_screen( 'dashboard' );
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$nonce = wp_create_nonce( 'wcs_stripe_connected' );
		$state = 'state_for_redirect_test';

		$_GET['wcs_stripe_code']  = 'code_for_redirect_test';
		$_GET['wcs_stripe_state'] = $state;
		$_GET['wcs_stripe_mode']  = 'test';
		$_GET['_wpnonce']         = $nonce;
		unset( $_GET['stripe_connect_popup'] );

		$connect = $this->getMockBuilder( WC_Stripe_Connect::class )
			->setConstructorArgs( [ $this->mock_api ] )
			->setMethods( [ 'connect_oauth' ] )
			->getMock();

		$connect->method( 'connect_oauth' )
			->willReturn( new \stdClass() );

		// Capture the redirect URL and abort via exception to prevent exit().
		$redirect_url = null;
		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$redirect_url ) {
				$redirect_url = $location;
				throw new \Exception( 'redirect intercepted' );
			},
			99
		);

		try {
			$connect->maybe_handle_redirect();
		} catch ( \Exception $e ) {
			// Expected: thrown by the wp_redirect filter to prevent exit().
			unset( $e );
		}

		$this->assertNotNull( $redirect_url );
		$this->assertStringNotContainsString( 'stripe_connect_popup', (string) $redirect_url );
	}
}
