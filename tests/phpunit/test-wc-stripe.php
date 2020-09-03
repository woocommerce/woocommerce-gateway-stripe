<?php

class WC_Stripe_Test extends WP_UnitTestCase {
	public function test_constants_defined() {
		$this->assertTrue( defined( 'WC_STRIPE_VERSION' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MIN_PHP_VER' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MIN_WC_VER' ) );
		$this->assertTrue( defined( 'WC_STRIPE_MAIN_FILE' ) );
		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'WC_STRIPE_PLUGIN_PATH' ) );
	}

	/**
	 * Stripe requires price in the smallest dominations aka cents.
	 * This test will see if we're indeed converting the price correctly.
	 */
	public function test_price_conversion_before_send_to_stripe() {
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50, 'USD' ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 10050, 'JPY' ) );
		$this->assertEquals( 100, WC_Stripe_Helper::get_stripe_amount( 100.50, 'JPY' ) );
		$this->assertEquals( 10050, WC_Stripe_Helper::get_stripe_amount( 100.50 ) );
		$this->assertInternalType( 'int', WC_Stripe_Helper::get_stripe_amount( 100.50, 'USD' ) );
	}

	/**
	 * We store balance fee/net amounts coming from Stripe.
	 * We need to make sure we format it correctly to be stored in WC.
	 * These amounts are posted in lowest dominations.
	 */
	public function test_format_balance_fee() {
		$balance_fee1 = new stdClass();
		$balance_fee1->fee = 10500;
		$balance_fee1->net = 10000;
		$balance_fee1->currency = 'USD';

		$this->assertEquals( 105.00, WC_Stripe_Helper::format_balance_fee( $balance_fee1, 'fee' ) );

		$balance_fee2 = new stdClass();
		$balance_fee2->fee = 10500;
		$balance_fee2->net = 10000;
		$balance_fee2->currency = 'JPY';

		$this->assertEquals( 10500, WC_Stripe_Helper::format_balance_fee( $balance_fee2, 'fee' ) );

		$balance_fee3 = new stdClass();
		$balance_fee3->fee = 10500;
		$balance_fee3->net = 10000;
		$balance_fee3->currency = 'USD';

		$this->assertEquals( 100.00, WC_Stripe_Helper::format_balance_fee( $balance_fee3, 'net' ) );

		$balance_fee4 = new stdClass();
		$balance_fee4->fee = 10500;
		$balance_fee4->net = 10000;
		$balance_fee4->currency = 'JPY';

		$this->assertEquals( 10000, WC_Stripe_Helper::format_balance_fee( $balance_fee4, 'net' ) );

		$balance_fee5 = new stdClass();
		$balance_fee5->fee = 10500;
		$balance_fee5->net = 10000;
		$balance_fee5->currency = 'USD';

		$this->assertEquals( 105.00, WC_Stripe_Helper::format_balance_fee( $balance_fee5 ) );

		$this->assertInternalType( 'string', WC_Stripe_Helper::format_balance_fee( $balance_fee5 ) );
	}

	/**
	 * Stripe requires statement_descriptor to be no longer than 22 characters.
	 * In addition, it cannot contain <>"' special characters.
	 *
     * @dataProvider statement_descriptor_sanitation_provider
	 */
	public function test_statement_descriptor_sanitation( $original, $expected ) {
		$this->assertEquals( $expected, WC_Stripe_Helper::clean_statement_descriptor( $original ) );
	}

	public function statement_descriptor_sanitation_provider() {
		return [
			'removes \'' => [ 'Test\'s Store', 'Tests Store' ],
			'removes "' => [ 'Test " Store', 'Test  Store' ],
			'removes <' => [ 'Test < Store', 'Test  Store' ],
			'removes >' => [ 'Test > Store', 'Test  Store' ],
			'removes /' => [ 'Test / Store', 'Test  Store' ],
			'removes (' => [ 'Test ( Store', 'Test  Store' ],
			'removes )' => [ 'Test ) Store', 'Test  Store' ],
			'removes {' => [ 'Test { Store', 'Test  Store' ],
			'removes }' => [ 'Test } Store', 'Test  Store' ],
			'keeps at most 22 chars' => [ 'Test\'s Store > Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and >' => [ 'Test\'s Store > Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and <' => [ 'Test\'s Store < Driving Course Range', 'Tests Store  Driving C' ],
			'mixed length, \' and "' => [ 'Test\'s Store " Driving Course Range', 'Tests Store  Driving C' ]
		];
	}
}
