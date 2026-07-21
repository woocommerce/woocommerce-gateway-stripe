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
	public const TEST_MODE_CONFIGURATION_PARENT_ID = 'pmc_1LEKjBGX8lmJQndTBOzjqxSa';

	/**
	 * The live mode configuration parent ID.
	 *
	 * @var string|null
	 */
	public const LIVE_MODE_CONFIGURATION_PARENT_ID = 'pmc_1LEKjAGX8lmJQndTk2ziRchV';

	/**
	 * The payment method configuration cache key.
	 *
	 * @var string
	 */
	public const CONFIGURATION_CACHE_KEY = 'payment_method_configuration';

	/**
	 * The payment method configuration cache expiration (TTL).
	 *
	 * @var int
	 */
	public const CONFIGURATION_CACHE_EXPIRATION = 20 * MINUTE_IN_SECONDS;

	/**
	 * The payment method configuration fetch cooldown option key.
	 */
	public const FETCH_COOLDOWN_OPTION_KEY = 'wcstripe_payment_method_config_fetch_cooldown';

	/**
	 * Get the merchant payment method configuration in Stripe.
	 *
	 * @param bool $force_refresh Whether to force a refresh of the payment method configuration from Stripe.
	 * @return object|null
	 */
	private static function get_primary_configuration( $force_refresh = false ) {
		// Only allow fetching payment configuration once per minute.
		// Even when $force_refresh is true, we will not fetch the configuration from Stripe more than once per minute.
		$fetch_cooldown = get_option( self::FETCH_COOLDOWN_OPTION_KEY, 0 );
		$is_in_cooldown = $fetch_cooldown > time();
		if ( ! $force_refresh || $is_in_cooldown ) {
			$cached_primary_configuration = self::get_payment_method_configuration_from_cache();
			if ( $cached_primary_configuration ) {
				return $cached_primary_configuration;
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
	 * @return object|null
	 */
	private static function get_payment_method_configuration_from_cache() {
		if ( null !== self::$primary_configuration ) {
			return self::$primary_configuration;
		}

		$cached_primary_configuration = WC_Stripe_Database_Cache::get( self::CONFIGURATION_CACHE_KEY );
		if ( false === $cached_primary_configuration || null === $cached_primary_configuration ) {
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
		WC_Stripe_Database_Cache::delete( self::CONFIGURATION_CACHE_KEY );
	}

	/**
	 * Cache the payment method configuration.
	 *
	 * @param object|array $configuration The payment method configuration to set in cache.
	 */
	private static function set_payment_method_configuration_cache( $configuration ) {
		self::$primary_configuration = $configuration;
		WC_Stripe_Database_Cache::set( self::CONFIGURATION_CACHE_KEY, $configuration, self::CONFIGURATION_CACHE_EXPIRATION );
	}

	/**
	 * Get the payment method configuration from Stripe.
	 *
	 * @return object|null
	 */
	private static function get_payment_method_configuration_from_stripe() {
		$is_test_mode = WC_Stripe_Mode::is_test();

		$preselected_pmc = self::get_preselected_pmc( $is_test_mode );

		if ( null !== $preselected_pmc['pmc_id'] ) {
			if ( null !== $preselected_pmc['error'] ) {
				WC_Stripe_Logger::error(
					'Error retrieving preselected Payment Method Configuration',
					[
						'pmc_id' => $preselected_pmc['pmc_id'],
						'error'  => $preselected_pmc['error'],
					]
				);
			} elseif ( ! empty( $preselected_pmc['configuration'] ) ) {
				self::set_payment_method_configuration_cache( $preselected_pmc['configuration'] );
				return $preselected_pmc['configuration'];
			}
			// If the preselected Payment Method Configuration is not found, we continue with the default logic below.
		}

		$result         = WC_Stripe_API::get_instance()->get_payment_method_configurations();
		$configurations = $result->data ?? [];

		[
			'pmc'    => $pmc,
			'reason' => $reason,
		] = self::select_platform_or_fallback_pmc( $configurations );

		if ( null === $pmc ) {
			// If we can't find a usable Payment Method Configuration, disable Payment Method Configuration sync.
			WC_Stripe_Logger::error(
				'No usable Payment Method Configuration found; disabling Payment Method Configuration sync',
				[
					'reason'      => $reason,
					'stripe_mode' => $is_test_mode ? 'test' : 'live',
				]
			);
			self::disable_payment_method_configuration_sync();
			return null;
		}

		self::set_payment_method_configuration_cache( $pmc );
		return $pmc;
	}

	/**
	 * Gets the merchant-selected Payment Method Configuration when the preselect filter is set.
	 *
	 * @param bool $is_test_mode Whether the site is in test mode.
	 * @return array {
	 *     @type string|null $pmc_id        The preselected Payment Method Configuration ID.
	 *     @type mixed       $configuration The retrieved Payment Method Configuration, if available.
	 *     @type mixed       $error         The normalized retrieval error, if any.
	 * }
	 */
	private static function get_preselected_pmc( bool $is_test_mode ): array {
		/**
		 * Allows merchants to specify the ID of a Payment Method Configuration to use. This makes it possible for
		 * merchants to create configurations for specific sites, e.g. when they operate sites in different countries
		 * with different local payment methods.
		 *
		 * @since 10.1.0
		 *
		 * @param string|null $preselected_pmc_id The ID of the Payment Method Configuration to use. Null by default, but a string value may be returned.
		 * @param bool        $is_test_mode       Whether the site is in test mode.
		 */
		$preselected_pmc_id = apply_filters( 'wc_stripe_preselect_payment_method_configuration', null, $is_test_mode );

		if ( ! is_string( $preselected_pmc_id ) || ! str_starts_with( $preselected_pmc_id, 'pmc_' ) ) {
			return [
				'pmc_id'        => null,
				'configuration' => null,
				'error'         => null,
			];
		}

		$response = WC_Stripe_API::retrieve( 'payment_method_configurations/' . $preselected_pmc_id );
		$error    = null;
		if ( is_wp_error( $response ) ) {
			$error = $response;
		} elseif ( is_object( $response ) && ! empty( $response->error ) ) {
			$error = $response->error;
		}

		return [
			'pmc_id'        => $preselected_pmc_id,
			'configuration' => null === $error ? $response : null,
			'error'         => $error,
		];
	}

	/**
	 * Selects a usable Payment Method Configuration from a Stripe configurations list:
	 * the platform-child PMC if present, otherwise the fallback PMC.
	 *
	 * @param array $configurations The list of payment method configurations returned from Stripe.
	 * @return array {
	 *     @type object|null $pmc    The selected Payment Method Configuration, or null if none is usable.
	 *     @type string|null $reason The reason for the selection (or for not finding one).
	 * }
	 */
	private static function select_platform_or_fallback_pmc( array $configurations ): array {
		$is_test_mode     = WC_Stripe_Mode::is_test();
		$fallback_pmc_key = $is_test_mode ? 'woocommerce_stripe_pmc_fallback_id_test' : 'woocommerce_stripe_pmc_fallback_id_live';

		// When connecting to the WooCommerce Platform account a new payment method configuration is created for the merchant.
		// This new payment method configuration has the WooCommerce Platform payment method configuration as parent, and inherits it's default payment methods.
		foreach ( $configurations as $configuration ) {
			// The API returns data for the corresponding mode of the api keys used, so we'll get either test or live PMCs, but never both.
			if ( ( $configuration->parent ?? null ) && ( self::LIVE_MODE_CONFIGURATION_PARENT_ID === $configuration->parent || self::TEST_MODE_CONFIGURATION_PARENT_ID === $configuration->parent ) ) {
				delete_option( $fallback_pmc_key );
				return [
					'pmc'    => $configuration,
					'reason' => 'platform_child_used',
				];
			}
		}

		WC_Stripe_Logger::warning( 'Did not find Payment Method Configuration that inherits from the WooCommerce platform' );

		[
			'pmc'    => $fallback_pmc,
			'reason' => $fallback_reason,
		] = self::get_fallback_payment_method_configuration( $configurations );

		if ( null !== $fallback_pmc ) {
			WC_Stripe_Logger::debug(
				'Using fallback Payment Method Configuration',
				[
					'pmc_id'      => $fallback_pmc->id,
					'reason'      => $fallback_reason,
					'name'        => $fallback_pmc->name ?? null,
					'livemode'    => $fallback_pmc->livemode ?? null,
					'stripe_mode' => $is_test_mode ? 'test' : 'live',
					'option_name' => $fallback_pmc_key,
				]
			);
		}

		return [
			'pmc'    => $fallback_pmc,
			'reason' => $fallback_reason,
		];
	}

	/**
	 * Given the list of payment method configurations returned from Stripe,
	 * find the fallback Payment Method Configuration to use.
	 *
	 * @param array $payment_method_configurations The list of payment method configurations returned from Stripe.
	 * @return array {
	 *     @type object|null $pmc    The fallback Payment Method Configuration.
	 *     @type string      $reason The reason for using the fallback Payment Method Configuration.
	 * }
	 */
	private static function get_fallback_payment_method_configuration( array $payment_method_configurations ): array {
		$active_non_child_payment_method_configurations = array_filter(
			$payment_method_configurations,
			function ( $configuration ) {
				if ( $configuration->parent ?? null ) {
					return false;
				}
				return isset( $configuration->active ) && $configuration->active;
			}
		);

		$is_test_mode     = WC_Stripe_Mode::is_test();
		$fallback_pmc_key = $is_test_mode ? 'woocommerce_stripe_pmc_fallback_id_test' : 'woocommerce_stripe_pmc_fallback_id_live';
		$fallback_pmc_id  = get_option( $fallback_pmc_key );
		$fallback_pmc     = null;

		if ( [] === $active_non_child_payment_method_configurations ) {
			if ( $fallback_pmc_id ) {
				WC_Stripe_Logger::debug(
					'No eligible Payment Method Configurations returned from Stripe; deleting fallback ID option',
					[
						'fallback_pmc_id' => $fallback_pmc_id,
						'stripe_mode'     => $is_test_mode ? 'test' : 'live',
						'option_name'     => $fallback_pmc_key,
					]
				);
				delete_option( $fallback_pmc_key );
			}

			return [
				'pmc'    => null,
				'reason' => 'no_eligible_pmcs',
			];
		}

		if ( $fallback_pmc_id ) {
			foreach ( $active_non_child_payment_method_configurations as $configuration ) {
				if ( $configuration->id === $fallback_pmc_id ) {
					return [
						'pmc'    => $configuration,
						'reason' => 'existing_fallback_pmc_used',
					];
				}
			}

			// If we get here and don't have our fallback yet, we need to delete the fallback ID option.
			WC_Stripe_Logger::debug(
				'Fallback Payment Method Configuration not returned from Stripe, deleting fallback ID option',
				[
					'fallback_pmc_id' => $fallback_pmc_id,
					'stripe_mode'     => $is_test_mode ? 'test' : 'live',
					'option_name'     => $fallback_pmc_key,
				]
			);
			delete_option( $fallback_pmc_key );
			$fallback_pmc_id = null;
		}

		$fallback_reason = null;
		// If we only have one remaining PMC, it should be the fallback, so we don't need to check for default PMCs.
		if ( 1 === count( $active_non_child_payment_method_configurations ) ) {
			$fallback_pmc    = reset( $active_non_child_payment_method_configurations );
			$fallback_reason = 'only_pmc_used';
		}

		if ( ! $fallback_pmc ) {
			$default_payment_method_configurations = array_filter(
				$active_non_child_payment_method_configurations,
				function ( $configuration ) {
					return isset( $configuration->default ) && $configuration->default;
				}
			);

			if ( 1 === count( $default_payment_method_configurations ) ) {
				// Use reset() as array_filter() preserves keys.
				$fallback_pmc    = reset( $default_payment_method_configurations );
				$fallback_reason = 'only_default_pmc_used';
			}
		}

		if ( $fallback_pmc ) {
			WC_Stripe_Logger::debug(
				'Updating Payment Method Configuration fallback',
				[
					'pmc_id'      => $fallback_pmc->id,
					'reason'      => $fallback_reason,
					'name'        => $fallback_pmc->name ?? null,
					'livemode'    => $fallback_pmc->livemode ?? null,
					'stripe_mode' => $is_test_mode ? 'test' : 'live',
					'option_name' => $fallback_pmc_key,
				]
			);
			update_option( $fallback_pmc_key, $fallback_pmc->id );

			return [
				'pmc'    => $fallback_pmc,
				'reason' => $fallback_reason,
			];
		}

		return [
			'pmc'    => null,
			'reason' => 'no_fallback_pmc_found',
		];
	}

	/**
	 * Get the WooCommerce Platform payment method configuration id.
	 *
	 * @return string
	 */
	public static function get_parent_configuration_id() {
		return WC_Stripe_Mode::is_test() ? self::TEST_MODE_CONFIGURATION_PARENT_ID : self::LIVE_MODE_CONFIGURATION_PARENT_ID;
	}

	/**
	 * Get the current payment method configuration ID.
	 *
	 * @return string|null The payment method configuration ID when settings sync is enabled and we have a PMC. Null otherwise.
	 */
	public static function get_configuration_id(): ?string {
		if ( ! self::is_enabled() ) {
			return null;
		}

		$primary_configuration = self::get_primary_configuration();
		if ( ! $primary_configuration || empty( $primary_configuration->id ) ) {
			return null;
		}

		return (string) $primary_configuration->id;
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
	 * Get the enabled payment method IDs in the PMC that are not supported in the plugin.
	 *
	 * @return string[] List of payment method IDs that are enabled in the PMC but not supported in the plugin.
	 */
	public static function get_unsupported_enabled_payment_method_ids_in_pmc(): array {
		// Bail if the payment method configurations API is not enabled.
		if ( ! self::is_enabled() ) {
			return [];
		}

		$unsupported_payment_method_ids        = [];
		$merchant_payment_method_configuration = self::get_primary_configuration();

		if ( $merchant_payment_method_configuration ) {
			foreach ( (array) $merchant_payment_method_configuration as $payment_method_id => $payment_method ) {
				if ( isset( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS[ $payment_method_id ] ) ) {
					continue;
				}

				if ( isset( $payment_method->display_preference->value ) && 'on' === $payment_method->display_preference->value ) {
					$unsupported_payment_method_ids[] = $payment_method_id;
				}
			}
		}

		return $unsupported_payment_method_ids;
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
			WC_Stripe_Logger::error( 'No primary payment method configuration found while updating payment method configuration' );
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
			WC_Stripe_Logger::error(
				'Unable to update Payment Method Configuration',
				[
					'pmc_id'        => $payment_method_configuration->id,
					'configuration' => $updated_payment_method_configuration,
					'response'      => $response,
				]
			);
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
	 * This requires the Stripe account to be connected to Stripe.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		// Bail if account is not connected.
		if ( ! WC_Stripe_Helper::is_connected() ) {
			return false;
		}

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		// If we have the pmc_enabled flag, and it is set to no, we should not use the payment method configurations API.
		// We only disable the PMC if the flag is set to no explicitly, an empty value means the migration has not been attempted yet.
		if ( isset( $stripe_settings['pmc_enabled'] ) && 'no' === $stripe_settings['pmc_enabled'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Re-evaluates whether the merchant has a usable Payment Method Configuration in Stripe and
	 * updates the `pmc_enabled` setting accordingly.
	 *
	 * Fetches directly from Stripe, bypassing the configuration cache, so an explicit caller (e.g. the
	 * "Refresh account details" admin action) forces a reconciliation. On transport or Stripe API
	 * errors `pmc_enabled` is left untouched so a transient failure can't flip the merchant into the
	 * DB-only fallback path.
	 *
	 * Selection follows the same precedence as the normal fetch path: the
	 * `wc_stripe_preselect_payment_method_configuration` filter takes priority, then the
	 * platform-child PMC, then the fallback PMC.
	 *
	 * Outcomes:
	 *   - No usable PMC found: delegates to {@see self::disable_payment_method_configuration_sync()} ('no').
	 *   - Usable PMC + `pmc_enabled` already 'yes': no-op.
	 *   - Usable PMC + `pmc_enabled` empty or 'no': runs the DB-to-PMC migration to reconcile any
	 *     payment methods edited while the flag was 'no', and the migration sets 'yes' on success.
	 *
	 * Note: when recovering from 'no', the union behavior of the migration may re-enable methods the
	 * merchant disabled during the 'no' window. Disables made during that window are not preserved.
	 *
	 * @return void
	 */
	public static function refresh_pmc_availability() {
		// Re-arm the cooldown instead of clearing it: this admin action triggers a forced settings
		// re-fetch (get_upe_enabled_payment_method_ids( true )), which would otherwise bypass the PMC
		// we cache below and make a redundant Stripe call — one that, on a transient failure, could
		// flip pmc_enabled to 'no' and undo the protection this method provides.
		update_option( self::FETCH_COOLDOWN_OPTION_KEY, time() + MINUTE_IN_SECONDS );

		$usable_pmc   = null;
		$is_test_mode = WC_Stripe_Mode::is_test();

		$preselected_pmc = self::get_preselected_pmc( $is_test_mode );
		if ( null !== $preselected_pmc['pmc_id'] ) {
			// A pinned PMC is authoritative during refresh, so lookup failures must not fall
			// through to a different PMC or the DB-only fallback path.
			if ( null !== $preselected_pmc['error'] || ! is_object( $preselected_pmc['configuration'] ) ) {
				WC_Stripe_Logger::warning(
					'Skipping PMC availability refresh: preselected Payment Method Configuration could not be retrieved',
					[
						'pmc_id' => $preselected_pmc['pmc_id'],
						'error'  => $preselected_pmc['error'],
					]
				);
				return;
			}
			$usable_pmc = $preselected_pmc['configuration'];
		}

		if ( null === $usable_pmc ) {
			$api_response = WC_Stripe_API::get_instance()->get_payment_method_configurations();

			// `WC_Stripe_API::get_payment_method_configurations()` returns null on invalid keys,
			// WP_Error on transport failures, and an object with `->error` set on Stripe API errors.
			// Bail without mutating pmc_enabled so a transient failure can't flip the merchant into
			// the DB-only fallback path.
			if ( ! is_object( $api_response ) || is_wp_error( $api_response ) || ! empty( $api_response->error ) ) {
				WC_Stripe_Logger::warning(
					'Skipping PMC availability refresh because the Stripe API call failed',
					[ 'response' => $api_response ]
				);
				return;
			}

			$configurations = is_array( $api_response->data ?? null ) ? $api_response->data : [];

			[ 'pmc' => $usable_pmc ] = self::select_platform_or_fallback_pmc( $configurations );
		}

		if ( ! $usable_pmc ) {
			WC_Stripe_Logger::warning(
				'No usable Payment Method Configuration found during account refresh; disabling Payment Method Configuration sync',
				[ 'stripe_mode' => $is_test_mode ? 'test' : 'live' ]
			);
			self::disable_payment_method_configuration_sync();
			return;
		}

		self::set_payment_method_configuration_cache( $usable_pmc );

		$stripe_settings    = WC_Stripe_Helper::get_stripe_settings();
		$previous_pmc_state = $stripe_settings['pmc_enabled'] ?? '';

		if ( 'yes' === $previous_pmc_state ) {
			return;
		}

		if ( 'no' === $previous_pmc_state ) {
			// Reset the flag so the migration's is_enabled() short-circuit doesn't block it.
			// The migration will set 'yes' once it finishes.
			unset( $stripe_settings['pmc_enabled'] );
			WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
		}

		self::maybe_migrate_payment_methods_from_db_to_pmc();
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

		// Add default express checkout methods to the list if express checkout is enabled
		if (
			! empty( $stripe_settings['express_checkout'] ) &&
			'yes' === $stripe_settings['express_checkout'] &&
			'yes' !== ( $stripe_settings['skip_pmc_express_checkout_defaults'] ?? 'no' )
		) {
			$enabled_payment_methods = array_merge(
				$enabled_payment_methods,
				[ WC_Stripe_Payment_Methods::GOOGLE_PAY, WC_Stripe_Payment_Methods::APPLE_PAY ]
			);

			// If Amazon Pay should be defaulted on, and the account country and currency are supported, enable Amazon Pay.
			if ( 'yes' === get_option( 'wc_stripe_amazon_pay_default_on' ) && WC_Stripe_UPE_Payment_Method_Amazon_Pay::is_amazon_pay_available_for_account_country() && in_array( get_woocommerce_currency(), WC_Stripe_UPE_Payment_Method_Amazon_Pay::get_amazon_pay_supported_currencies(), true ) ) {
				$enabled_payment_methods[] = WC_Stripe_Payment_Methods::AMAZON_PAY;
			}
		}

		// Update the PMC if there are locally enabled payment methods
		if ( ! empty( $enabled_payment_methods ) ) {
			// Get all available payment method IDs from the configuration.
			// We explicitly disable all payment methods that are not in the enabled_payment_methods array
			$available_payment_method_ids = [];
			foreach ( $merchant_payment_method_configuration as $payment_method_id => $payment_method ) {
				if ( isset( $payment_method->display_preference ) ) {
					$available_payment_method_ids[] = $payment_method_id;
				}

				// Add all payment methods enabled in the PMC that are not enabled locally.
				if (
					! in_array( $payment_method_id, $enabled_payment_methods, true ) &&
					isset( $payment_method->display_preference->value ) && 'on' === $payment_method->display_preference->value
				) {
					$enabled_payment_methods[] = $payment_method_id;
				}
			}

			WC_Stripe_Logger::error(
				'Switching to Stripe-hosted payment method configuration',
				[
					'pmc_id'                       => $merchant_payment_method_configuration->id,
					'enabled_payment_methods'      => $enabled_payment_methods,
					'available_payment_method_ids' => $available_payment_method_ids,
				]
			);

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
		$stripe_settings                = WC_Stripe_Helper::get_stripe_settings();
		$stripe_settings['pmc_enabled'] = 'no';
		WC_Stripe_Helper::update_main_stripe_settings( $stripe_settings );
	}
}
