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

	/**
	 * Constructor
	 *
	 * @param WC_Stripe_Connect $connect Stripe connect
	 */
	public function __construct( WC_Stripe_Connect $connect ) {
		$this->connect = $connect;
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
	 * @return array
	 */
	private function read_account_from_cache() {
		$account_cache = json_decode( json_encode( get_transient( self::ACCOUNT_OPTION ) ), true );
		$settings_options = get_option( 'woocommerce_stripe_settings', [] );
		$mode             = isset( $settings_options['testmode'] ) && 'yes' === $settings_options['testmode'] ? 'test' : 'live';

		// No data found in transient
		if ( false === $account_cache ) {
			return [];
		}

		// Current account mode does not match with the cached mode
		if ( $account_cache['mode'] !== $mode ) {
			return [];
		}

		// We have fresh account data in the cache, so return it.
		return $account_cache;
	}

	/**
	 * Caches account data for a period of time.
	 */
	private function cache_account() {
		$expiration       = 2 * HOUR_IN_SECONDS;
		$settings_options = get_option( 'woocommerce_stripe_settings', [] );
		$mode             = isset( $settings_options['testmode'] ) && 'yes' === $settings_options['testmode'] ? 'test' : 'live';

		try {
			$account       = WC_Stripe_API::request(
				[],
				self::ACCOUNT_API,
				'GET'
			);
			$account->mode = $mode;
		} catch ( WC_Stripe_Exception $e ) {
			return [];
		}

		// Add the account data and mode to the array we're caching.
		$account_cache = $account;

		// Create or update the account option cache.
		set_transient( self::ACCOUNT_OPTION, $account_cache, $expiration );

		return $account;
	}

	/**
	 * Refetches account data and returns the fresh data.
	 *
	 * @return array Either the new account data or empty if unavailable.
	 */
	public function refresh_account_data() {
		$this->clear_cache();
		return $this->get_cached_account_data();
	}

	/**
	 * Wipes the account data option.
	 */
	public function clear_cache() {
		delete_transient( self::ACCOUNT_OPTION );
	}

	/**
	 * Indicates whether card payments are enabled for this (Stripe) account.
	 *
	 * @return bool True if account can accept card payments, false otherwise.
	 */
	private function are_payments_enabled( $account ) {
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
	private function are_deposits_enabled( $account ) {
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
	public function get_account_status() {
		$account = json_decode( json_encode( $this->get_cached_account_data() ), true );

		if ( empty( $account ) ) {
			return [
				'error' => true,
			];
		}

		return [
			'email'           => isset( $account['email'] ) ? $account['email'] : '',
			'paymentsEnabled' => $this->are_payments_enabled( $account ),
			'depositsEnabled' => $this->are_deposits_enabled( $account ),
			'accountLink'     => 'https://stripe.com/support',
			'mode'            => $account['mode'],
		];
	}
}
