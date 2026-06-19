<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies smart payment method defaults for newly connected Stripe accounts.
 */
class WC_Stripe_Smart_Payment_Method_Defaults {

	public const TRIGGER_ACCOUNT_CONNECTION      = 'account_connection';
	public const TRIGGER_SUBSCRIPTIONS_ACTIVATED = 'subscriptions_activated';

	private const EXPLICITLY_DISABLED_OPTION = 'wc_stripe_smart_default_explicitly_disabled_payment_methods';

	private const UNIVERSAL_PAYMENT_METHODS = [
		WC_Stripe_Payment_Methods::CARD,
		WC_Stripe_Payment_Methods::AFFIRM,
		WC_Stripe_Payment_Methods::AFTERPAY_CLEARPAY,
		WC_Stripe_Payment_Methods::KLARNA,
		WC_Stripe_Payment_Methods::LINK,
		WC_Stripe_Payment_Methods::APPLE_PAY,
		WC_Stripe_Payment_Methods::GOOGLE_PAY,
	];

	private const DIRECT_DEBIT_PAYMENT_METHODS = [
		WC_Stripe_Payment_Methods::ACH,
		WC_Stripe_Payment_Methods::ACSS_DEBIT,
		WC_Stripe_Payment_Methods::BACS_DEBIT,
		WC_Stripe_Payment_Methods::BECS_DEBIT,
		WC_Stripe_Payment_Methods::SEPA_DEBIT,
	];

	private const COUNTRY_PAYMENT_METHODS = [
		WC_Stripe_Country_Code::NETHERLANDS    => [ WC_Stripe_Payment_Methods::IDEAL ],
		WC_Stripe_Country_Code::BELGIUM        => [ WC_Stripe_Payment_Methods::BANCONTACT ],
		WC_Stripe_Country_Code::GERMANY        => [ WC_Stripe_Payment_Methods::SEPA_DEBIT ],
		WC_Stripe_Country_Code::AUSTRIA        => [ WC_Stripe_Payment_Methods::EPS ],
		WC_Stripe_Country_Code::POLAND         => [ WC_Stripe_Payment_Methods::BLIK, WC_Stripe_Payment_Methods::P24 ],
		WC_Stripe_Country_Code::MEXICO         => [ WC_Stripe_Payment_Methods::OXXO ],
		WC_Stripe_Country_Code::BRAZIL         => [ WC_Stripe_Payment_Methods::BOLETO ],
		WC_Stripe_Country_Code::UNITED_STATES  => [ WC_Stripe_Payment_Methods::ACH ],
		WC_Stripe_Country_Code::CANADA         => [ WC_Stripe_Payment_Methods::ACSS_DEBIT ],
		WC_Stripe_Country_Code::AUSTRALIA      => [ WC_Stripe_Payment_Methods::BECS_DEBIT ],
		WC_Stripe_Country_Code::PORTUGAL       => [ WC_Stripe_Payment_Methods::MULTIBANCO ],
		WC_Stripe_Country_Code::UNITED_KINGDOM => [ WC_Stripe_Payment_Methods::BACS_DEBIT ],
	];

	/**
	 * Registers lifecycle hooks for defaults that are applied after initial connection.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'activated_plugin', [ $this, 'maybe_apply_subscriptions_activation_backfill' ], 10, 1 );
		add_action( 'woocommerce_subscriptions_activated', [ $this, 'handle_subscriptions_activated_action' ] );
	}

	/**
	 * Applies smart defaults after a new account connection if no method choices exist yet.
	 *
	 * @param string     $mode              The connected mode. Either 'test' or 'live'.
	 * @param array      $previous_settings Stripe settings before the account connection was saved.
	 * @param bool       $force             Whether to apply defaults even when previous choices exist.
	 * @param array|null $account_data      Stripe account data, if already available.
	 * @return array Details about the application result.
	 */
	public function apply_for_account_connection( string $mode, array $previous_settings, bool $force = false, ?array $account_data = null ): array {
		if ( ! $force && $this->has_existing_payment_method_configuration( $previous_settings ) ) {
			return [
				'applied'         => false,
				'methods_enabled' => [],
				'methods_skipped' => [],
				'reason'          => 'existing_configuration',
			];
		}

		return $this->apply( self::TRIGGER_ACCOUNT_CONNECTION, $mode, false, true, $force, $account_data );
	}

