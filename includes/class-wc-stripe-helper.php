<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

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
	public static function update_stripe_currency( $order, $currency ) {
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
	 * @param float  $amount
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

		$order->delete_meta_data( self::META_NAME_FEE );
		$order->delete_meta_data( self::LEGACY_META_NAME_FEE );
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
	 * @param float  $amount
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

		$order->delete_meta_data( self::META_NAME_NET );
		$order->delete_meta_data( self::LEGACY_META_NAME_NET );
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
			[
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
				'postal_code_invalid'      => __( 'Invalid zip code, please correct and try again', 'woocommerce-gateway-stripe' ),
				'invalid_expiry_year_past' => __( 'The card\'s expiration year is in the past', 'woocommerce-gateway-stripe' ),
				'card_declined'            => __( 'The card was declined.', 'woocommerce-gateway-stripe' ),
				'missing'                  => __( 'There is no card on a customer that is being charged.', 'woocommerce-gateway-stripe' ),
				'processing_error'         => __( 'An error occurred while processing the card.', 'woocommerce-gateway-stripe' ),
				'invalid_sofort_country'   => __( 'The billing country is not accepted by Sofort. Please try another country.', 'woocommerce-gateway-stripe' ),
				'email_invalid'            => __( 'Invalid email address, please correct and try again.', 'woocommerce-gateway-stripe' ),
				'invalid_request_error'    => is_add_payment_method_page()
					? __( 'Unable to save this payment method, please try again or use alternative method.', 'woocommerce-gateway-stripe' )
					: __( 'Unable to process this payment, please try again or use alternative method.', 'woocommerce-gateway-stripe' ),
				'amount_too_large'         => __( 'The order total is too high for this payment method', 'woocommerce-gateway-stripe' ),
				'amount_too_small'         => __( 'The order total is too low for this payment method', 'woocommerce-gateway-stripe' ),
				'country_code_invalid'     => __( 'Invalid country code, please try again with a valid country code', 'woocommerce-gateway-stripe' ),
				'tax_id_invalid'           => __( 'Invalid Tax Id, please try again with a valid tax id', 'woocommerce-gateway-stripe' ),
			]
		);
	}

	/**
	 * List of currencies supported by Stripe that has no decimals
	 * https://stripe.com/docs/currencies#zero-decimal from https://stripe.com/docs/currencies#presentment-currencies
	 * ugx is an exception and not in this list for being a special cases in Stripe https://stripe.com/docs/currencies#special-cases
	 *
	 * @return array $currencies
	 */
	public static function no_decimal_currencies() {
		return [
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
			'vnd', // Vietnamese Đồng
			'vuv', // Vanuatu Vatu
			'xaf', // Central African Cfa Franc
			'xof', // West African Cfa Franc
			'xpf', // Cfp Franc
		];
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
		$all_settings = null === $method ? get_option( 'woocommerce_stripe_settings', [] ) : get_option( 'woocommerce_stripe_' . $method . '_settings', [] );

		if ( null === $setting ) {
			return $all_settings;
		}

		return isset( $all_settings[ $setting ] ) ? $all_settings[ $setting ] : '';
	}

	/**
	 * List of legacy payment methods.
	 *
	 * @return array
	 */
	public static function get_legacy_payment_methods() {
		$payment_method_classes = [
			WC_Gateway_Stripe_Bancontact::class,
			WC_Gateway_Stripe_EPS::class,
			WC_Gateway_Stripe_Giropay::class,
			WC_Gateway_Stripe_Ideal::class,
			WC_Gateway_Stripe_p24::class,
			WC_Gateway_Stripe_Sepa::class,
			WC_Gateway_Stripe_Boleto::class,
			WC_Gateway_Stripe_Oxxo::class,
		];

		/** Show Sofort if it's already enabled. Hide from the new merchants and keep it for the old ones who are already using this gateway, until we remove it completely.
		 * Stripe is deprecating Sofort https://support.stripe.com/questions/sofort-is-being-deprecated-as-a-standalone-payment-method.
		 */
		$sofort_settings = get_option( 'woocommerce_stripe_sofort_settings', [] );
		if ( isset( $sofort_settings['enabled'] ) && 'yes' === $sofort_settings['enabled'] ) {
			$payment_method_classes[] = WC_Gateway_Stripe_Sofort::class;
		}

		$payment_methods = [];

		foreach ( $payment_method_classes as $payment_method_class ) {
			$payment_method                         = new $payment_method_class();
			$payment_methods[ $payment_method->id ] = $payment_method;
		}

		return $payment_methods;
	}

	/**
	 * List of available legacy payment methods.
	 *
	 * @return array
	 */
	public static function get_legacy_available_payment_method_ids() {
		$payment_methods = self::get_legacy_payment_methods();

		// In legacy mode (when UPE is disabled), Stripe refers to card as payment method.
		$available_payment_method_ids = [ 'card' ];

		foreach ( $payment_methods as $payment_method ) {
			$payment_method_id              = 'stripe_sepa' === $payment_method->id ? 'sepa_debit' : str_replace( 'stripe_', '', $payment_method->id );
			$available_payment_method_ids[] = $payment_method_id;
		}

		return $available_payment_method_ids;
	}

	/**
	 * List of enabled legacy payment methods.
	 *
	 * @return array
	 */
	public static function get_legacy_enabled_payment_methods( $field = null ) {
		$stripe_settings   = get_option( 'woocommerce_stripe_settings', [] );
		$is_stripe_enabled = isset( $stripe_settings['enabled'] ) && 'yes' === $stripe_settings['enabled'];
		$payment_methods   = self::get_legacy_payment_methods();

		// In legacy mode (when UPE is disabled), Stripe refers to card as payment method.
		$enabled_payment_method_ids = $is_stripe_enabled ? [ 'card' ] : [];
		$enabled_payment_methods    = [];

		foreach ( $payment_methods as $payment_method ) {
			if ( ! $payment_method->is_enabled() ) {
				continue;
			}
			$enabled_payment_methods[ $payment_method->id ] = $payment_method;

			$payment_method_id            = 'stripe_sepa' === $payment_method->id ? 'sepa_debit' : str_replace( 'stripe_', '', $payment_method->id );
			$enabled_payment_method_ids[] = $payment_method_id;
		}

		if ( 'id' === $field ) {
			return $enabled_payment_method_ids;
		}

		return $enabled_payment_methods;
	}

	/**
	 * Get settings of individual payment methods.
	 *
	 * @return array
	 */
	public static function get_legacy_individual_payment_method_settings() {
		$payment_methods = self::get_legacy_payment_methods();

		$payment_method_settings = [];

		foreach ( $payment_methods as $payment_method ) {
			$settings = [
				'name'        => $payment_method->get_option( 'title' ),
				'description' => $payment_method->get_option( 'description' ),
			];

			if ( method_exists( $payment_method, 'get_unique_settings' ) ) {
				$settings = $payment_method->get_unique_settings( $settings );
			}

			$payment_method_settings[ str_replace( 'stripe_', '', $payment_method->id ) ] = $settings;
		}

		return $payment_method_settings;
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
		return wp_sanitize_redirect(
			esc_url_raw(
				add_query_arg( 'wc-api', 'wc_stripe', trailingslashit( get_home_url() ) )
			)
		);
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

		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders   = wc_get_orders(
				[
					'limit'      => 1,
					'meta_query' => [
						[
							'key'   => '_stripe_source_id',
							'value' => $source_id,
						],
					],
				]
			);
			$order_id = current( $orders ) ? current( $orders )->get_id() : false;
		} else {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $source_id, '_stripe_source_id' ) );
		}

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

		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders   = wc_get_orders(
				[
					'transaction_id' => $charge_id,
					'limit'          => 1,
				]
			);
			$order_id = current( $orders ) ? current( $orders )->get_id() : false;
		} else {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $charge_id, '_transaction_id' ) );
		}

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Gets the order by Stripe refund ID.
	 *
	 * @since 7.5.0
	 * @param string $refund_id
	 */
	public static function get_order_by_refund_id( $refund_id ) {
		global $wpdb;

		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders   = wc_get_orders(
				[
					'limit'          => 1,
					'meta_query' => [
						[
							'key'   => '_stripe_refund_id',
							'value' => $refund_id,
						],
					],
				]
			);
			$order_id = current( $orders ) ? current( $orders )->get_id() : false;
		} else {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $refund_id, '_stripe_refund_id' ) );
		}

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

		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders   = wc_get_orders(
				[
					'limit'      => 1,
					'meta_query' => [
						[
							'key'   => '_stripe_intent_id',
							'value' => $intent_id,
						],
					],
				]
			);
			$order_id = current( $orders ) ? current( $orders )->get_id() : false;
		} else {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $intent_id, '_stripe_intent_id' ) );
		}

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

		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders   = wc_get_orders(
				[
					'limit'      => 1,
					'meta_query' => [
						[
							'key'   => '_stripe_setup_intent',
							'value' => $intent_id,
						],
					],
				]
			);
			$order_id = current( $orders ) ? current( $orders )->get_id() : false;
		} else {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $intent_id, '_stripe_setup_intent' ) );
		}

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Sanitize and retrieve the shortened statement descriptor concatenated with the order number.
	 *
	 * @param string   $statement_descriptor Shortened statement descriptor.
	 * @param WC_Order $order Order.
	 * @param string   $fallback_descriptor (optional) Fallback of the shortened statement descriptor in case it's blank.
	 * @return string $statement_descriptor Final shortened statement descriptor.
	 */
	public static function get_dynamic_statement_descriptor( $statement_descriptor = '', $order = null, $fallback_descriptor = '' ) {
		$actual_descriptor = ! empty( $statement_descriptor ) ? $statement_descriptor : $fallback_descriptor;
		$prefix            = self::clean_statement_descriptor( $actual_descriptor );
		$suffix            = '';

		if ( empty( $prefix ) ) {
			return '';
		}

		if ( method_exists( $order, 'get_order_number' ) && ! empty( $order->get_order_number() ) ) {
			$suffix = '* #' . $order->get_order_number();
		}

		// Make sure it is limited at 22 characters.
		$statement_descriptor = substr( $prefix . $suffix, 0, 22 );

		return $statement_descriptor;
	}

	/**
	 * Sanitize statement descriptor text.
	 *
	 * Stripe requires max of 22 characters and no special characters.
	 *
	 * @since 4.0.0
	 * @param string $statement_descriptor Statement descriptor.
	 * @return string $statement_descriptor Sanitized statement descriptor.
	 */
	public static function clean_statement_descriptor( $statement_descriptor = '' ) {
		$disallowed_characters = [ '<', '>', '\\', '*', '"', "'", '/', '(', ')', '{', '}' ];

		// Strip any tags.
		$statement_descriptor = strip_tags( $statement_descriptor );

		// Strip any HTML entities.
		// Props https://stackoverflow.com/questions/657643/how-to-remove-html-special-chars .
		$statement_descriptor = preg_replace( '/&#?[a-z0-9]{2,8};/i', '', $statement_descriptor );

		// Next, remove any remaining disallowed characters.
		$statement_descriptor = str_replace( $disallowed_characters, '', $statement_descriptor );

		// Remove non-Latin characters, excluding numbers, whitespaces and especial characters.
		$statement_descriptor = preg_replace( '/[^a-zA-Z0-9\s\x{00C0}-\x{00FF}\p{P}]/u', '', $statement_descriptor );

		// Trim any whitespace at the ends and limit to 22 characters.
		$statement_descriptor = substr( trim( $statement_descriptor ), 0, 22 );

		return $statement_descriptor;
	}

	/**
	 * Converts a WooCommerce locale to the closest supported by Stripe.js.
	 *
	 * Stripe.js supports only a subset of IETF language tags, if a country specific locale is not supported we use
	 * the default for that language (https://stripe.com/docs/js/appendix/supported_locales).
	 * If no match is found we return 'auto' so Stripe.js uses the browser locale.
	 *
	 * @param string $wc_locale The locale to convert.
	 *
	 * @return string Closest locale supported by Stripe ('auto' if NONE).
	 */
	public static function convert_wc_locale_to_stripe_locale( $wc_locale ) {
		// List copied from: https://stripe.com/docs/js/appendix/supported_locales.
		$supported = [
			'ar',     // Arabic.
			'bg',     // Bulgarian (Bulgaria).
			'cs',     // Czech (Czech Republic).
			'da',     // Danish.
			'de',     // German (Germany).
			'el',     // Greek (Greece).
			'en',     // English.
			'en-GB',  // English (United Kingdom).
			'es',     // Spanish (Spain).
			'es-419', // Spanish (Latin America).
			'et',     // Estonian (Estonia).
			'fi',     // Finnish (Finland).
			'fr',     // French (France).
			'fr-CA',  // French (Canada).
			'he',     // Hebrew (Israel).
			'hu',     // Hungarian (Hungary).
			'id',     // Indonesian (Indonesia).
			'it',     // Italian (Italy).
			'ja',     // Japanese.
			'lt',     // Lithuanian (Lithuania).
			'lv',     // Latvian (Latvia).
			'ms',     // Malay (Malaysia).
			'mt',     // Maltese (Malta).
			'nb',     // Norwegian Bokmål.
			'nl',     // Dutch (Netherlands).
			'pl',     // Polish (Poland).
			'pt-BR',  // Portuguese (Brazil).
			'pt',     // Portuguese (Brazil).
			'ro',     // Romanian (Romania).
			'ru',     // Russian (Russia).
			'sk',     // Slovak (Slovakia).
			'sl',     // Slovenian (Slovenia).
			'sv',     // Swedish (Sweden).
			'th',     // Thai.
			'tr',     // Turkish (Turkey).
			'zh',     // Chinese Simplified (China).
			'zh-HK',  // Chinese Traditional (Hong Kong).
			'zh-TW',  // Chinese Traditional (Taiwan).
		];

		// Stripe uses '-' instead of '_' (used in WordPress).
		$locale = str_replace( '_', '-', $wc_locale );

		if ( in_array( $locale, $supported, true ) ) {
			return $locale;
		}

		// The plugin has been fully translated for Spanish (Ecuador), Spanish (Mexico), and
		// Spanish(Venezuela), and partially (88% at 2021-05-14) for Spanish (Colombia).
		// We need to map these locales to Stripe's Spanish (Latin America) 'es-419' locale.
		// This list should be updated if more localized versions of Latin American Spanish are
		// made available.
		$lowercase_locale                  = strtolower( $wc_locale );
		$translated_latin_american_locales = [
			'es_co', // Spanish (Colombia).
			'es_ec', // Spanish (Ecuador).
			'es_mx', // Spanish (Mexico).
			'es_ve', // Spanish (Venezuela).
		];
		if ( in_array( $lowercase_locale, $translated_latin_american_locales, true ) ) {
			return 'es-419';
		}

		// Finally, we check if the "base locale" is available.
		$base_locale = substr( $wc_locale, 0, 2 );
		if ( in_array( $base_locale, $supported, true ) ) {
			return $base_locale;
		}

		// Default to 'auto' so Stripe.js uses the browser locale.
		return 'auto';
	}

	/**
	 * Checks if this page is a cart or checkout page.
	 *
	 * @since 5.2.3
	 * @return boolean
	 */
	public static function has_cart_or_checkout_on_current_page() {
		return is_cart() || is_checkout();
	}

	/**
	 * Return true if the current_tab and current_section match the ones we want to check against.
	 *
	 * @param string $tab
	 * @param string $section
	 * @return boolean
	 */
	public static function should_enqueue_in_current_tab_section( $tab, $section ) {
		global $current_tab, $current_section;

		if ( ! isset( $current_tab ) || $tab !== $current_tab ) {
			return false;
		}

		if ( ! isset( $current_section ) || $section !== $current_section ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns true if the Stripe JS should be loaded on product pages.
	 *
	 * The critical part here is running the filter to allow merchants to disable Stripe's JS to
	 * improve their store's performance when PRBs are disabled.
	 *
	 * @since 5.8.0
	 * @return boolean True if Stripe's JS should be loaded, false otherwise.
	 */
	public static function should_load_scripts_on_product_page() {
		if ( self::should_load_scripts_for_prb_location( 'product' ) ) {
			return true;
		}

		return apply_filters( 'wc_stripe_load_scripts_on_product_page_when_prbs_disabled', true );
	}

	/**
	 * Returns true if the Stripe JS should be loaded on the cart page.
	 *
	 * The critical part here is running the filter to allow merchants to disable Stripe's JS to
	 * improve their store's performance when PRBs are disabled.
	 *
	 * @since 5.8.0
	 * @return boolean True if Stripe's JS should be loaded, false otherwise.
	 */
	public static function should_load_scripts_on_cart_page() {
		if ( self::should_load_scripts_for_prb_location( 'cart' ) ) {
			return true;
		}

		return apply_filters( 'wc_stripe_load_scripts_on_cart_page_when_prbs_disabled', true );
	}

	/**
	 * Returns true if the Stripe JS should be loaded for the provided location.
	 *
	 * @since 5.8.1
	 * @param string $location  Either 'product' or 'cart'. Used to specify which location to check.
	 * @return boolean True if Stripe's JS should be loaded for the provided location, false otherwise.
	 */
	private static function should_load_scripts_for_prb_location( $location ) {
		// Make sure location parameter is sanitized.
		$location         = in_array( $location, [ 'product', 'cart' ], true ) ? $location : '';
		$are_prbs_enabled = self::get_settings( null, 'payment_request' ) ?? 'yes';
		$prb_locations    = self::get_settings( null, 'payment_request_button_locations' ) ?? [ 'product', 'cart' ];

		// The scripts should be loaded when all of the following are true:
		//   1. The PRBs are enabled; and
		//   2. The PRB location settings have an array value (saving an empty option in the GUI results in non-array value); and
		//   3. The PRBs are enabled at $location.
		return 'yes' === $are_prbs_enabled && is_array( $prb_locations ) && in_array( $location, $prb_locations, true );
	}

	/**
	 * Adds payment intent id and order note to order if payment intent is not already saved
	 *
	 * @param $payment_intent_id
	 * @param $order
	 */
	public static function add_payment_intent_to_order( $payment_intent_id, $order ) {

		$old_intent_id = $order->get_meta( '_stripe_intent_id' );

		if ( $old_intent_id === $payment_intent_id ) {
			return;
		}

		$order->add_order_note(
			sprintf(
			/* translators: $1%s payment intent ID */
				__( 'Stripe payment intent created (Payment Intent ID: %1$s)', 'woocommerce-gateway-stripe' ),
				$payment_intent_id
			)
		);

		$order->update_meta_data( '_stripe_intent_id', $payment_intent_id );
		$order->save();
	}

	/**
	 * Adds a source or payment method argument to the request array depending on what sort of
	 * payment method ID is provided. If ID is neither a source or a payment method ID then nothing
	 * is added.
	 *
	 * @param string $payment_method_id  The payment method ID that should be added to the request array.
	 * @param array $request             The request representing the arguments that will be sent in the request.
	 *
	 * @return array  The updated request array.
	 */
	public static function add_payment_method_to_request_array( string $payment_method_id, array $request ): array {
		// Extract the payment method prefix using the first '_' character
		$payment_method_type = substr( $payment_method_id, 0, strpos( $payment_method_id, '_' ) );

		switch ( $payment_method_type ) {
			case 'src':
				$request['source'] = $payment_method_id;
				break;
			case 'pm':
			case 'card':
				$request['payment_method'] = $payment_method_id;
				break;
		}

		return $request;
	}

	/**
	 * Evaluates whether the object passed to this function is a Stripe Payment Method.
	 *
	 * @param stdClass $object  The object that should be evaluated.
	 * @return bool             Returns true if the object is a Payment Method; false otherwise.
	 */
	public static function is_payment_method_object( stdClass $payment_method ): bool {
		return isset( $payment_method->object ) && 'payment_method' === $payment_method->object;
	}

	/**
	 * Evaluates whether a given Stripe Source (or Stripe Payment Method) is reusable.
	 * Payment Methods are always reusable; Sources are only reusable when the appropriate
	 * usage metadata is provided.
	 *
	 * @param stdClass $payment_method  The source or payment method to be evaluated.

	 * @return bool  Returns true if the source is reusable; false otherwise.
	 */
	public static function is_reusable_payment_method( stdClass $payment_method ): bool {
		return self::is_payment_method_object( $payment_method ) || ( isset( $payment_method->usage ) && 'reusable' === $payment_method->usage );
	}

	/**
	 * Returns true if the provided payment method is a card, false otherwise.
	 *
	 * @param stdClass $payment_method  The provided payment method object. Can be a Source or a Payment Method.
	 *
	 * @return bool  True if payment method is a card, false otherwise.
	 */
	public static function is_card_payment_method( stdClass $payment_method ): bool {
		if ( ! isset( $payment_method->object ) || ! isset( $payment_method->type ) ) {
			return false;
		}

		if ( 'payment_method' !== $payment_method->object && 'source' !== $payment_method->object ) {
			return false;
		}

		return 'card' === $payment_method->type;
	}

	/**
	 * Returns a source or payment method from a given intent object.
	 *
	 * @param stdClass|object $intent  The intent that contains the payment method.
	 *
	 * @return stdClass|string|null  The payment method if found, null otherwise.
	 */
	public static function get_payment_method_from_intent( $intent ) {
		if ( ! empty( $intent->source ) ) {
			return $intent->source;
		}

		if ( ! empty( $intent->payment_method ) ) {
			return $intent->payment_method;
		}

		return null;
	}
}
