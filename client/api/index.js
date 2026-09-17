import { getAjaxUrl } from './core';
import {
	expressCheckoutAddToCart,
	expressCheckoutAddToCartLegacy,
	expressCheckoutECECreateOrder,
	expressCheckoutECEPayForOrder,
	expressCheckoutEmptyCartLegacy,
	expressCheckoutFetchNonces,
	expressCheckoutGetCartDetails,
	expressCheckoutGetNonce,
	expressCheckoutGetSelectedProductData,
	expressCheckoutNormalizeAddress,
} from './express-checkout';
import {
	confirmIntent,
	createIntent,
	initSetupIntent,
	processCheckout,
	setupIntent,
	updateFailedOrder,
} from './intents';
import { getStripe, loadStripe } from './stripe';

/**
 * Object-style access to the functions in `./core`, `./stripe`, `./intents` and
 * `./express-checkout`.
 *
 * Importing this class pulls every one of those modules into the bundle, because a
 * bundler cannot tell which methods of an instance get called. Import the individual
 * functions instead and pass them a client from `createApiClient()`; an instance of
 * this class is accepted in its place.
 */
export default class WCStripeAPI {
	/**
	 * Prepares the API.
	 *
	 * @param {Object}   options Options for the initialization.
	 * @param {Function} request A function to use for AJAX requests.
	 */
	constructor( options, request ) {
		this.options = options;
		this.request = request;
		this.expressCheckoutNoncesPromise = null;
	}

	getAjaxUrl( endpoint, prefix ) {
		return getAjaxUrl( this, endpoint, prefix );
	}

	getStripe() {
		return getStripe( this );
	}

	loadStripe() {
		return loadStripe( this );
	}

	initSetupIntent( paymentMethodType ) {
		return initSetupIntent( this, paymentMethodType );
	}

	createIntent( orderId, paymentMethodType, orderKey ) {
		return createIntent( this, orderId, paymentMethodType, orderKey );
	}

	setupIntent( paymentMethod, additionalData ) {
		return setupIntent( this, paymentMethod, additionalData );
	}

	confirmIntent( redirectUrl, paymentMethodToSave ) {
		return confirmIntent( this, redirectUrl, paymentMethodToSave );
	}

	processCheckout( paymentIntentId, fields ) {
		return processCheckout( this, paymentIntentId, fields );
	}

	updateFailedOrder( intentId, orderId ) {
		updateFailedOrder( this, intentId, orderId );
	}

	expressCheckoutFetchNonces() {
		return expressCheckoutFetchNonces( this );
	}

	expressCheckoutGetNonce( key ) {
		return expressCheckoutGetNonce( this, key );
	}

	expressCheckoutNormalizeAddress( billingAddress, shippingAddress ) {
		return expressCheckoutNormalizeAddress(
			this,
			billingAddress,
			shippingAddress
		);
	}

	expressCheckoutGetCartDetails() {
		return expressCheckoutGetCartDetails();
	}

	expressCheckoutAddToCart( productData ) {
		return expressCheckoutAddToCart( productData );
	}

	expressCheckoutAddToCartLegacy( productData ) {
		return expressCheckoutAddToCartLegacy( this, productData );
	}

	expressCheckoutEmptyCartLegacy( params ) {
		return expressCheckoutEmptyCartLegacy( this, params );
	}

	expressCheckoutECECreateOrder( orderData ) {
		return expressCheckoutECECreateOrder( orderData );
	}

	expressCheckoutECEPayForOrder( order, orderDetails, paymentData ) {
		return expressCheckoutECEPayForOrder(
			order,
			orderDetails,
			paymentData
		);
	}

	expressCheckoutGetSelectedProductData( productData ) {
		return expressCheckoutGetSelectedProductData( this, productData );
	}
}
