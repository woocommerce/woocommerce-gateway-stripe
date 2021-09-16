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

	const ACCOUNT_OPTION = 'wcstripe_account_data';
	const ACCOUNT_API    = 'account';

	public function __construct() {
		self::cache_account();
	}

	/**
	 * Gets and caches the data for the account connected to this site.
	 *
	 * @return array Account data or empty if failed to retrieve account data.
	 */
	public static function get_cached_account_data() {
		if ( ! woocommerce_gateway_stripe()->connect->is_connected() ) {
			return [];
		}

		$account = self::read_account_from_cache();

		if ( ! empty( $account ) ) {
			return $account;
		}

		$account = self::cache_account();
		return $account;
	}

	/**
	 * Read the account from the WP option we cache it in.
	 *
	 * @return array
	 */
	private static function read_account_from_cache() {
		$account_cache = get_option( self::ACCOUNT_OPTION );

		if ( false === $account_cache || ! isset( $account_cache['account'] ) || ! isset( $account_cache['expires'] ) ) {
			// No option found or the data isn't in the shape we expect.
			return [];
		}

		// Set $account to empty if the cache has expired, triggering another fetch.
		if ( $account_cache['expires'] < time() ) {
			return [];
		}

		// We have fresh account data in the cache, so return it.
		return $account_cache['account'];
	}

	/**
	 * Caches account data for a period of time.
	 */
	private static function cache_account() {
		$expiration = 2 * HOUR_IN_SECONDS;

		try {
			$account = WC_Stripe_API::request(
				[],
				self::ACCOUNT_API,
				'GET'
			);
		} catch ( WC_Stripe_Exception $e ) {
			return [];
		}

		// Add the account data and expiry time to the array we're caching.
		$account_cache            = [];
		$account_cache['account'] = $account;
		$account_cache['expires'] = time() + $expiration;

		// Create or update the account option cache.
		if ( false === get_option( self::ACCOUNT_OPTION ) ) {
			add_option( self::ACCOUNT_OPTION, $account_cache, '', 'no' );
		} else {
			update_option( self::ACCOUNT_OPTION, $account_cache, 'no' );
		}

		return $account;
	}

	/**
	 * Refetches account data and returns the fresh data.
	 *
	 * @return array Either the new account data or empty if unavailable.
	 */
	public static function refresh_account_data() {
		self::clear_cache();
		return self::get_cached_account_data();
	}

	/**
	 * Wipes the account data option.
	 */
	public static function clear_cache() {
		delete_option( self::ACCOUNT_OPTION );
	}

	/**
	 * Indicates whether card payments are enabled for this (Stripe) account.
	 *
	 * @return bool True if account can accept card payments, false otherwise.
	 */
	private static function are_payments_enabled( $account ) {
		$capabilities = $account['capabilities'] ? $account['capabilities'] : [];

		if ( empty( $capabilities ) ) {
			return false;
		}

		return isset( $capabilities['card_payments'] ) && 'active' === $capabilities['card_payments'];
	}

	/**
	 * Indicates if payouts are enabled for the (Stripe) account and if there is deposits schedule set.
	 *
	 * @return bool Returns 'false' if payouts aren't enabled for the (Stripe) account or of there is no
	 * deposits schedule set.
	 */
	private static function are_deposits_enabled( $account ) {
		$are_payouts_enabled = $account['payouts_enabled'] || false;
		$payout_settings     = $account['settings']['payouts'] ? $account['settings']['payouts'] : [];

		if ( ! $are_payouts_enabled || ! isset( $payout_settings['schedule']['interval'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets the acoount's status from the acount data that is connected to this site.
	 *
	 * @return array Account status data or empty if failed to retrieve account data.
	 */
	public static function get_account_status() {
		$account = json_decode( json_encode( self::get_cached_account_data() ), true );

		if ( empty( $account ) ) {
			return [];
		}
		return [
			'email'           => isset( $account['email'] ) ? $account['email'] : '',
			'paymentsEnabled' => self::are_payments_enabled( $account ),
			'depositsEnabled' => self::are_deposits_enabled( $account ),
			'accountLink'     => 'https://stripe.com/support',
		];
	}
}
