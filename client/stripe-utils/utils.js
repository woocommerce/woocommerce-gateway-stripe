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

export { getStripeServerData, getApiKey, getErrorMessageForTypeAndCode };
