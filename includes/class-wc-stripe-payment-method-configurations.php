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
	 * Last payment method selections both sides agreed on, per mode. Only used to preselect review answers.
	 */
	private const SYNC_BASELINE_OPTION_KEY = 'wc_stripe_pmc_sync_baseline';

	/**
	 * Option key remembering whether the last fresh comparison found a difference.
	 */
	private const SYNC_REVIEW_PENDING_OPTION_KEY = 'wc_stripe_pmc_sync_review_pending';

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
				self::sync_payment_methods_to_settings( $cached_primary_configuration );
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
	 *
	 * @return void
	 */
	public static function clear_payment_method_configuration_cache() {
		self::clear_in_memory_payment_method_configuration_cache();
		WC_Stripe_Database_Cache::delete( self::CONFIGURATION_CACHE_KEY );
	}

	/**
	 * Clear the in-memory payment method configuration cache.
	 * Used when we switch between test and live modes, as we
	 * don't need to invalidate the database cache.
	 * Most callers should call {@see clear_payment_method_configuration_cache()} instead.
	 *
	 * @since 11.1.0
	 */
	public static function clear_in_memory_payment_method_configuration_cache(): void {
		self::$primary_configuration = null;
	}

	/**
	 * Cache the payment method configuration.
	 *
	 * @param object|array $configuration The payment method configuration to set in cache.
	 */
	private static function set_payment_method_configuration_cache( $configuration ) {
		self::$primary_configuration = $configuration;
		WC_Stripe_Database_Cache::set( self::CONFIGURATION_CACHE_KEY, $configuration, self::CONFIGURATION_CACHE_EXPIRATION );
		self::sync_payment_methods_to_settings( $configuration );

		// Only fresh configurations from Stripe land here, never cached reads, so storefront
		// requests don't touch the baseline. While sync is on, both sides agree by definition.
		if ( self::is_enabled() && self::is_valid_configuration( $configuration ) ) {
			self::update_sync_baseline( $configuration );
		}
	}

	/**
	 * Keep the fallback selections current without overwriting edits awaiting migration.
	 *
	 * @since x.x.x
	 * @version x.x.x
	 * @param mixed $configuration Payment method configuration returned by Stripe.
	 */
	private static function sync_payment_methods_to_settings( $configuration ): void {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		if ( 'yes' !== ( $settings['pmc_enabled'] ?? '' ) || ! is_object( $configuration ) || is_wp_error( $configuration )
			|| ! empty( $configuration->error )
			|| WC_Stripe_Mode::is_live() !== ( $configuration->livemode ?? null )
			|| ! in_array( $configuration->card->display_preference->value ?? null, [ 'on', 'off' ], true ) ) {
			return;
		}

		$enabled_methods = [];
		foreach ( (array) $configuration as $id => $method ) {
			if ( 'on' === ( $method->display_preference->value ?? null ) ) {
				$enabled_methods[] = $id;
			}
		}
		$express_checkout = array_intersect( [ WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ], $enabled_methods ) ? 'yes' : 'no';
		$enabled_methods  = array_values( array_intersect( array_keys( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS ), $enabled_methods ) );
		$saved_methods    = (array) ( $settings['upe_checkout_experience_accepted_payments'] ?? [ WC_Stripe_Payment_Methods::CARD ] );
		sort( $enabled_methods );
		sort( $saved_methods );

		if ( $enabled_methods === $saved_methods && ( $settings['express_checkout'] ?? '' ) === $express_checkout ) {
			return;
		}

		$settings['upe_checkout_experience_accepted_payments'] = $enabled_methods;
		$settings['express_checkout']                          = $express_checkout;
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
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
			self::disable_payment_method_configuration_sync();
			return null;
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
		if ( ! self::is_enabled() || ! $primary_configuration || empty( $primary_configuration->id ) ) {
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
			return isset( $stripe_settings['upe_checkout_experience_accepted_payments'] ) && is_array( $stripe_settings['upe_checkout_experience_accepted_payments'] )
				? $stripe_settings['upe_checkout_experience_accepted_payments']
				: [ WC_Stripe_Payment_Methods::CARD ];
		}

		$enabled_payment_method_ids            = [];
		$merchant_payment_method_configuration = self::get_primary_configuration( $force_refresh );
		// A fetch that found no usable configuration disables synchronization, so serve the local
		// list rather than reporting no enabled payment methods at checkout.
		if ( ! self::is_enabled() ) {
			return self::get_upe_enabled_payment_method_ids();
		}

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
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update_payment_method_configuration( $enabled_payment_method_ids, $available_payment_method_ids ) {
		$payment_method_configuration         = self::get_primary_configuration();
		$updated_payment_method_configuration = [];
		$newly_enabled_methods                = [];
		$newly_disabled_methods               = [];

		if ( ! $payment_method_configuration ) {
			WC_Stripe_Logger::error( 'No primary payment method configuration found while updating payment method configuration' );
			return new WP_Error( 'pmc_not_found', __( 'No primary payment method configuration found.', 'woocommerce-gateway-stripe' ) );
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
			return new WP_Error( 'pmc_update_failed', __( 'Unable to update payment method configuration.', 'woocommerce-gateway-stripe' ) );
		}

		self::set_payment_method_configuration_cache( $response );

		self::record_payment_method_settings_event( $newly_enabled_methods, $newly_disabled_methods );

		return true;
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
	 * An absent flag means the merchant has not confirmed which side wins yet, which is not a
	 * licence to sync: it reads as disabled here, matching every other reader of the setting
	 * ({@see WC_Stripe_Feature_Flags::is_oc_available()}, {@see WC_Stripe_Helper}, the status
	 * report and the OCS/AP migration).
	 *
	 * Marked impure because the answer can change mid-request: a fetch that finds no usable
	 * configuration calls {@see self::disable_payment_method_configuration_sync()}, so callers
	 * that re-check after fetching are not re-reading a value static analysis can fold away.
	 *
	 * @phpstan-impure
	 * @return bool
	 */
	public static function is_enabled() {
		// Bail if account is not connected.
		if ( ! WC_Stripe_Helper::is_connected() ) {
			return false;
		}

		return 'yes' === ( WC_Stripe_Helper::get_stripe_settings()['pmc_enabled'] ?? '' );
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
	 * Different selections require merchant review; equal selections still require fresh verification.
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
	}

	/**
	 * No longer migrates: local selections are never pushed to Stripe without the merchant
	 * confirming the comparison first ({@see self::resolve_sync_review()}). Kept because it is
	 * public and reachable from outside this plugin.
	 *
	 * @deprecated 11.2.0 Synchronization is resumed through resolve_sync_review().
	 *
	 * @param bool $force_migration Unused.
	 */
	public static function maybe_migrate_payment_methods_from_db_to_pmc( $force_migration = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	}

	private static function connection_context(): string {
		$settings = WC_Stripe_Helper::get_stripe_settings();
		$prefix   = 'yes' === ( $settings['testmode'] ?? 'no' ) ? 'test_' : '';
		return hash( 'sha256', (string) wp_json_encode( [ get_current_blog_id(), $prefix, $settings[ $prefix . 'publishable_key' ] ?? '', $settings[ $prefix . 'secret_key' ] ?? '' ] ) );
	}

	/**
	 * Confirmation requires a complete configuration for the current Stripe mode.
	 *
	 * @param mixed $configuration Stripe response.
	 * @phpstan-assert-if-true \stdClass $configuration
	 */
	private static function is_valid_configuration( $configuration ): bool {
		return is_object( $configuration ) && ! is_wp_error( $configuration ) && empty( $configuration->error )
			&& is_string( $configuration->id ?? null ) && str_starts_with( $configuration->id, 'pmc_' )
			&& true === ( $configuration->active ?? false )
			&& ( ! WC_Stripe_Mode::is_test() ) === ( $configuration->livemode ?? null )
			&& in_array( $configuration->card->display_preference->value ?? null, [ 'on', 'off' ], true );
	}

	private static function local_selections( array $settings ): array {
		$methods = $settings['upe_checkout_experience_accepted_payments'] ?? [ WC_Stripe_Payment_Methods::CARD ];
		$methods = is_array( $methods ) ? array_values( array_intersect( array_keys( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS ), array_filter( $methods, 'is_string' ) ) ) : [];
		sort( $methods );
		return [
			'upe_checkout_experience_accepted_payments' => $methods,
			'express_checkout'                          => 'yes' === ( $settings['express_checkout'] ?? 'yes' ) ? 'yes' : 'no',
		];
	}

	private static function configuration_selections( object $configuration ): array {
		$methods = [];
		foreach ( (array) $configuration as $id => $method ) {
			if ( 'on' === ( $method->display_preference->value ?? null ) ) {
				$methods[] = $id;
			}
		}
		return self::local_selections(
			[
				'upe_checkout_experience_accepted_payments' => $methods,
				'express_checkout'                          => array_intersect( [ 'apple_pay', 'google_pay' ], $methods ) ? 'yes' : 'no',
			]
		);
	}

	/**
	 * Bypass cached discovery without changing selection bookkeeping.
	 *
	 * @return \stdClass|WP_Error
	 */
	private static function retrieve_review_configuration() {
		$preselected = self::get_preselected_pmc( WC_Stripe_Mode::is_test() );
		if ( null !== $preselected['pmc_id'] ) {
			$configuration = $preselected['configuration'];
		} else {
			$response = WC_Stripe_API::get_instance()->get_payment_method_configurations();
			if ( ! is_object( $response ) || is_wp_error( $response ) || ! empty( $response->error ) || ! is_array( $response->data ?? null ) ) {
				return new WP_Error( 'pmc_unavailable', __( 'Unable to compare payment methods with Stripe. Please try again.', 'woocommerce-gateway-stripe' ), [ 'status' => 502 ] );
			}
			[ 'pmc' => $configuration ] = self::select_platform_or_fallback_pmc( $response->data );
		}
		if ( ! self::is_valid_configuration( $configuration ) ) {
			return new WP_Error( 'pmc_unavailable', __( 'No usable Stripe payment method configuration is available.', 'woocommerce-gateway-stripe' ), [ 'status' => 502 ] );
		}
		return $configuration;
	}

	private static function review_data( object $configuration, array $settings ): array {
		$local  = self::local_selections( $settings );
		$stripe = self::configuration_selections( $configuration );
		return [
			'configuration_id' => $configuration->id ?? '',
			'local'            => $local,
			'stripe'           => $stripe,
			'wallets'          => [
				'apple_pay'  => 'on' === ( $configuration->apple_pay->display_preference->value ?? null ),
				'google_pay' => 'on' === ( $configuration->google_pay->display_preference->value ?? null ),
			],
			'differs'          => $local !== $stripe,
			// Proves the merchant acted on the comparison they were shown; it is recomputed and
			// compared server-side, never trusted, so it needs no keyed digest. Per-method
			// `overridable` is re-checked against Stripe before each write instead of being bound
			// here, where an unrelated flip would only invalidate a review the merchant can act on.
			'revision'         => hash( 'sha256', (string) wp_json_encode( [ self::connection_context(), $configuration->id ?? '', $local, $stripe ] ) ),
		];
	}

	/**
	 * Compare persisted local choices without adopting the remote configuration.
	 *
	 * @since x.x.x
	 * @return array|WP_Error
	 */
	public static function get_sync_review() {
		$result = self::build_sync_review();
		return is_wp_error( $result ) ? $result : $result['review'];
	}

	/**
	 * Whether the last known Stripe configuration differs from the local selection while sync is off.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function has_pending_sync_review(): bool {
		if ( ! WC_Stripe_Helper::is_connected() || self::is_enabled() ) {
			return false;
		}

		// The configuration cache expires after minutes, so the result of the last fresh
		// comparison is what keeps the notice up until the merchant resolves it.
		if ( 'yes' === get_option( self::SYNC_REVIEW_PENDING_OPTION_KEY ) ) {
			return true;
		}

		// This runs on every admin page load, so it never calls Stripe.
		$configuration = self::get_payment_method_configuration_from_cache();
		if ( ! self::is_valid_configuration( $configuration ) ) {
			return false;
		}

		$local_selections = self::local_selections( WC_Stripe_Helper::get_stripe_settings() );
		return self::configuration_selections( $configuration ) !== $local_selections;
	}

	/**
	 * Builds the comparison and keeps the configuration it was derived from, so a caller that
	 * goes on to write to Stripe does not have to fetch the same configuration a second time.
	 *
	 * @return array{review: array, configuration: \stdClass|null}|WP_Error
	 */
	private static function build_sync_review() {
		if ( self::is_enabled() || ! WC_Stripe_Helper::is_connected() ) {
			return [
				'review'        => [ 'differs' => false ],
				'configuration' => null,
			];
		}
		$context       = self::connection_context();
		$configuration = self::retrieve_review_configuration();
		if ( is_wp_error( $configuration ) ) {
			return $configuration;
		}
		$settings = WC_Stripe::get_instance()->get_settings();
		if ( self::connection_context() !== $context || self::is_enabled() ) {
			return new WP_Error( 'pmc_changed', __( 'Payment settings changed. Refresh the comparison and try again.', 'woocommerce-gateway-stripe' ), [ 'status' => 409 ] );
		}
		$review = self::review_data( $configuration, $settings );
		self::remember_sync_review_result( $review['differs'] );
		return [
			'review'        => $review,
			'configuration' => $configuration,
		];
	}

	/**
	 * Stores the outcome of a fresh comparison for the admin-wide notice.
	 *
	 * @since x.x.x
	 * @param bool $differs Whether the local and Stripe selections differ.
	 */
	private static function remember_sync_review_result( bool $differs ): void {
		if ( $differs ) {
			update_option( self::SYNC_REVIEW_PENDING_OPTION_KEY, 'yes', false );
			return;
		}
		delete_option( self::SYNC_REVIEW_PENDING_OPTION_KEY );
	}

	/**
	 * Resolve a reviewed difference, then activate synchronization after verification.
	 *
	 * @since x.x.x
	 * @param string $source Either woocommerce or stripe.
	 * @param string $revision Revision returned by get_sync_review().
	 * @return true|WP_Error
	 */
	public static function resolve_sync_review( string $source, string $revision ) {
		$result = self::build_sync_review();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$review        = $result['review'];
		$configuration = $result['configuration'];
		if ( null === $configuration || ! in_array( $source, [ 'woocommerce', 'stripe' ], true ) || empty( $review['revision'] ) || ! hash_equals( $review['revision'], $revision ) ) {
			return new WP_Error( 'pmc_changed', __( 'Payment settings changed. Refresh the comparison and try again.', 'woocommerce-gateway-stripe' ), [ 'status' => 409 ] );
		}
		$context = self::connection_context();
		if ( 'woocommerce' === $source ) {
			$enabled = $review['local']['upe_checkout_experience_accepted_payments'];
			if ( 'yes' === $review['local']['express_checkout'] ) {
				$enabled = array_merge( $enabled, [ 'apple_pay', 'google_pay' ] );
			}
			$payload = [];
			foreach ( array_merge( array_keys( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS ), [ 'apple_pay', 'google_pay' ] ) as $id ) {
				$preference = in_array( $id, $enabled, true ) ? 'on' : 'off';
				$display    = $configuration->$id->display_preference ?? null;
				if ( null === $display || false === ( $display->overridable ?? true ) ) {
					if ( ( $display->value ?? 'off' ) !== $preference ) {
						/* translators: %s: payment method ID. */
						return new WP_Error( 'pmc_method_unavailable', sprintf( __( 'Stripe cannot apply the WooCommerce selection for %s. Change that selection or use Stripe’s settings.', 'woocommerce-gateway-stripe' ), $id ), [ 'status' => 409 ] );
					}
					continue;
				}
				$payload[ $id ] = [ 'display_preference' => [ 'preference' => $preference ] ];
			}
			$configuration = WC_Stripe_API::get_instance()->update_payment_method_configurations( $configuration->id, $payload );
		}
		$rejected = [];
		foreach ( $payload ?? [] as $id => $preference ) {
			if ( ( $configuration->$id->display_preference->value ?? null ) !== $preference['display_preference']['preference'] ) {
				$rejected[] = $id;
			}
		}
		$expected = $review[ 'woocommerce' === $source ? 'local' : 'stripe' ];
		if ( $rejected || ! self::is_valid_configuration( $configuration ) || $review['configuration_id'] !== $configuration->id || self::configuration_selections( $configuration ) !== $expected ) {
			return new WP_Error( 'pmc_verification_failed', __( 'Stripe could not apply all selected payment methods. Synchronization remains off. Refresh the comparison or use Stripe’s settings.', 'woocommerce-gateway-stripe' ) . ( $rejected ? ' ' . implode( ', ', $rejected ) : '' ), [ 'status' => 502 ] );
		}
		$settings = WC_Stripe::get_instance()->get_settings();
		if ( self::connection_context() !== $context || self::local_selections( $settings ) !== $review['local'] || self::is_enabled() ) {
			return new WP_Error( 'pmc_changed', __( 'Payment settings changed. Refresh the comparison and try again.', 'woocommerce-gateway-stripe' ), [ 'status' => 409 ] );
		}
		WC_Stripe::get_instance()->update_settings( array_merge( $settings, $expected, [ 'pmc_enabled' => 'yes' ] ) );
		self::remember_sync_review_result( false );
		self::set_payment_method_configuration_cache( $configuration );
		update_option( self::FETCH_COOLDOWN_OPTION_KEY, time() + MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Compare local and Stripe choices method by method, preselecting a side for each.
	 *
	 * @since x.x.x
	 * @return array|WP_Error
	 */
	public static function get_sync_method_review() {
		$result = self::build_sync_review();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( null === $result['configuration'] ) {
			return $result['review'];
		}
		return self::method_review_data( $result['configuration'], $result['review'] );
	}

	/**
	 * Apply per-method picks to Stripe, then activate synchronization after verification.
	 *
	 * @since x.x.x
	 * @param string[] $enabled  Method IDs the merchant wants on, wallets included.
	 * @param string   $revision Revision returned by get_sync_method_review().
	 * @return true|WP_Error
	 */
	public static function resolve_sync_method_review( array $enabled, string $revision ) {
		$result = self::build_sync_review();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$configuration = $result['configuration'];
		if ( null === $configuration ) {
			return new WP_Error( 'pmc_changed', __( 'Payment settings changed. Refresh the comparison and try again.', 'woocommerce-gateway-stripe' ), [ 'status' => 409 ] );
		}
		$review = self::method_review_data( $configuration, $result['review'] );
		if ( ! hash_equals( $review['revision'], $revision ) ) {
			return new WP_Error( 'pmc_changed', __( 'Payment settings changed. Refresh the comparison and try again.', 'woocommerce-gateway-stripe' ), [ 'status' => 409 ] );
		}

		$context = self::connection_context();
		$wanted  = [];
		$payload = [];
		foreach ( self::reviewable_method_ids() as $id ) {
			$wanted[ $id ] = in_array( $id, $enabled, true );
			$display       = $configuration->$id->display_preference ?? null;
			$is_on         = 'on' === ( $display->value ?? null );
			if ( $is_on === $wanted[ $id ] ) {
				continue;
			}
			if ( null === $display || false === ( $display->overridable ?? true ) ) {
				/* translators: %s: payment method name. */
				return new WP_Error( 'pmc_method_unavailable', sprintf( __( 'Stripe cannot change %s from this store. Change that selection or use Stripe’s settings.', 'woocommerce-gateway-stripe' ), self::method_label( $id ) ), [ 'status' => 409 ] );
			}
			$payload[ $id ] = [ 'display_preference' => [ 'preference' => $wanted[ $id ] ? 'on' : 'off' ] ];
		}

		if ( $payload ) {
			$configuration = WC_Stripe_API::get_instance()->update_payment_method_configurations( $configuration->id, $payload );
		}

		// Stripe can accept the request yet keep a method in its previous state, so check every
		// method rather than only the ones sent.
		$rejected = [];
		foreach ( $wanted as $id => $is_wanted ) {
			if ( ( 'on' === ( $configuration->$id->display_preference->value ?? null ) ) !== $is_wanted ) {
				$rejected[] = self::method_label( $id );
			}
		}
		if ( $rejected || ! self::is_valid_configuration( $configuration ) || $review['configuration_id'] !== $configuration->id ) {
			return new WP_Error( 'pmc_verification_failed', __( 'Stripe could not apply all selected payment methods. Synchronization remains off. Refresh the comparison or use Stripe’s settings.', 'woocommerce-gateway-stripe' ) . ( $rejected ? ' ' . implode( ', ', $rejected ) : '' ), [ 'status' => 502 ] );
		}

		$settings = WC_Stripe::get_instance()->get_settings();
		if ( self::connection_context() !== $context || self::local_selections( $settings ) !== $result['review']['local'] || self::is_enabled() ) {
			return new WP_Error( 'pmc_changed', __( 'Payment settings changed. Refresh the comparison and try again.', 'woocommerce-gateway-stripe' ), [ 'status' => 409 ] );
		}

		$upe_methods = array_values( array_intersect( array_keys( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS ), array_keys( array_filter( $wanted ) ) ) );
		sort( $upe_methods );
		$wants_wallets = $wanted[ WC_Stripe_Payment_Methods::APPLE_PAY ] || $wanted[ WC_Stripe_Payment_Methods::GOOGLE_PAY ];

		$settings['upe_checkout_experience_accepted_payments'] = $upe_methods;
		$settings['express_checkout']                          = $wants_wallets ? 'yes' : 'no';
		$settings['pmc_enabled']                               = 'yes';
		WC_Stripe::get_instance()->update_settings( $settings );
		self::remember_sync_review_result( false );
		self::set_payment_method_configuration_cache( $configuration );
		update_option( self::FETCH_COOLDOWN_OPTION_KEY, time() + MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Adds per-method rows and their preselection to a review built by build_sync_review().
	 */
	private static function method_review_data( object $configuration, array $review ): array {
		$mode          = WC_Stripe_Mode::is_test() ? 'test' : 'live';
		$baseline      = self::get_sync_baseline();
		$mode_baseline = $baseline[ $mode ] ?? null;
		// The account cache can be empty, but a configuration ID never moves between accounts,
		// so it still catches a reconnect to another account for the current mode.
		if ( null !== $mode_baseline && ( $mode_baseline['configuration_id'] ?? null ) !== ( $configuration->id ?? null ) ) {
			$mode_baseline = null;
		}
		$source_mode   = $baseline['local_source_mode'] ?? null;
		$switched_from = ( null !== $source_mode && $source_mode !== $mode ) ? $source_mode : null;
		$other_mode    = null !== $switched_from ? ( $baseline[ $switched_from ] ?? null ) : null;
		$local_methods = $review['local']['upe_checkout_experience_accepted_payments'];
		$local_wallets = 'yes' === $review['local']['express_checkout'];

		$stripe_wallets = [
			WC_Stripe_Payment_Methods::APPLE_PAY  => 'on' === ( $configuration->apple_pay->display_preference->value ?? null ),
			WC_Stripe_Payment_Methods::GOOGLE_PAY => 'on' === ( $configuration->google_pay->display_preference->value ?? null ),
		];

		$other_mode_wallets = null !== $other_mode && ( $other_mode[ WC_Stripe_Payment_Methods::APPLE_PAY ] || $other_mode[ WC_Stripe_Payment_Methods::GOOGLE_PAY ] );

		$rows = [];
		foreach ( self::reviewable_method_ids() as $id ) {
			$display   = $configuration->$id->display_preference ?? null;
			$is_wallet = isset( $stripe_wallets[ $id ] );
			$stripe    = 'on' === ( $display->value ?? null );
			$local     = $is_wallet ? self::local_wallet_value( $id, $local_wallets, $stripe_wallets ) : in_array( $id, $local_methods, true );
			if ( ! $local && ! $stripe && null === $display ) {
				continue;
			}

			$baseline_value   = null === $mode_baseline ? null : self::baseline_value( $mode_baseline, $id );
			$other_mode_value = null === $other_mode ? null : self::baseline_value( $other_mode, $id );

			// The local list was copied from the other mode, so a value that still matches it was
			// not chosen for this mode. One that no longer matches was edited after the switch.
			$edited_after_switch = null;
			if ( null !== $other_mode ) {
				$edited_after_switch = $is_wallet ? $other_mode_wallets !== $local_wallets : $other_mode_value !== $local;
			}

			$stripe_inherited         = 'none' === ( $display->preference ?? null );
			[ $preselected, $reason ] = self::preselect( $local, $stripe, $baseline_value, $edited_after_switch, $stripe_inherited );

			$rows[] = [
				'id'                => $id,
				'local'             => $local,
				'stripe'            => $stripe,
				'baseline'          => $baseline_value,
				'other_mode_stripe' => $other_mode_value,
				'preselected'       => $preselected,
				'reason'            => $reason,
				'overridable'       => null !== $display && false !== ( $display->overridable ?? true ),
			];
		}

		return [
			'differs'              => $review['differs'],
			// The base revision folds wallets into one express checkout flag, so a single wallet
			// flipping in Stripe would otherwise go unnoticed between review and save.
			'revision'             => hash( 'sha256', (string) wp_json_encode( [ $review['revision'], $review['wallets'] ] ) ),
			'configuration_id'     => $review['configuration_id'],
			'mode'                 => $mode,
			'mode_switched_from'   => $switched_from,
			'has_baseline'         => null !== $mode_baseline,
			'baseline_synced_at'   => $mode_baseline['synced_at'] ?? null,
			'other_mode_synced_at' => $other_mode['synced_at'] ?? null,
			'methods'              => $rows,
		];
	}

	/**
	 * The store keeps one toggle for both wallets, so it can't say which wallet it means.
	 *
	 * When that toggle agrees with Stripe overall, each wallet is treated as matching Stripe;
	 * otherwise a single wallet enabled in Stripe would look like a local change to the other one.
	 */
	private static function local_wallet_value( string $id, bool $local_wallets_on, array $stripe_wallets ): bool {
		$stripe_wallets_on = in_array( true, $stripe_wallets, true );
		if ( $stripe_wallets_on === $local_wallets_on ) {
			return $stripe_wallets[ $id ];
		}
		return $local_wallets_on;
	}

	/**
	 * A method's value in a stored baseline entry.
	 */
	private static function baseline_value( array $entry, string $id ): bool {
		if ( isset( $entry[ $id ] ) && is_bool( $entry[ $id ] ) ) {
			return $entry[ $id ];
		}
		return in_array( $id, $entry['methods'], true );
	}

	/**
	 * Picks the value a merchant most likely wants for one method, and why.
	 *
	 * The baseline is the last state both sides agreed on, so whichever side moved away from it
	 * holds the newer intent. A Stripe value inherited from the platform was never chosen by
	 * anyone, so it does not count as a change made in Stripe.
	 *
	 * @param bool      $local               Value saved in WooCommerce.
	 * @param bool      $stripe              Value in the Stripe configuration.
	 * @param bool|null $baseline            Last agreed value for this mode, if known.
	 * @param bool|null $edited_after_switch Null without a mode switch, otherwise whether the local
	 *                                       value changed after it was copied from the other mode.
	 * @param bool      $stripe_inherited    Whether Stripe's value is an inherited default.
	 * @return array{0: bool, 1: string} Preselected value and reason.
	 */
	private static function preselect( bool $local, bool $stripe, ?bool $baseline, ?bool $edited_after_switch, bool $stripe_inherited ): array {
		if ( $local === $stripe ) {
			return [ $local, 'match' ];
		}
		if ( true === $edited_after_switch ) {
			return [ $local, 'changed_locally' ];
		}
		if ( false === $edited_after_switch ) {
			return [ $stripe, 'mode_switched' ];
		}
		if ( null === $baseline ) {
			return [ $local, 'no_baseline' ];
		}
		if ( $local !== $baseline ) {
			return [ $local, 'changed_locally' ];
		}
		if ( $stripe_inherited ) {
			return [ $local, 'inherited' ];
		}
		return [ $stripe, 'changed_in_stripe' ];
	}

	/**
	 * UPE methods plus wallets, which Stripe tracks separately but the store groups.
	 *
	 * @return string[]
	 */
	private static function reviewable_method_ids(): array {
		return array_merge( array_keys( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS ), [ WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ] );
	}

	private static function method_label( string $id ): string {
		$wallet_labels = [
			WC_Stripe_Payment_Methods::APPLE_PAY  => 'Apple Pay',
			WC_Stripe_Payment_Methods::GOOGLE_PAY => 'Google Pay',
		];
		if ( isset( $wallet_labels[ $id ] ) ) {
			return $wallet_labels[ $id ];
		}
		$method = WC_Stripe::get_instance()->get_main_stripe_gateway()->payment_methods[ $id ] ?? null;
		return $method instanceof WC_Stripe_UPE_Payment_Method ? $method->get_label() : $id;
	}

	/**
	 * The stored baseline, with malformed mode entries dropped.
	 */
	private static function get_sync_baseline(): array {
		$baseline = get_option( self::SYNC_BASELINE_OPTION_KEY, [] );
		if ( ! is_array( $baseline ) ) {
			return [];
		}
		foreach ( [ 'test', 'live' ] as $mode ) {
			if ( ! is_array( $baseline[ $mode ]['methods'] ?? null ) || ! isset( $baseline[ $mode ][ WC_Stripe_Payment_Methods::APPLE_PAY ], $baseline[ $mode ][ WC_Stripe_Payment_Methods::GOOGLE_PAY ] ) ) {
				unset( $baseline[ $mode ] );
			}
		}
		if ( ! in_array( $baseline['local_source_mode'] ?? null, [ 'test', 'live' ], true ) ) {
			unset( $baseline['local_source_mode'] );
		}

		// A baseline recorded for another Stripe account says nothing about this one.
		$stored_account  = $baseline['account_id'] ?? null;
		$current_account = self::get_current_account_id();
		if ( null !== $stored_account && null !== $current_account && $stored_account !== $current_account ) {
			return [];
		}
		return $baseline;
	}

	/**
	 * The connected account's ID, read from the account cache only.
	 *
	 * The baseline is written while fetching the configuration, which can happen on storefront
	 * requests, so this must never trigger an extra account request.
	 */
	private static function get_current_account_id(): ?string {
		$account = WC_Stripe_Database_Cache::get_with_mode( WC_Stripe_Account::ACCOUNT_CACHE_KEY );
		$id      = is_array( $account ) ? ( $account['id'] ?? null ) : null;
		return is_string( $id ) && '' !== $id ? $id : null;
	}

	/**
	 * Records a configuration both sides agree on, skipping the write when nothing changed.
	 *
	 * @param object $configuration A valid configuration for the current Stripe mode.
	 */
	private static function update_sync_baseline( object $configuration ): void {
		$mode    = ( $configuration->livemode ?? false ) ? 'live' : 'test';
		$methods = [];
		foreach ( array_keys( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS ) as $id ) {
			if ( 'on' === ( $configuration->$id->display_preference->value ?? null ) ) {
				$methods[] = $id;
			}
		}
		sort( $methods );
		$entry = [
			'configuration_id'                    => $configuration->id ?? null,
			'methods'                             => $methods,
			WC_Stripe_Payment_Methods::APPLE_PAY  => 'on' === ( $configuration->apple_pay->display_preference->value ?? null ),
			WC_Stripe_Payment_Methods::GOOGLE_PAY => 'on' === ( $configuration->google_pay->display_preference->value ?? null ),
		];

		$baseline   = self::get_sync_baseline();
		$account_id = self::get_current_account_id() ?? ( $baseline['account_id'] ?? null );
		$stored     = $baseline[ $mode ] ?? [];
		unset( $stored['synced_at'] );
		$same_entry   = $stored === $entry;
		$same_mode    = ( $baseline['local_source_mode'] ?? null ) === $mode;
		$same_account = ( $baseline['account_id'] ?? null ) === $account_id;
		if ( $same_entry && $same_mode && $same_account ) {
			return;
		}

		$baseline[ $mode ]             = array_merge( $entry, [ 'synced_at' => time() ] );
		$baseline['local_source_mode'] = $mode;
		$baseline['account_id']        = $account_id;
		update_option( self::SYNC_BASELINE_OPTION_KEY, $baseline, false );
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