	/**
	 * Applies the direct debit backfill when WooCommerce Subscriptions is activated.
	 *
	 * @param string $plugin Activated plugin path.
	 * @return void
	 */
	public function maybe_apply_subscriptions_activation_backfill( $plugin ): void {
		$is_subscriptions_plugin = is_string( $plugin ) && false !== strpos( $plugin, 'woocommerce-subscriptions' );
		if ( ! $is_subscriptions_plugin ) {
			return;
		}

		$this->apply_subscriptions_activation_backfill();
	}

	/**
	 * Handles Subscriptions activation actions where return values are ignored by WordPress.
	 *
	 * @return void
	 */
	public function handle_subscriptions_activated_action(): void {
		$this->apply_subscriptions_activation_backfill();
	}

	/**
	 * Applies the direct debit backfill for the current Stripe mode.
	 *
	 * @return array Details about the application result.
	 */
	public function apply_subscriptions_activation_backfill(): array {
		return $this->apply( self::TRIGGER_SUBSCRIPTIONS_ACTIVATED, null, true, false, false, null );
	}

	/**
	 * Stores methods that the merchant explicitly disabled.
	 *
	 * @param string[] $payment_method_ids Payment method IDs.
	 * @return void
	 */
	public static function record_explicitly_disabled_payment_methods( array $payment_method_ids ): void {
		$payment_method_ids = self::normalize_payment_method_ids( $payment_method_ids );
		if ( empty( $payment_method_ids ) ) {
			return;
		}

		$disabled_payment_method_ids = self::get_explicitly_disabled_payment_method_ids();
		$disabled_payment_method_ids = array_values( array_unique( array_merge( $disabled_payment_method_ids, $payment_method_ids ) ) );

		update_option( self::EXPLICITLY_DISABLED_OPTION, $disabled_payment_method_ids );
	}

	/**
	 * Removes methods from the explicit-disable list when the merchant enables them.
	 *
	 * @param string[] $payment_method_ids Payment method IDs.
	 * @return void
	 */
	public static function record_explicitly_enabled_payment_methods( array $payment_method_ids ): void {
		$payment_method_ids = self::normalize_payment_method_ids( $payment_method_ids );
		if ( empty( $payment_method_ids ) ) {
			return;
		}

		$disabled_payment_method_ids = array_values(
			array_diff(
				self::get_explicitly_disabled_payment_method_ids(),
				$payment_method_ids
			)
		);

		update_option( self::EXPLICITLY_DISABLED_OPTION, $disabled_payment_method_ids );
	}

	/**
	 * Returns configured smart defaults for a country.
	 *
	 * @param string $country_code          Stripe account country code.
	 * @param bool   $include_direct_debits Whether direct debit defaults should be included.
	 * @param string $trigger               Trigger that is applying defaults.
	 * @return string[]
	 */
	public function get_default_payment_method_ids( string $country_code, bool $include_direct_debits, string $trigger ): array {
		$country_code                 = strtoupper( $country_code );
		$country_payment_method_map   = $this->get_country_payment_method_map();
		$country_payment_method_ids   = $country_payment_method_map[ $country_code ] ?? [];
		$default_payment_method_ids   = array_merge( self::UNIVERSAL_PAYMENT_METHODS, $country_payment_method_ids );
		$supported_payment_method_ids = $this->get_supported_default_payment_method_ids();

		if ( ! $include_direct_debits ) {
			$default_payment_method_ids = array_diff( $default_payment_method_ids, self::DIRECT_DEBIT_PAYMENT_METHODS );
		}

		if ( self::TRIGGER_SUBSCRIPTIONS_ACTIVATED === $trigger ) {
			$default_payment_method_ids = array_intersect( $default_payment_method_ids, self::DIRECT_DEBIT_PAYMENT_METHODS );
		}

		$default_payment_method_ids = array_values( array_intersect( self::normalize_payment_method_ids( $default_payment_method_ids ), $supported_payment_method_ids ) );

		/**
		 * Filters the smart default payment methods before availability checks are applied.
		 *
		 * @param string[] $default_payment_method_ids Default payment method IDs.
		 * @param string   $country_code               Stripe account country code.
		 * @param bool     $include_direct_debits      Whether direct debit defaults should be included.
		 * @param string   $trigger                    Trigger that is applying defaults.
		 */
		$default_payment_method_ids = apply_filters(
			'wc_stripe_smart_default_payment_methods',
			$default_payment_method_ids,
			$country_code,
			$include_direct_debits,
			$trigger
		);

		return array_values( array_intersect( self::normalize_payment_method_ids( (array) $default_payment_method_ids ), $supported_payment_method_ids ) );
	}

