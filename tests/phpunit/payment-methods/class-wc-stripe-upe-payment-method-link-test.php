<?php

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Link.
 */
class WC_Stripe_UPE_Payment_Method_Link_Test extends WP_UnitTestCase {
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
		$settings             = WC_Stripe_Helper::get_stripe_settings();
		$original_saved_cards = $settings['saved_cards'] ?? '';
		try {
			$settings['saved_cards'] = $saved_cards;
			WC_Stripe_Helper::update_main_stripe_settings( $settings );

			$payment_method = new WC_Stripe_UPE_Payment_Method_Link();

			$this->assertFalse(
				$payment_method->should_show_save_option(),
				'Link should never show the save option regardless of saved_cards setting.'
			);
		} finally {
			$settings['saved_cards'] = $original_saved_cards;
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
			'saved cards enabled'  => [ 'saved_cards' => 'yes' ],
			'saved cards disabled' => [ 'saved_cards' => 'no' ],
		];
	}
}
