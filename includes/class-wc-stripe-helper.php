<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static methods as helpers.
 *
 * @since 4.0.0
 */
class WC_Stripe_Helper {
	/**
	 * Get Stripe amount to pay
	 *
	 * @param float  $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_stripe_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		if ( in_array( strtolower( $currency ), self::no_decimal_currencies() ) ) {
			return absint( $total );
		} else {
			return absint( wc_format_decimal( ( (float) $total * 100 ), wc_get_price_decimals() ) ); // In cents.
		}
	}

	/**
	 * Localize Stripe messages based on code
	 *
	 * @since 3.0.6
	 * @version 3.0.6
	 * @return array
	 */
	public static function get_localized_messages() {
		return apply_filters( 'wc_stripe_localized_messages', array(
			'invalid_number'           => __( 'The card number is not a valid credit card number.', 'woocommerce-gateway-stripe' ),
			'invalid_expiry_month'     => __( 'The card\'s expiration month is invalid.', 'woocommerce-gateway-stripe' ),
			'invalid_expiry_year'      => __( 'The card\'s expiration year is invalid.', 'woocommerce-gateway-stripe' ),
			'invalid_cvc'              => __( 'The card\'s security code is invalid.', 'woocommerce-gateway-stripe' ),
			'incorrect_number'         => __( 'The card number is incorrect.', 'woocommerce-gateway-stripe' ),
			'incomplete_number'        => __( 'The card number is incomplete.', 'woocommerce-gateway-stripe' ),
			'incomplete_cvc'           => __( 'The card\'s security code is incomplete.', 'woocommerce-gateway-stripe' ),
			'incomplete_expiry'        => __( 'The card\'s expiration date is incomplete.', 'woocommerce-gateway-stripe' ),
			'expired_card'             => __( 'The card has expired.', 'woocommerce-gateway-stripe' ),
			'incorrect_cvc'            => __( 'The card\'s security code is incorrect.', 'woocommerce-gateway-stripe' ),
			'incorrect_zip'            => __( 'The card\'s zip code failed validation.', 'woocommerce-gateway-stripe' ),
			'invalid_expiry_year_past' => __( 'The card\'s expiration year is in the past', 'woocommerce-gateway-stripe' ),
			'card_declined'            => __( 'The card was declined.', 'woocommerce-gateway-stripe' ),
			'missing'                  => __( 'There is no card on a customer that is being charged.', 'woocommerce-gateway-stripe' ),
			'processing_error'         => __( 'An error occurred while processing the card.', 'woocommerce-gateway-stripe' ),
			'invalid_request_error'    => __( 'Unable to process this payment, please try again or use alternative method.', 'woocommerce-gateway-stripe' ),
		) );
	}

	/**
	 * List of currencies supported by Stripe that has no decimals.
	 *
	 * @return array $currencies
	 */
	public static function no_decimal_currencies() {
		return array(
			'bif', // Burundian Franc
			'djf', // Djiboutian Franc
			'jpy', // Japanese Yen
			'krw', // South Korean Won
			'pyg', // Paraguayan Guaraní
			'vnd', // Vietnamese Đồng
			'xaf', // Central African Cfa Franc
			'xpf', // Cfp Franc
			'clp', // Chilean Peso
			'gnf', // Guinean Franc
			'kmf', // Comorian Franc
			'mga', // Malagasy Ariary
			'rwf', // Rwandan Franc
			'vuv', // Vanuatu Vatu
			'xof', // West African Cfa Franc
		);
	}

	/**
	 * Stripe uses smallest denomination in currencies such as cents.
	 * We need to format the returned currency from Stripe into human readable form.
	 * The amount is not used in any calculations so returning string is sufficient.
	 *
	 * @param object $balance_transaction
	 * @param string $type Type of number to format
	 * @return string
	 */
	public static function format_balance_fee( $balance_transaction, $type = 'fee' ) {
		if ( ! is_object( $balance_transaction ) ) {
			return;
		}

		if ( in_array( strtolower( $balance_transaction->currency ), self::no_decimal_currencies() ) ) {
			if ( 'fee' === $type ) {
				return $balance_transaction->fee;
			}

			return $balance_transaction->net;
		}

		if ( 'fee' === $type ) {
			return number_format( $balance_transaction->fee / 100, 2, '.', '' );
		}

		return number_format( $balance_transaction->net / 100, 2, '.', '' );
	}

	/**
	 * Checks Stripe minimum order value authorized per currency
	 */
	public static function get_minimum_amount() {
		// Check order amount
		switch ( get_woocommerce_currency() ) {
			case 'USD':
			case 'CAD':
			case 'EUR':
			case 'CHF':
			case 'AUD':
			case 'SGD':
				$minimum_amount = 50;
				break;
			case 'GBP':
				$minimum_amount = 30;
				break;
			case 'DKK':
				$minimum_amount = 250;
				break;
			case 'NOK':
			case 'SEK':
				$minimum_amount = 300;
				break;
			case 'JPY':
				$minimum_amount = 5000;
				break;
			case 'MXN':
				$minimum_amount = 1000;
				break;
			case 'HKD':
				$minimum_amount = 400;
				break;
			default:
				$minimum_amount = 50;
				break;
		}

		return $minimum_amount;
	}

	/**
	 * Gets all the saved setting options from a specific method.
	 * If specific setting is passed, only return that.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $method The payment method to get the settings from.
	 * @param string $setting The name of the setting to get.
	 */
	public static function get_settings( $method = null, $setting = null ) {
		$all_settings = null === $method ? get_option( 'woocommerce_stripe_settings', array() ) : get_option( 'woocommerce_stripe_' . $method . '_settings', array() );

		if ( null === $setting ) {
			return $all_settings;
		}

		return isset( $all_settings[ $setting ] ) ? $all_settings[ $setting ] : '';
	}

	/**
	 * Check if WC version is pre 3.0.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return bool
	 */
	public static function is_pre_30() {
		return version_compare( WC_VERSION, '3.0.0', '<' );
	}

	/**
	 * Gets the webhook URL for Stripe triggers. Used mainly for
	 * asyncronous redirect payment methods in which statuses are
	 * not immediately chargeable.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public static function get_webhook_url() {
		return add_query_arg( 'wc-api', 'wc_stripe', trailingslashit( get_home_url() ) );
	}

	/**
	 * Gets the order by Stripe source ID.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $source_id
	 */
	public static function get_order_by_source_id( $source_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s", $source_id ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Gets the order by Stripe charge ID.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $charge_id
	 */
	public static function get_order_by_charge_id( $charge_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s", $charge_id ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Sanitize statement descriptor text.
	 *
	 * Stripe requires max of 22 characters and no
	 * special characters with ><"'.
	 *
	 * @since 4.0.0
	 * @param string $statement_descriptor
	 * @return string $statement_descriptor Sanitized statement descriptor
	 */
	public static function clean_statement_descriptor( $statement_descriptor = '' ) {
		$disallowed_characters = array( '<', '>', '"', "'" );

		// Remove special characters.
		$statement_descriptor = str_replace( $disallowed_characters, '', $statement_descriptor );

		$statement_descriptor = substr( trim( $statement_descriptor ), 0, 22 );

		return $statement_descriptor;
	}
}
