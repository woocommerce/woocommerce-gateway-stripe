/* global wc */

/**
 * Internal dependencies
 */
import {
	buildAjaxURL,
	getConfig
} from "../utils";

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
		this.options = options;
		this.stripe = null;
		this.request = request;
	}

	/**
	 * Generates a new instance of Stripe.
	 *
	 * @return {Object} The Stripe Object.
	 */
	getStripe() {
		const {
			publishableKey,
			locale,
			isUPEEnabled,
		} = this.options;

		if ( ! this.stripe ) {
			if ( isUPEEnabled ) {
				this.stripe = new Stripe( publishableKey, {
					betas: [ 'payment_element_beta_1' ],
					locale,
				} );
			} else {
				this.stripe = new Stripe( publishableKey, {
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
			buildAjaxURL( getConfig( 'ajaxUrl' ), 'create_payment_intent' ),
			{
				stripe_order_id: orderId,
				_ajax_nonce: getConfig( 'createPaymentIntentNonce' ),
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
