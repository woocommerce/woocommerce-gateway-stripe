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
	 * The test mode payment method configuration cache key.
	 *
	 * @var string
	 */
	const TEST_MODE_CONFIGURATION_CACHE_KEY = 'wcstripe_test_payment_method_configuration_cache';

	/**
	 * The live mode payment method configuration cache key.
	 *
	 * @var string
	 */
	const LIVE_MODE_CONFIGURATION_CACHE_KEY = 'wcstripe_live_payment_method_configuration_cache';

	/**
	 * The payment method configuration cache expiration.
	 *
	 * @var int
	 */
	const CONFIGURATION_CACHE_EXPIRATION = 10 * MINUTE_IN_SECONDS;

	/**
	 * The payment method configuration fetch cooldown option key.
	 */
	const FETCH_COOLDOWN_OPTION_KEY = 'wcstripe_payment_method_config_fetch_cooldown';

	/**
	 * Get the merchant payment method configuration in Stripe.
	 *
	 * @param bool $force_refresh Whether to force a refresh of the payment method configuration from Stripe.
	 * @return object|null
	 */
	private static function get_primary_configuration( $force_refresh = false ) {
		// Only allow fetching payment configuration once per minute.
		$fetch_cooldown = get_option( self::FETCH_COOLDOWN_OPTION_KEY, 0 );
		$is_in_cooldown = $fetch_cooldown > time();
		if ( ! $force_refresh || $is_in_cooldown ) {
			$cached_primary_configuration = self::get_payment_method_configuration_from_cache();
			if ( $cached_primary_configuration ) {
				return $cached_primary_configuration;
			}

			// If we are hitting the API too much, and our main cache is not working, use the fallback cache.
			if ( $is_in_cooldown ) {
				$fallback_cache = self::get_payment_method_configuration_from_cache( true );
				if ( $fallback_cache ) {
					return $fallback_cache;
				}
			}

			// Intentionally fall through to fetching the data from Stripe if we don't have it locally,
			// even when $force_refresh == false and/or $is_in_cooldown is true.
			// We _need_ the payment method configuration for things to work as expected,
			// so we will fetch it if we don't have anything locally.
		}

		update_option( self::FETCH_COOLDOWN_OPTION_KEY, time() + MINUTE_IN_SECONDS );

		return self::get_payment_method_configuration_from_stripe();
	}

	/**
	 * Get the payment method configuration from cache.
	 *
	 * @param bool $use_fallback Whether to use the fallback cache if the transient is not available.
	 *
	 * @return object|null
	 */
	private static function get_payment_method_configuration_from_cache( $use_fallback = false ) {
		if ( null !== self::$primary_configuration ) {
			return self::$primary_configuration;
		}

		$cache_key                    = WC_Stripe_Mode::is_test() ? self::TEST_MODE_CONFIGURATION_CACHE_KEY : self::LIVE_MODE_CONFIGURATION_CACHE_KEY;
		$cached_primary_configuration = WC_Stripe_Database_Cache::get( $cache_key );
		if ( false === $cached_primary_configuration || null === $cached_primary_configuration ) {
			if ( $use_fallback ) {
				return get_option( $cache_key . '_fallback' );
			}
			return null;
		}

		self::$primary_configuration = $cached_primary_configuration;
		return self::$primary_configuration;
	}

	/**
	 * Clear the payment method configuration from cache.
	 */
	public static function clear_payment_method_configuration_cache() {
		self::$primary_configuration = null;
		$cache_key                   = WC_Stripe_Mode::is_test() ? self::TEST_MODE_CONFIGURATION_CACHE_KEY : self::LIVE_MODE_CONFIGURATION_CACHE_KEY;
		WC_Stripe_Database_Cache::delete( $cache_key );
		delete_option( $cache_key . '_fallback' );
	}

	/**
	 * Cache the payment method configuration.
	 *
	 * @param object|array $configuration The payment method configuration to set in cache.
	 */
	private static function set_payment_method_configuration_cache( $configuration ) {
		self::$primary_configuration = $configuration;
		$cache_key                   = WC_Stripe_Mode::is_test() ? self::TEST_MODE_CONFIGURATION_CACHE_KEY : self::LIVE_MODE_CONFIGURATION_CACHE_KEY;
		WC_Stripe_Database_Cache::set( $cache_key, $configuration, self::CONFIGURATION_CACHE_EXPIRATION );

		// To be used as fallback if we are in API cooldown and the main cache is not available.
		update_option( $cache_key . '_fallback', $configuration );
	}

	/**
	 * Get the payment method configuration from Stripe.
	 *
	 * @return object|null
	 */
	private static function get_payment_method_configuration_from_stripe() {
		$result         = WC_Stripe_API::get_instance()->get_payment_method_configurations();
		$configurations = $result->data ?? [];

		// When connecting to the WooCommerce Platform account a new payment method configuration is created for the merchant.
		// This new payment method configuration has the WooCommerce Platform payment method configuration as parent, and inherits it's default payment methods.
		foreach ( $configurations as $configuration ) {
			// The API returns data for the corresponding mode of the api keys used, so we'll get either test or live PMCs, but never both.
			if ( $configuration->parent && ( self::LIVE_MODE_CONFIGURATION_PARENT_ID === $configuration->parent || self::TEST_MODE_CONFIGURATION_PARENT_ID === $configuration->parent ) ) {
				self::set_payment_method_configuration_cache( $configuration );
				return $configuration;
			}
		}

		// If we don't have a Payment Method Configuration that inherits from the WooCommerce Platform, disable Payment Method Configuration sync.
		self::disable_payment_method_configuration_sync();
		return null;
	}

	/**
	 * Get the parent configuration ID.
	 *
	 * @return string|null
	 */
	public static function get_parent_configuration_id() {
		return self::get_primary_configuration()->parent ?? null;
	}

	/**
	 * Get the UPE available payment method IDs.
	 *
	 * @return array
	 */
	public static function get_upe_available_payment_method_ids() {
		// Bail if the payment method configurations API is not enabled.
		if ( ! self::is_enabled() ) {
			return [];
		}

		$available_payment_method_ids          = [];
		$merchant_payment_method_configuration = self::get_primary_configuration();

		if ( $merchant_payment_method_configuration ) {
			foreach ( $merchant_payment_method_configuration as $payment_method_id => $payment_method ) {
				if ( isset( $payment_method->display_preference->value ) && isset( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS[ $payment_method_id ] ) ) {
					$available_payment_method_ids[] = $payment_method_id;
				}
			}
		}

		return $available_payment_method_ids;
	}

	/**
	 * Get the UPE enabled payment method IDs.
	 *
	 * @param bool $force_refresh Whether to force a refresh of the payment method configuration from Stripe.
	 * @return array
	 */
	public static function get_upe_enabled_payment_method_ids( $force_refresh = false ) {
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
		$merchant_payment_method_configuration = self::get_primary_configuration( $force_refresh );

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

		if ( ! $payment_method_configuration ) {
			WC_Stripe_Logger::log( 'No primary payment method configuration found while updating payment method configuration' );
			return;
		}

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

		$response = WC_Stripe_API::get_instance()->update_payment_method_configurations(
			$payment_method_configuration->id,
			$updated_payment_method_configuration
		);
		if ( ! empty( $response->error ) ) {
			WC_Stripe_Logger::log( 'Error: ' . $response->error->message . ': ' . $response->error->request_log_url );
		}

		self::clear_payment_method_configuration_cache();

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
		$stripe_settings     = WC_Stripe_Helper::get_stripe_settings();
		$connection_type_key = WC_Stripe_Mode::is_test() ? 'test_connection_type' : 'connection_type';

		// If the account is not a Connect OAuth account, we can't use the payment method configurations API.
		if ( ! isset( $stripe_settings[ $connection_type_key ] ) || 'connect' !== $stripe_settings[ $connection_type_key ] ) {
			return false;
		}

		// If we have the pmc_enabled flag, and it is set to no, we should not use the payment method configurations API.
		// We only disable the PMC if the flag is set to no explicitly, an empty value means the migration has not been attempted yet.
		if ( isset( $stripe_settings['pmc_enabled'] ) && 'no' === $stripe_settings['pmc_enabled'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Migrates the payment methods from the DB option to PMC if needed.
	 *
	 * @param bool $force_migration Whether to force the migration.
	 */
	public static function maybe_migrate_payment_methods_from_db_to_pmc( $force_migration = false ) {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		// Skip if PMC is not enabled.
		if ( ! self::is_enabled() ) {
			return;
		}

		// Skip if migration already done (pmc_enabled is set) and we are not forcing the migration.
		if ( ! empty( $stripe_settings['pmc_enabled'] ) && ! $force_migration ) {
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

		// If there is no payment method order defined, set it to the default order
		if ( empty( $stripe_settings['stripe_upe_payment_method_order'] ) ) {
			$stripe_settings['stripe_upe_payment_method_order'] = array_keys( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS );
		}

		// Mark migration as complete in stripe settings
		$stripe_settings['pmc_enabled'] = 'yes';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}

	/**
	 * Disables the payment method configuration sync by setting pmc_enabled to 'no' in the Stripe settings.
	 * This is called when no Payment Method Configuration is found that inherits from the WooCommerce Platform.
	 */
	private static function disable_payment_method_configuration_sync() {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['pmc_enabled'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}
}