	/**
	 * Applies smart defaults for the supplied trigger.
	 *
	 * @param string      $trigger                  Trigger that is applying defaults.
	 * @param string|null $mode                     Stripe mode. Null uses the current mode.
	 * @param bool        $direct_debits_only       Whether only direct debit methods should be considered.
	 * @param bool        $force_account_data       Whether account data should be refreshed from Stripe.
	 * @param bool        $replace_existing_methods Whether enabled methods should be replaced with defaults.
	 * @param array|null  $account_data             Stripe account data, if already available.
	 * @return array Details about the application result.
	 */
	private function apply( string $trigger, ?string $mode, bool $direct_debits_only, bool $force_account_data, bool $replace_existing_methods, ?array $account_data ): array {
		$mode = $mode ?? ( WC_Stripe_Mode::is_test() ? 'test' : 'live' );
		if ( ! WC_Stripe_Helper::is_connected( $mode ) ) {
			return [
				'applied'         => false,
				'methods_enabled' => [],
				'methods_skipped' => [],
				'reason'          => 'not_connected',
			];
		}

		$account_data = $account_data ?? $this->get_account_data( $mode, $force_account_data );
		$country_code = strtoupper( (string) ( $account_data['country'] ?? '' ) );
		if ( '' === $country_code ) {
			return [
				'applied'         => false,
				'methods_enabled' => [],
				'methods_skipped' => [],
				'reason'          => 'missing_country',
			];
		}

		$default_payment_method_ids = $this->get_default_payment_method_ids(
			$country_code,
			$this->is_subscriptions_enabled(),
			$trigger
		);

		if ( $direct_debits_only ) {
			$default_payment_method_ids = array_values( array_intersect( $default_payment_method_ids, self::DIRECT_DEBIT_PAYMENT_METHODS ) );
		}

		if ( empty( $default_payment_method_ids ) ) {
			return [
				'applied'         => false,
				'methods_enabled' => [],
				'methods_skipped' => [],
				'reason'          => 'no_defaults',
			];
		}

		if ( WC_Stripe_Payment_Method_Configurations::is_enabled() ) {
			$result = $this->apply_to_payment_method_configuration( $default_payment_method_ids, $trigger, $replace_existing_methods );
			if ( 'pmc_disabled' === ( $result['reason'] ?? '' ) ) {
				$result = $this->apply_to_settings( $default_payment_method_ids, $account_data, $mode, $trigger, $replace_existing_methods );
			}
		} else {
			$result = $this->apply_to_settings( $default_payment_method_ids, $account_data, $mode, $trigger, $replace_existing_methods );
		}

		if ( ! empty( $result['should_record_event'] ) ) {
			$this->record_smart_defaults_applied_event(
				$country_code,
				$result['methods_enabled'],
				$result['methods_skipped'],
				$trigger,
				'test' === $mode
			);
		}

		return [
			'applied'         => ! empty( $result['methods_enabled'] ),
			'methods_enabled' => $result['methods_enabled'],
			'methods_skipped' => $result['methods_skipped'],
			'reason'          => $result['reason'] ?? '',
		];
	}

