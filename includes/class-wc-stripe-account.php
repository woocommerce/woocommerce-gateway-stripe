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
		delete_transient( self::LIVE_ACCOUNT_OPTION );
		delete_transient( self::TEST_ACCOUNT_OPTION );
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
		$payout_settings     = isset( $account['settings']['payouts'] ) ? $account['settings']['payouts'] : [];

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
		$account          = json_decode( json_encode( $this->get_cached_account_data() ), true );
		$settings_options = get_option( 'woocommerce_stripe_settings', [] );
		$mode             = isset( $settings_options['testmode'] ) && 'yes' === $settings_options['testmode'] ? 'test' : 'live';

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
			'mode'            => $mode,
		];
	}
}
