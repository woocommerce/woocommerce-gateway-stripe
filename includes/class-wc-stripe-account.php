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

	/**
	 * The Account Data cache key.
	 *
	 * @var string
	 */
	public const ACCOUNT_CACHE_KEY = 'account_data';

	/**
	 * The Account Data cache expiration (TTL).
	 *
	 * @var int
	 */
	public const ACCOUNT_CACHE_EXPIRATION = 2 * HOUR_IN_SECONDS;

	/**
	 * The transient key that was previously used to cache the live mode webhook status.
	 *
	 * @deprecated 11.0.0
	 */
	public const LIVE_WEBHOOK_STATUS_OPTION = 'wcstripe_webhook_status_live';

	/**
	 * The transient key that was previously used to cache the test mode webhook status.
	 *
	 * @deprecated 11.0.0
	 */
	public const TEST_WEBHOOK_STATUS_OPTION = 'wcstripe_webhook_status_test';

	public const STATUS_COMPLETE        = 'complete';
	public const STATUS_NO_ACCOUNT      = 'NOACCOUNT';
	public const STATUS_RESTRICTED_SOON = 'restricted_soon';
	public const STATUS_RESTRICTED      = 'restricted';

	/**
	 * Retained for callers that read webhook events from the account class.
	 *
	 * @deprecated x.x.x Use WC_Stripe_Webhook_Settings::WEBHOOK_EVENTS instead.
	 */
	public const WEBHOOK_EVENTS = WC_Stripe_Webhook_Settings::WEBHOOK_EVENTS;

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
	 * @param string $stripe_api Stripe API class
	 */
	public function __construct( WC_Stripe_Connect $connect, $stripe_api ) {
		$this->connect    = $connect;
		$this->stripe_api = $stripe_api;
	}

	/**
	 * Gets and caches the data for the account connected to this site.
	 *
	 * @param string|null $mode          Optional. The mode to get the account data for. 'live' or 'test'. Default will use the current mode.
	 * @param bool        $force_refresh Optional. Whether to fetch the account data from Stripe instead of using the cache. Default is false.
	 * @return array Account data or empty if failed to retrieve account data.
	 */
	public function get_cached_account_data( $mode = null, bool $force_refresh = false ) {
		if ( ! in_array( $mode, [ 'test', 'live' ], true ) ) {
			$mode = WC_Stripe_Mode::is_test() ? 'test' : 'live';
		}

		if ( ! $this->connect->is_connected( $mode ) ) {
			return [];
		}

		if ( ! $force_refresh ) {
			$account = $this->read_account_from_cache( $mode );

			if ( ! empty( $account ) ) {
				return $account;
			}
		}

		return $this->cache_account( $mode );
	}

	/**
	 * Read the account from the WP option we cache it in.
	 *
	 * @param string|null $mode Optional. The mode to get the account data for. 'live' or 'test'. Default will use the current mode.
	 * @return array empty when no data found, otherwise returns the cached data
	 */
	private function read_account_from_cache( $mode = null ) {
		$account_cache = WC_Stripe_Database_Cache::get_with_mode( self::ACCOUNT_CACHE_KEY, $mode );

		return is_array( $account_cache ) ? $account_cache : [];
	}

	/**
	 * Caches account data for a period of time.
	 *
	 * @param string|null $mode Optional. The mode to get the account data for. 'live' or 'test'. Default will use the current mode.
	 */
	private function cache_account( $mode = null ) {
		WC_Stripe_API::set_secret_key_for_mode( $mode );

		// need call_user_func() as ( $this->stripe_api )::retrieve this syntax is not supported in php < 5.2
		$account = call_user_func( [ $this->stripe_api, 'retrieve' ], 'account' );

		// Restore the secret key to the original value.
		WC_Stripe_API::set_secret_key_for_mode();

		if ( is_wp_error( $account ) || isset( $account->error->message ) ) {
			return [];
		}

		// Convert the account data to an array.
		$account_cache = json_decode( wp_json_encode( $account ), true );

		// Create or update the account data cache.
		WC_Stripe_Database_Cache::set_with_mode( self::ACCOUNT_CACHE_KEY, $account_cache, self::ACCOUNT_CACHE_EXPIRATION, $mode );

		return $account_cache;
	}

	/**
	 * Re-reads the account data from Stripe and drops the cached webhook status.
	 *
	 * A failed fetch (network error, Stripe outage) leaves the previously cached account data in
	 * place so the UI keeps rendering the last known account; an invalid API key does not, so the
	 * reconnect prompt can still surface.
	 *
	 * @return array Account data or empty if failed to retrieve account data.
	 */
	public function refresh_cache() {
		$this->clear_webhook_status_cache();

		return $this->get_cached_account_data( null, true );
	}

	/**
	 * Wipes the account data option.
	 */
	public function clear_cache() {
		WC_Stripe_Database_Cache::delete_with_mode( self::ACCOUNT_CACHE_KEY, 'live' );
		WC_Stripe_Database_Cache::delete_with_mode( self::ACCOUNT_CACHE_KEY, 'test' );

		$this->clear_webhook_status_cache();
	}

	/**
	 * Wipes the cached webhook status for both modes.
	 */
	private function clear_webhook_status_cache(): void {
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Webhook_Settings::WEBHOOK_STATUS_CACHE_KEY, 'live' );
		WC_Stripe_Database_Cache::delete_with_mode( WC_Stripe_Webhook_Settings::WEBHOOK_STATUS_CACHE_KEY, 'test' );
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
