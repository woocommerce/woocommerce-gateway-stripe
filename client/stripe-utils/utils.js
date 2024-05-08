/* global wc_stripe_upe_params */

import { __ } from '@wordpress/i18n';
import { getAppearance } from '../styles/upe';
import {
	errorTypes,
	errorCodes,
	getPaymentMethodsConstants,
} from './constants';

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

// Used by dPE.

/**
 * Check whether Stripe Link is enabled.
 *
 * @param {Object} paymentMethodsConfig Checkout payment methods configuration settings object.
 * @return {boolean} True, if enabled; false otherwise.
 */
export const isLinkEnabled = ( paymentMethodsConfig ) => {
	return (
		paymentMethodsConfig.link !== undefined &&
		paymentMethodsConfig.card !== undefined
	);
};

/**
 * Get array of payment method types to use with intent.
 *
 * @todo Make paymentMethodType required when Split is implemented.
 *
 * @param {string} paymentMethodType Payment method type Stripe ID.
 * @return {Array} Array of payment method types to use with intent.
 */
export const getPaymentMethodTypes = ( paymentMethodType = null ) => {
	const paymentMethodsConfig = getStripeServerData()?.paymentMethodsConfig;

	if ( paymentMethodType === null ) {
		if (
			getStripeServerData()?.isCheckout ||
			getStripeServerData()?.isOrderPay
		) {
			return Object.keys( paymentMethodsConfig || {} );
		}

		// If we're on the My Account > Add payment method page make sure we only support the card paymentMethodType.
		return [ 'card' ];
	}

	const paymentMethodTypes = [ paymentMethodType ];
	if (
		paymentMethodType === 'card' &&
		isLinkEnabled( paymentMethodsConfig )
	) {
		paymentMethodTypes.push( 'link' );
	}
	return paymentMethodTypes;
};

function shouldIncludeTerms() {
	if ( getStripeServerData()?.cartContainsSubscription ) {
		return true;
	}

	const savePaymentMethodCheckbox = document.getElementById(
		'wc-stripe-new-payment-method'
	);
	if (
		savePaymentMethodCheckbox !== null &&
		savePaymentMethodCheckbox.checked
	) {
		return true;
	}

	return false;
}

/**
 * Returns a string of event names to be used for registering checkout submission handlers.
 * For example: "checkout_place_order_stripe checkout_place_order_stripe_ideal ...checkout_place_order_{paymentMethod}"
 *
 * @return {string} String of event names.
 */
export const generateCheckoutEventNames = () => {
	return Object.values( getPaymentMethodsConstants() )
		.map( ( method ) => `checkout_place_order_${ method }` )
		.join( ' ' );
};

export const appendPaymentMethodIdToForm = ( form, paymentMethodId ) => {
	form.append(
		`<input type="hidden" id="wc-stripe-payment-method" name="wc-stripe-payment-method" value="${ paymentMethodId }" />`
	);
};

export const appendSetupIntentToForm = ( form, setupIntent ) => {
	form.append(
		`<input type="hidden" id="wc-stripe-setup-intent" name="wc-stripe-setup-intent" value="${ setupIntent.id }" />`
	);
};

/**
 * Checks if the customer is using a saved payment method.
 *
 * @param {string} paymentMethodType The payment method type ('card', 'ideal', etc.).
 *
 * @return {boolean} Boolean indicating whether or not a saved payment method is being used.
 */
export const isUsingSavedPaymentMethod = ( paymentMethodType ) => {
	const paymentMethod = getPaymentMethodName( paymentMethodType );
	return (
		document.querySelector( `#wc-${ paymentMethod }-payment-token-new` ) !==
			null &&
		! document.querySelector( `#wc-${ paymentMethod }-payment-token-new` )
			.checked
	);
};

/**
 * Finds selected payment gateway and returns matching Stripe payment method for gateway.
 *
 * @return {string} Stripe payment method type
 */
export const getSelectedUPEGatewayPaymentMethod = () => {
	const paymentMethodsConfig = getStripeServerData()?.paymentMethodsConfig;
	const gatewayCardId = getStripeServerData()?.gatewayId;
	let selectedGatewayId = null;

	// Handle payment method selection on the Checkout page or Add Payment Method page where class names differ.
	const radio = document.querySelector(
		'li.wc_payment_method input.input-radio:checked, li.woocommerce-PaymentMethod input.input-radio:checked'
	);
	if ( radio !== null ) {
		selectedGatewayId = radio.id;
	}

	if ( selectedGatewayId === 'payment_method_stripe' ) {
		selectedGatewayId = 'payment_method_stripe_card';
	}

	let selectedPaymentMethod = null;

	for ( const paymentMethodType in paymentMethodsConfig ) {
		if (
			`payment_method_${ gatewayCardId }_${ paymentMethodType }` ===
			selectedGatewayId
		) {
			selectedPaymentMethod = paymentMethodType;
			break;
		}
	}

	return selectedPaymentMethod;
};

