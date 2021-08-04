/* global Stripe, wc_stripe_upe_params */

/**
 * External dependencies
 */
import $ from 'jquery';

/**
 * Internal dependencies
 */
import {
	normalizeOrderData,
	normalizeAddress,
	getStripeServerData,
} from '../stripe-utils';

/**
 * Construct WC AJAX endpoint URL.
 *
 * @param {string} endpoint Request endpoint URL.
 * @return {string} URL with interpolated endpoint.
 */
const getAjaxUrl = ( endpoint ) => {
	return getStripeServerData()
		?.ajax_url?.toString()
		?.replace( '%%endpoint%%', 'wc_stripe_' + endpoint );
};

export const getCartDetails = () => {
	const data = {
		security: getStripeServerData()?.nonce?.payment,
	};

	return $.ajax( {
		type: 'POST',
		data,
		url: getAjaxUrl( 'get_cart_details' ),
	} );
};

/**
 * Update shipping options.
 *
 * @param {Object} address Customer address.
 * @param {string} paymentRequestType Either 'apple_pay' or 'payment_request_api' depending on the type of request.
 */
export const updateShippingOptions = ( address, paymentRequestType ) => {
	const data = {
		security: getStripeServerData()?.nonce?.shipping,
		payment_request_type: paymentRequestType,
		is_product_page: getStripeServerData()?.is_product_page,
		...normalizeAddress( address ),
	};

	return $.ajax( {
		type: 'POST',
		data,
		url: getAjaxUrl( 'get_shipping_options' ),
	} );
};

export const updateShippingDetails = ( shippingOption ) => {
	const data = {
		security: getStripeServerData()?.nonce?.update_shipping,
		shipping_method: [ shippingOption.id ],
		is_product_page: getStripeServerData()?.is_product_page,
	};

	return $.ajax( {
		type: 'POST',
		data,
		url: getAjaxUrl( 'update_shipping_method' ),
	} );
};

export const createOrder = ( sourceEvent, paymentRequestType ) => {
	const data = normalizeOrderData( sourceEvent, paymentRequestType );

	return $.ajax( {
		type: 'POST',
		data,
		dataType: 'json',
		url: getAjaxUrl( 'create_order' ),
	} );
};

/**
 * Handles generic connections to the server and Stripe.
 */
export default class WCStripeAPI {

	/**
	 * Prepares the API.
	 *
	 * @param {Object}   options Options for the initialization.
	 * @param {Function} request A function to use for AJAX requests.
	 */
	constructor(options, request) {
		this.stripe = null;
		this.options = options;
		this.request = request;
	}

	/**
	 * Generates a new instance of Stripe.
	 *
	 * @return {Object} The Stripe Object.
	 */
	getStripe() {
		const {
			key,
			locale,
			isUPEEnabled,
		} = this.options;

		if ( ! this.stripe ) {
			if ( isUPEEnabled ) {
				this.stripe = new Stripe( key, {
					betas: [ 'payment_element_beta_1' ],
					locale,
				} );
			} else {
				this.stripe = new Stripe( key, {
					locale,
				} );
			}
		}
		return this.stripe;
	}

	/**
	 * Load Stripe for payment request button.
	 *
	 * @return {Promise} Promise with the Stripe object or an error.
	 */
	loadStripe() {
		return new Promise( ( resolve ) => {
			try {
				resolve( this.getStripe() );
			} catch ( error ) {
				// In order to avoid showing console error publicly to users,
				// we resolve instead of rejecting when there is an error.
				resolve( { error } );
			}
		} );
	}

	initSetupIntent() {
		console.error( 'TODO: Not implemented yet: initSetupIntent' );
	}

	/**
	 * Creates an intent based on a payment method.
	 *
	 * @param {int} orderId The id of the order if creating the intent on Order Pay page.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	createIntent( orderId ) {
		return this.request(
			getAjaxUrl( 'create_payment_intent' ),
			{
				stripe_order_id: orderId,
				_ajax_nonce: getStripeServerData()?.createPaymentIntentNonce,
			}
		)
			.then( ( response ) => {
				if ( ! response.success ) {
					throw response.data.error;
				}
				return response.data;
			} )
			.catch( ( error ) => {
				if ( error.message ) {
					throw error;
				} else {
					// Covers the case of error on the Ajax request.
					throw new Error( error.statusText );
				}
			} );
	}

	confirmIntent( url, savePaymentMethod ) {
		console.error( 'TODO: Not implemented yet: confirmIntent' );
		return true;
	}

	saveUPEAppearance( appearance ) {
		console.error( 'TODO: Not implemented yet: saveUPEAppearance' );
	}

}
