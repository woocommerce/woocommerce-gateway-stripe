<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Payment_Method_Configurations
 */
class WC_Stripe_Payment_Method_Configurations {
	/**
	 * Get the merchant payment method configuration in Stripe.
	 *
	 * @return object|null
	*/
	private static function get_primary_configuration() {
		$result = WC_Stripe_API::get_instance()->get_payment_method_configurations();
		$payment_method_configurations = $result->data ?? null;

		if ( ! $payment_method_configurations ) {
			return null;
		}

		foreach ( $payment_method_configurations as $payment_method_configuration ) {
			if ( $payment_method_configuration->active && $payment_method_configuration->parent ) {
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
		$upe_enabled_payment_method_ids = get_transient( 'woocommerce_stripe_upe_enabled_payment_method_ids' );

		if ( $upe_enabled_payment_method_ids ) {
			return $upe_enabled_payment_method_ids;
		}

		$enabled_payment_method_ids            = [];
		$merchant_payment_method_configuration = self::get_primary_configuration();

		if ( $merchant_payment_method_configuration ) {
			foreach ( $merchant_payment_method_configuration as $payment_method_id => $payment_method ) {
				if ( is_object( $payment_method ) &&
					property_exists( $payment_method, 'display_preference' ) &&
					property_exists( $payment_method->display_preference, 'value' ) ) {

					$payment_method_status = 'on' === $payment_method->display_preference->value;

					if ( $payment_method_status ) {
						$enabled_payment_method_ids[] = $payment_method_id;
					}
				}
			}
		}

		set_transient( 'woocommerce_stripe_upe_enabled_payment_method_ids', $enabled_payment_method_ids, DAY_IN_SECONDS );
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

		set_transient( 'woocommerce_stripe_upe_enabled_payment_method_ids', $enabled_payment_method_ids, DAY_IN_SECONDS );
	}
}
