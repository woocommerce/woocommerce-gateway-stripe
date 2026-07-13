<?php
class WC_Stripe_REST_Response_Filter_Test extends WP_UnitTestCase {
	public static function provide_test_data(): array {
		$default_response_as_string = '{
			"object": "list",
			"data": [
				{
					"id": "pi_3TbL9RJlUF0dQbSB00q0FJS2",
					"amount": 2460,
					"amount_capturable": 0,
					"amount_details": {
						"tip": {}
					},
					"payment_details": {
						"customer_reference": null,
						"order_reference": "in_1TbKCUJlUF0dQbSBCq67mKfR"
					},
					"payment_method": null,
					"payment_method_configuration_details": null,
					"payment_method_options": {
						"card": {
							"installments": null,
							"mandate_options": null,
							"network": null,
							"request_three_d_secure": "automatic"
						},
						"link": {
							"persistent_token": null
						}
					},
					"payment_method_types": [
						"card",
						"link"
					]
				}
			]
		}';
		return [
			'filters_nested_payment_details' => [
				$default_response_as_string,
				[
					'object'                               => '',
					'data.id'                              => '',
					'data.payment_details.order_reference' => '',
				],
				'{
					"object": "list",
					"data": [
						{
							"id": "pi_3TbL9RJlUF0dQbSB00q0FJS2",
							"payment_details": {
								"order_reference": "in_1TbKCUJlUF0dQbSBCq67mKfR"
							}
						}
					]
				}',
			],
			'includes_allowed_array_leaf'    => [
				$default_response_as_string,
				[
					'object'                               => '',
					'data.id'                              => '',
					'data.payment_details.order_reference' => '',
					'data.payment_method_types'            => '',
				],
				'{
					"object": "list",
					"data": [
						{
							"id": "pi_3TbL9RJlUF0dQbSB00q0FJS2",
							"payment_details": {
								"order_reference": "in_1TbKCUJlUF0dQbSBCq67mKfR"
							},
							"payment_method_types": [
								"card",
								"link"
							]
						}
					]
				}',
			],
			// Test format callback
			'applies_money_format_callback'  => [
				'{"object": "list", "data": [{"id": "pi_123", "amount": 2460}]}',
				[
					'object'      => '',
					'data.id'     => '',
					'data.amount' => [ WC_Stripe_REST_Response_Filter::class, 'money_format' ],
				],
				'{"object": "list", "data": [{"id": "pi_123", "amount": "24.60"}]}',
			],

			// Test null property value handling
			'preserves_allowed_null_leaf'    => [
				'{"object": "list", "data": [{"id": "pi_123", "payment_method": null}]}',
				[
					'object'              => '',
					'data.id'             => '',
					'data.payment_method' => '',
				],
				'{"object": "list", "data": [{"id": "pi_123", "payment_method": null}]}',
			],
		];
	}

	/**
	 * @dataProvider provide_test_data
	*/
	public function test_filter_response( $response_as_json, $allowed_properties, $expected_response_as_json ) {
		$response          = json_decode( $response_as_json );
		$expected_response = json_decode( $expected_response_as_json );

		$actual_response = WC_Stripe_REST_Response_Filter::filter_response( $response, $allowed_properties );

		$this->assertEquals( $expected_response, $actual_response );
	}
}
