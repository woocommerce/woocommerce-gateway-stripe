<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_CC.
 */
class WC_Stripe_UPE_Payment_Method_CC_Test extends WP_UnitTestCase {
	/**
	 * Tests for `get_title`.
	 *
	 * @param array|bool $payment_details Payment details.
	 * @param array      $query_params Query parameters.
	 * @param string     $expected Expected title.
	 * @return void
	 *
	 * @dataProvider provide_test_get_title
	 */
	public function test_get_title( $payment_details, $query_params, $expected ) {
		if ( is_array( $payment_details ) ) {
			$payment_details = json_decode( wp_json_encode( $payment_details ) );
		}
		if ( ! empty( $query_params ) ) {
			$_GET = array_merge( $_GET, $query_params );
		}

		$payment_method = new WC_Stripe_UPE_Payment_Method_CC();
		$actual         = $payment_method->get_title( $payment_details );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data provider for `test_get_title`.
	 *
	 * @return array
	 */
	public function provide_test_get_title() {
		return [
			'Google Pay'         => [
				'payment details' => [
					'card' => [
						'wallet' => [
							'type' => WC_Stripe_Payment_Methods::GOOGLE_PAY,
						],
					],
				],
				'query params'    => [],
				'expected'        => 'Google Pay (Stripe)',
			],
			'default, hardcoded' => [
				'payment details' => false,
				'query params'    => [],
				'expected'        => 'Credit / Debit Card',
			],
		];
	}

	/**
	 * Guards that `create_payment_token_for_user()` persists `wallet_type` when the
	 * Stripe PaymentMethod was tokenized via a digital wallet (Apple Pay / Google Pay
	 * / Link). The saved-methods list relies on this meta to surface wallet branding
	 * instead of just the underlying card.
	 *
	 * @param string|null $wallet_input Stripe `card.wallet.type` value, or null to omit.
	 * @param string      $expected     Expected token wallet_type after save.
	 * @return void
	 * @dataProvider provide_test_create_payment_token_for_user_wallet_type
	 */
	public function test_create_payment_token_for_user_wallet_type( $wallet_input, $expected ) {
		$user_id = wp_insert_user(
			[
				'user_login' => 'wallet_tester_' . wp_generate_password( 6, false ),
				'user_pass'  => 'password',
				'user_email' => 'wallet_' . wp_generate_password( 6, false ) . '@example.com',
			]
		);

		$card = [
			'brand'         => 'visa',
			'display_brand' => 'visa',
			'exp_month'     => 12,
			'exp_year'      => 2030,
			'last4'         => '4242',
			'fingerprint'   => 'F_wallet_' . wp_generate_password( 6, false ),
		];
		if ( null !== $wallet_input ) {
			$card['wallet'] = [ 'type' => $wallet_input ];
		}

		$payment_method = json_decode(
			wp_json_encode(
				[
					'id'   => 'pm_test_' . wp_generate_password( 6, false ),
					'type' => WC_Stripe_Payment_Methods::CARD,
					'card' => $card,
				]
			)
		);

		$method = new WC_Stripe_UPE_Payment_Method_CC();
		$token  = $method->create_payment_token_for_user( $user_id, $payment_method );

		$this->assertInstanceOf( WC_Stripe_Payment_Token_CC::class, $token );
		$this->assertSame( $expected, $token->get_wallet_type() );

		$reloaded = WC_Payment_Tokens::get( $token->get_id() );
		$this->assertSame( $expected, $reloaded->get_wallet_type(), 'wallet_type must round-trip through storage.' );
	}

	/**
	 * Data provider for `test_create_payment_token_for_user_wallet_type`.
	 *
	 * @return array
	 */
	public function provide_test_create_payment_token_for_user_wallet_type() {
		return [
			'Apple Pay'                     => [ 'apple_pay', 'apple_pay' ],
			'Google Pay'                    => [ 'google_pay', 'google_pay' ],
			'Link-wrapped'                  => [ 'link', 'link' ],
			'No wallet (manual card entry)' => [ null, '' ],
		];
	}

	/**
	 * Test for `get_testing_instructions`.
	 *
	 * @dataProvider provider_get_testing_instructions
	 * @return void
	 */
	public function test_get_testing_instructions( bool $include_test_mode_label, string $expected ) {
		$payment_method = new WC_Stripe_UPE_Payment_Method_CC();
		$this->assertEquals( $expected, $payment_method->get_testing_instructions( false, $include_test_mode_label ) );
	}

	public function provider_get_testing_instructions(): array {
		return [
			'with label (classic checkout)'   => [
				true,
				'<strong>Test mode:</strong> use card <number>4242 4242 4242 4242</number> with any expiry and CVC. <a href="https://docs.stripe.com/testing" target="_blank">More test cards</a>.',
			],
			'without label (blocks checkout)' => [
				false,
				'Use card <number>4242 4242 4242 4242</number> with any expiry and CVC. <a href="https://docs.stripe.com/testing" target="_blank">More test cards</a>.',
			],
		];
	}

	/**
	 * Tests for `should_show_save_option`.
	 *
	 * The CC override returns false when Link is enabled, otherwise
	 * delegates to the parent (which checks is_reusable && saved_cards).
	 *
	 * Because `should_show_save_option` calls `is_link_enabled( woocommerce_gateway_stripe() )`,
	 * which requires a proper gateway instance, the test controls the enabled
	 * payment methods via the Stripe settings option.
	 *
	 * @param bool   $link_enabled Whether Link is in the enabled payment methods.
	 * @param string $saved_cards  The 'saved_cards' setting value.
	 * @param bool   $expected     Expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_should_show_save_option
	 */
	public function test_should_show_save_option( $link_enabled, $saved_cards, $expected ) {
		$settings                = WC_Stripe_Helper::get_stripe_settings();
		$original_saved_cards    = $settings['saved_cards'] ?? '';
		$original_accepted       = $settings['upe_checkout_experience_accepted_payments'] ?? [];
		$settings['saved_cards'] = $saved_cards;

		$enabled_methods = [ WC_Stripe_Payment_Methods::CARD ];
		if ( $link_enabled ) {
			$enabled_methods[] = WC_Stripe_Payment_Methods::LINK;
		}
		$settings['upe_checkout_experience_accepted_payments'] = $enabled_methods;

		WC_Stripe_Helper::update_main_stripe_settings( $settings );

		// Ensure the WC_Stripe singleton has a gateway whose
		// get_upe_enabled_payment_method_ids reads from settings.
		$wc_stripe        = woocommerce_gateway_stripe();
		$original_gateway = $this->get_stripe_gateway( $wc_stripe );
		$gateway          = new WC_Stripe_UPE_Payment_Gateway();
		$this->set_stripe_gateway( $wc_stripe, $gateway );

		try {
			$payment_method = new WC_Stripe_UPE_Payment_Method_CC();
			$actual         = $payment_method->should_show_save_option();

			$this->assertSame( $expected, $actual );
		} finally {
			// Restore original state.
			$this->set_stripe_gateway( $wc_stripe, $original_gateway );
			$settings['saved_cards']                               = $original_saved_cards;
			$settings['upe_checkout_experience_accepted_payments'] = $original_accepted;
			WC_Stripe_Helper::update_main_stripe_settings( $settings );
		}
	}

	/**
	 * Data provider for `test_should_show_save_option`.
	 *
	 * @return array
	 */
	public function provide_test_should_show_save_option() {
		return [
			'Link enabled — always false'                 => [
				'link_enabled' => true,
				'saved_cards'  => 'yes',
				'expected'     => false,
			],
			'Link disabled, saved cards enabled — true'   => [
				'link_enabled' => false,
				'saved_cards'  => 'yes',
				'expected'     => true,
			],
			'Link disabled, saved cards disabled — false' => [
				'link_enabled' => false,
				'saved_cards'  => 'no',
				'expected'     => false,
			],
		];
	}

	/**
	 * Gets the stripe_gateway property from WC_Stripe via reflection.
	 *
	 * @param WC_Stripe $wc_stripe The WC_Stripe instance.
	 * @return WC_Stripe_UPE_Payment_Gateway|null
	 */
	private function get_stripe_gateway( $wc_stripe ) {
		$property = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$property->setAccessible( true );
		return $property->getValue( $wc_stripe );
	}

	/**
	 * Sets the stripe_gateway property on WC_Stripe via reflection.
	 *
	 * @param WC_Stripe                          $wc_stripe The WC_Stripe instance.
	 * @param WC_Stripe_UPE_Payment_Gateway|null $gateway   The gateway to set.
	 */
	private function set_stripe_gateway( $wc_stripe, $gateway ) {
		$property = new ReflectionProperty( WC_Stripe::class, 'stripe_gateway' );
		$property->setAccessible( true );
		$property->setValue( $wc_stripe, $gateway );
	}
}
