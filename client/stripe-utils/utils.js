/* global wc_stripe_upe_params, wc */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { errorTypes, errorCodes } from './constants';

/**
 * @typedef {import('./type-defs').StripeServerData} StripeServerData
 * @typedef {import('./type-defs').StripePaymentItem} StripePaymentItem
 * @typedef {import('./type-defs').StripePaymentRequest} StripePaymentRequest
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').PreparedCartTotalItem} CartTotalItem
 */

/**
 * Stripe data comes form the server passed on a global object.
 *
 * @return  {StripeServerData} Stripe server data.
 */
const getStripeServerData = () => {
	// Classic checkout or blocks-based one.
	const stripeServerData =
		typeof wc_stripe_upe_params !== 'undefined' // eslint-disable-line camelcase
			? wc_stripe_upe_params // eslint-disable-line camelcase
			: wc?.wcSettings?.getSetting( 'stripe_data', null );
	if ( ! stripeServerData ) {
		throw new Error( 'Stripe initialization data is not available' );
	}
	return stripeServerData;
};

/**
 * Returns the public api key for the stripe payment method
 *
 * @throws Error
 * @return {string} The public api key for the stripe payment method.
 */
const getApiKey = () => {
	const apiKey = getStripeServerData()?.key;
	if ( ! apiKey ) {
		throw new Error(
			'There is no api key available for stripe. Make sure it is available on the wc.stripe_data.stripe.key property.'
		);
	}
	return apiKey;
};

/**
 * Creates a payment request using cart data from WooCommerce.
 *
 * @param {Object} stripe - The Stripe JS object.
 * @param {Object} cart - The cart data response from the store's AJAX API.
 *
 * @return {Object} A Stripe payment request.
 */
export const createPaymentRequestUsingCart = ( stripe, cart ) => {
	const options = {
		total: cart.order_data.total,
		currency: cart.order_data.currency,
		country: cart.order_data.country_code,
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: getStripeServerData()?.checkout?.needs_payer_phone,
		requestShipping: cart.shipping_required ? true : false,
		displayItems: cart.order_data.displayItems,
	};

	// Puerto Rico (PR) is the only US territory/possession that's supported by Stripe.
	// Since it's considered a US state by Stripe, we need to do some special mapping.
	if ( options.country === 'PR' ) {
		options.country = 'US';
	}

	return stripe.paymentRequest( options );
};

const isNonFriendlyError = ( type ) =>
	[
		errorTypes.INVALID_REQUEST,
		errorTypes.API_CONNECTION,
		errorTypes.API_ERROR,
		errorTypes.AUTHENTICATION_ERROR,
		errorTypes.RATE_LIMIT_ERROR,
	].includes( type );

const getErrorMessageForCode = ( code ) => {
	const messages = {
		[ errorCodes.INVALID_NUMBER ]: __(
			'The card number is not a valid credit card number.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INVALID_EXPIRY_MONTH ]: __(
			'The card expiration month is invalid.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INVALID_EXPIRY_YEAR ]: __(
			'The card expiration year is invalid.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INVALID_CVC ]: __(
			'The card security code is invalid.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INCORRECT_NUMBER ]: __(
			'The card number is incorrect.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INCOMPLETE_NUMBER ]: __(
			'The card number is incomplete.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INCOMPLETE_CVC ]: __(
			'The card security code is incomplete.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INCOMPLETE_EXPIRY ]: __(
			'The card expiration date is incomplete.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.EXPIRED_CARD ]: __(
			'The card has expired.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INCORRECT_CVC ]: __(
			'The card security code is incorrect.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INCORRECT_ZIP ]: __(
			'The card zip code failed validation.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.INVALID_EXPIRY_YEAR_PAST ]: __(
			'The card expiration year is in the past',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.CARD_DECLINED ]: __(
			'The card was declined.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.MISSING ]: __(
			'There is no card on a customer that is being charged.',
			'woocommerce-gateway-stripe'
		),
		[ errorCodes.PROCESSING_ERROR ]: __(
			'An error occurred while processing the card.',
			'woocommerce-gateway-stripe'
		),
	};
	return messages[ code ] || null;
};

const getErrorMessageForTypeAndCode = ( type, code = '' ) => {
	switch ( type ) {
		case errorTypes.INVALID_EMAIL:
			return __(
				'Invalid email address, please correct and try again.',
				'woocommerce-gateway-stripe'
			);
		case isNonFriendlyError( type ):
			return __(
				'Unable to process this payment, please try again or use alternative method.',
				'woocommerce-gateway-stripe'
			);
		case errorTypes.CARD_ERROR:
			return getErrorMessageForCode( code );
		case errorTypes.VALIDATION_ERROR:
			return ''; // These are shown inline.
	}
	return null;
};

/**
 * `storageKeys` object contains keys for storing values related to the appearance
 *  settings of Stripe UPE (Universal Payment Element) in different contexts.
 */
export const storageKeys = {
	// Key to store the appearance settings for Stripe UPE in the general WooCommerce context
	UPE_APPEARANCE: 'wc_stripe_upe_appearance',
};

/**
 * Sets a key-value pair in the localStorage along with a time-to-live (TTL) value, which specifies
 * the time (in milliseconds) after which the item will be considered expired.
 *
 * @param {string} key - The key to be stored in the localStorage.
 * @param {*} value - The value to be stored corresponding to the key.
 * @param {number} ttl - The time-to-live (TTL) value in milliseconds for the stored item.
 */
export const setStorageWithExpiration = ( key, value, ttl ) => {
	const now = new Date();
	const item = {
		value,
		expiration: now.getTime() + ttl,
	};

	localStorage.setItem( key, JSON.stringify( item ) );
};

/**
 * Retrieves the value stored in the localStorage with the specified key, if it's not expired.
 * If the item has expired, it's removed from the localStorage and null is returned.
 *
 * @param {string} key - The key of the item to be retrieved from the localStorage.
 * @return {*} - The value associated with the key if it's not expired, or null if the item doesn't exist or has expired.
 */
export const getStorageWithExpiration = ( key ) => {
	const itemStr = localStorage.getItem( key );

	if ( ! itemStr ) {
		return null;
	}

	const item = JSON.parse( itemStr );
	const now = new Date();

	if ( now.getTime() > item.expiration ) {
		localStorage.removeItem( key );
		return null;
	}

	return item.value;
};


export { getStripeServerData, getApiKey, getErrorMessageForTypeAndCode };