	/**
	 * Applies defaults through the Stripe Payment Method Configuration API.
	 *
	 * @param string[] $default_payment_method_ids Default payment method IDs.
	 * @param string   $trigger                    Trigger that is applying defaults.
	 * @param bool     $replace_existing_methods   Whether enabled methods should be replaced with defaults.
	 * @return array Result details.
	 */
	private function apply_to_payment_method_configuration( array $default_payment_method_ids, string $trigger, bool $replace_existing_methods ): array {
		$available_payment_method_ids = WC_Stripe_Payment_Method_Configurations::get_available_payment_method_ids();
		$current_enabled_method_ids   = WC_Stripe_Payment_Method_Configurations::get_enabled_payment_method_ids_from_configuration();

		if ( empty( $available_payment_method_ids ) ) {
			return [
				'methods_enabled'     => [],
				'methods_skipped'     => $default_payment_method_ids,
				'should_record_event' => false,
				'reason'              => WC_Stripe_Payment_Method_Configurations::is_enabled() ? 'no_available_payment_methods' : 'pmc_disabled',
			];
		}

		$methods_blocked_by_merchant = [];
		if ( self::TRIGGER_SUBSCRIPTIONS_ACTIVATED === $trigger ) {
			$methods_blocked_by_merchant = array_intersect( $default_payment_method_ids, self::get_explicitly_disabled_payment_method_ids() );
			$default_payment_method_ids  = array_values( array_diff( $default_payment_method_ids, $methods_blocked_by_merchant ) );
		}

		$methods_enabled            = array_values( array_intersect( $default_payment_method_ids, $available_payment_method_ids ) );
		$methods_skipped            = array_values( array_unique( array_merge( array_diff( $default_payment_method_ids, $methods_enabled ), $methods_blocked_by_merchant ) ) );
		$enabled_method_ids_to_save = $replace_existing_methods
			? $methods_enabled
			: array_values( array_unique( array_merge( $current_enabled_method_ids, $methods_enabled ) ) );
		$has_changes                = ! empty( array_diff( $enabled_method_ids_to_save, $current_enabled_method_ids ) )
			|| ! empty( array_diff( $current_enabled_method_ids, $enabled_method_ids_to_save ) );

		if ( $has_changes ) {
			WC_Stripe_Payment_Method_Configurations::update_payment_method_configuration(
				$enabled_method_ids_to_save,
				$available_payment_method_ids
			);
		}

		return [
			'methods_enabled'     => $methods_enabled,
			'methods_skipped'     => $methods_skipped,
			'should_record_event' => self::TRIGGER_ACCOUNT_CONNECTION === $trigger || $has_changes,
		];
	}

	/**
	 * Applies defaults to local settings when the PMC API is unavailable.
	 *
	 * @param string[] $default_payment_method_ids Default payment method IDs.
	 * @param array    $account_data               Stripe account data.
	 * @param string   $mode                       Stripe mode.
	 * @param string   $trigger                    Trigger that is applying defaults.
	 * @param bool     $replace_existing_methods   Whether enabled methods should be replaced with defaults.
	 * @return array Result details.
	 */
	private function apply_to_settings( array $default_payment_method_ids, array $account_data, string $mode, string $trigger, bool $replace_existing_methods ): array {
		$settings                   = WC_Stripe_Helper::get_stripe_settings();
		$current_enabled_method_ids = isset( $settings['upe_checkout_experience_accepted_payments'] ) && is_array( $settings['upe_checkout_experience_accepted_payments'] )
			? $settings['upe_checkout_experience_accepted_payments']
			: [ WC_Stripe_Payment_Methods::CARD ];
		$available_payment_methods  = $this->get_available_payment_method_ids_from_capabilities( $default_payment_method_ids, $account_data, $mode );

		$methods_blocked_by_merchant = [];
		if ( self::TRIGGER_SUBSCRIPTIONS_ACTIVATED === $trigger ) {
			$methods_blocked_by_merchant = array_intersect( $default_payment_method_ids, self::get_explicitly_disabled_payment_method_ids() );
			$default_payment_method_ids  = array_values( array_diff( $default_payment_method_ids, $methods_blocked_by_merchant ) );
		}

		$methods_enabled            = array_values( array_intersect( $default_payment_method_ids, $available_payment_methods ) );
		$methods_skipped            = array_values( array_unique( array_merge( array_diff( $default_payment_method_ids, $methods_enabled ), $methods_blocked_by_merchant ) ) );
		$enabled_method_ids_to_save = $replace_existing_methods
			? $methods_enabled
			: array_values( array_unique( array_merge( $current_enabled_method_ids, $methods_enabled ) ) );
		$new_methods                = array_values( array_diff( $methods_enabled, $current_enabled_method_ids ) );
		$removed_methods            = array_values( array_diff( $current_enabled_method_ids, $enabled_method_ids_to_save ) );

		if ( ! empty( $methods_enabled ) ) {
			$upe_methods_to_enable = array_values(
				array_diff(
					$enabled_method_ids_to_save,
					[ WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ]
				)
			);

			$settings['upe_checkout_experience_accepted_payments'] = $upe_methods_to_enable;

			if (
				in_array( WC_Stripe_Payment_Methods::APPLE_PAY, $methods_enabled, true ) ||
				in_array( WC_Stripe_Payment_Methods::GOOGLE_PAY, $methods_enabled, true )
			) {
				$settings['express_checkout'] = 'yes';
			}

			WC_Stripe_Helper::update_main_stripe_settings( $settings );

			$new_upe_methods     = array_diff( $new_methods, [ WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ] );
			$removed_upe_methods = array_diff( $removed_methods, [ WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ] );
			if ( ! empty( $new_upe_methods ) || ! empty( $removed_upe_methods ) ) {
				WC_Stripe_Payment_Method_Configurations::record_payment_method_settings_event( $new_upe_methods, $removed_upe_methods );
			}
		}

		return [
			'methods_enabled'     => $methods_enabled,
			'methods_skipped'     => $methods_skipped,
			'should_record_event' => self::TRIGGER_ACCOUNT_CONNECTION === $trigger || ! empty( $new_methods ) || ! empty( $removed_methods ),
		];
	}

