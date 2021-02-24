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
	const LEGACY_META_NAME_FEE      = 'Stripe Fee';
	const LEGACY_META_NAME_NET      = 'Net Revenue From Stripe';
	const META_NAME_FEE             = '_stripe_fee';
	const META_NAME_NET             = '_stripe_net';
	const META_NAME_STRIPE_CURRENCY = '_stripe_currency';

	/**
	 * Gets the Stripe currency for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @return string $currency
	 */
	public static function get_stripe_currency( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta( self::META_NAME_STRIPE_CURRENCY, true );
	}

	/**
	 * Updates the Stripe currency for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @param string $currency
	 */
	public static function update_stripe_currency( $order = null, $currency ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( self::META_NAME_STRIPE_CURRENCY, $currency );
	}

	/**
	 * Gets the Stripe fee for order. With legacy check.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @return string $amount
	 */
	public static function get_stripe_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( self::META_NAME_FEE, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( self::LEGACY_META_NAME_FEE, true );

			// If found update to new name.
			if ( $amount ) {
				self::update_stripe_fee( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the Stripe fee for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @param float $amount
	 */
	public static function update_stripe_fee( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( self::META_NAME_FEE, $amount );
	}

	/**
	 * Deletes the Stripe fee for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 */
	public static function delete_stripe_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = $order->get_id();

		delete_post_meta( $order_id, self::META_NAME_FEE );
		delete_post_meta( $order_id, self::LEGACY_META_NAME_FEE );
	}

	/**
	 * Gets the Stripe net for order. With legacy check.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @return string $amount
	 */
	public static function get_stripe_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( self::META_NAME_NET, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( self::LEGACY_META_NAME_NET, true );

			// If found update to new name.
			if ( $amount ) {
				self::update_stripe_net( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the Stripe net for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @param float $amount
	 */
	public static function update_stripe_net( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( self::META_NAME_NET, $amount );
	}

	/**
	 * Deletes the Stripe net for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 */
	public static function delete_stripe_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = $order->get_id();

		delete_post_meta( $order_id, self::META_NAME_NET );
		delete_post_meta( $order_id, self::LEGACY_META_NAME_NET );
	}

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
		return apply_filters(
			'wc_stripe_localized_messages',
			array(
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
				'invalid_sofort_country'   => __( 'The billing country is not accepted by SOFORT. Please try another country.', 'woocommerce-gateway-stripe' ),
				'email_invalid'            => __( 'Invalid email address, please correct and try again.', 'woocommerce-gateway-stripe' ),
				'invalid_request_error'    => is_add_payment_method_page()
					? __( 'Unable to save this payment method, please try again or use alternative method.', 'woocommerce-gateway-stripe' )
					: __( 'Unable to process this payment, please try again or use alternative method.', 'woocommerce-gateway-stripe' ),
			)
		);
	}

	/**
	 * List of currencies supported by Stripe that has no decimals
	 * https://stripe.com/docs/currencies#zero-decimal from https://stripe.com/docs/currencies#presentment-currencies
	 *
	 * @return array $currencies
	 */
	public static function no_decimal_currencies() {
		return array(
			'bif', // Burundian Franc
			'clp', // Chilean Peso
			'djf', // Djiboutian Franc
			'gnf', // Guinean Franc
			'jpy', // Japanese Yen
			'kmf', // Comorian Franc
			'krw', // South Korean Won
			'mga', // Malagasy Ariary
			'pyg', // Paraguayan Guaraní
			'rwf', // Rwandan Franc
			'ugx', // Ugandan Shilling
			'vnd', // Vietnamese Đồng
			'vuv', // Vanuatu Vatu
			'xaf', // Central African Cfa Franc
			'xof', // West African Cfa Franc
			'xpf', // Cfp Franc
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
	 * Gets the supported card brands, taking the store's base country and currency into account.
	 * For more information, please see: https://stripe.com/docs/payments/cards/supported-card-brands.
	 *
	 * @since 4.9.0
	 * @version 4.9.0
	 * @return array
	 */
	public static function get_supported_card_brands() {
		$base_country = wc_get_base_location()['country'];
		$base_currency = get_woocommerce_currency();

		$supported_card_brands = array( 'visa', 'mastercard' );

		// American Express is not supported in Brazil and Malaysia (https://stripe.com/docs/payments/cards/supported-card-brands).
		if ( ! in_array( $base_country, array( 'BR', 'MY' ) ) ) {
			array_push( $supported_card_brands, 'amex' );
		}

		// Discover and Diners Club are only supported in the US and Canada. If the store is in the US, USD must be used. (https://stripe.com/docs/currencies#presentment-currencies). 
		if ( 'US' === $base_country && 'USD' === $base_currency || 'CA' === $base_country ) {
			array_push( $supported_card_brands, 'discover', 'diners' );
		}

		// See: https://support.stripe.com/questions/accepting-japan-credit-bureau-(jcb)-payments.
		if ( 'US' === $base_country && 'USD' === $base_currency ||
			 'JP' === $base_country && 'JPY' === $base_currency ||
			 in_array( $base_country, array( 'CA', 'AU', 'NZ' ) ) 
		) {
			array_push( $supported_card_brands, 'jcb' );
		}

		return $supported_card_brands;
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
	 * Checks if Pre Orders is available.
	 *
	 * @since 4.1.0
	 * @return bool
	 */
	public static function is_pre_orders_exists() {
		return class_exists( 'WC_Pre_Orders_Order' );
	}

	/**
	 * Checks if WC version is less than passed in version.
	 *
	 * @since 4.1.11
	 * @param string $version Version to check against.
	 * @return bool
	 */
	public static function is_wc_lt( $version ) {
		return version_compare( WC_VERSION, $version, '<' );
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

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $source_id, '_stripe_source_id' ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Gets the order by Stripe charge ID.
	 *
	 * @since 4.0.0
	 * @since 4.1.16 Return false if charge_id is empty.
	 * @param string $charge_id
	 */
	public static function get_order_by_charge_id( $charge_id ) {
		global $wpdb;

		if ( empty( $charge_id ) ) {
			return false;
		}

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $charge_id, '_transaction_id' ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Gets the order by Stripe PaymentIntent ID.
	 *
	 * @since 4.2
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_order_by_intent_id( $intent_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $intent_id, '_stripe_intent_id' ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Gets the order by Stripe SetupIntent ID.
	 *
	 * @since 4.3
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_order_by_setup_intent_id( $intent_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $intent_id, '_stripe_setup_intent' ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Sanitize statement descriptor text.
	 *
	 * Stripe requires max of 22 characters and no special characters.
	 *
	 * @since 4.0.0
	 * @param string $statement_descriptor
	 * @return string $statement_descriptor Sanitized statement descriptor
	 */
	public static function clean_statement_descriptor( $statement_descriptor = '' ) {
		$disallowed_characters = array( '<', '>', '\\', '*', '"', "'", '/', '(', ')', '{', '}' );

		// Strip any tags.
		$statement_descriptor = strip_tags( $statement_descriptor );

		// Strip any HTML entities.
		// Props https://stackoverflow.com/questions/657643/how-to-remove-html-special-chars .
		$statement_descriptor = preg_replace( "/&#?[a-z0-9]{2,8};/i", "", $statement_descriptor );

		// Next, remove any remaining disallowed characters.
		$statement_descriptor = str_replace( $disallowed_characters, '', $statement_descriptor );

		// Trim any whitespace at the ends and limit to 22 characters.
		$statement_descriptor = substr( trim( $statement_descriptor ), 0, 22 );

		return $statement_descriptor;
	}
}
