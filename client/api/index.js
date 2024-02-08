/* global Stripe */
import { __ } from '@wordpress/i18n';

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
	constructor( options, request ) {
		this.stripe = null;
		this.options = options;
		this.request = request;
	}

	/**
	 * Construct WC AJAX endpoint URL.
	 *
	 * @param {string} endpoint Request endpoint URL.
	 * @param {string} prefix Endpoint URI prefix (default: 'wc_stripe_').
	 * @return {string} URL with interpolated endpoint.
	 */
	getAjaxUrl( endpoint, prefix = 'wc_stripe_' ) {
		return this.options?.ajax_url
			?.toString()
			?.replace( '%%endpoint%%', prefix + endpoint );
	}

	getFriendlyErrorMessage( error ) {
		// error is a jqXHR and statusText is one of "timeout", "error", "abort", and "parsererror".
		switch ( error.statusText ) {
			case 'timeout':
				return __(
					'A timeout occurred while connecting to the server. Please try again.',
					'woocommerce-gateway-stripe'
				);
			case 'abort':
				return __(
					'The connection to the server was aborted. Please try again.',
					'woocommerce-gateway-stripe'
				);
			case 'error':
			default:
				return __(
					'An error occurred while connecting to the server. Please try again.',
					'woocommerce-gateway-stripe'
				);
		}
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
			paymentMethodsConfig,
		} = this.options;
		const isStripeLinkEnabled =
			undefined !== paymentMethodsConfig.card &&
			undefined !== paymentMethodsConfig.link;
		if ( ! this.stripe ) {
			if ( isUPEEnabled && isStripeLinkEnabled ) {
				this.stripe = this.createStripe( key, locale, [
					'link_autofill_modal_beta_1',
				] );
			} else {
				this.stripe = this.createStripe( key, locale );
			}
		}
		return this.stripe;
	}

	createStripe( key, locale, betas = [] ) {
		const options = { locale };

		if ( betas.length ) {
			options.betas = betas;
		}

		return new Stripe( key, options );
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

	/**
	 * Creates a setup intent without confirming it.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	initSetupIntent() {
		return this.request( this.getAjaxUrl( 'init_setup_intent' ), {
			_ajax_nonce: this.options?.createSetupIntentNonce,
		} )
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
					throw new Error(
						this.getFriendlyErrorMessage( error.statusText )
					);
				}
			} );
	}

	/**
	 * Creates an intent based on a payment method.
	 *
	 * @param {number} orderId The id of the order if creating the intent on Order Pay page.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	createIntent( orderId ) {
		return this.request( this.getAjaxUrl( 'create_payment_intent' ), {
			stripe_order_id: orderId,
			_ajax_nonce: this.options?.createPaymentIntentNonce,
		} )
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
					throw new Error(
						this.getFriendlyErrorMessage( error.statusText )
					);
				}
			} );
	}

	/**
	 * Updates a payment intent with data from order: customer, level3 data and and maybe sets the payment for future use.
	 *
	 * @param {string} intentId The id of the payment intent.
	 * @param {number} orderId The id of the order.
	 * @param {string} savePaymentMethod 'yes' if saving.
	 * @param {string} selectedUPEPaymentType The name of the selected UPE payment type or empty string.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	updateIntent(
		intentId,
		orderId,
		savePaymentMethod,
		selectedUPEPaymentType
	) {
		// Don't update setup intents.
		if ( intentId.includes( 'seti_' ) ) {
			return;
		}

		return this.request( this.getAjaxUrl( 'update_payment_intent' ), {
			stripe_order_id: orderId,
			wc_payment_intent_id: intentId,
			save_payment_method: savePaymentMethod,
			selected_upe_payment_type: selectedUPEPaymentType,
			_ajax_nonce: this.options?.updatePaymentIntentNonce,
		} )
			.then( ( response ) => {
				if ( response.result === 'failure' ) {
					throw new Error( response.messages );
				}
				return response;
			} )
			.catch( ( error ) => {
				if ( error.message ) {
					throw error;
				} else {
					// Covers the case of error on the Ajaxrequest.
					throw new Error(
						this.getFriendlyErrorMessage( error.statusText )
					);
				}
			} );
	}

	/**
	 * Extracts the details about a payment intent from the redirect URL,
	 * and displays the intent confirmation modal (if needed).
	 *
	 * @param {string} redirectUrl The redirect URL, returned from the server.
	 * @param {string} paymentMethodToSave The ID of a Payment Method if it should be saved (optional).
	 * @return {string|true} A redirect URL on success, or `true` if no confirmation is needed.
	 */
	confirmIntent( redirectUrl, paymentMethodToSave ) {
		const partials = redirectUrl.match(
			/#wc-stripe-confirm-(pi|si):(.+):(.+):(.+)$/
		);

		if ( ! partials ) {
			return true;
		}

		const isSetupIntent = partials[ 1 ] === 'si';
		let orderId = partials[ 2 ];
		const clientSecret = partials[ 3 ];
		const nonce = partials[ 4 ];

		const orderPayIndex = redirectUrl.indexOf( 'order-pay' );
		const isOrderPage = orderPayIndex > -1;

		// If we're on the Pay for Order page, get the order ID
		// directly from the URL instead of relying on the hash.
		// The checkout URL does not contain the string 'order-pay'.
		// The Pay for Order page contains the string 'order-pay' and
		// can have these formats:
		// Plain permalinks:
		// /?page_id=7&order-pay=189&pay_for_order=true&key=wc_order_key
		// Non-plain permalinks:
		// /checkout/order-pay/189/
		// Match for consecutive digits after the string 'order-pay' to get the order ID.
		const orderIdPartials =
			isOrderPage &&
			redirectUrl.substring( orderPayIndex ).match( /\d+/ );
		if ( orderIdPartials ) {
			orderId = orderIdPartials[ 0 ];
		}

		const confirmAction = isSetupIntent
			? this.getStripe().confirmCardSetup( clientSecret )
			: this.getStripe( true ).confirmCardPayment( clientSecret );

		const request = confirmAction
			// ToDo: Switch to an async function once it works with webpack.
			.then( ( result ) => {
				const intentId =
					( result.paymentIntent && result.paymentIntent.id ) ||
					( result.setupIntent && result.setupIntent.id ) ||
					( result.error &&
						result.error.payment_intent &&
						result.error.payment_intent.id ) ||
					( result.error.setup_intent &&
						result.error.setup_intent.id );

				const ajaxCall = this.request(
					this.getAjaxUrl( 'update_order_status' ),
					{
						order_id: orderId,
						// Update the current order status nonce with the new one to ensure that the update
						// order status call works when a guest user creates an account during checkout.
						intent_id: intentId,
						payment_method_id: paymentMethodToSave || null,
						_ajax_nonce: nonce,
					}
				);

				return [ ajaxCall, result.error ];
			} )
			.then( ( [ verificationCall, originalError ] ) => {
				if ( originalError ) {
					throw originalError;
				}

				return verificationCall.then( ( response ) => {
					if ( ! response.success ) {
						throw response.data.error;
					}
					return response.data.return_url;
				} );
			} );

		return {
			request,
			isOrderPage,
		};
	}

	/**
	 * Process checkout and update payment intent via AJAX.
	 *
	 * @param {string} paymentIntentId ID of payment intent to be updated.
	 * @param {Object} fields Checkout fields.
	 * @return {Promise} Promise containing redirect URL for UPE element.
	 */
	processCheckout( paymentIntentId, fields ) {
		return this.request( this.getAjaxUrl( 'checkout', '' ), {
			...fields,
			wc_payment_intent_id: paymentIntentId,
		} )
			.then( ( response ) => {
				if ( response.result === 'failure' ) {
					throw new Error( response.messages );
				}
				return response;
			} )
			.catch( ( error ) => {
				if ( error.message ) {
					throw error;
				} else {
					// Covers the case of error on the Ajax request.
					throw new Error(
						this.getFriendlyErrorMessage( error.statusText )
					);
				}
			} );
	}

	/**
	 * Updates order status, if there is an error while confirming intent.
	 *
	 * @param {string} intentId The id of the Payment/Setup Intent.
	 * @param {number} orderId The id of the WC_Order.
	 */
	updateFailedOrder( intentId, orderId ) {
		this.request( this.getAjaxUrl( 'update_failed_order' ), {
			intent_id: intentId,
			order_id: orderId,
			_ajax_nonce: this.options?.updateFailedOrderNonce,
		} ).catch( () => {
			// If something goes wrong here,
			// we would still rather throw the Stripe error rather than this one.
		} );
	}
}