export const getHiddenBillingFields = ( enabledBillingFields ) => {
	return {
		name:
			enabledBillingFields.includes( 'billing_first_name' ) ||
			enabledBillingFields.includes( 'billing_last_name' )
				? 'never'
				: 'auto',
		email: enabledBillingFields.includes( 'billing_email' )
			? 'never'
			: 'auto',
		phone: enabledBillingFields.includes( 'billing_phone' )
			? 'never'
			: 'auto',
		address: {
			country: enabledBillingFields.includes( 'billing_country' )
				? 'never'
				: 'auto',
			line1: enabledBillingFields.includes( 'billing_address_1' )
				? 'never'
				: 'auto',
			line2: enabledBillingFields.includes( 'billing_address_2' )
				? 'never'
				: 'auto',
			city: enabledBillingFields.includes( 'billing_city' )
				? 'never'
				: 'auto',
			state: enabledBillingFields.includes( 'billing_state' )
				? 'never'
				: 'auto',
			postalCode: enabledBillingFields.includes( 'billing_postcode' )
				? 'never'
				: 'auto',
		},
	};
};

export const getUpeSettings = () => {
	const upeSettings = {};
	const showTerms = shouldIncludeTerms() ? 'always' : 'never';

	upeSettings.terms = getUPETerms( showTerms );

	if (
		getStripeServerData()?.isCheckout &&
		! (
			getStripeServerData()?.isOrderPay ||
			getStripeServerData()?.isChangingPayment
		)
	) {
		upeSettings.fields = {
			billingDetails: getHiddenBillingFields(
				getStripeServerData()?.enabledBillingFields
			),
		};
	}

	return upeSettings;
};

/**
 * Show error notice at top of checkout form.
 * Will try to use a translatable message using the message code if available
 *
 * @param {string} errorMessage
 */
export const showErrorCheckout = ( errorMessage ) => {
	if (
		typeof errorMessage !== 'string' &&
		! ( errorMessage instanceof String )
	) {
		if ( errorMessage.code && getStripeServerData()[ errorMessage.code ] ) {
			errorMessage = getStripeServerData()[ errorMessage.code ];
		} else {
			errorMessage = errorMessage.message;
		}
	}

	let messageWrapper = '';
	if ( errorMessage.includes( 'woocommerce-error' ) ) {
		messageWrapper = errorMessage;
	} else {
		messageWrapper =
			'<ul class="woocommerce-error" role="alert"><li>' +
			errorMessage +
			'</li></ul>';
	}
	const $container = jQuery( '.woocommerce-notices-wrapper' ).first();

	if ( ! $container.length ) {
		return;
	}

	// Adapted from WooCommerce core @ ea9aa8c, assets/js/frontend/checkout.js#L514-L529
	jQuery(
		'.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
	).remove();
	$container.prepend( messageWrapper );
	jQuery( 'form.checkout' )
		.find( '.input-text, select, input:checkbox' )
		.trigger( 'validate' )
		.trigger( 'blur' );

	jQuery.scroll_to_notices( $container );
	jQuery( document.body ).trigger( 'checkout_error' );
};

/**
 * Initializes the appearance of the payment element by retrieving the UPE configuration
 * from the API and saving the appearance if it doesn't exist.
 *
 * If the appearance already exists, it is simply returned.
 *
 * @param {Object} api             The API object used to save the appearance.
 * @param {string} isBlockCheckout Whether the checkout is being used in a block context.
 *
 * @return {Object} The appearance object for the UPE.
 */
export const initializeUPEAppearance = ( api, isBlockCheckout = 'false' ) => {
	let appearance =
		isBlockCheckout === 'true'
			? getStripeServerData()?.blocksAppearance
			: getStripeServerData()?.appearance;

	// If appearance is empty, get a fresh copy and save it in a transient.
	if ( ! appearance ) {
		appearance = getAppearance();
		api.saveAppearance( appearance, isBlockCheckout );
	}

	return appearance;
};

/**
 * Gets the payment method name from the given payment method type.
 * For example, when passed 'card' returns 'stripe' and for 'ideal' returns 'stripe_ideal'.
 *
 * Defaults to 'stripe' if the given payment method type is not found in the list of payment methods constants.
 *
 * @param {string} paymentMethodType The payment method type ('card', 'ideal', etc.).
 *
 * @return {string} The payment method name.
 */
export const getPaymentMethodName = ( paymentMethodType ) => {
	return getPaymentMethodsConstants()[ paymentMethodType ] || 'stripe';
};

/**
 * Determines if the payment method is restricted to specific countries.
 *
 * @param {Object} upeElement The selector of the DOM element of particular payment method to mount the UPE element to.
 * @return {boolean} Whether the payment method is restricted to selected billing country.
 **/
export const isPaymentMethodRestrictedToLocation = ( upeElement ) => {
	const paymentMethodsConfig = getStripeServerData()?.paymentMethodsConfig;
	const paymentMethodType = upeElement.dataset.paymentMethodType;
	return !! paymentMethodsConfig[ paymentMethodType ].countries.length;
};

/**
 * @param {Object} upeElement The selector of the DOM element of particular payment method to mount the UPE element to.
 **/
export const togglePaymentMethodForCountry = ( upeElement ) => {
	const paymentMethodsConfig = getStripeServerData()?.paymentMethodsConfig;
	const paymentMethodType = upeElement.dataset.paymentMethodType;
	const supportedCountries =
		paymentMethodsConfig[ paymentMethodType ].countries;

	// in the case of "pay for order", there is no "billing country" input, so we need to rely on backend data.
	const billingCountry =
		document.getElementById( 'billing_country' )?.value ||
		getStripeServerData()?.customerData.billing_country ||
		'';

	const upeContainer = document.querySelector(
		'.payment_method_stripe_' + paymentMethodType
	);
	if ( supportedCountries.includes( billingCountry ) ) {
		upeContainer.style.display = 'block';
	} else {
		upeContainer.style.display = 'none';
	}
};
