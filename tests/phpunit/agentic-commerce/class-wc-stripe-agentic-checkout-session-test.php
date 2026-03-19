<?php
/**
 * Tests for WC_Stripe_Agentic_Checkout_Session
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Checkout_Session_Test
 *
 * Tests the typed wrapper around Stripe checkout session data.
 *
 * @covers WC_Stripe_Agentic_Checkout_Session
 */
class WC_Stripe_Agentic_Checkout_Session_Test extends WP_UnitTestCase {

	/**
	 * Test get_id returns the session ID.
	 */
	public function test_get_id() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [ 'id' => 'cs_test_123' ] );
		$this->assertSame( 'cs_test_123', $session->get_id() );
	}

	/**
	 * Test get_id returns null when missing.
	 */
	public function test_get_id_returns_null_when_missing() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [] );
		$this->assertNull( $session->get_id() );
	}

	/**
	 * Test get_currency returns uppercase.
	 */
	public function test_get_currency_returns_uppercase() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [ 'currency' => 'usd' ] );
		$this->assertSame( 'USD', $session->get_currency() );
	}

	/**
	 * Test get_currency returns null when missing.
	 */
	public function test_get_currency_returns_null_when_missing() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [] );
		$this->assertNull( $session->get_currency() );
	}

	/**
	 * Test get_currency_lowercase returns lowercase.
	 */
	public function test_get_currency_lowercase() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [ 'currency' => 'EUR' ] );
		$this->assertSame( 'eur', $session->get_currency_lowercase() );
	}

	/**
	 * @dataProvider provide_amount_total_cases
	 */
	public function test_get_amount_total( object $raw, ?int $expected ) {
		$session = new WC_Stripe_Agentic_Checkout_Session( $raw );
		$this->assertSame( $expected, $session->get_amount_total() );
	}

	/**
	 * @return array
	 */
	public function provide_amount_total_cases(): array {
		return [
			'integer value'          => [
				(object) [ 'amount_total' => 2500 ],
				2500,
			],
			'string float truncated' => [
				(object) [ 'amount_total' => '2500.3' ],
				2500,
			],
			'zero is zero'           => [
				(object) [ 'amount_total' => 0 ],
				0,
			],
			'missing is null'        => [
				(object) [],
				null,
			],
		];
	}

	/**
	 * @dataProvider provide_customer_email_cases
	 */
	public function test_get_customer_email( object $raw, ?string $expected ) {
		$session = new WC_Stripe_Agentic_Checkout_Session( $raw );
		$this->assertSame( $expected, $session->get_customer_email() );
	}

	/**
	 * @return array
	 */
	public function provide_customer_email_cases(): array {
		return [
			'from customer_details'      => [
				(object) [
					'customer_details' => (object) [ 'email' => 'details@example.com' ],
					'customer_email'   => 'top@example.com',
				],
				'details@example.com',
			],
			'fallback to customer_email' => [
				(object) [
					'customer_details' => (object) [ 'email' => null ],
					'customer_email'   => 'top@example.com',
				],
				'top@example.com',
			],
			'null when both missing'     => [
				(object) [],
				null,
			],
		];
	}

	/**
	 * @dataProvider provide_customer_name_cases
	 */
	public function test_get_customer_name( object $raw, ?string $expected ) {
		$session = new WC_Stripe_Agentic_Checkout_Session( $raw );
		$this->assertSame( $expected, $session->get_customer_name() );
	}

	/**
	 * @return array
	 */
	public function provide_customer_name_cases(): array {
		return [
			'from customer_details'             => [
				(object) [
					'customer_details' => (object) [ 'name' => 'John Smith' ],
					'shipping_details' => (object) [ 'name' => 'Jane Doe' ],
				],
				'John Smith',
			],
			'fallback to shipping name'         => [
				(object) [
					'customer_details' => (object) [ 'name' => null ],
					'shipping_details' => (object) [ 'name' => 'Jane Doe' ],
				],
				'Jane Doe',
			],
			'fallback to collected_information' => [
				(object) [
					'customer_details'      => (object) [ 'name' => null ],
					'shipping_details'      => null,
					'collected_information' => (object) [
						'shipping_details' => (object) [ 'name' => 'Collected Name' ],
					],
				],
				'Collected Name',
			],
			'null when all missing'             => [
				(object) [
					'customer_details' => (object) [ 'name' => null ],
				],
				null,
			],
			'null for empty object'             => [
				(object) [],
				null,
			],
		];
	}

	/**
	 * Test get_billing_phone.
	 */
	public function test_get_billing_phone() {
		$session = new WC_Stripe_Agentic_Checkout_Session(
			(object) [
				'customer_details' => (object) [ 'phone' => '+15551234567' ],
			]
		);
		$this->assertSame( '+15551234567', $session->get_billing_phone() );
	}

	/**
	 * Test get_billing_phone returns null when missing.
	 */
	public function test_get_billing_phone_null_when_missing() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [] );
		$this->assertNull( $session->get_billing_phone() );
	}

	/**
	 * Test get_billing_address returns a WC_Stripe_API_Address wrapping the raw address.
	 */
	public function test_get_billing_address() {
		$address = (object) [
			'line1' => '123 Main St',
			'city'  => 'Anytown',
		];
		$session = new WC_Stripe_Agentic_Checkout_Session(
			(object) [
				'customer_details' => (object) [ 'address' => $address ],
			]
		);
		$result  = $session->get_billing_address();
		$this->assertInstanceOf( \WC_Stripe_API_Address::class, $result );
		$this->assertEquals( '123 Main St', $result->get_line1() );
		$this->assertEquals( 'Anytown', $result->get_city() );
	}

	/**
	 * Test get_billing_address throws when missing.
	 */
	public function test_get_billing_address_null_when_missing() {
		$this->expectException( \Exception::class );
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [] );
		$session->get_billing_address();
	}

	/**
	 * @dataProvider provide_shipping_details_cases
	 */
	public function test_get_shipping_details( object $raw, bool $expect_non_null ) {
		$session = new WC_Stripe_Agentic_Checkout_Session( $raw );
		if ( $expect_non_null ) {
			$this->assertNotNull( $session->get_shipping_details() );
		} else {
			$this->assertNull( $session->get_shipping_details() );
		}
	}

	/**
	 * @return array
	 */
	public function provide_shipping_details_cases(): array {
		return [
			'from top-level'             => [
				(object) [
					'shipping_details' => (object) [ 'name' => 'Test' ],
				],
				true,
			],
			'from collected_information' => [
				(object) [
					'shipping_details'      => null,
					'collected_information' => (object) [
						'shipping_details' => (object) [ 'name' => 'Collected' ],
					],
				],
				true,
			],
			'null when both missing'     => [
				(object) [
					'shipping_details' => null,
				],
				false,
			],
		];
	}

	/**
	 * @dataProvider provide_shipping_phone_cases
	 */
	public function test_get_shipping_phone( object $raw, ?string $expected ) {
		$session = new WC_Stripe_Agentic_Checkout_Session( $raw );
		$this->assertSame( $expected, $session->get_shipping_phone() );
	}

	/**
	 * @return array
	 */
	public function provide_shipping_phone_cases(): array {
		return [
			'from shipping details'     => [
				(object) [
					'shipping_details' => (object) [ 'phone' => '+15559999999' ],
					'customer_details' => (object) [ 'phone' => '+15551111111' ],
				],
				'+15559999999',
			],
			'fallback to billing phone' => [
				(object) [
					'shipping_details' => (object) [ 'name' => 'Test' ],
					'customer_details' => (object) [ 'phone' => '+15551111111' ],
				],
				'+15551111111',
			],
			'null when no phone'        => [
				(object) [
					'shipping_details' => (object) [ 'name' => 'Test' ],
				],
				null,
			],
		];
	}

	/**
	 * Test get_shipping_name.
	 */
	public function test_get_shipping_name() {
		$session = new WC_Stripe_Agentic_Checkout_Session(
			(object) [
				'shipping_details' => (object) [ 'name' => 'Bob Jones' ],
			]
		);
		$this->assertSame( 'Bob Jones', $session->get_shipping_name() );
	}

	/**
	 * Test get_shipping_address returns a WC_Stripe_API_Address.
	 */
	public function test_get_shipping_address() {
		$address = (object) [ 'line1' => '456 Oak Ave' ];
		$session = new WC_Stripe_Agentic_Checkout_Session(
			(object) [
				'shipping_details' => (object) [
					'name'    => 'Test',
					'address' => $address,
				],
			]
		);
		$result  = $session->get_shipping_address();
		$this->assertInstanceOf( \WC_Stripe_API_Address::class, $result );
		$this->assertEquals( '456 Oak Ave', $result->get_line1() );
	}

	/**
	 * Test get_shipping_address null when no shipping.
	 */
	public function test_get_shipping_address_null_when_no_shipping() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [ 'shipping_details' => null ] );
		$this->assertNull( $session->get_shipping_address() );
	}

	/**
	 * Test get_line_items.
	 */
	public function test_get_line_items() {
		$items      = [ (object) [ 'id' => 'li_1' ], (object) [ 'id' => 'li_2' ] ];
		$session    = new WC_Stripe_Agentic_Checkout_Session(
			(object) [
				'line_items' => (object) [ 'data' => $items ],
			]
		);
		$line_items = $session->get_line_items();
		$this->assertCount( 2, $line_items );
		$this->assertInstanceOf( \WC_Stripe_Agentic_Line_Item::class, $line_items[0] );
		$this->assertInstanceOf( \WC_Stripe_Agentic_Line_Item::class, $line_items[1] );
		$this->assertSame( 'li_1', $line_items[0]->get_id() );
		$this->assertSame( 'li_2', $line_items[1]->get_id() );
	}

	/**
	 * Test get_line_items empty when missing.
	 */
	public function test_get_line_items_empty_when_missing() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [] );
		$this->assertSame( [], $session->get_line_items() );
	}

	/**
	 * Test get_payment_intent_id.
	 */
	public function test_get_payment_intent_id() {
		$session = new WC_Stripe_Agentic_Checkout_Session(
			(object) [
				'payment_intent' => (object) [ 'id' => 'pi_test_123' ],
			]
		);
		$this->assertSame( 'pi_test_123', $session->get_payment_intent_id() );
	}

	/**
	 * Test get_payment_intent_id null when missing.
	 */
	public function test_get_payment_intent_id_null_when_missing() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [] );
		$this->assertNull( $session->get_payment_intent_id() );
	}

	/**
	 * Test get_customer_id.
	 */
	public function test_get_customer_id() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [ 'customer' => 'cus_test_abc' ] );
		$this->assertSame( 'cus_test_abc', $session->get_customer_id() );
	}

	/**
	 * Test get_customer_id null when missing.
	 */
	public function test_get_customer_id_null_when_missing() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [] );
		$this->assertNull( $session->get_customer_id() );
	}

	/**
	 * Test get_customer_id returns id from expanded customer object.
	 */
	public function test_get_customer_id_from_expanded_object() {
		$session = new WC_Stripe_Agentic_Checkout_Session(
			(object) [ 'customer' => (object) [ 'id' => 'cus_expanded' ] ]
		);
		$this->assertSame( 'cus_expanded', $session->get_customer_id() );
	}

	/**
	 * Test get_shipping_amount.
	 */
	public function test_get_shipping_amount() {
		$session = new WC_Stripe_Agentic_Checkout_Session(
			(object) [
				'total_details' => (object) [ 'amount_shipping' => 500 ],
			]
		);
		$this->assertSame( 500, $session->get_shipping_amount() );
	}

	/**
	 * Test get_shipping_amount returns null when missing.
	 */
	public function test_get_shipping_amount_null_when_missing() {
		$session = new WC_Stripe_Agentic_Checkout_Session( (object) [] );
		$this->assertNull( $session->get_shipping_amount() );
	}

	/**
	 * @dataProvider provide_is_agentic_cases
	 */
	public function test_is_agentic( object $raw, bool $expected ) {
		$session = new WC_Stripe_Agentic_Checkout_Session( $raw );
		$this->assertSame( $expected, $session->is_agentic() );
	}

	/**
	 * @return array
	 */
	public function provide_is_agentic_cases(): array {
		return [
			'has external_reference product ID' => [
				(object) [
					'line_items' => (object) [
						'data' => [
							(object) [
								'price' => (object) [ 'external_reference' => '42' ],
							],
						],
					],
				],
				true,
			],
			'multiple items one with reference' => [
				(object) [
					'line_items' => (object) [
						'data' => [
							(object) [
								'price' => (object) [ 'external_reference' => null ],
							],
							(object) [
								'price' => (object) [ 'external_reference' => '99' ],
							],
						],
					],
				],
				true,
			],
			'no external_reference'             => [
				(object) [
					'line_items' => (object) [
						'data' => [
							(object) [ 'price' => (object) [] ],
						],
					],
				],
				false,
			],
			'external_reference is zero string' => [
				(object) [
					'line_items' => (object) [
						'data' => [
							(object) [
								'price' => (object) [ 'external_reference' => '0' ],
							],
						],
					],
				],
				false,
			],
			'empty line items'                  => [
				(object) [
					'line_items' => (object) [ 'data' => [] ],
				],
				false,
			],
			'external_reference is non-numeric' => [
				(object) [
					'line_items' => (object) [
						'data' => [
							(object) [
								'price' => (object) [ 'external_reference' => 'not-a-number' ],
							],
						],
					],
				],
				false,
			],
		];
	}

	/**
	 * Test get_fields_to_expand includes both line items and shipping cost.
	 */
	public function test_get_fields_to_expand() {
		$fields = WC_Stripe_Agentic_Checkout_Session::get_fields_to_expand();
		$this->assertIsArray( $fields );
		$this->assertContains( 'line_items.data.price.product', $fields );
		$this->assertContains( 'shipping_cost.shipping_rate', $fields );
	}

	/**
	 * @dataProvider provide_chosen_shipping_rate_wc_id_cases
	 */
	public function test_get_chosen_shipping_rate_wc_id( object $raw, ?string $expected ) {
		$session = new WC_Stripe_Agentic_Checkout_Session( $raw );
		$this->assertSame( $expected, $session->get_chosen_shipping_rate_wc_id() );
	}

	/**
	 * @return array
	 */
	public function provide_chosen_shipping_rate_wc_id_cases(): array {
		return [
			'present'       => [
				(object) [
					'shipping_cost' => (object) [
						'shipping_rate' => (object) [
							'metadata' => (object) [ 'wc_rate_id' => 'flat_rate:1' ],
						],
					],
				],
				'flat_rate:1',
			],
			'empty string'  => [
				(object) [
					'shipping_cost' => (object) [
						'shipping_rate' => (object) [
							'metadata' => (object) [ 'wc_rate_id' => '' ],
						],
					],
				],
				null,
			],
			'missing field' => [
				(object) [],
				null,
			],
			'null metadata' => [
				(object) [
					'shipping_cost' => (object) [
						'shipping_rate' => (object) [
							'metadata' => null,
						],
					],
				],
				null,
			],
		];
	}

	/**
	 * @dataProvider provide_chosen_shipping_rate_display_name_cases
	 */
	public function test_get_chosen_shipping_rate_display_name( object $raw, ?string $expected ) {
		$session = new WC_Stripe_Agentic_Checkout_Session( $raw );
		$this->assertSame( $expected, $session->get_chosen_shipping_rate_display_name() );
	}

	/**
	 * @return array
	 */
	public function provide_chosen_shipping_rate_display_name_cases(): array {
		return [
			'present'       => [
				(object) [
					'shipping_cost' => (object) [
						'shipping_rate' => (object) [ 'display_name' => 'Standard Shipping' ],
					],
				],
				'Standard Shipping',
			],
			'empty string'  => [
				(object) [
					'shipping_cost' => (object) [
						'shipping_rate' => (object) [ 'display_name' => '' ],
					],
				],
				null,
			],
			'missing field' => [
				(object) [],
				null,
			],
		];
	}

	/**
	 * Test get_billing_address returns WC_Stripe_API_Address and throws when missing.
	 */
	public function test_get_billing_address_returns_api_address_type() {
		$session = new WC_Stripe_Agentic_Checkout_Session(
			(object) [
				'customer_details' => (object) [
					'address' => (object) [
						'country'     => 'US',
						'state'       => 'CA',
						'postal_code' => '90210',
						'city'        => 'Beverly Hills',
						'line1'       => '123 Main St',
						'line2'       => 'Apt 4',
					],
				],
			]
		);

		$address = $session->get_billing_address();
		$this->assertInstanceOf( \WC_Stripe_API_Address::class, $address );
		$this->assertSame( 'US', $address->get_country() );
		$this->assertSame( 'CA', $address->get_state() );
		$this->assertSame( '90210', $address->get_postal_code() );
		$this->assertSame( 'Beverly Hills', $address->get_city() );
		$this->assertSame( '123 Main St', $address->get_line1() );
		$this->assertSame( 'Apt 4', $address->get_line2() );
	}

	/**
	 * Test get_shipping_address returns WC_Stripe_API_Address.
	 */
	public function test_get_shipping_address_returns_api_address_type() {
		$session = new WC_Stripe_Agentic_Checkout_Session(
			(object) [
				'shipping_details' => (object) [
					'name'    => 'Test',
					'address' => (object) [
						'country' => 'DE',
						'city'    => 'Berlin',
					],
				],
			]
		);

		$address = $session->get_shipping_address();
		$this->assertInstanceOf( \WC_Stripe_API_Address::class, $address );
		$this->assertSame( 'DE', $address->get_country() );
		$this->assertSame( 'Berlin', $address->get_city() );
		$this->assertNull( $address->get_state() );
		$this->assertNull( $address->get_postal_code() );
		$this->assertNull( $address->get_line1() );
		$this->assertNull( $address->get_line2() );
	}
}
