<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Payment_Method_Configurations
 */
class WC_Stripe_Payment_Method_Configurations {
	/**
	 * The primary configuration.
	 *
	 * @var object|null
	*/
	private static $primary_configuration = null;

	/**
	 * The test mode configuration parent ID.
	 *
	 * @var string|null
	*/
	const TEST_MODE_CONFIGURATION_PARENT_ID = 'pmc_1LEKjBGX8lmJQndTBOzjqxSa';

	/**
	 * The live mode configuration parent ID.
	 *
	 * @var string|null
	*/
	const LIVE_MODE_CONFIGURATION_PARENT_ID = 'pmc_1LEKjAGX8lmJQndTk2ziRchV';

	/**
	 * The transient key for the UPE enabled payment method IDs.
	 *
	 * @var string
	*/
	const UPE_ENABLED_PAYMENT_METHOD_IDS_TRANSIENT_KEY = 'wc_stripe_upe_enabled_payment_method_ids';

	/**
	 * Reset the primary configuration.
	 */
	public static function reset_primary_configuration() {
		self::$primary_configuration = null;
	}

	/**
	 * Get the merchant payment method configuration in Stripe.
	 *
	 * @return object|null
	*/
	private static function get_primary_configuration() {
		if ( null !== self::$primary_configuration ) {
			return self::$primary_configuration;
		}

		$result = WC_Stripe_API::get_instance()->get_payment_method_configurations();
		$payment_method_configurations = $result->data ?? null;

		if ( ! $payment_method_configurations ) {
			return null;
		}

		foreach ( $payment_method_configurations as $payment_method_configuration ) {
			if ( ! $payment_method_configuration->livemode && $payment_method_configuration->parent && self::TEST_MODE_CONFIGURATION_PARENT_ID === $payment_method_configuration->parent ) {
				self::$primary_configuration = $payment_method_configuration;
				return $payment_method_configuration;
			}

			if ( $payment_method_configuration->livemode && $payment_method_configuration->parent && self::LIVE_MODE_CONFIGURATION_PARENT_ID === $payment_method_configuration->parent ) {
				self::$primary_configuration = $payment_method_configuration;
				return $payment_method_configuration;
			}
		}
		return null;
	}

	/**
	* Get the UPE enabled payment method IDs.
	*
	* @return array
	*/
	public static function get_upe_enabled_payment_method_ids() {
		if ( ( ! defined( 'REST_REQUEST' ) || REST_REQUEST ) && ! is_admin() && get_transient( self::UPE_ENABLED_PAYMENT_METHOD_IDS_TRANSIENT_KEY ) ) {
			return get_transient( self::UPE_ENABLED_PAYMENT_METHOD_IDS_TRANSIENT_KEY );
		}

		$enabled_payment_method_ids            = [];
		$merchant_payment_method_configuration = self::get_primary_configuration();

		if ( $merchant_payment_method_configuration ) {
			foreach ( $merchant_payment_method_configuration as $payment_method_id => $payment_method ) {
				if ( isset( $payment_method->display_preference->value ) && 'on' === $payment_method->display_preference->value ) {
					$enabled_payment_method_ids[] = $payment_method_id;
				}
			}
		}

		set_transient( self::UPE_ENABLED_PAYMENT_METHOD_IDS_TRANSIENT_KEY, $enabled_payment_method_ids, DAY_IN_SECONDS );
		return $enabled_payment_method_ids;
	}

	/**
	 * Update the payment method configuration.
	 *
	 * @param array $enabled_payment_method_ids
	 * @param array $available_payment_method_ids
	*/
	public static function update_payment_method_configuration( $enabled_payment_method_ids, $available_payment_method_ids ) {
		$payment_method_configuration         = self::get_primary_configuration();
		$updated_payment_method_configuration = [];

		foreach ( $available_payment_method_ids as $stripe_id ) {
			$updated_payment_method_configuration[ $stripe_id ] = [
				'display_preference' => [
					'preference' => in_array( $stripe_id, $enabled_payment_method_ids, true ) ? 'on' : 'off',
				],
			];
		}

		if ( ! $payment_method_configuration ) {
			WC_Stripe_Logger::log( 'No primary payment method configuration found while updating payment method configuration' );
			return;
		}

		WC_Stripe_API::get_instance()->update_payment_method_configurations(
			$payment_method_configuration->id,
			$updated_payment_method_configuration
		);
	}
}
