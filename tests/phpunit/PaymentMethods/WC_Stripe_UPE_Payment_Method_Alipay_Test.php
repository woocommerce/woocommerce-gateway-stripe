<?php

namespace WooCommerce\Stripe\Tests\PaymentMethods;

use WC_Stripe_Currency_Code;
use WC_Stripe_UPE_Payment_Method_Alipay;
use WooCommerce\Stripe\Tests\WC_Mock_Stripe_API_Unit_Test_Case;

/**
 * These tests make assertions against class WC_Stripe_UPE_Payment_Method_Alipay.
 */
class WC_Stripe_UPE_Payment_Method_Alipay_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * @dataProvider get_supported_currencies_provider
	 */
	public function test_get_supported_currencies( string $country, array $expected_currencies ) {
		$this->set_stripe_account_data( [ 'country' => $country ] );

		$alipay = new WC_Stripe_UPE_Payment_Method_Alipay();

		$this->assertSame( $expected_currencies, $alipay->get_supported_currencies() );
	}

	public function get_supported_currencies_provider(): array {
		return [
			'GB account does not include GBP' => [
				'country'             => 'GB',
				'expected_currencies' => [ WC_Stripe_Currency_Code::CHINESE_YUAN ],
			],
			'US account includes USD and CNY' => [
				'country'             => 'US',
				'expected_currencies' => [ WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ],
			],
			'AU account includes AUD and CNY' => [
				'country'             => 'AU',
				'expected_currencies' => [ WC_Stripe_Currency_Code::AUSTRALIAN_DOLLAR, WC_Stripe_Currency_Code::CHINESE_YUAN ],
			],
			'FR account includes EUR and CNY' => [
				'country'             => 'FR',
				'expected_currencies' => [ WC_Stripe_Currency_Code::EURO, WC_Stripe_Currency_Code::CHINESE_YUAN ],
			],
			'Unknown country defaults to CNY only' => [
				'country'             => 'ZZ',
				'expected_currencies' => [ WC_Stripe_Currency_Code::CHINESE_YUAN ],
			],
		];
	}

	/**
	 * GBP must not appear in the general supported_currencies list.
	 */
	public function test_gbp_not_in_supported_currencies() {
		$alipay = new WC_Stripe_UPE_Payment_Method_Alipay();

		$this->assertNotContains(
			WC_Stripe_Currency_Code::POUND_STERLING,
			$alipay->get_supported_currencies()
		);
	}
}
