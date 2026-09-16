<?php
/**
 * Class WC_Stripe_REST_Args_Validator_Test
 *
 * Implements WC_Stripe_REST_Args_Validator class unit tests.
 */
class WC_Stripe_REST_Args_Validator_Test extends WP_UnitTestCase {
	/**
	 * Provide customer_id test cases as value-validity pairs.
	 */
	public static function provide_customer_id(): array {
		return [
			[ 'cus_test', true ],
			[ '', false ],
			[ 'xyz', false ],
			[ 123, false ],
			[ [], false ],
		];
	}

	/**
	 * @dataProvider provide_customer_id
	*/
	public function test_validate_customer_id( $customer_id, $expected ) {
		$this->assertEquals( WC_Stripe_REST_Args_Validator::validate_customer_id( $customer_id, new WP_REST_Request(), 'test_param' ), $expected );
	}

	/**
	 * Provide payment intents pagination cursor test cases as triplets of parameter name, value, and validity.
	 */
	public static function provide_payment_intent_id_pagination_cursor(): array {
		return [
			'valid_starting_after'                  => [
				'starting_after',
				[
					'starting_after' => 'pi_test',
				],
				true,
			],
			'invalid_starting_after'                => [
				'starting_after',
				[
					'starting_after' => 'xyz',
				],
				false,
			],
			'valid_ending_before'                   => [
				'ending_before',
				[
					'ending_before' => 'pi_test',
				],
				true,
			],
			'invalid_ending_before'                 => [
				'ending_before',
				[
					'ending_before' => [],
				],
				false,
			],
			'both_starting_after_and_ending_before' => [
				'starting_after',
				[
					'starting_after' => 'pi_test',
					'ending_before'  => 'pi_test',
				],
				false,
			],
		];
	}

	/**
	 * @dataProvider provide_payment_intent_id_pagination_cursor
	*/
	public function test_validate_payment_intent_id_pagination_cursor( $test_param_name, $all_params, $expected ) {
		$request = new WP_REST_Request();

		foreach ( $all_params as $param_name => $param_value ) {
			$request->set_param( $param_name, $param_value );

			if ( $param_name === $test_param_name ) {
				$test_param_value = $param_value;
			}
		}

		$actual = WC_Stripe_REST_Args_Validator::validate_payment_intent_pagination_cursor(
			$test_param_value,
			$request,
			$test_param_name,
		);

		if ( is_object( $actual ) ) {
			$this->assertTrue( $actual instanceof WP_Error );
		} else {
			$this->assertEquals( $actual, $expected );
		}
	}

	/**
	 * Provide payouts pagination cursor test cases as triplets of parameter name, value, and validity.
	 */
	public static function provide_payout_id_pagination_cursor(): array {
		return [
			'valid_starting_after'                  => [
				'starting_after',
				[
					'starting_after' => 'po_test',
				],
				true,
			],
			'invalid_starting_after'                => [
				'starting_after',
				[
					'starting_after' => 'xyz',
				],
				false,
			],
			'valid_ending_before'                   => [
				'ending_before',
				[
					'ending_before' => 'po_test',
				],
				true,
			],
			'invalid_ending_before'                 => [
				'ending_before',
				[
					'ending_before' => [],
				],
				false,
			],
			'both_starting_after_and_ending_before' => [
				'starting_after',
				[
					'starting_after' => 'po_test',
					'ending_before'  => 'po_test',
				],
				false,
			],
		];
	}

	/**
	 * @dataProvider provide_payout_id_pagination_cursor
	*/
	public function test_validate_payout_id_pagination_cursor( $test_param_name, $all_params, $expected ) {
		$request = new WP_REST_Request();

		foreach ( $all_params as $param_name => $param_value ) {
			$request->set_param( $param_name, $param_value );

			if ( $param_name === $test_param_name ) {
				$test_param_value = $param_value;
			}
		}

		$actual = WC_Stripe_REST_Args_Validator::validate_payout_pagination_cursor(
			$test_param_value,
			$request,
			$test_param_name,
		);

		if ( is_object( $actual ) ) {
			$this->assertTrue( $actual instanceof WP_Error );
		} else {
			$this->assertEquals( $actual, $expected );
		}
	}

	/**
	 * Provide unix timestamp range test cases as value-validity pairs.
	 */
	public static function provide_unix_timestamp_range(): array {
		return [
			[
				'1779802569',
				true,
			],
			[
				[
					'lt' => '1779802569',
				],
				true,
			],
			[
				[
					'lte' => '1779802569',
				],
				true,
			],
			[
				[
					'gt' => '1779802569',
				],
				true,
			],
			[
				[
					'gte' => '1779802569',
				],
				true,
			],
			[
				[
					'lt' => '1779802569',
					'gt' => '1779802569',
				],
				true,
			],
			[
				'xyz',
				false,
			],
			[
				[
					'ltxyz' => '1779802569',
				],
				false,
			],
		];
	}

	/**
	 * @dataProvider provide_unix_timestamp_range
	*/
	public function test_validate_unix_timestamp_range( $param_value, $expect ) {
		$this->assertEquals(
			WC_Stripe_REST_Args_Validator::validate_unix_timestamp_range( $param_value, new WP_REST_Request(), 'test_param' ),
			$expect,
		);
	}

	/**
	 * Provide unix timestamp test cases as value-validity pairs.
	 */
	public static function provide_unix_timestamp(): array {
		return [
			[
				'1779802569',
				true,
			],
			[
				'0',
				true,
			],
			[
				'-1779802569',
				false,
			],
			[
				'',
				false,
			],
			[
				'xyz',
				false,
			],
		];
	}

	/**
	 * @dataProvider provide_unix_timestamp
	*/
	public function test_is_valid_timestamp( $param_value, $expect ) {
		$this->assertEquals( WC_Stripe_REST_Args_Validator::is_valid_timestamp( $param_value ), $expect );
	}

	/**
	 * Provide non-empty string test cases as value-validity pairs.
	 */
	public static function provide_non_empty_string(): array {
		return [
			[
				'abc',
				true,
			],
			[
				'',
				false,
			],
			[
				123,
				false,
			],
			[
				[],
				false,
			],
		];
	}

	/**
	 * @dataProvider provide_non_empty_string
	*/
	public function test_validate_non_empty_string( $param_value, $expect ) {
		$this->assertEquals( WC_Stripe_REST_Args_Validator::validate_non_empty_string( $param_value, new WP_REST_Request(), 'test_param' ), $expect );
	}
}
