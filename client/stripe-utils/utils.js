/* global wc_stripe_upe_params */

import { __ } from '@wordpress/i18n';
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
	// Classic checkout.
	// eslint-disable-next-line camelcase
	if ( ! wc_stripe_upe_params ) {
		throw new Error( 'Stripe initialization data is not available' );
	}
	// eslint-disable-next-line camelcase
	return wc_stripe_upe_params;
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
 * Generates terms parameter for UPE, with value set for reusable payment methods
 *
 * @param {string} value The terms value for each available payment method.
 * @return {Object} Terms parameter fit for UPE.
 */
export const getUPETerms = ( value = 'always' ) => {
	const config = getStripeServerData()?.paymentMethodsConfig;
	const reusablePaymentMethods = Object.keys( config ).filter(
		( method ) => config[ method ].isReusable
	);

	return reusablePaymentMethods.reduce( ( obj, method ) => {
		obj[ method ] = value;
		return obj;
	}, {} );
};

/**
 * `storageKeys` object contains keys for storing values related to the appearance
 *  settings of Stripe UPE (Universal Payment Element) in different contexts.
 */
export const storageKeys = {
	// Key to store the appearance settings for Stripe UPE in the general WooCommerce context
	UPE_APPEARANCE: 'wc_stripe_upe_appearance',

	// Key to store the appearance settings for Stripe UPE when used within WooCommerce Blocks context
	WC_BLOCKS_UPE_APPEARANCE: 'wc_stripe_wc_blocks_upe_appearance',
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

export { getStripeServerData, getErrorMessageForTypeAndCode };
