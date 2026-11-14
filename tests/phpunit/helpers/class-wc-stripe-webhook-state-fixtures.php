<?php
/**
 * Shared fixtures for tests that corrupt the webhook health options.
 *
 * @package WooCommerce_Stripe/Tests/Helpers
 */

require_once __DIR__ . '/class-wc-stripe-stringable-option-value.php';

/**
 * The webhook state options are read from several classes, so this helper
 * centralizes the test configuration to provide consistent coverage.
 */
class WC_Stripe_Webhook_State_Fixtures {

	/**
	 * Every option making up the webhook health state, across both live and test modes.
	 *
	 * @return string[]
	 */
	public static function get_all_option_names(): array {
		return [
			WC_Stripe_Webhook_State::OPTION_LIVE_MONITORING_BEGAN_AT,
			WC_Stripe_Webhook_State::OPTION_LIVE_LAST_SUCCESS_AT,
			WC_Stripe_Webhook_State::OPTION_LIVE_LAST_FAILURE_AT,
			WC_Stripe_Webhook_State::OPTION_LIVE_LAST_ERROR,
			WC_Stripe_Webhook_State::OPTION_LIVE_PENDING_WEBHOOKS,
			WC_Stripe_Webhook_State::OPTION_TEST_MONITORING_BEGAN_AT,
			WC_Stripe_Webhook_State::OPTION_TEST_LAST_SUCCESS_AT,
			WC_Stripe_Webhook_State::OPTION_TEST_LAST_FAILURE_AT,
			WC_Stripe_Webhook_State::OPTION_TEST_LAST_ERROR,
			WC_Stripe_Webhook_State::OPTION_TEST_PENDING_WEBHOOKS,
		];
	}

	/**
	 * The options that are expected to be integer values, across both live and test modes.
	 *
	 * @return string[]
	 */
	public static function get_integer_option_names(): array {
		return [
			WC_Stripe_Webhook_State::OPTION_LIVE_MONITORING_BEGAN_AT,
			WC_Stripe_Webhook_State::OPTION_LIVE_LAST_SUCCESS_AT,
			WC_Stripe_Webhook_State::OPTION_LIVE_LAST_FAILURE_AT,
			WC_Stripe_Webhook_State::OPTION_LIVE_PENDING_WEBHOOKS,
			WC_Stripe_Webhook_State::OPTION_TEST_MONITORING_BEGAN_AT,
			WC_Stripe_Webhook_State::OPTION_TEST_LAST_SUCCESS_AT,
			WC_Stripe_Webhook_State::OPTION_TEST_LAST_FAILURE_AT,
			WC_Stripe_Webhook_State::OPTION_TEST_PENDING_WEBHOOKS,
		];
	}

	/**
	 * Non-scalar shapes an integer-typed option must not allow to be stored, but also skip when reading.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_corrupt_non_integer_values(): array {
		return [
			'empty array'          => [],
			'list array'           => [ 1, 2, 3 ],
			'associative array'    => [ 'poison' => 'value' ],
			'nested array'         => [ 'nested' => [ 'deep' => 'value' ] ],
			'empty object'         => new stdClass(),
			'object with property' => (object) [ 'poison' => 'value' ],
			'stringable object'    => new WC_Stripe_Stringable_Option_Value( '1700000000' ),
		];
	}

	/**
	 * {@see self::get_corrupt_non_integer_values()} wrapped as PHPUnit data provider cases.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function get_corrupt_value_cases(): array {
		$cases = [];

		foreach ( self::get_corrupt_non_integer_values() as $description => $value ) {
			$cases[ $description ] = [ $value ];
		}

		return $cases;
	}

	/**
	 * Writes a corrupt value into every webhook state option returned by {@see self::get_all_option_names()}.
	 *
	 * Corrupting all of them at once is the worst case and the cheapest to assert against: if any
	 * single read on the surface under test is unguarded, we should see a failure.
	 *
	 * @param mixed $value Corrupt value to store.
	 */
	public static function corrupt_all_options( $value ): void {
		foreach ( self::get_all_option_names() as $option_name ) {
			update_option( $option_name, $value );
		}
	}

	/**
	 * Removes every webhook state option returned by {@see self::get_all_option_names()}.
	 */
	public static function delete_all_options(): void {
		foreach ( self::get_all_option_names() as $option_name ) {
			delete_option( $option_name );
		}
	}
}
