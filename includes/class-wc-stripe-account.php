<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Account class.
 *
 * Communicates with Stripe API.
 */
class WC_Stripe_Account {

	const LIVE_ACCOUNT_OPTION = 'wcstripe_account_data_live';
	const TEST_ACCOUNT_OPTION = 'wcstripe_account_data_test';

	const STATUS_COMPLETE        = 'complete';
	const STATUS_NO_ACCOUNT      = 'NOACCOUNT';
	const STATUS_RESTRICTED_SOON = 'restricted_soon';
	const STATUS_RESTRICTED      = 'restricted';

	/**
	 * The Stripe connect instance.
	 *
	 * @var WC_Stripe_Connect
	 */
	private $connect;

	/**
	 * The Stripe API class to access the static method.
	 *
	 * @var WC_Stripe_API
	 */
	private $stripe_api;

	/**
	 * Constructor
	 *
	 * @param WC_Stripe_Connect $connect Stripe connect
	 * @param $stripe_api Stripe API class
	 */
	public function __construct( WC_Stripe_Connect $connect, $stripe_api ) {
		$this->connect    = $connect;
		$this->stripe_api = $stripe_api;
	}

	/**
	 * Gets and caches the data for the account connected to this site.
	 *
	 * @return array Account data or empty if failed to retrieve account data.
	 */
	public function get_cached_account_data() {
		if ( ! $this->connect->is_connected() ) {
			return [];
		}

		$account = $this->read_account_from_cache();

		if ( ! empty( $account ) ) {
			return $account;
		}

		return $this->cache_account();
	}

	/**
	 * Read the account from the WP option we cache it in.
	 *
	 * @return array empty when no data found in transient, otherwise returns cached data
	 */
	private function read_account_from_cache() {
		$account_cache = json_decode( wp_json_encode( get_transient( $this->get_transient_key() ) ), true );

		return false === $account_cache ? [] : $account_cache;
	}

	/**
	 * Caches account data for a period of time.
	 */
	private function cache_account() {
		$expiration = 2 * HOUR_IN_SECONDS;

		// need call_user_func() as (  $this->stripe_api )::retrieve this syntax is not supported in php < 5.2
		$account = call_user_func( [ $this->stripe_api, 'retrieve' ], 'account' );

		if ( is_wp_error( $account ) || isset( $account->error->message ) ) {
			return [];
		}

		// Add the account data and mode to the array we're caching.
		$account_cache = $account;

		// Create or update the account option cache.
		set_transient( $this->get_transient_key(), $account_cache, $expiration );

		return json_decode( wp_json_encode( $account ), true );
	}

	/**
	 * Checks Stripe connection mode if it is test mode or live mode
	 *
	 * @return string Transient key of test mode when testmode is enabled, otherwise returns the key of live mode.
	 */
	private function get_transient_key() {
		$settings_options = get_option( 'woocommerce_stripe_settings', [] );
		$key              = isset( $settings_options['testmode'] ) && 'yes' === $settings_options['testmode'] ? self::TEST_ACCOUNT_OPTION : self::LIVE_ACCOUNT_OPTION;

		return $key;
	}

	/**
	 * Wipes the account data option.
	 */
	public function clear_cache() {
		delete_transient( self::LIVE_ACCOUNT_OPTION );
		delete_transient( self::TEST_ACCOUNT_OPTION );
	}

	/**
	 * Indicates whether the account has any pending requirements that could cause the account to be restricted.
	 *
	 * @return bool True if account has pending restrictions, false otherwise.
	 */
	public function has_pending_requirements() {
		$requirements = $this->get_cached_account_data()['requirements'] ?? [];

		if ( empty( $requirements ) ) {
			return false;
		}

		$currently_due  = $requirements['currently_due'] ?? [];
		$past_due       = $requirements['past_due'] ?? [];
		$eventually_due = $requirements['eventually_due'] ?? [];

		return (
			! empty( $currently_due ) ||
			! empty( $past_due ) ||
			! empty( $eventually_due )
		);
	}

	/**
	 * Indicates whether the account has any overdue requirements that could cause the account to be restricted.
	 *
	 * @return bool True if account has overdue restrictions, false otherwise.
	 */
	public function has_overdue_requirements() {
		$requirements = $this->get_cached_account_data()['requirements'] ?? [];
		return ! empty( $requirements['past_due'] );
	}

	/**
	 * Returns the account's Stripe status (completed, restricted_soon, restricted).
	 *
	 * @return string The account's status.
	 */
	public function get_account_status() {
		$account = $this->get_cached_account_data();
		if ( empty( $account ) ) {
			return self::STATUS_NO_ACCOUNT;
		}

		$requirements = $account['requirements'] ?? [];
		if ( empty( $requirements ) ) {
			return self::STATUS_COMPLETE;
		}

		if ( isset( $requirements['disabled_reason'] ) && is_string( $requirements['disabled_reason'] ) ) {
			// If an account has been rejected, then disabled_reason will have a value like "rejected.<reason>"
			if ( strpos( $requirements['disabled_reason'], 'rejected' ) === 0 ) {
				return $requirements['disabled_reason'];
			}
			// If disabled_reason is not empty, then the account has been restricted.
			if ( ! empty( $requirements['disabled_reason'] ) ) {
				return self::STATUS_RESTRICTED;
			}
		}
		// Should be covered by the non-empty disabled_reason, but past due requirements also restrict the account.
		if ( isset( $requirements['past_due'] ) && ! empty( $requirements['past_due'] ) ) {
			return self::STATUS_RESTRICTED;
		}
		// Any other pending requirments indicate restricted soon.
		if ( $this->has_pending_requirements() ) {
			return self::STATUS_RESTRICTED_SOON;
		}

		return self::STATUS_COMPLETE;
	}

	/**
	 * Returns the Stripe's account supported currencies.
	 *
	 * @return string[] Supported store currencies.
	 */
	public function get_supported_store_currencies(): array {
		$account = $this->get_cached_account_data();
		if ( ! isset( $account['external_accounts']['data'] ) ) {
			return [ $account['default_currency'] ?? get_woocommerce_currency() ];
		}

		$currencies = array_filter( array_column( $account['external_accounts']['data'], 'currency' ) );
		return array_values( array_unique( $currencies ) );
	}

	/**
	 * Gets the account default currency.
	 *
	 * @return string Currency code in lowercase.
	 */
	public function get_account_default_currency(): string {
		$account = $this->get_cached_account_data();

		return isset( $account['default_currency'] ) ? strtolower( $account['default_currency'] ) : '';
	}

	/**
	 * Returns the Stripe account's card payment bank statement prefix.
	 *
	 * Merchants can set this in their Stripe settings at: https://dashboard.stripe.com/settings/public.
	 *
	 * @return string The Stripe Accounts card statement prefix.
	 */
	public function get_card_statement_prefix() {
		$account = $this->get_cached_account_data();
		return $account['settings']['card_payments']['statement_descriptor_prefix'] ?? '';
	}

	/**
	 * Gets the account country.
	 *
	 * @return string Country.
	 */
	public function get_account_country() {
		$account = $this->get_cached_account_data();
		return $account['country'] ?? 'US';
	}
}
