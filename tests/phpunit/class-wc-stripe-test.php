<?php

/**
 * These tests make assertions against the class WC_Stripe.
 *
 * Class WC_Stripe_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe
 */
class WC_Stripe_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * Tests that the plugin constants are defined.
	 *
	 * @return void
	 */
	public function test_constants_defined() {
		$this->assertTrue( defined( 'WC_STRIPE_VERSION' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MIN_PHP_VER' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MIN_WC_VER' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MAIN_FILE' ) );
		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_PATH' ) );
	}

	/**
	 * Tests for `maybe_toggle_payment_methods`.
	 *
	 * @param array $active_gateways The active payment gateways.
	 * @param array $enabled_payment_method_ids The enabled payment method IDs.
	 * @param int $update_enable_payment_methods_calls The number of times `update_enabled_payment_methods` should be called.
	 * @return void
	 *
	 * @dataProvider provide_test_maybe_toggle_payment_methods
	 */
	public function test_maybe_toggle_payment_methods(
		$active_gateways,
		$enabled_payment_method_ids,
		$update_enable_payment_methods_calls
	) {
		$original_payment_gateways = WC()->payment_gateways->payment_gateways;

		// Mock the available payment gateways.
		WC()->payment_gateways->payment_gateways = $active_gateways;

		$upe_payment_gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		$upe_payment_gateway->expects( $this->once() )
			->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( $enabled_payment_method_ids );

		$upe_payment_gateway->expects( $this->exactly( $update_enable_payment_methods_calls ) )
			->method( 'update_enabled_payment_methods' )
			->with( [ WC_Stripe_Payment_Methods::CARD ] );

		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_main_stripe_gateway' ] )
			->getMock();

		$wc_stripe->method( 'get_main_stripe_gateway' )
			->willReturn( $upe_payment_gateway );

		$wc_stripe->maybe_toggle_payment_methods( WC()->payment_gateways );

		// Clean up.
		WC()->payment_gateways->payment_gateways = $original_payment_gateways;
	}

	/**
	 * Provider for `test_maybe_deactivate_payment_methods`.
	 *
	 * @return array
	 */
	public function provide_test_maybe_toggle_payment_methods() {
		return [
			'none active'                                 => [
				'active gateways'                     => [],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
				],
				'update enable payment methods calls' => 0,
			],
			'affirm'                                      => [
				'active gateways'                     => [
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
						'enabled' => 'yes',
					],
				],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::AFFIRM,
				],
				'update enable payment methods calls' => 1,
			],
			'klarna'                                      => [
				'active gateways'                     => [
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
						'enabled' => 'yes',
					],
				],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::KLARNA,
				],
				'update enable payment methods calls' => 1,
			],
			'klarna and affirm active, but not on Stripe' => [
				'active gateways'                     => [
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
						'enabled' => 'yes',
					],
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
						'enabled' => 'yes',
					],
				],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
				],
				'update enable payment methods calls' => 0,
			],
			'klarna and affirm active in both'            => [
				'active gateways'                     => [
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_AFFIRM,
						'enabled' => 'yes',
					],
					WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA => (object) [
						'id'      => WC_Stripe_Helper::OFFICIAL_PLUGIN_ID_KLARNA,
						'enabled' => 'yes',
					],
				],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::AFFIRM,
					WC_Stripe_Payment_Methods::KLARNA,
				],
				'update enable payment methods calls' => 1,
			],
			'amazon pay'                                  => [
				'active gateways'                     => [],
				'enabled payment method IDs'          => [
					WC_Stripe_Payment_Methods::CARD,
					WC_Stripe_Payment_Methods::AMAZON_PAY,
				],
				'update enable payment methods calls' => 0,
			],
		];
	}

	/**
	 * Tests for the 'install' method when it updates the main Stripe settings.
	 *
	 * @param array $stripe_settings   The initial Stripe settings.
	 * @param array $expected_settings The expected Stripe settings after installation.
	 * @return void
	 *
	 * @dataProvider provide_test_install_settings
	 */
	public function test_install_settings_update( array $stripe_settings = [], array $expected_settings = [] ): void {
		// Activate the Stripe plugin.
		update_option( 'active_plugins', [ plugin_basename( WC_STRIPE_MAIN_FILE ) ] );

		// Set initial settings.
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );

		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'update_plugin_version',
					'update_prb_location_settings',
					'migrate_to_new_checkout_experience',
				]
			)
			->getMock();

		$wc_stripe->install();

		$actual_settings = WC_Stripe_Helper::get_stripe_settings();
		foreach ( $expected_settings as $key => $value ) {
			if ( null == $value ) {
				$this->assertArrayNotHasKey( $key, $actual_settings );
			} else {
				$this->assertArrayHasKey( $key, $actual_settings );
				$this->assertSame( $value, $actual_settings[ $key ] );
			}
		}
	}

	/**
	 * Data provider for {@see test_install_settings_update()}.
	 *
	 * @return array
	 */
	public function provide_test_install_settings(): array {
		return [
			'will not enable OCS by default due to PMC being disabled' => [
				'stripe settings'   => [
					'pmc_enabled' => 'no',
				],
				'expected settings' => [
					'pmc_enabled'                        => null,
					'optimized_checkout_element'         => null,
					'skip_pmc_express_checkout_defaults' => 'yes',
				],
			],
			'will not enable OCS by default due to OCS being set'      => [
				'stripe settings'   => [
					'pmc_enabled'                => 'yes',
					'optimized_checkout_element' => 'no',
				],
				'expected settings' => [
					'pmc_enabled'                        => 'yes',
					'optimized_checkout_element'         => 'no',
					'skip_pmc_express_checkout_defaults' => null,
				],
			],
		];
	}

	/**
	 * Tests that the 'install' method sets the fresh install flag.
	 *
	 * @return void
	 */
	public function test_fresh_install_sets_correct_options(): void {
		update_option( 'active_plugins', [ plugin_basename( WC_STRIPE_MAIN_FILE ) ] );

		// Ensure the flags are not set.
		delete_option( 'wc_stripe_optimized_checkout_default_on' );
		delete_option( 'wc_stripe_amazon_pay_default_on' );

		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'update_plugin_version',
					'update_prb_location_settings',
					'migrate_to_new_checkout_experience',
				]
			)
			->getMock();

		$wc_stripe->install();

		$this->assertEquals( 'yes', get_option( 'wc_stripe_optimized_checkout_default_on' ) );
		$this->assertEquals( 'yes', get_option( 'wc_stripe_amazon_pay_default_on' ) );
	}

	/**
	 * Tests that update_prb_location_settings copies payment_request_button_locations to express_checkout_button_locations.
	 *
	 * @return void
	 */
	public function test_update_prb_location_settings_copies_existing_payment_request_locations(): void {
		$this->remove_gateway_settings_update_filter();

		// Set up settings with payment_request_button_locations but no express_checkout_button_locations.
		$stripe_settings = [
			'enabled'                          => 'yes',
			'payment_request_button_locations' => [ 'checkout' ],
		];
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		WC_Stripe::get_instance()->update_prb_location_settings();

		$updated_settings = get_option( 'woocommerce_stripe_settings' );
		$this->assertSame( [ 'checkout' ], $updated_settings['express_checkout_button_locations'] );
	}

	/**
	 * Tests that update_prb_location_settings falls back to filter defaults when no existing locations are set.
	 *
	 * @return void
	 */
	public function test_update_prb_location_settings_uses_filter_defaults_when_no_existing_locations(): void {
		$this->remove_gateway_settings_update_filter();

		// Set up settings with no location settings.
		$stripe_settings = [
			'enabled' => 'yes',
		];
		update_option( 'woocommerce_stripe_settings', $stripe_settings );

		WC_Stripe::get_instance()->update_prb_location_settings();

		$updated_settings = get_option( 'woocommerce_stripe_settings' );
		$this->assertContains( 'product', $updated_settings['express_checkout_button_locations'] );
		$this->assertContains( 'cart', $updated_settings['express_checkout_button_locations'] );
	}

	/**
	 * Tests for `maybe_redirect_to_stripe_settings`.
	 *
	 * @return void
	 */
	public function test_maybe_redirect_to_stripe_settings(): void {
		$redirected_to      = '';
		$wp_redirect_filter = function ( string $url ) use ( &$redirected_to ) {
			$redirected_to = $url;

			// Throw exception to prevent exit() from being called after wp_safe_redirect().
			throw new \Exception();
		};

		// Add filter to catch redirects.
		add_filter( 'wp_redirect', $wp_redirect_filter );

		// Set the transient to trigger redirection.
		set_transient( 'wc_stripe_redirect_to_settings', true, 30 );

		// Simulate activation by setting the 'activate' GET parameter.
		$_GET['activate'] = 'true';

		try {
			WC_Stripe::get_instance()->maybe_redirect_to_stripe_settings();
		} catch ( \Exception $e ) {
			// Expected - this prevents exit() from killing the test.
			unset( $e );
		}

		$redirect_to_settings_transient = get_transient( 'wc_stripe_redirect_to_settings' );

		// Clean up.
		remove_filter( 'wp_redirect', $wp_redirect_filter );

		// Check that the transient has been deleted after redirection.
		$this->assertFalse( $redirect_to_settings_transient );

		// Check that the redirect location is the Stripe settings page.
		$this->assertStringContainsString( 'admin.php?page=wc-settings&tab=checkout&section=stripe', $redirected_to );
	}

	/**
	 * Removes the gateway_settings_update filter that merges defaults when saving settings.
	 *
	 * This filter adds default field values when saving settings for the first time,
	 * which interferes with tests that need to set specific settings without defaults.
	 *
	 * @return void
	 */
	private function remove_gateway_settings_update_filter(): void {
		remove_filter( 'pre_update_option_woocommerce_stripe_settings', [ WC_Stripe::get_instance(), 'gateway_settings_update' ] );
	}

	/**
	 * Tests the {@see WC_Stripe::add_gateways()} method.
	 *
	 * @param array  $payment_methods    The payment methods to add.
	 * @param array  $expected_gateways  The expected gateways.
	 * @param bool   $is_admin           Whether the test is running in the admin.
	 * @param bool   $oc_enabled         Whether the optimized checkout is enabled.
	 * @param string $edited_post_block  Block the edited post hosts: 'checkout', 'cart', 'none' (a non-Stripe post),
	 *                                   or '' for no edited post at all (e.g. the refund AJAX action).
	 * @return void
	 * @dataProvider provide_test_add_gateways
	 */
	public function test_add_gateways( array $payment_methods, array $expected_gateways, bool $is_admin = false, bool $oc_enabled = false, string $edited_post_block = '' ): void {
		$wc_stripe = $this->getMockBuilder( WC_Stripe::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_main_stripe_gateway' ] )
			->getMock();

		// add_gateways() keys OCS filtering off the instantiated gateway class, mirroring the
		// selection done in get_main_stripe_gateway(): the OCS gateway when OC is enabled.
		$main_gateway_class = $oc_enabled ? WC_Stripe_OCS_Payment_Gateway::class : WC_Stripe_UPE_Payment_Gateway::class;
		$mock_main_gateway  = $this->getMockBuilder( $main_gateway_class )
			->disableOriginalConstructor()
			->getMock();

		$mock_main_gateway->payment_methods = $payment_methods;

		$wc_stripe->method( 'get_main_stripe_gateway' )
			->willReturn( $mock_main_gateway );

		// The filter keys on the edited post ID in $_GET['post']. '' means no post is being edited.
		$post_content = [
			'checkout' => '<!-- wp:woocommerce/checkout /-->',
			'cart'     => '<!-- wp:woocommerce/cart /-->',
			'none'     => '<!-- wp:paragraph --><p>No Stripe blocks here.</p><!-- /wp:paragraph -->',
		];
		$initial_get  = $_GET;
		if ( '' !== $edited_post_block ) {
			$_GET['post'] = (string) self::factory()->post->create( [ 'post_content' => $post_content[ $edited_post_block ] ] );
		}

		// is_admin() reads the current screen; set an admin screen so the admin branch is exercised.
		$initial_current_screen = $GLOBALS['current_screen'] ?? null;
		if ( $is_admin ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['current_screen'] = \WP_Screen::get( 'post.php' );
		}

		try {
			$gateways = $wc_stripe->add_gateways( [] );
		} finally {
			$_GET = $initial_get;
			if ( $is_admin ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$GLOBALS['current_screen'] = $initial_current_screen;
			}
		}

		// First gateway should always be the main stripe gateway.
		$main_stripe_gateway = array_shift( $gateways );
		$this->assertEquals( $mock_main_gateway, $main_stripe_gateway );

		// Remaining gateways should be the expected "other" gateways.
		$this->assertEquals( count( $expected_gateways ), count( $gateways ) );
		foreach ( $expected_gateways as $expected_gateway ) {
			$this->assertContains( $expected_gateway, $gateways );
		}
	}

	/**
	 * Data provider for {@see test_add_gateways()}.
	 */
	public function provide_test_add_gateways(): array {
		$card_gateway = $this->getMockBuilder( \WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();

		// Use real classes as the code tests for the specific class names.
		$link_gateway = new \WC_Stripe_UPE_Payment_Method_Link();

		$amazon_pay_gateway = new \WC_Stripe_UPE_Payment_Method_Amazon_Pay();

		$klarna_gateway = $this->getMockBuilder( \WC_Stripe_UPE_Payment_Method_Klarna::class )
			->disableOriginalConstructor()
			->getMock();
		$klarna_gateway->method( 'is_enabled_at_checkout' )->willReturn( true );

		$afterpay_clearpay_gateway = $this->getMockBuilder( \WC_Stripe_UPE_Payment_Method_Afterpay_Clearpay::class )
			->disableOriginalConstructor()
			->getMock();
		$afterpay_clearpay_gateway->method( 'is_enabled_at_checkout' )->willReturn( true );

		$sepa_gateway = $this->getMockBuilder( \WC_Stripe_UPE_Payment_Method_Sepa::class )
			->disableOriginalConstructor()
			->getMock();
		$sepa_gateway->method( 'is_enabled_at_checkout' )->willReturn( false );

		// All non-card methods, used by the regression guards that assert nothing is filtered.
		$all_methods  = [
			'card'              => $card_gateway,
			'afterpay_clearpay' => $afterpay_clearpay_gateway,
			'klarna'            => $klarna_gateway,
			'amazon_pay'        => $amazon_pay_gateway,
			'link'              => $link_gateway,
			'sepa_debit'        => $sepa_gateway,
		];
		$all_non_card = [ $afterpay_clearpay_gateway, $klarna_gateway, $amazon_pay_gateway, $link_gateway, $sepa_gateway ];

		return [
			'none active'                                                              => [
				'payment_methods'   => [],
				'expected_gateways' => [],
			],
			'none active admin'                                                        => [
				'payment_methods'   => [],
				'expected_gateways' => [],
				'is_admin'          => true,
			],
			'card only non-admin is filtered out'                                      => [
				'payment_methods'   => [
					'card' => $card_gateway,
				],
				'expected_gateways' => [],
			],
			'card only admin is filtered out'                                          => [
				'payment_methods'   => [
					'card' => $card_gateway,
				],
				'expected_gateways' => [],
				'is_admin'          => true,
			],
			'link correctly included non-admin'                                        => [
				'payment_methods'   => [
					'klarna' => $klarna_gateway,
					'link'   => $link_gateway,
				],
				'expected_gateways' => [ $klarna_gateway, $link_gateway ],
			],
			'link filtered out when editing the checkout page'                         => [
				'payment_methods'   => [
					'klarna' => $klarna_gateway,
					'link'   => $link_gateway,
				],
				'expected_gateways' => [ $klarna_gateway ],
				'is_admin'          => true,
				'oc_enabled'        => false,
				'edited_post_block' => 'checkout',
			],
			'amazon pay correctly included non-admin'                                  => [
				'payment_methods'   => [
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
				],
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway, $amazon_pay_gateway ],
			],
			'amazon pay filtered out when editing the cart page'                       => [
				'payment_methods'   => [
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
				],
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway ],
				'is_admin'          => true,
				'oc_enabled'        => false,
				'edited_post_block' => 'cart',
			],
			'card filtered out; amazon pay and link correctly included non-admin'      => [
				'payment_methods'   => [
					'card'              => $card_gateway,
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
					'link'              => $link_gateway,
				],
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway, $amazon_pay_gateway, $link_gateway ],
			],
			'card, amazon pay, and link filtered out when editing the checkout page'   => [
				'payment_methods'   => [
					'card'              => $card_gateway,
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
					'link'              => $link_gateway,
				],
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway ],
				'is_admin'          => true,
				'oc_enabled'        => false,
				'edited_post_block' => 'checkout',
			],
			'disabled-at-checkout methods filtered out when editing the checkout page' => [
				'payment_methods'   => [
					'card'              => $card_gateway,
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
					'link'              => $link_gateway,
					'sepa_debit'        => $sepa_gateway,
				],
				// sepa is disabled at checkout, so it should be filtered out.
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway ],
				'is_admin'          => true,
				'oc_enabled'        => false,
				'edited_post_block' => 'checkout',
			],
			'optimized checkout enabled when editing the checkout page'                => [
				'payment_methods'   => [
					'card'              => $card_gateway,
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
					'link'              => $link_gateway,
				],
				'expected_gateways' => [],
				'is_admin'          => true,
				'oc_enabled'        => true,
				'edited_post_block' => 'checkout',
			],
			// Editing a non-Stripe post must not filter the gateway list; extensions reading it need every method.
			'link and amazon pay kept when editing a non-Stripe post'                  => [
				'payment_methods'   => [
					'afterpay_clearpay' => $afterpay_clearpay_gateway,
					'klarna'            => $klarna_gateway,
					'amazon_pay'        => $amazon_pay_gateway,
					'link'              => $link_gateway,
				],
				'expected_gateways' => [ $afterpay_clearpay_gateway, $klarna_gateway, $amazon_pay_gateway, $link_gateway ],
				'is_admin'          => true,
				'oc_enabled'        => false,
				'edited_post_block' => 'none',
			],
			// With OCS on, editing a non-Stripe post must still expose every non-card method to extensions.
			'optimized checkout keeps UPE methods when editing a non-Stripe post'      => [
				'payment_methods'   => $all_methods,
				'expected_gateways' => $all_non_card,
				'is_admin'          => true,
				'oc_enabled'        => true,
				'edited_post_block' => 'none',
			],
			// Regression guard: order-edit screens must never drop a gateway, or WooCommerce can't find the
			// gateway for refund processing. An order is a post without the Cart/Checkout block.
			'order edit screen keeps all gateways'                                     => [
				'payment_methods'   => $all_methods,
				'expected_gateways' => $all_non_card,
				'is_admin'          => true,
				'oc_enabled'        => true,
				'edited_post_block' => 'none',
			],
			// Regression guard: the refund AJAX action runs without an edited post; the full gateway list
			// must survive there too.
			'refund AJAX without an edited post keeps all gateways'                    => [
				'payment_methods'   => $all_methods,
				'expected_gateways' => $all_non_card,
				'is_admin'          => true,
				'oc_enabled'        => true,
				'edited_post_block' => '',
			],
		];
	}

	/**
	 * Test that the update_option_woocommerce_stripe_settings action triggers webhook reconfiguration when AP/OC is enabled.
	 *
	 * @param array|false $old_value   Previous option value.
	 * @param array       $new_value   New option value.
	 * @param bool        $expect_call Whether maybe_reconfigure_webhooks_on_update is expected to be called on the account.
	 * @dataProvider provide_test_maybe_reconfigure_webhooks_after_adaptive_pricing_enabled
	 */
	public function test_maybe_reconfigure_webhooks_after_adaptive_pricing_enabled( $old_value, $new_value, $expect_call ) {
		$stripe           = WC_Stripe::get_instance();
		$original_account = $stripe->account;

		$mock_account = $this->getMockBuilder( \WC_Stripe_Account::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'maybe_reconfigure_webhooks_on_update' ] )
			->getMock();
		$mock_account->expects( $expect_call ? $this->once() : $this->never() )
			->method( 'maybe_reconfigure_webhooks_on_update' )
			->with( 'settings' );

		$stripe->account = $mock_account;

		try {
			do_action( 'update_option_woocommerce_stripe_settings', $old_value, $new_value, 'woocommerce_stripe_settings' );
		} finally {
			$stripe->account = $original_account;
		}
	}

	/**
	 * Tests that get_main_stripe_gateway() returns the OCS gateway when OCS is enabled
	 * and the base UPE gateway otherwise.
	 *
	 * @dataProvider provide_test_get_main_stripe_gateway
	 */
	public function test_get_main_stripe_gateway_returns_expected_class( array $settings, string $expected_class ): void {
		$original_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe            = WC_Stripe::get_instance();

		$reflection = new ReflectionClass( WC_Stripe::class );
		$property   = $reflection->getProperty( 'stripe_gateway' );
		$property->setAccessible( true );
		$previous_gateway = $property->getValue( $stripe );

		try {
			WC_Stripe_Helper::update_main_stripe_settings( $settings );
			$property->setValue( $stripe, null );

			$gateway = $stripe->get_main_stripe_gateway();

			$this->assertInstanceOf( $expected_class, $gateway );
		} finally {
			$property->setValue( $stripe, $previous_gateway );
			WC_Stripe_Helper::update_main_stripe_settings( $original_settings );
		}
	}

	/**
	 * Data provider for {@see self::test_get_main_stripe_gateway_returns_expected_class()}.
	 */
	public function provide_test_get_main_stripe_gateway(): array {
		return [
			'OCS disabled by setting' => [
				'settings'       => [
					'pmc_enabled'                => 'yes',
					'optimized_checkout_element' => 'no',
				],
				'expected_class' => WC_Stripe_UPE_Payment_Gateway::class,
			],
			'OCS setting missing'     => [
				'settings'       => [ 'pmc_enabled' => 'yes' ],
				'expected_class' => WC_Stripe_UPE_Payment_Gateway::class,
			],
			'OCS gated by pmc flag'   => [
				'settings'       => [
					'pmc_enabled'                => 'no',
					'optimized_checkout_element' => 'yes',
				],
				'expected_class' => WC_Stripe_UPE_Payment_Gateway::class,
			],
			'OCS enabled'             => [
				'settings'       => [
					'pmc_enabled'                => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'expected_class' => WC_Stripe_OCS_Payment_Gateway::class,
			],
		];
	}

	/**
	 * Data provider for maybe_reconfigure_webhooks_after_adaptive_pricing_enabled tests.
	 * Covers the update_option_woocommerce_stripe_settings hook behavior: reconfigure only when
	 * both AP and OC are enabled in the new value and at least one of them changed from the old value.
	 *
	 * @return array[] Old value, new value, whether reconfigure is expected, and scenario name.
	 */
	public function provide_test_maybe_reconfigure_webhooks_after_adaptive_pricing_enabled() {
		return [
			'AP and OC newly enabled'                            => [
				'old_value'   => [
					'adaptive_pricing'           => 'no',
					'optimized_checkout_element' => 'no',
				],
				'new_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'expect_call' => true,
			],
			'AP newly enabled, OC already enabled'               => [
				'old_value'   => [
					'adaptive_pricing'           => 'no',
					'optimized_checkout_element' => 'yes',
				],
				'new_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'expect_call' => true,
			],
			'OC newly enabled, AP already enabled'               => [
				'old_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'no',
				],
				'new_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'expect_call' => true,
			],
			'AP and OC unchanged and both enabled'               => [
				'old_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'new_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'expect_call' => false,
			],
			'AP disabled in new value'                           => [
				'old_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'new_value'   => [
					'adaptive_pricing'           => 'no',
					'optimized_checkout_element' => 'yes',
				],
				'expect_call' => false,
			],
			'OC disabled in new value'                           => [
				'old_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'new_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'no',
				],
				'expect_call' => false,
			],
			'both disabled in new value'                         => [
				'old_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'new_value'   => [
					'adaptive_pricing'           => 'no',
					'optimized_checkout_element' => 'no',
				],
				'expect_call' => false,
			],
			'old value not array, AP enabled in new value'       => [
				'old_value'   => false,
				'new_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'expect_call' => true,
			],
			'old value not array, AP disabled in new value'      => [
				'old_value'   => false,
				'new_value'   => [
					'adaptive_pricing'           => 'no',
					'optimized_checkout_element' => 'yes',
				],
				'expect_call' => false,
			],
			'old value missing AP key, AP enabled in new value'  => [
				'old_value'   => [
					'optimized_checkout_element' => 'yes',
				],
				'new_value'   => [
					'adaptive_pricing'           => 'yes',
					'optimized_checkout_element' => 'yes',
				],
				'expect_call' => true,
			],
			'old value missing AP key, AP disabled in new value' => [
				'old_value'   => [
					'optimized_checkout_element' => 'yes',
				],
				'new_value'   => [
					'adaptive_pricing'           => 'no',
					'optimized_checkout_element' => 'yes',
				],
				'expect_call' => false,
			],
		];
	}

	/* -----------------------------------------------------------------
	 * Plugin initialization guards
	 *
	 * Cover the `if ( self::$instance === $this )` guard pattern added to
	 * WC_Stripe::__construct() and WC_Stripe::init() to prevent duplicate
	 * hook registration and collaborator re-instantiation when WC_Stripe is
	 * constructed more than once.
	 * ----------------------------------------------------------------- */

	/**
	 * The singleton accessor must keep returning the same instance.
	 */
	public function test_get_instance_returns_same_singleton_on_repeated_calls(): void {
		$this->assertSame( WC_Stripe::get_instance(), WC_Stripe::get_instance() );
	}

	/**
	 * Constructing a second WC_Stripe via `new` must not replace the singleton.
	 */
	public function test_direct_construction_does_not_replace_existing_singleton(): void {
		$first  = WC_Stripe::get_instance();
		$second = new WC_Stripe();

		$this->assertSame( $first, WC_Stripe::get_instance() );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * The two admin_init hooks added in __construct()'s guarded block must
	 * only land on the first instance.
	 */
	public function test_admin_init_hooks_registered_only_for_first_instance(): void {
		$first = WC_Stripe::get_instance();

		$this->assertSame( 10, has_action( 'admin_init', [ $first, 'install' ] ) );
		$this->assertSame( 15, has_action( 'admin_init', [ $first, 'maybe_redirect_to_stripe_settings' ] ) );

		$second = new WC_Stripe();

		$this->assertFalse( has_action( 'admin_init', [ $second, 'install' ] ) );
		$this->assertFalse( has_action( 'admin_init', [ $second, 'maybe_redirect_to_stripe_settings' ] ) );

		// First instance's hooks remain at their original priorities.
		$this->assertSame( 10, has_action( 'admin_init', [ $first, 'install' ] ) );
		$this->assertSame( 15, has_action( 'admin_init', [ $first, 'maybe_redirect_to_stripe_settings' ] ) );
	}

	/**
	 * The rest_api_init guard at the end of __construct() must only register
	 * register_routes on the first instance.
	 */
	public function test_register_routes_hook_registered_only_for_first_instance(): void {
		$first = WC_Stripe::get_instance();

		$this->assertSame( 10, has_action( 'rest_api_init', [ $first, 'register_routes' ] ) );

		$second = new WC_Stripe();

		$this->assertFalse( has_action( 'rest_api_init', [ $second, 'register_routes' ] ) );
		$this->assertSame( 10, has_action( 'rest_api_init', [ $first, 'register_routes' ] ) );
	}

	/**
	 * Every hook added by the guarded blocks in init() must be registered on
	 * the first instance and must NOT be registered on a second instance.
	 *
	 * Single sweep test — failure messages identify the offending row.
	 */
	public function test_init_hooks_registered_only_for_first_instance(): void {
		$hooks = [
			[ 'woocommerce_payment_gateways', 'add_gateways', 10 ],
			[ 'pre_update_option_woocommerce_stripe_settings', 'gateway_settings_update', 10 ],
			[ 'plugin_action_links_' . plugin_basename( WC_STRIPE_MAIN_FILE ), 'plugin_action_links', 10 ],
			[ 'plugin_row_meta', 'plugin_row_meta', 10 ],
			[ 'update_option_woocommerce_gateway_order', 'set_stripe_gateways_in_list', 10 ],
			[ 'woocommerce_email_classes', 'add_emails', 20 ],
			[ 'init', 'init_express_checkout', 11 ],
			[ 'init', 'initialize_subscriptions_updater', 10 ],
			[ 'init', 'load_plugin_textdomain', 10 ],
			[ 'init', 'initialize_status_page', 15 ],
			[ 'init', 'initialize_apple_pay_registration', 10 ],
			[ 'woocommerce_init', 'initialize_agentic_commerce', 10 ],
			[ 'wc_payment_gateways_initialized', 'maybe_toggle_payment_methods', 10 ],
			[ 'update_option_woocommerce_stripe_settings', 'maybe_reconfigure_webhooks_after_adaptive_pricing_enabled', 10 ],
		];

		$first = WC_Stripe::get_instance();

		foreach ( $hooks as [ $hook, $callback, $priority ] ) {
			$this->assertSame(
				$priority,
				has_filter( $hook, [ $first, $callback ] ),
				"hook {$hook} -> {$callback} should be registered on the first instance at priority {$priority}"
			);
		}

		$second = new WC_Stripe();

		foreach ( $hooks as [ $hook, $callback, $priority ] ) {
			$this->assertFalse(
				has_filter( $hook, [ $second, $callback ] ),
				"hook {$hook} -> {$callback} should NOT be registered on the second instance"
			);
			$this->assertSame(
				$priority,
				has_filter( $hook, [ $first, $callback ] ),
				"hook {$hook} -> {$callback} on the first instance should remain at priority {$priority} after second construction"
			);
		}
	}

	/**
	 * The frontend-only billing-fields filter must follow the same guard.
	 */
	public function test_checkout_update_email_field_priority_filter_registered_only_for_first_instance_in_frontend(): void {
		$initial_screen = $GLOBALS['current_screen'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['current_screen'] = \WP_Screen::get( 'post.php' );

		try {
			$first = WC_Stripe::get_instance();

			$this->assertSame(
				50,
				has_filter( 'woocommerce_billing_fields', [ $first, 'checkout_update_email_field_priority' ] )
			);

			$second = new WC_Stripe();

			$this->assertFalse(
				has_filter( 'woocommerce_billing_fields', [ $second, 'checkout_update_email_field_priority' ] )
			);
			$this->assertSame(
				50,
				has_filter( 'woocommerce_billing_fields', [ $first, 'checkout_update_email_field_priority' ] )
			);
		} finally {
			if ( null !== $initial_screen ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$GLOBALS['current_screen'] = $initial_screen;
			}
		}
	}

	/**
	 * Collaborator classes that init() instantiates inside guarded blocks must
	 * not be re-instantiated when a second WC_Stripe is constructed.
	 *
	 * Detection: count the callbacks registered on a hook unique to each
	 * collaborator before and after the second construction. The delta must
	 * be zero.
	 */
	public function test_collaborators_not_reinstantiated_on_second_construction(): void {
		// Hooks chosen because they are registered ONLY by the collaborator's
		// constructor / init_hooks() — so a duplicate instance bumps the count
		// by exactly 1.
		$collaborators = [
			'WC_Stripe_Webhook_Handler'                => [ 'woocommerce_api_wc_stripe', 10 ],
			'WC_Stripe_Order_Handler'                  => [ 'woocommerce_admin_order_totals_after_total', 10 ],
			'WC_Stripe_Payment_Tokens'                 => [ 'woocommerce_payment_methods_list_item', 10 ],
			'WC_Stripe_Intent_Controller'              => [ 'wc_ajax_wc_stripe_verify_intent', 10 ],
			'WC_Stripe_Checkout_Sessions_Ajax_Handler' => [ 'wc_ajax_wc_stripe_create_checkout_session', 10 ],
		];

		$before = [];
		foreach ( $collaborators as $class => [ $hook, $priority ] ) {
			$before[ $class ] = $this->count_hook_callbacks( $hook, $priority );
		}

		$wc_stripe_reflection = new ReflectionClass( WC_Stripe::class );
		$instance_reflection  = $wc_stripe_reflection->getProperty( 'instance' );
		$instance_reflection->setAccessible( true );
		$instance_reflection->setValue( null, null );

		$first = WC_Stripe::get_instance();

		$after_first = [];
		foreach ( $collaborators as $class => [ $hook, $priority ] ) {
			$after_first[ $class ] = $this->count_hook_callbacks( $hook, $priority );
			$this->assertGreaterThan( $before[ $class ], $after_first[ $class ], "{$class} should have been instantiated on the first instance" );
		}

		$second = new WC_Stripe();

		foreach ( $collaborators as $class => [ $hook, $priority ] ) {
			$after = $this->count_hook_callbacks( $hook, $priority );
			$this->assertSame(
				$after_first[ $class ] ?? -1,
				$after,
				"{$class} appears to have been re-instantiated: callback count on {$hook} (priority {$priority}) changed from {$before[ $class ]} to {$after}"
			);
		}
	}

	/**
	 * Helper: count callbacks registered on `$hook` at `$priority`.
	 *
	 * @param string $hook     Hook name.
	 * @param int    $priority Priority level.
	 * @return int Number of registered callbacks at that priority.
	 */
	private function count_hook_callbacks( string $hook, int $priority ): int {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) || ! isset( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
			return 0;
		}

		return count( $wp_filter[ $hook ]->callbacks[ $priority ] );
	}
}
