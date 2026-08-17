<?php

/**
 * These teste make assertions against class WC_Stripe_Apple_Pay_Registration.
 *
 * @package WooCommerce/Stripe/Apple_Pay_Registration
 *
 * WC_Stripe_Apple_Pay_Registration unit tests.
 */
class WC_Stripe_Apple_Pay_Registration_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * Mocked system under test.
	 *
	 * @var WC_Stripe_Apple_Pay_Registration
	 */
	private $mock_wc_apple_pay_registration;

	/**
	 * UPE test helper.
	 *
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_wc_apple_pay_registration = $this->getMockBuilder( 'WC_Stripe_Apple_Pay_Registration' )
		->disableOriginalConstructor()
		->setMethods(
			[
				'register_domain',
			]
		)
		->getMock();

		// The constructor is disabled above, so seed a non-empty domain the way a real request would.
		$domain_name = new ReflectionProperty( 'WC_Stripe_Apple_Pay_Registration', 'domain_name' );
		$domain_name->setAccessible( true );
		$domain_name->setValue( $this->mock_wc_apple_pay_registration, 'example.com' );

		$settings                    = WC_Stripe_Helper::get_stripe_settings();
		$settings['enabled']         = 'yes';
		$settings['testmode']        = 'yes';
		$settings['test_secret_key'] = '123';
		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		$this->upe_helper = new UPE_Test_Helper();
	}

	/**
	 * Enable UPE and enable/disable Apple Pay/Google Pay.
	 *
	 * @param bool $payment_request_enabled Whether Apple Pay/Google Pay should be enabled.
	 */
	private function upe_checkout_setup( $payment_request_enabled = true ) {
		$this->upe_helper->enable_upe();
		$this->upe_helper->reload_payment_gateways();

		if ( $payment_request_enabled ) {
			$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::APPLE_PAY ] );
		} else {
			$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::CARD, WC_Stripe_Payment_Methods::LINK ] );
		}
	}

	public function test_register_domain_if_configured_supported_country() {
		$this->upe_checkout_setup();

		WC_Stripe::get_instance()->account = $this->getMockBuilder( 'WC_Stripe_Account' )
			->disableOriginalConstructor()
			->setMethods(
				[
					'get_cached_account_data',
				]
			)
			->getMock();

		WC_Stripe::get_instance()->account
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'country' => 'US' ] );

		$this->mock_wc_apple_pay_registration
			->expects( $this->once() )
			->method( 'register_domain' );

		$this->mock_wc_apple_pay_registration->register_domain_if_configured();
	}

	/**
	 * Test for UPE, Apple Pay enabled.
	 */
	public function test_register_domain_if_configured_upe_apple_pay_enabled() {
		$this->upe_checkout_setup();

		$this->mock_wc_apple_pay_registration
			->expects( $this->once() )
			->method( 'register_domain' );

		$this->mock_wc_apple_pay_registration->register_domain_if_configured();
	}

	/**
	 * Test for UPE, Apple Pay disabled.
	 */
	public function test_register_domain_if_configured_upe_apple_pay_disabled() {
		$this->upe_checkout_setup( false );

		$this->mock_payment_method_configurations( [ WC_Stripe_Payment_Methods::CARD ] );

		$this->mock_wc_apple_pay_registration
			->expects( $this->never() )
			->method( 'register_domain' );
	}

	/**
	 * A derived domain that is empty (e.g. a site URL with no parseable host) must not trigger a
	 * registration, so we neither call Stripe nor overwrite the stored domain with an empty string.
	 */
	public function test_register_domain_if_configured_skips_when_domain_is_empty() {
		$this->upe_checkout_setup();

		$domain_name = new ReflectionProperty( 'WC_Stripe_Apple_Pay_Registration', 'domain_name' );
		$domain_name->setAccessible( true );
		$domain_name->setValue( $this->mock_wc_apple_pay_registration, '' );

		$this->mock_wc_apple_pay_registration
			->expects( $this->never() )
			->method( 'register_domain' );

		$this->mock_wc_apple_pay_registration->register_domain_if_configured();
	}

	/**
	 * The admin_init-triggered registration must only run for users who can manage WooCommerce.
	 * The capability is forced through the user_has_cap filter rather than assigned via a role to ensure
	 * we are not dependent on the WooCommerce roles being defined in the test environment.
	 *
	 * @dataProvider provide_capability_gate_scenarios
	 *
	 * @param bool $has_capability  Whether the current user has manage_woocommerce.
	 * @param bool $should_register Whether registration should be attempted.
	 */
	public function test_register_domain_on_domain_name_change_capability_gate( $has_capability, $should_register ) {
		wp_set_current_user( self::factory()->user->create() );
		$grant = function ( $allcaps ) use ( $has_capability ) {
			$allcaps['manage_woocommerce'] = $has_capability;
			return $allcaps;
		};
		add_filter( 'user_has_cap', $grant );

		try {
			$mock = $this->getMockBuilder( 'WC_Stripe_Apple_Pay_Registration' )
				->disableOriginalConstructor()
				->setMethods( [ 'register_domain_if_configured', 'get_option' ] )
				->getMock();

			// Force a domain mismatch so the capability gate is the only deciding factor.
			$mock->method( 'get_option' )->willReturn( 'stored-domain.example' );

			$domain_name = new ReflectionProperty( 'WC_Stripe_Apple_Pay_Registration', 'domain_name' );
			$domain_name->setAccessible( true );
			$domain_name->setValue( $mock, 'current-domain.example' );

			$mock->expects( $should_register ? $this->once() : $this->never() )
				->method( 'register_domain_if_configured' );

			$mock->register_domain_on_domain_name_change();
		} finally {
			remove_filter( 'user_has_cap', $grant );
		}
	}

	/**
	 * @return array<string, array{0: bool, 1: bool}>
	 */
	public function provide_capability_gate_scenarios() {
		return [
			'without manage_woocommerce (anonymous or low-privilege)' => [ false, false ],
			'with manage_woocommerce'                                 => [ true, true ],
		];
	}

	/**
	 * The domain must be the bare host of the configured site URL: scheme, path, and port are
	 * dropped (Stripe's payment_method_domains rejects anything but a bare host), subdomains kept.
	 *
	 * @dataProvider provide_site_url_domain_scenarios
	 *
	 * @param string $site_url Value returned by get_site_url().
	 * @param string $expected Expected derived domain_name.
	 */
	public function test_domain_name_derivation_from_site_url( $site_url, $expected ) {
		$filter = function () use ( $site_url ) {
			return $site_url;
		};
		add_filter( 'site_url', $filter );

		try {
			$registration = new WC_Stripe_Apple_Pay_Registration();

			$domain_name = new ReflectionProperty( 'WC_Stripe_Apple_Pay_Registration', 'domain_name' );
			$domain_name->setAccessible( true );

			$this->assertSame( $expected, $domain_name->getValue( $registration ) );
		} finally {
			remove_filter( 'site_url', $filter );
		}
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function provide_site_url_domain_scenarios() {
		return [
			'https bare domain'                 => [ 'https://example.com', 'example.com' ],
			'http bare domain'                  => [ 'http://example.com', 'example.com' ],
			'subdomain preserved'               => [ 'https://shop.example.com', 'shop.example.com' ],
			'subdirectory path drop'            => [ 'https://example.com/store', 'example.com' ],
			'non-standard port drop'            => [ 'https://example.com:8443', 'example.com' ],
			'malformed url yields empty string' => [ 'not-a-valid-url', '' ],
		];
	}

	/**
	 * Registration is recorded only for a well-formed 2xx response; asserting the
	 * exact exception message pins each failure to the check that threw.
	 *
	 * @param array|WP_Error $http_response          The mocked wp_remote_post() response.
	 * @param string|null    $expected_error_message Expected exception message, or null when registration should succeed.
	 * @dataProvider provide_test_register_domain_response_validation
	 */
	public function test_register_domain_response_validation( $http_response, ?string $expected_error_message ) {
		$registration = new WC_Stripe_Apple_Pay_Registration();

		$mock_response = function () use ( $http_response ) {
			return $http_response;
		};
		add_filter( 'pre_http_request', $mock_response, 10, 3 );

		$request_method = new ReflectionMethod( WC_Stripe_Apple_Pay_Registration::class, 'make_domain_registration_request' );
		$request_method->setAccessible( true );

		$actual_error_message = null;
		try {
			$request_method->invoke( $registration, '123' );
		} catch ( Exception $e ) {
			$actual_error_message = $e->getMessage();
		}

		$result = $registration->register_domain( '123' );

		remove_filter( 'pre_http_request', $mock_response, 10 );

		$this->assertSame( $expected_error_message, $actual_error_message );

		$expected_success = null === $expected_error_message;
		$this->assertSame( $expected_success, $result );

		$settings = WC_Stripe_Helper::get_stripe_settings();
		$this->assertSame( $expected_success ? 'yes' : 'no', $settings['apple_pay_domain_set'] );
	}

	/**
	 * Data provider for `test_register_domain_response_validation`.
	 *
	 * @return array
	 */
	public function provide_test_register_domain_response_validation(): array {
		// Bodies mirror Stripe's payment_method_domain object:
		// https://docs.stripe.com/api/payment_method_domains/object
		$success_body = wp_json_encode(
			[
				'id'        => 'pmd_123',
				'object'    => 'payment_method_domain',
				'apple_pay' => [ 'status' => 'active' ],
			]
		);

		return [
			'well-formed 2xx response records success'         => [
				[
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => $success_body,
				],
				null,
			],
			'transport error records failure'                  => [
				new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ),
				'Unable to register domain - cURL error 28: Operation timed out',
			],
			'401 with top-level Stripe error records failure'  => [
				[
					'response' => [
						'code'    => 401,
						'message' => 'Unauthorized',
					],
					'body'     => wp_json_encode(
						[
							'error' => [
								'type'    => 'invalid_request_error',
								'message' => 'Invalid API Key provided',
							],
						]
					),
				],
				'Unable to register domain - Invalid API Key provided',
			],
			'500 with non-JSON body records failure'           => [
				[
					'response' => [
						'code'    => 500,
						'message' => 'Internal Server Error',
					],
					'body'     => '<html>Internal Server Error</html>',
				],
				'Unable to register domain - Stripe returned an unexpected HTTP status code: 500.',
			],
			'2xx with top-level Stripe error records failure'  => [
				[
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode(
						[
							'error' => [ 'message' => 'Something went wrong' ],
						]
					),
				],
				'Unable to register domain - Something went wrong',
			],
			'2xx without a domain object records failure'      => [
				[
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => 'OK',
				],
				'Unable to register domain - unexpected response from Stripe.',
			],
			'2xx with Apple Pay error message records failure' => [
				[
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'body'     => wp_json_encode(
						[
							'id'        => 'pmd_123',
							'object'    => 'payment_method_domain',
							'apple_pay' => [
								'status'         => 'inactive',
								'status_details' => [ 'error_message' => 'Unable to verify domain' ],
							],
						]
					),
				],
				'Unable to register domain - Unable to verify domain',
			],
		];
	}
}
