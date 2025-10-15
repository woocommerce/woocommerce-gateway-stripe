<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class to handle subscriptions.
 */
class WC_Stripe_Subscriptions_Helper {
	/**
	 * Stripe customer page base URL.
	 *
	 * @var string
	 */
	public const STRIPE_CUSTOMER_PAGE_BASE_URL = 'https://dashboard.stripe.com/customers/';

	/**
	 * Transient key for detached subscriptions.
	 *
	 * @var string
	 */
	private const DETACHED_SUBSCRIPTIONS_CACHE_PREFIX = 'detached_subscriptions';

	/**
	 * Maximum number of subscriptions to load per page.
	 *
	 * @var int
	 */
	private const MAX_SUBSCRIPTIONS_PER_PAGE = 50;

	/**
	 * Fallback maximum execution time in seconds.
	 *
	 * @var int
	 */
	private const MAX_EXECUTION_TIME_FALLBACK = 30;

	/**
	 * Checks if subscriptions are enabled on the site.
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 */
	public static function is_subscriptions_enabled() {
		return class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscription' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' );
	}

	/**
	 * Loads up to 50 subscriptions, and attempts to return those that are detached from the customer.
	 *
	 * @return array
	 *
	 * @deprecated 9.6.0 This method is no longer used and will be removed in a future version.
	 */
	public static function get_some_detached_subscriptions() {
		_deprecated_function( __METHOD__, '9.6.0' );
		return self::get_detached_subscriptions( 50 );
	}

	/**
	 * Loads all active subscriptions renewing in less than a month, and attempts to return those that are detached from the customer.
	 *
	 * @param int $limit The maximum number of subscriptions to retrieve. Use -1 for no limit (default).
	 * @return array
	 */
	public static function get_detached_subscriptions( $limit = -1 ) {
		// Check if we have a cached result.
		$cached_subscriptions = WC_Stripe_Database_Cache::get( self::DETACHED_SUBSCRIPTIONS_CACHE_PREFIX . '_' . $limit );
		if ( is_array( $cached_subscriptions ) ) {
			return $cached_subscriptions;
		}

		$subscriptions     = [];
		$num_subscriptions = 0;
		$page              = 1;
		$per_page          = self::MAX_SUBSCRIPTIONS_PER_PAGE;
		$start_time        = time();

		// Defaults maximum execution time to server's `max_execution_time` (when available, or 30 if not) minus 5 seconds.
		$default_max_time = ( ini_get( 'max_execution_time' ) ? ini_get( 'max_execution_time' ) : self::MAX_EXECUTION_TIME_FALLBACK ) - 5;

		/**
		 * Filter the maximum time allowed for fetching detached subscriptions.
		 *
		 * @since 9.7.0
		 * @param int $max_time The maximum time allowed in seconds. Default is server's `max_execution_time` (when available, or 30 if not) minus 5 seconds.
		 */
		$max_time = apply_filters( 'wc_stripe_detached_subscriptions_maximum_time', $default_max_time );

		do {
			if ( ( time() - $start_time ) > $max_time ) {
				// If we have been running for more than the default limit, stop to avoid long execution times.
				WC_Stripe_Logger::log(
					sprintf(
						/* translators: %d is the maximum time allowed for fetching detached subscriptions */
						__( 'Stopped fetching detached subscriptions before the %d seconds limit for safety.', 'woocommerce-gateway-stripe' ),
						$max_time
					)
				);
				break;
			}

			$batch             = wcs_get_subscriptions(
				[
					'subscriptions_per_page' => $per_page,
					'paged'                  => $page,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'subscription_status'    => [ 'active' ],
				]
			);
			$num_batch         = count( $batch );
			$subscriptions     = array_merge( $subscriptions, $batch );
			$num_subscriptions = count( $subscriptions );
			++$page;
		} while ( $num_batch === $per_page && ( -1 === $limit || $num_subscriptions < $limit ) );

		if ( -1 !== $limit && $num_subscriptions > $limit ) {
			$subscriptions = array_slice( $subscriptions, 0, $limit );
		}

		$detached_subscriptions = [];
		foreach ( $subscriptions as $subscription ) {
			if ( ! $subscription instanceof WC_Subscription ) {
				continue;
			}

			// Filter subscriptions not renewing in the next month
			if ( $subscription->get_time( 'next_payment' ) > ( time() + MONTH_IN_SECONDS + DAY_IN_SECONDS ) ) {
				continue;
			}

			if ( self::is_subscription_payment_method_detached( $subscription ) ) {
				$detached_subscriptions[] = self::get_detached_payment_data_from_subscription( $subscription );
			}
		}

		// Cache the result for a day.
		WC_Stripe_Database_Cache::set( self::DETACHED_SUBSCRIPTIONS_CACHE_PREFIX . '_' . $limit, $detached_subscriptions, DAY_IN_SECONDS );

		return $detached_subscriptions;
	}

	/**
	 * Checks if a subscription's payment method is detached from the customer.
	 *
	 * @param $subscription WC_Subscription The subscription object to check.
	 * @return bool True if the payment method is detached, false otherwise.
	 */
	public static function is_subscription_payment_method_detached( $subscription ) {
		if ( ! $subscription instanceof WC_Subscription ) {
			return false;
		}

		if ( ! WC_Stripe_Order_Helper::get_instance()->is_stripe_gateway_order( $subscription ) ) {
			// If the payment method is not a Stripe method, we don't need to check further.
			return false;
		}

		$source_id = $subscription->get_meta( '_stripe_source_id' );
		if ( ! $source_id ) {
			return false;
		}

		$payment_method = WC_Stripe_Database_Cache::get( 'payment_method_for_source_' . $source_id );
		if ( ! $payment_method ) {
			$payment_method = WC_Stripe_API::get_payment_method( $source_id );
			if ( is_wp_error( $payment_method ) || isset( $payment_method->error ) ) {
				$error_message = is_wp_error( $payment_method ) ? $payment_method->get_error_message() : ( $payment_method->error->message ?? 'Unknown error.' );
				// If we can't retrieve the payment method, assume it's detached.
				WC_Stripe_Logger::error(
					sprintf(
					/* translators: %1$s is the subscription ID, %2$s is the error message */
						__( 'Error retrieving payment method for subscription %1$s: %2$s', 'woocommerce-gateway-stripe' ),
						$subscription->get_id(),
						$error_message
					)
				);
				return true;
			}

			WC_Stripe_Database_Cache::set( 'payment_method_for_source_' . $source_id, $payment_method, HOUR_IN_SECONDS );
		}

		if ( ! empty( $payment_method->customer ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns boolean on whether manual renewal is required for the subscriptions of this store.
	 *
	 * @since 9.6.0
	 *
	 * @return bool
	 */
	public static function is_manual_renewal_required() {
		if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
			return function_exists( 'wcs_is_manual_renewal_required' ) && wcs_is_manual_renewal_required();
		}
		return false;
	}

	/**
	 * Returns boolean on whether manual renewal is enabled for the subscriptions of this store.
	 *
	 * @since 9.6.0
	 *
	 * @return bool
	 */
	public static function is_manual_renewal_enabled() {
		if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
			return function_exists( 'wcs_is_manual_renewal_enabled' ) && wcs_is_manual_renewal_enabled();
		}
		return false;
	}

	/**
	 * Extracts data from a subscription object for detached subscriptions.
	 *
	 * @param WC_Subscription $subscription The subscription object to extract data from.
	 * @return array
	 */
	public static function get_detached_payment_data_from_subscription( $subscription ) {
		return [
			'id'                        => $subscription->get_id(),
			'customer_id'               => $subscription->get_meta( '_stripe_customer_id' ),
			'change_payment_method_url' => $subscription->get_change_payment_method_url(),
		];
	}

	/**
	 * Builds a string containing messages about subscriptions that are detached from the customer.
	 *
	 * @param $subscriptions array An array of subscriptions that are detached from the customer.
	 * @return string A string containing the messages to be displayed in the admin interface.
	 */
	public static function build_subscriptions_detached_messages( $subscriptions = [] ) {
		if ( empty( $subscriptions ) || ! is_array( $subscriptions ) ) {
			return '';
		}

		$detached_messages = '';
		foreach ( $subscriptions as $subscription ) {
			$detached_messages .= self::build_subscription_detached_message( $subscription );
		}

		$intro_message = sprintf(
			wp_kses(
			/* translators: %s: subscriptions count */
				_n(
					'%s subscription is missing the payment method, <strong>preventing renewals</strong>. ',
					'%s subscriptions are missing payment methods, <strong>preventing renewals</strong>. ',
					count( $subscriptions ),
					'woocommerce-gateway-stripe'
				),
				[ 'strong' => [] ]
			),
			count( $subscriptions )
		);
		$intro_message .= esc_html__( 'To fix this, either:', 'woocommerce-gateway-stripe' ) . '<br />';
		$intro_message .= esc_html__( '1) Share the payment method page link with the customer to update it, or', 'woocommerce-gateway-stripe' ) . '<br />';
		$intro_message .= esc_html__( "2) Manually update the payment method in the subscription's billing details using a valid payment method from the customer's Stripe account. ", 'woocommerce-gateway-stripe' );
		$intro_message .= esc_html__( 'Below are the affected subscriptions and their update links:', 'woocommerce-gateway-stripe' ) . '<br />';
		return $intro_message . $detached_messages;
	}

	/**
	 * Builds a message for a single subscription that is detached from the customer.
	 *
	 * @param $subscription array An array containing the (single) subscription details.
	 * @return string
	 */
	public static function build_subscription_detached_message( $subscription ) {
		$customer_payment_method_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $subscription['change_payment_method_url'] ),
			esc_html(
			/* translators: this is a text for a link pointing to the customer's payment method page */
				__( 'Payment method page &rarr;', 'woocommerce-gateway-stripe' )
			)
		);
		$customer_stripe_page = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::STRIPE_CUSTOMER_PAGE_BASE_URL . $subscription['customer_id'] ),
			esc_html(
			/* translators: this is a text for a link pointing to the customer's page on Stripe */
				__( 'Stripe customer page &rarr;', 'woocommerce-gateway-stripe' )
			)
		);
		return sprintf(
		/* translators: %1$s is the subscription ID. %2$s is a customer payment method page. %3$s is the customer's page on Stripe */
			__( '#%1$s: %2$s | %3$s<br/>', 'woocommerce-gateway-stripe' ),
			$subscription['id'],
			$customer_payment_method_link,
			$customer_stripe_page
		);
	}

	/**
	 * Helper function to get and temporarily cache the payment method details for a customer and payment method ID.
	 *
	 * @param string $stripe_customer_id The Stripe customer ID.
	 * @param string $payment_method_id  The Stripe payment method ID. This may be a source ID or a payment method ID.
	 * @return object|null The payment method details or null if the payment method is not found.
	 */
	public static function get_subscription_payment_method_details( string $stripe_customer_id, string $payment_method_id ): ?object {
		static $cached_payment_methods = [];

		if ( empty( $stripe_customer_id ) || empty( $payment_method_id ) ) {
			return null;
		}

		if ( isset( $cached_payment_methods[ $stripe_customer_id ][ $payment_method_id ] ) ) {
			return $cached_payment_methods[ $stripe_customer_id ][ $payment_method_id ];
		}

		$saved_payment_method = WC_Stripe_API::get_payment_method( $payment_method_id );
		if ( is_wp_error( $saved_payment_method ) ) {
			return null;
		}

		if ( isset( $saved_payment_method->error ) || empty( $saved_payment_method->id ) || empty( $saved_payment_method->customer ) || $saved_payment_method->customer !== $stripe_customer_id ) {
			$saved_payment_method = null;
		}

		// Make sure we build the array tree.
		if ( ! isset( $cached_payment_methods[ $stripe_customer_id ] ) ) {
			$cached_payment_methods[ $stripe_customer_id ] = [];
		}

		$cached_payment_methods[ $stripe_customer_id ][ $payment_method_id ] = $saved_payment_method;

		return $saved_payment_method;
	}
}
