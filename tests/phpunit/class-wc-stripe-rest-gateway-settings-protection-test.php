<?php
/**
 * @package WooCommerce/Stripe
 */

/**
 * Tests for WC_Stripe_REST_Gateway_Settings_Protection.
 */
class WC_Stripe_REST_Gateway_Settings_Protection_Test extends WP_UnitTestCase {

	public function tear_down() {
		unset( $GLOBALS['wp_rest_auth_cookie'], $_COOKIE[ LOGGED_IN_COOKIE ], $_SERVER['HTTP_X_WP_NONCE'] );

		$this->reload_stripe_gateway_settings();

		parent::tear_down();
	}

	/**
	 * Builds a Stripe gateway REST response with the given settings map.
	 *
	 * @param array $settings Settings keyed by field id, each `[ 'type' => ..., 'value' => ... ]`.
	 * @return WP_REST_Response
	 */
	private function build_response( array $settings ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'id'       => 'stripe',
				'settings' => $settings,
			]
		);
	}

	/**
	 * A Stripe gateway double (instanceof WC_Stripe_Payment_Gateway) with a pinned option key.
	 *
	 * @param array $settings Optional settings keyed by field id; each entry's `type` becomes a form field, as the read path checks form fields.
	 * @return WC_Stripe_Payment_Gateway
	 */
	private function get_stripe_gateway_double( array $settings = [] ) {
		$gateway = $this->getMockBuilder( WC_Stripe_Payment_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_option_key' ] )
			->getMockForAbstractClass();
		$gateway->method( 'get_option_key' )->willReturn( 'woocommerce_stripe_settings' );
		foreach ( $settings as $id => $setting ) {
			$gateway->form_fields[ $id ] = [
				'title' => $id,
				'type'  => $setting['type'],
			];
		}
		return $gateway;
	}

	/**
	 * The protection class with the request check pinned, so the auth source is deterministic.
	 *
	 * @param bool $can_view_secrets Whether the request may view secret values.
	 * @return WC_Stripe_REST_Gateway_Settings_Protection
	 */
	private function get_protection_with_auth( bool $can_view_secrets ) {
		$protection = $this->getMockBuilder( WC_Stripe_REST_Gateway_Settings_Protection::class )
			->onlyMethods( [ 'can_view_secret_values_via_rest' ] )
			->getMock();
		$protection->method( 'can_view_secret_values_via_rest' )->willReturn( $can_view_secrets );
		return $protection;
	}

	/**
	 * An API-key request gets secret values replaced with the placeholder, while
	 * non-secret values (publishable key, descriptor, token checkbox) are untouched.
	 *
	 * @return void
	 */
	public function test_withholds_secret_values_for_api_key_request(): void {
		$response = $this->build_response(
			[
				'secret_key'            => [
					'type'  => 'password',
					'value' => 'sk_live_abc',
				],
				'test_secret_key'       => [
					'type'  => 'password',
					'value' => 'sk_test_abc',
				],
				'webhook_secret'        => [
					'type'  => 'password',
					'value' => 'whsec_live_abc',
				],
				'test_webhook_secret'   => [
					'type'  => 'password',
					'value' => 'whsec_test_abc',
				],
				'publishable_key'       => [
					'type'  => 'text',
					'value' => 'pk_live_abc',
				],
				'test_publishable_key'  => [
					'type'  => 'text',
					'value' => 'pk_test_abc',
				],
				'statement_descriptor'  => [
					'type'  => 'text',
					'value' => 'STORE',
				],
				'sepa_tokens_for_ideal' => [
					'type'  => 'checkbox',
					'value' => 'yes',
				],
				'api_credentials'       => [
					'type'  => 'stripe_account_keys',
					'value' => 'test',
				],
				'test_type'             => [
					'type'  => 'password',
					'value' => 'test_password',
				],
				'test_secret_id'        => [
					'type'  => 'text',
					'value' => 'test_secret_id',
				],
				'test_password'         => [
					'type'  => 'text',
					'value' => 'test_password',
				],
				'test_token'            => [
					'type'  => 'text',
					'value' => 'test_token',
				],
				'test_token_type'       => [
					'type'  => 'text',
					'value' => 'test_token_type',
				],
				'test_secret_checkbox'  => [
					'type'  => 'checkbox',
					'value' => 'no',
				],
				'test_private_value'    => [
					'type'  => 'text',
					'value' => 'test_private_value',
				],
				'test_signature'        => [
					'type'  => 'text',
					'value' => 'test_signature',
				],
				'test_empty_secret'     => [
					'type'  => 'text',
					'value' => '',
				],
				'test_empty_password'   => [
					'type'  => 'text',
					'value' => '',
				],
			]
		);

		$result   = $this->get_protection_with_auth( false )->withhold_secret_values( $response, $this->get_stripe_gateway_double( $response->get_data()['settings'] ) );
		$settings = $result->get_data()['settings'];

		$expected_withheld_fields = [
			'secret_key',
			'test_secret_key',
			'webhook_secret',
			'test_webhook_secret',
			'api_credentials',
			'test_type',
			'test_secret_id',
			'test_password',
			'test_token',
			'test_token_type',
			'test_private_value',
			'test_signature',
		];

		foreach ( $expected_withheld_fields as $expected_withheld_field ) {
			$this->assertArrayHasKey( $expected_withheld_field, $settings );
			$this->assertArrayHasKey( 'value', $settings[ $expected_withheld_field ] );
			$this->assertArrayHasKey( 'value_withheld', $settings[ $expected_withheld_field ] );
			$this->assertSame( WC_Stripe_REST_Gateway_Settings_Protection::WITHHELD_PLACEHOLDER, $settings[ $expected_withheld_field ]['value'] );
			$this->assertTrue( $settings[ $expected_withheld_field ]['value_withheld'] );
		}

		$unmodified_fields = [
			'publishable_key'       => 'pk_live_abc',
			'test_publishable_key'  => 'pk_test_abc',
			'statement_descriptor'  => 'STORE',
			'sepa_tokens_for_ideal' => 'yes',
			'test_secret_checkbox'  => 'no',
			'test_empty_secret'     => '',
			'test_empty_password'   => '',
		];

		foreach ( $unmodified_fields as $unmodified_field => $unmodified_value ) {
			$this->assertArrayHasKey( $unmodified_field, $settings );
			$this->assertArrayHasKey( 'value', $settings[ $unmodified_field ] );
			$this->assertSame( $unmodified_value, $settings[ $unmodified_field ]['value'] );
			$this->assertArrayNotHasKey( 'value_withheld', $settings[ $unmodified_field ] );
		}
	}

	/**
	 * A cookie request from an admin keeps every value, so the admin UI is unaffected.
	 *
	 * @return void
	 */
	public function test_returns_secret_values_for_cookie_request(): void {
		$response = $this->build_response(
			[
				'secret_key' => [
					'type'  => 'password',
					'value' => 'sk_live_abc',
				],
			]
		);

		$result = $this->get_protection_with_auth( true )->withhold_secret_values( $response, $this->get_stripe_gateway_double( $response->get_data()['settings'] ) );

		$this->assertSame( 'sk_live_abc', $result->get_data()['settings']['secret_key']['value'] );
	}

	/**
	 * A non-Stripe gateway is left alone, so this plugin only touches its own data.
	 *
	 * @return void
	 */
	public function test_leaves_non_stripe_gateways_untouched(): void {
		$other    = $this->getMockBuilder( WC_Payment_Gateway::class )->disableOriginalConstructor()->getMockForAbstractClass();
		$response = $this->build_response(
			[
				'secret_key' => [
					'type'  => 'password',
					'value' => 'sk_live_abc',
				],
			]
		);

		$result = $this->get_protection_with_auth( false )->withhold_secret_values( $response, $other );

		$this->assertSame( 'sk_live_abc', $result->get_data()['settings']['secret_key']['value'] );
	}

	/**
	 * The filter can force a field secret or non-secret in either direction.
	 *
	 * @return void
	 */
	public function test_secret_filter_overrides_detection(): void {
		$mock_filter = function ( $is_secret, $id ) {
			if ( 'publishable_key' === $id ) {
				return true; // force a normally-public field to be withheld.
			}
			if ( 'secret_key' === $id ) {
				return false; // force a normally-secret field to be returned.
			}
			return $is_secret;
		};
		add_filter( 'wc_stripe_rest_withhold_secret_setting', $mock_filter, 10, 2 );

		try {
			$response = $this->build_response(
				[
					'secret_key'      => [
						'type'  => 'password',
						'value' => 'sk_live_abc',
					],
					'publishable_key' => [
						'type'  => 'text',
						'value' => 'pk_live_abc',
					],
				]
			);

			$settings = $this->get_protection_with_auth( false )->withhold_secret_values( $response, $this->get_stripe_gateway_double( $response->get_data()['settings'] ) )->get_data()['settings'];

			$this->assertSame( 'sk_live_abc', $settings['secret_key']['value'] );
			$this->assertSame( WC_Stripe_REST_Gateway_Settings_Protection::WITHHELD_PLACEHOLDER, $settings['publishable_key']['value'] );
		} finally {
			remove_filter( 'wc_stripe_rest_withhold_secret_setting', $mock_filter, 10 );
		}
	}

	/**
	 * A filter callback gets the same field definition on read and write, so a field it
	 * marks secret is withheld on read and restored on write. If the callback saw different
	 * data, the placeholder would be saved in place of the secret.
	 *
	 * @return void
	 */
	public function test_secret_filter_gets_same_field_on_read_and_write(): void {
		$previous = get_option( 'woocommerce_stripe_settings' );
		update_option( 'woocommerce_stripe_settings', [ 'custom_reference' => 'ref_real' ] );

		$seen_fields = [];
		$mock_filter = function ( $is_secret, $id, $setting ) use ( &$seen_fields ) {
			if ( 'custom_reference' !== $id ) {
				return $is_secret;
			}
			$seen_fields[] = $setting;
			return ! empty( $setting['is_secret_reference'] );
		};
		add_filter( 'wc_stripe_rest_withhold_secret_setting', $mock_filter, 10, 3 );

		try {
			$gateway                                  = $this->get_stripe_gateway_double();
			$gateway->form_fields['custom_reference'] = [
				'title'               => 'Reference',
				'type'                => 'text',
				'is_secret_reference' => true,
			];

			$response   = $this->build_response(
				[
					'custom_reference' => [
						'type'  => 'text',
						'value' => 'ref_real',
					],
				]
			);
			$protection = $this->get_protection_with_auth( false );
			$read_value = $protection->withhold_secret_values( $response, $gateway )->get_data()['settings']['custom_reference']['value'];
			$saved      = $protection->restore_withheld_secret_values( [ 'custom_reference' => $read_value ], $gateway );

			$this->assertSame( WC_Stripe_REST_Gateway_Settings_Protection::WITHHELD_PLACEHOLDER, $read_value );
			$this->assertSame( 'ref_real', $saved['custom_reference'] );
			$this->assertCount( 2, $seen_fields );
			$this->assertSame( $seen_fields[0], $seen_fields[1] );
			$this->assertArrayNotHasKey( 'value', $seen_fields[0] );
		} finally {
			remove_filter( 'wc_stripe_rest_withhold_secret_setting', $mock_filter, 10 );
			$this->restore_stripe_settings( $previous );
		}
	}

	/**
	 * A field saved as the placeholder keeps its stored value; a real value is saved;
	 * a placeholder with no stored value becomes empty rather than persisting the placeholder.
	 *
	 * @return void
	 */
	public function test_restore_keeps_stored_secret_on_placeholder_write(): void {
		$previous = get_option( 'woocommerce_stripe_settings' );

		update_option(
			'woocommerce_stripe_settings',
			[
				'secret_key'     => 'sk_live_real',
				'webhook_secret' => 'whsec_real',
			]
		);

		try {
			$protection = new WC_Stripe_REST_Gateway_Settings_Protection();
			$settings   = $protection->restore_withheld_secret_values(
				[
					'secret_key'      => WC_Stripe_REST_Gateway_Settings_Protection::WITHHELD_PLACEHOLDER,
					'webhook_secret'  => 'whsec_new',
					'title'           => 'My store',
					'test_secret_key' => WC_Stripe_REST_Gateway_Settings_Protection::WITHHELD_PLACEHOLDER,
				],
				$this->get_stripe_gateway_double()
			);

			$this->assertSame( 'sk_live_real', $settings['secret_key'] );
			$this->assertSame( 'whsec_new', $settings['webhook_secret'] );
			$this->assertSame( 'My store', $settings['title'] );
			$this->assertSame( '', $settings['test_secret_key'] );
		} finally {
			$this->restore_stripe_settings( $previous );
		}
	}

	/**
	 * A placeholder sent for a field that is not a secret is saved as sent, since it
	 * cannot stand for a withheld value.
	 *
	 * @return void
	 */
	public function test_restore_ignores_placeholder_on_non_secret_field(): void {
		$previous = get_option( 'woocommerce_stripe_settings' );
		update_option( 'woocommerce_stripe_settings', [ 'title' => 'Stored title' ] );

		try {
			$protection = new WC_Stripe_REST_Gateway_Settings_Protection();
			$settings   = $protection->restore_withheld_secret_values(
				[ 'title' => WC_Stripe_REST_Gateway_Settings_Protection::WITHHELD_PLACEHOLDER ],
				$this->get_stripe_gateway_double()
			);

			$this->assertSame( WC_Stripe_REST_Gateway_Settings_Protection::WITHHELD_PLACEHOLDER, $settings['title'] );
		} finally {
			$this->restore_stripe_settings( $previous );
		}
	}

	/**
	 * Secret values are shown only to a cookie request with a valid REST nonce from a user
	 * who can edit the gateway. Every other request has them withheld.
	 *
	 * @param bool        $logged_in   Whether a user is logged in.
	 * @param mixed       $auth_cookie The value core left in $wp_rest_auth_cookie.
	 * @param string|null $nonce_user  Whose nonce is sent: 'current', 'other', or null for none.
	 * @param bool        $can_edit    Whether the user passes the gateway edit permission check.
	 * @param bool        $expected    Whether secret values may be shown.
	 *
	 * @dataProvider provide_can_view_secret_values_via_rest_cases
	 */
	public function test_can_view_secret_values_via_rest( bool $logged_in, $auth_cookie, ?string $nonce_user, bool $can_edit, bool $expected ): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$other_id = self::factory()->user->create( [ 'role' => 'customer' ] );

		if ( 'other' === $nonce_user ) {
			// Another user's login cookie and nonce, sent along with the admin's credential.
			$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $other_id, time() + HOUR_IN_SECONDS, 'logged_in' );
			wp_set_current_user( $other_id );
			$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );
		} elseif ( 'current' === $nonce_user ) {
			$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $admin_id, time() + HOUR_IN_SECONDS, 'logged_in' );
			wp_set_current_user( $admin_id );
			$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );
		}

		wp_set_current_user( $logged_in ? $admin_id : 0 );
		$GLOBALS['wp_rest_auth_cookie'] = $auth_cookie;

		$permissions_filter = null;

		if ( ! $can_edit ) {
			$permissions_filter = function ( $permission, $context ) {
				return 'edit' === $context ? false : $permission;
			};
			add_filter( 'woocommerce_rest_check_permissions', $permissions_filter, 10, 2 );
		}

		$method = new ReflectionMethod( WC_Stripe_REST_Gateway_Settings_Protection::class, 'can_view_secret_values_via_rest' );
		$method->setAccessible( true );

		try {
			$this->assertSame( $expected, $method->invoke( new WC_Stripe_REST_Gateway_Settings_Protection() ) );
		} finally {
			if ( $permissions_filter ) {
				remove_filter( 'woocommerce_rest_check_permissions', $permissions_filter, 10 );
			}
		}
	}

	public function provide_can_view_secret_values_via_rest_cases(): array {
		return [
			'admin cookie with a valid nonce'               => [ true, true, 'current', true, true ],
			'no user'                                       => [ false, null, null, true, false ],
			'API key or application password'               => [ true, null, null, true, false ],
			'API key with another user\'s cookie and nonce' => [ true, 'malformed', 'other', true, false ],
			'cookie flag set, but nonce of another user'    => [ true, true, 'other', true, false ],
			'admin cookie without a nonce'                  => [ true, true, null, true, false ],
			'admin cookie, user cannot edit the gateway'    => [ true, true, 'current', false, false ],
		];
	}

	/**
	 * End to end over the real wc/v3 controller: an API-key read (logged in, no
	 * login cookie) gets the secret withheld and the publishable key returned.
	 *
	 * @return void
	 */
	public function test_rest_read_withholds_secret_end_to_end(): void {
		if ( ! class_exists( 'WC_REST_Payment_Gateways_Controller' ) || is_null( $this->get_stripe_gateway() ) ) {
			$this->markTestSkipped( 'WC payment-gateways REST controller or Stripe gateway not available.' );
		}

		$previous = get_option( 'woocommerce_stripe_settings' );

		try {
			$this->set_stripe_secrets( 'sk_live_itest', 'pk_live_itest' );

			wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
			unset( $_COOKIE[ LOGGED_IN_COOKIE ] ); // No login cookie: treated as API-key auth.

			$response = rest_do_request( new WP_REST_Request( 'GET', '/wc/v3/payment_gateways/stripe' ) );
			$settings = $response->get_data()['settings'] ?? [];

			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( WC_Stripe_REST_Gateway_Settings_Protection::WITHHELD_PLACEHOLDER, $settings['secret_key']['value'] );
			$this->assertSame( 'pk_live_itest', $settings['publishable_key']['value'] );
		} finally {
			$this->restore_stripe_settings( $previous );
		}
	}

	/**
	 * End to end over the real wc/v3 controller: a write keeps or changes the secret as
	 * expected in the stored option, in the gateway object, and in the response. The
	 * controller builds the response from the gateway object, which it sets before the
	 * settings filter runs.
	 *
	 * @dataProvider provide_rest_write_cases
	 *
	 * @param bool   $cookie_auth       Whether the request uses a login cookie and REST nonce, so it may view secrets.
	 * @param string $sent_value        The secret_key value sent in the request.
	 * @param string $expected_stored   The secret_key expected in the option and the gateway object.
	 * @param string $expected_response The secret_key value expected in the response.
	 * @param bool   $expected_withheld Whether the response marks the value as withheld.
	 * @return void
	 */
	public function test_rest_write_end_to_end( bool $cookie_auth, string $sent_value, string $expected_stored, string $expected_response, bool $expected_withheld ): void {
		if ( ! class_exists( 'WC_REST_Payment_Gateways_Controller' ) || is_null( $this->get_stripe_gateway() ) ) {
			$this->markTestSkipped( 'WC payment-gateways REST controller or Stripe gateway not available.' );
		}

		$previous = get_option( 'woocommerce_stripe_settings' );
		try {
			$this->set_stripe_secrets( 'sk_live_keepme', 'pk_live_itest' );

			$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
			wp_set_current_user( $admin_id );
			if ( $cookie_auth ) {
				$_COOKIE[ LOGGED_IN_COOKIE ]    = wp_generate_auth_cookie( $admin_id, time() + HOUR_IN_SECONDS, 'logged_in' );
				$_SERVER['HTTP_X_WP_NONCE']     = wp_create_nonce( 'wp_rest' );
				$GLOBALS['wp_rest_auth_cookie'] = true;
			} else {
				unset( $_COOKIE[ LOGGED_IN_COOKIE ] ); // No login cookie: treated as API-key auth.
			}

			$request = new WP_REST_Request( 'PUT', '/wc/v3/payment_gateways/stripe' );
			$request->set_body_params(
				[
					'settings' => [
						'secret_key'      => $sent_value,
						'publishable_key' => 'pk_live_changed',
					],
				]
			);
			$response = rest_do_request( $request );
			$returned = $response->get_data()['settings']['secret_key'] ?? [];

			$this->assertSame( 200, $response->get_status() );

			$stored = get_option( 'woocommerce_stripe_settings' );
			$this->assertSame( $expected_stored, $stored['secret_key'] );
			$this->assertSame( 'pk_live_changed', $stored['publishable_key'] );
			$this->assertSame( $expected_stored, $this->get_stripe_gateway()->settings['secret_key'] );

			$this->assertSame( $expected_response, $returned['value'] );
			$this->assertSame( $expected_withheld, ! empty( $returned['value_withheld'] ) );
		} finally {
			$this->restore_stripe_settings( $previous );
		}
	}

	/**
	 * Data provider for `test_rest_write_end_to_end`.
	 *
	 * @return array<string, array{bool, string, string, string, bool}>
	 */
	public function provide_rest_write_cases(): array {
		$placeholder = WC_Stripe_REST_Gateway_Settings_Protection::WITHHELD_PLACEHOLDER;

		return [
			'API key, placeholder sent back' => [ false, $placeholder, 'sk_live_keepme', $placeholder, true ],
			'cookie, placeholder sent back'  => [ true, $placeholder, 'sk_live_keepme', 'sk_live_keepme', false ],
			'API key, secret cleared'        => [ false, '', '', '', false ],
			'cookie, secret cleared'         => [ true, '', '', '', false ],
		];
	}

	/**
	 * The registered Stripe gateway, or null if it is not available in this env.
	 *
	 * @return WC_Payment_Gateway|null
	 */
	private function get_stripe_gateway() {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
		return $gateways['stripe'] ?? null;
	}

	/**
	 * Writes the given secret and publishable keys into the live Stripe settings.
	 *
	 * @param string $secret_key      Live secret key.
	 * @param string $publishable_key Live publishable key.
	 * @return void
	 */
	private function set_stripe_secrets( string $secret_key, string $publishable_key ): void {
		$settings = get_option( 'woocommerce_stripe_settings' );
		$settings = is_array( $settings ) ? $settings : [];
		update_option(
			'woocommerce_stripe_settings',
			array_merge(
				$settings,
				[
					'secret_key'      => $secret_key,
					'publishable_key' => $publishable_key,
				]
			)
		);

		// The registered gateway cached its settings at init, so reload them.
		$this->reload_stripe_gateway_settings();
	}

	/**
	 * Reloads the registered Stripe gateway settings from the database.
	 */
	private function reload_stripe_gateway_settings(): void {
		$gateway = $this->get_stripe_gateway();
		if ( $gateway ) {
			$gateway->init_settings();
		}
	}

	/**
	 * Restores the Stripe settings option to a previous value.
	 *
	 * @param mixed $previous The value returned by the earlier get_option() call.
	 * @return void
	 */
	private function restore_stripe_settings( $previous ): void {
		if ( false === $previous ) {
			delete_option( 'woocommerce_stripe_settings' );
		} else {
			update_option( 'woocommerce_stripe_settings', $previous );
		}
	}
}
