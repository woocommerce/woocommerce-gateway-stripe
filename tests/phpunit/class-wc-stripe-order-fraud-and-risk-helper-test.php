<?php

/**
 * Class WC_Stripe_Order_Fraud_And_Risk_Helper tests.
 */
class WC_Stripe_Order_Fraud_And_Risk_Helper_Test extends WP_UnitTestCase {
	/**
	 * Data provider for {@see test_fraud_and_risk_meta_box_visibility()}.
	 *
	 * @return array
	 */
	public function provide_charges_with_fraud_and_risk_flags_payloads(): Generator {
		$files = [
			'outcome.risk_level-normal.json'                                         => false,
			'outcome.risk_level-elevated.json'                                       => true,
			'fraud_details.json'                                                     => true,
			'payment_method_details.card.checks.cvc_check-fail.json'                 => true,
			'payment_method_details.card.checks.address_line1_check-fail.json'       => true,
			'payment_method_details.card.checks.address_postal_code_check-fail.json' => true,
			'payment_method_details.card.checks-pass.json'                           => false,
			'payment_method_details.card.three_d_secure.result-failed.json'          => true,
		];

		foreach ( $files as $file => $has_fraud_and_risk_flags ) {
			$file_contents = file_get_contents( __DIR__ . '/dummy-data/stripe_fraud_and_risk_charge_payloads/' . $file );

			yield [ $file_contents, $has_fraud_and_risk_flags ];
		}
	}

	/**
	 * @dataProvider provide_charges_with_fraud_and_risk_flags_payloads
	 */
	public function test_order_fraud_and_risk_flags( $charge_as_json, $has_fraud_and_risk_flags ) {
		$order = WC_Helper_Order::create_order();
		$order->set_transaction_id( 'ch_test1' );

		$stripe_request_mock = function ( $preempt, $request_args, $url ) use ( $charge_as_json ) {
			return [ 'body' => $charge_as_json ];
		};

		add_filter( 'pre_http_request', $stripe_request_mock, 10, 3 );

		WC_Stripe_Order_Fraud_And_Risk_Helper::get_order_transaction_fraud_flags( $order );
		$fraud_and_risk_flags = WC_Stripe_Order_Fraud_And_Risk_Helper::order_has_transaction_fraud_flags_risk( $order );

		$this->assertEquals( $has_fraud_and_risk_flags, is_object( $fraud_and_risk_flags ) ? true : false );

		remove_filter( 'pre_http_request', $stripe_request_mock );
	}

	public function test_order_fraud_and_risk_when_transaction_id_change() {
		$order = WC_Helper_Order::create_order();

		for ( $i = 1; $i <= 2; $i++ ) {
			$charge_id  = 'ch_test' . $i;
			$risk_level = ( 1 == $i ) ? 'elevated' : 'normal';
			$expected   = ( 1 == $i );

			$order->set_transaction_id( $charge_id );

			$charge_as_json = '{
				"id": "' . $charge_id . '",
				"object": "charge",
				"outcome":
				{
					"risk_level": "' . $risk_level . '"
				}
			}';

			$stripe_request_mock = function ( $preempt, $request_args, $url ) use ( $charge_as_json ) {
				return [ 'body' => $charge_as_json ];
			};

			add_filter( 'pre_http_request', $stripe_request_mock, 10, 3 );

			WC_Stripe_Order_Fraud_And_Risk_Helper::get_order_transaction_fraud_flags( $order );
			$fraud_and_risk_flags = WC_Stripe_Order_Fraud_And_Risk_Helper::order_has_transaction_fraud_flags_risk( $order );

			$this->assertEquals( $expected, is_object( $fraud_and_risk_flags ) ? true : false );

			remove_filter( 'pre_http_request', $stripe_request_mock );
		}
	}
}