	/**
	 * Returns whether saved payment method choices already exist.
	 *
	 * @param array $settings Stripe settings.
	 * @return bool
	 */
	private function has_existing_payment_method_configuration( array $settings ): bool {
		if ( array_key_exists( 'upe_checkout_experience_accepted_payments', $settings ) ) {
			return true;
		}

		return ! empty( $settings['pmc_enabled'] );
	}

	/**
	 * Returns the country defaults map after customizations.
	 *
	 * @return array<string,string[]>
	 */
	private function get_country_payment_method_map(): array {
		/**
		 * Filters the country-aware smart default payment method map.
		 *
		 * @param array<string,string[]> $country_payment_method_map Map of country codes to payment method IDs.
		 */
		$country_payment_method_map = apply_filters( 'wc_stripe_smart_default_payment_method_map', self::COUNTRY_PAYMENT_METHODS );

		if ( ! is_array( $country_payment_method_map ) ) {
			return self::COUNTRY_PAYMENT_METHODS;
		}

		$normalized_map = [];
		foreach ( $country_payment_method_map as $country_code => $payment_method_ids ) {
			if ( ! is_string( $country_code ) || ! is_array( $payment_method_ids ) ) {
				continue;
			}
			$normalized_map[ strtoupper( $country_code ) ] = self::normalize_payment_method_ids( $payment_method_ids );
		}

		return $normalized_map;
	}

	/**
	 * Returns smart defaults that the plugin knows how to manage.
	 *
	 * @return string[]
	 */
	private function get_supported_default_payment_method_ids(): array {
		return array_values(
			array_unique(
				array_merge(
					array_keys( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS ),
					[
						WC_Stripe_Payment_Methods::APPLE_PAY,
						WC_Stripe_Payment_Methods::GOOGLE_PAY,
					]
				)
			)
		);
	}

	/**
	 * Filters defaults against account capabilities for the non-PMC fallback path.
	 *
	 * @param string[] $payment_method_ids Payment method IDs.
	 * @param array    $account_data       Stripe account data.
	 * @param string   $mode               Stripe mode.
	 * @return string[]
	 */
	private function get_available_payment_method_ids_from_capabilities( array $payment_method_ids, array $account_data, string $mode ): array {
		if ( empty( $account_data['capabilities'] ) || ! is_array( $account_data['capabilities'] ) ) {
			return [];
		}

		$available_payment_method_ids = [];
		foreach ( $payment_method_ids as $payment_method_id ) {
			$capability_payment_method_id = in_array( $payment_method_id, [ WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ], true )
				? WC_Stripe_Payment_Methods::CARD
				: $payment_method_id;

			if ( $this->is_payment_method_capability_available( $capability_payment_method_id, $account_data['capabilities'], 'test' === $mode ) ) {
				$available_payment_method_ids[] = $payment_method_id;
			}
		}

		return $available_payment_method_ids;
	}

