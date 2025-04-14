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
		// If the payment method configurations API is not enabled, we fallback to the enabled payment methods stored in the DB.
		if ( ! self::is_enabled() ) {
			$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
			return isset( $stripe_settings['upe_checkout_experience_accepted_payments'] ) && ! empty( $stripe_settings['upe_checkout_experience_accepted_payments'] )
				? $stripe_settings['upe_checkout_experience_accepted_payments']
				: [ WC_Stripe_Payment_Methods::CARD ];
		}

		// Migrate payment methods from DB to Stripe PMC if needed
		self::maybe_migrate_payment_methods_from_db_to_pmc();

		$enabled_payment_method_ids            = [];
		$merchant_payment_method_configuration = self::get_primary_configuration();

		if ( $merchant_payment_method_configuration ) {
			foreach ( $merchant_payment_method_configuration as $payment_method_id => $payment_method ) {
				if ( isset( $payment_method->display_preference->value ) && 'on' === $payment_method->display_preference->value ) {
					$enabled_payment_method_ids[] = $payment_method_id;
				}
			}
		}

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
		$newly_enabled_methods                = [];
		$newly_disabled_methods               = [];

		foreach ( $available_payment_method_ids as $stripe_id ) {
			$will_enable = in_array( $stripe_id, $enabled_payment_method_ids, true );

			if ( 'on' === ( $payment_method_configuration->$stripe_id->display_preference->value ?? null ) && ! $will_enable ) {
				$newly_disabled_methods[] = $stripe_id;
			}

			if ( 'off' === ( $payment_method_configuration->$stripe_id->display_preference->value ?? null ) && $will_enable ) {
				$newly_enabled_methods[] = $stripe_id;
			}

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

		self::record_payment_method_settings_event( $newly_enabled_methods, $newly_disabled_methods );
	}

	/**
	 * Record tracks events for each payment method that was enabled or disabled.
	 *
	 * @param array $enabled_methods An array of payment method ids that were enabled.
	 * @param array $disabled_methods An array of payment method ids that were disabled.
	 *
	 * @return void
	 */
	public static function record_payment_method_settings_event( $enabled_methods, $disabled_methods ) {
		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}

		$is_test_mode = WC_Stripe_Mode::is_test();

		// Track the events for both arrays.
		array_map(
			function ( $id ) use ( $is_test_mode ) {
				wc_admin_record_tracks_event(
					'wcstripe_payment_method_settings_enabled',
					[
						'is_test_mode'   => $is_test_mode,
						'payment_method' => $id,
					]
				);
			},
			$enabled_methods
		);
		array_map(
			function ( $id ) use ( $is_test_mode ) {
				wc_admin_record_tracks_event(
					'wcstripe_payment_method_settings_disabled',
					[
						'is_test_mode'   => $is_test_mode,
						'payment_method' => $id,
					]
				);
			},
			$disabled_methods
		);
	}

	/**
	 * Check if the payment method configurations API can be used to store enabled payment methods.
	 * This requires the Stripe account to be connected to our platform ('connection_type' option to be 'connect').
	 *
	 * This is temporary until we finish the re-authentication campaign.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$key             = WC_Stripe_Mode::is_test() ? 'test_connection_type' : 'connection_type';
		return isset( $stripe_settings[ $key ] ) && 'connect' === $stripe_settings[ $key ];
	}

	/**
	 * Migrates the payment methods from the DB option to PMC if needed.
	 */
	public static function maybe_migrate_payment_methods_from_db_to_pmc() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		// Skip if PMC is not enabled or migration already done
		if ( ! self::is_enabled() || ! empty( $stripe_settings['pmc_enabled'] ) ) {
			return;
		}

		// Skip if there is no PMC available
		$merchant_payment_method_configuration = self::get_primary_configuration();
		if ( ! $merchant_payment_method_configuration ) {
			return;
		}

		$enabled_payment_methods = [];

		if ( isset( $stripe_settings['upe_checkout_experience_accepted_payments'] ) &&
				! empty( $stripe_settings['upe_checkout_experience_accepted_payments'] ) ) {
			$enabled_payment_methods = array_merge(
				$enabled_payment_methods,
				$stripe_settings['upe_checkout_experience_accepted_payments']
			);
		}

		// Add Google Pay and Apple Pay to the list if payment_request is enabled
		if ( ! empty( $stripe_settings['payment_request'] ) && 'yes' === $stripe_settings['payment_request'] ) {
			$enabled_payment_methods = array_merge(
				$enabled_payment_methods,
				[ WC_Stripe_Payment_Methods::GOOGLE_PAY, WC_Stripe_Payment_Methods::APPLE_PAY ]
			);
		}

		// Update the PMC if there are any enabled payment methods
		if ( ! empty( $enabled_payment_methods ) ) {

			// Get all available payment method IDs from the configuration.
			// We explicitly disable all payment methods that are not in the enabled_payment_methods array
			$available_payment_method_ids = [];
			foreach ( $merchant_payment_method_configuration as $payment_method_id => $payment_method ) {
				if ( isset( $payment_method->display_preference ) ) {
					$available_payment_method_ids[] = $payment_method_id;
				}
			}

			self::update_payment_method_configuration(
				$enabled_payment_methods,
				$available_payment_method_ids
			);
		}

		// Mark migration as complete in stripe settings
		$stripe_settings['pmc_enabled'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}
}
