<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Link.
 */
class WC_Stripe_UPE_Payment_Method_Link_Test extends WC_Stripe_UPE_Payment_Method_Test_Case {
	/**
	 * Test that `should_show_save_option` always returns false.
	 *
	 * Link handles its own save consent via the Payment Element, so
	 * the store-level save checkbox is never needed.
	 *
	 * @param string $saved_cards The 'saved_cards' setting value.
	 * @return void
	 *
	 * @dataProvider provide_test_should_show_save_option
	 */
	public function test_should_show_save_option( $saved_cards ) {
		$settings             = WC_Stripe::get_instance()->get_settings();
		$original_saved_cards = $settings['saved_cards'] ?? '';
		try {
			$settings['saved_cards'] = $saved_cards;
			WC_Stripe::get_instance()->update_settings( $settings );

			$payment_method = new WC_Stripe_UPE_Payment_Method_Link();

			$this->assertFalse(
				$payment_method->should_show_save_option(),
				'Link should never show the save option regardless of saved_cards setting.'
			);
		} finally {
			$settings['saved_cards'] = $original_saved_cards;
			WC_Stripe::get_instance()->update_settings( $settings );
		}
	}

	/**
	 * Data provider for `test_should_show_save_option`.
	 *
	 * @return array
	 */
	public function provide_test_should_show_save_option() {
		return [
			'saved cards enabled'  => [ 'saved_cards' => 'yes' ],
			'saved cards disabled' => [ 'saved_cards' => 'no' ],
		];
	}

	/**
	 * Test that {@see WC_Stripe_UPE_Payment_Method_Link::is_available_for_account_country()}
	 * behaves as expected.
	 *
	 * @param string $account_country The account country.
	 * @param bool   $expected_result The expected result.
	 * @return void
	 *
	 * @dataProvider provide_test_is_available_for_account_country
	 */
	public function test_is_available_for_account_country( string $account_country, bool $expected_result ): void {
		$this->run_is_available_for_account_country_test( WC_Stripe_UPE_Payment_Method_Link::class, $account_country, $expected_result );
	}

	/**
	 * Data provider for {@see test_is_available_for_account_country()}.
	 *
	 * @return array
	 */
	public function provide_test_is_available_for_account_country(): array {
		return [
			'US is supported'     => [ WC_Stripe_Country_Code::UNITED_STATES, true ],
			'GB is supported'     => [ WC_Stripe_Country_Code::UNITED_KINGDOM, true ],
			'ES is supported'     => [ WC_Stripe_Country_Code::SPAIN, true ],
			'JP is supported'     => [ WC_Stripe_Country_Code::JAPAN, true ],
			'BR is not supported' => [ WC_Stripe_Country_Code::BRAZIL, false ],
			'TH is not supported' => [ WC_Stripe_Country_Code::THAILAND, false ],
			'ZZ is supported'     => [ 'ZZ', true ],
		];
	}
}