	/**
	 * Returns whether a payment method capability is available.
	 *
	 * @param string $payment_method_id Payment method ID.
	 * @param array  $capabilities      Stripe account capabilities.
	 * @param bool   $is_test_mode      Whether Stripe is in test mode.
	 * @return bool
	 */
	private function is_payment_method_capability_available( string $payment_method_id, array $capabilities, bool $is_test_mode ): bool {
		$capability_id = WC_Stripe_Helper::get_payment_method_capability_id( $payment_method_id );

		if ( isset( $capabilities[ $capability_id ] ) ) {
			return $is_test_mode || 'active' === $capabilities[ $capability_id ];
		}

		if ( isset( $capabilities[ $payment_method_id ] ) ) {
			return $is_test_mode || 'active' === $capabilities[ $payment_method_id ];
		}

		if ( WC_Stripe_Payment_Methods::CARD === $payment_method_id && isset( $capabilities['legacy_payments'] ) ) {
			return $is_test_mode || 'active' === $capabilities['legacy_payments'];
		}

		return false;
	}

	/**
	 * Returns connected Stripe account data.
	 *
	 * @param string $mode          Stripe mode.
	 * @param bool   $force_refresh Whether the cache should be bypassed.
	 * @return array
	 */
	private function get_account_data( string $mode, bool $force_refresh ): array {
		$account = WC_Stripe::get_instance()->account;
		if ( ! is_callable( [ $account, 'get_cached_account_data' ] ) ) {
			return [];
		}

		$account_data = $account->get_cached_account_data( $mode, $force_refresh );

		return is_array( $account_data ) ? $account_data : [];
	}

	/**
	 * Returns whether WooCommerce Subscriptions is currently active.
	 *
	 * @return bool
	 */
	private function is_subscriptions_enabled(): bool {
		$is_subscriptions_enabled = WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled();

		/**
		 * Filters whether smart defaults should treat WooCommerce Subscriptions as active.
		 *
		 * @param bool $is_subscriptions_enabled Whether WooCommerce Subscriptions is active.
		 */
		return (bool) apply_filters( 'wc_stripe_smart_defaults_is_subscriptions_active', $is_subscriptions_enabled );
	}

	/**
	 * Records the smart defaults telemetry event.
	 *
	 * @param string   $country_code            Stripe account country code.
	 * @param string[] $enabled_payment_methods Default methods enabled or already enabled.
	 * @param string[] $skipped_payment_methods Default methods skipped.
	 * @param string   $trigger                 Trigger that applied defaults.
	 * @param bool     $is_test_mode            Whether Stripe is in test mode.
	 * @return void
	 */
	private function record_smart_defaults_applied_event( string $country_code, array $enabled_payment_methods, array $skipped_payment_methods, string $trigger, bool $is_test_mode ): void {
		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}

		wc_admin_record_tracks_event(
			'wcstripe_smart_defaults_applied',
			[
				'country'         => $country_code,
				'methods_enabled' => array_values( $enabled_payment_methods ),
				'methods_skipped' => array_values( $skipped_payment_methods ),
				'trigger'         => $trigger,
				'is_test_mode'    => $is_test_mode,
			]
		);
	}

	/**
	 * Returns methods the merchant explicitly disabled.
	 *
	 * @return string[]
	 */
	private static function get_explicitly_disabled_payment_method_ids(): array {
		$payment_method_ids = get_option( self::EXPLICITLY_DISABLED_OPTION, [] );

		return is_array( $payment_method_ids ) ? self::normalize_payment_method_ids( $payment_method_ids ) : [];
	}

	/**
	 * Normalizes payment method IDs.
	 *
	 * @param array $payment_method_ids Payment method IDs.
	 * @return string[]
	 */
	private static function normalize_payment_method_ids( array $payment_method_ids ): array {
		return array_values(
			array_unique(
				array_filter(
					$payment_method_ids,
					function ( $payment_method_id ) {
						return is_string( $payment_method_id ) && '' !== $payment_method_id;
					}
				)
			)
		);
	}
}
