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
	 */
	public function test_statement_descriptor_sanitation() {
		$statement_descriptor1 = array(
			'actual'   => 'Test\'s Store',
			'expected' => 'Tests Store',
		);

		$this->assertEquals( $statement_descriptor1['expected'], WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor1['actual'] ) );

		$statement_descriptor2 = array(
			'actual'   => 'Test\'s Store > Driving Course Range',
			'expected' => 'Tests Store  Driving C',
		);

		$this->assertEquals( $statement_descriptor2['expected'], WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor2['actual'] ) );

		$statement_descriptor3 = array(
			'actual'   => 'Test\'s Store < Driving Course Range',
			'expected' => 'Tests Store  Driving C',
		);

		$this->assertEquals( $statement_descriptor3['expected'], WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor3['actual'] ) );

		$statement_descriptor4 = array(
			'actual'   => 'Test\'s Store " Driving Course Range',
			'expected' => 'Tests Store  Driving C',
		);

		$this->assertEquals( $statement_descriptor4['expected'], WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor4['actual'] ) );
	}

	/**
	 * Test if credit card is of type 3DS.
	 */
	public function test_is_3ds_card() {
		$stripe = new WC_Gateway_Stripe();

		$source = new stdClass();
		$source->type = 'three_d_secure';

		$this->assertEquals( true, $stripe->is_3ds_card( $source ) );

		$source = new stdClass();
		$source->type = 'card';

		$this->assertEquals( false, $stripe->is_3ds_card( $source ) );
	}

	/**
	 * Test if 3DS is required.
	 */
	public function test_is_3ds_required() {
		$stripe = new WC_Gateway_Stripe();

		$source = new stdClass();
		$source->type = 'card';
		$source->card = new stdClass();
		$source->card->three_d_secure = 'required';

		$this->assertEquals( true, $stripe->is_3ds_required( $source ) );

		$source = new stdClass();
		$source->type = 'card';
		$source->card = new stdClass();
		$source->card->three_d_secure = 'optional';

		$this->assertEquals( false, $stripe->is_3ds_required( $source ) );

		$source = new stdClass();
		$source->type = 'card';
		$source->card = new stdClass();
		$source->card->three_d_secure = 'not_supported';

		$this->assertEquals( false, $stripe->is_3ds_required( $source ) );
	}
}
