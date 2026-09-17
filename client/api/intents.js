import { getAjaxUrl } from './core';
import { getStripe } from './stripe';
import { __ } from '@wordpress/i18n';
import { getStripeServerData } from 'wcstripe/stripe-utils';
import {
	PAYMENT_INTENT_STATUS_REQUIRES_ACTION,
	PAYMENT_METHOD_CASHAPP,
} from 'wcstripe/stripe-utils/constants';

/**
 * @typedef {import('./core').WCStripeApiClient} WCStripeApiClient
 */

/**
 * Returns a user-friendly error message for a jQuery XHR error object.
 *
 * @param {Object} error A jQuery XHR error object with a statusText property.
 * @return {string} A user-friendly error message.
 */
const getFriendlyErrorMessage = ( error ) => {
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
};

/**
 * Creates a setup intent without confirming it.
 *
 * @param {WCStripeApiClient} client            The API client.
 * @param {string}            paymentMethodType The type of payment method.
 *
 * @return {Promise} The final promise for the request to the server.
 */
export const initSetupIntent = ( client, paymentMethodType ) =>
	client
		.request( getAjaxUrl( client, 'init_setup_intent' ), {
			payment_method_type: paymentMethodType,
			_ajax_nonce: client.options?.createSetupIntentNonce,
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
				throw new Error( getFriendlyErrorMessage( error.statusText ) );
			}
		} );

/**
 * Creates an intent based on a payment method.
 *
 * @param {WCStripeApiClient} client            The API client.
 * @param {number|null}       orderId           The id of the order if creating the intent on Order Pay page.
 * @param {string|null}       paymentMethodType The type of payment method.
 * @param {string|null}       orderKey          The key of the order if creating the intent on Order Pay page.
 *
 * @return {Promise} The final promise for the request to the server.
 */
export const createIntent = (
	client,
	orderId = null,
	paymentMethodType = null,
	orderKey = null
) =>
	client
		.request( getAjaxUrl( client, 'create_payment_intent' ), {
			stripe_order_id: orderId,
			payment_method_type: paymentMethodType,
			order_key: orderKey,
			_ajax_nonce: client.options?.createPaymentIntentNonce,
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
				throw new Error( getFriendlyErrorMessage( error.statusText ) );
			}
		} );

/**
 * Creates and confirms a setup intent.
 *
 * @param {WCStripeApiClient} client         The API client.
 * @param {Object}            paymentMethod  Payment method data.
 * @param {Object}            additionalData Additional data to send with the request.
 *
 * @return {Promise} Promise containing the setup intent.
 */
export const setupIntent = ( client, paymentMethod, additionalData = {} ) =>
	client
		.request( client.options?.wp_ajax_url, {
			...additionalData,
			action: 'wc_stripe_create_and_confirm_setup_intent',
			'wc-stripe-payment-method': paymentMethod.id,
			'wc-stripe-payment-type': paymentMethod.type,
			_ajax_nonce: client.options?.createAndConfirmSetupIntentNonce,
		} )
		.then( ( response ) => {
			if ( ! response.success ) {
				throw response.data.error;
			}

			if ( response.data.status === 'succeeded' ) {
				// No need for further authentication.
				return response.data;
			}

			if (
				response.data.status ===
					PAYMENT_INTENT_STATUS_REQUIRES_ACTION &&
				response.data.next_action.type === 'redirect_to_url'
			) {
				window.location.href =
					response.data.next_action.redirect_to_url.url;

				return response.data.next_action.type;
			}

			if ( response.data.payment_type === PAYMENT_METHOD_CASHAPP ) {
				// Cash App Payments.
				const returnURL = decodeURIComponent(
					response.data.return_url
				);

				return getStripe( client )
					.confirmCashappSetup( response.data.client_secret, {
						return_url: returnURL,
					} )
					.then( ( confirmedSetupIntent ) => {
						const { setupIntent: confirmedIntent, error } =
							confirmedSetupIntent;
						if ( error ) {
							throw error;
						}

						if ( confirmedIntent.status === 'succeeded' ) {
							window.location.href = returnURL;
							return 'redirect_to_url';
						}

						// When the setup intent is incomplete, we need to notify the calling function that the set up didn't complete.
						return 'incomplete';
					} );
			}

			// Card Payments.
			return getStripe( client )
				.confirmSetup( {
					clientSecret: response.data.client_secret,
					redirect: 'if_required',
				} )
				.then( ( confirmedSetupIntent ) => {
					const { setupIntent: confirmedIntent, error } =
						confirmedSetupIntent;
					if ( error ) {
						throw error;
					}

					return confirmedIntent;
				} );
		} );

/**
 * Extracts the details about a payment intent from the redirect URL,
 * and displays the intent confirmation modal (if needed).
 *
 * @param {WCStripeApiClient} client              The API client.
 * @param {string}            redirectUrl         The redirect URL, returned from the server.
 * @param {string}            paymentMethodToSave The ID of a Payment Method if it should be saved (optional).
 * @return {Object|true} An object containing the redirect URL on success and a flag indicating
 *   if the page is the Pay for order page, or `true` if no confirmation is needed.
 */
export const confirmIntent = ( client, redirectUrl, paymentMethodToSave ) => {
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

	const isChangingPayment = getStripeServerData()?.isChangingPayment;

	// If we're on the Pay for Order page, get the order ID
	// directly from the server data instead of relying on the hash.
	if ( isChangingPayment ) {
		orderId = getStripeServerData().orderId;
	}

	// After processing the intent, trigger the appropriate AJAX action.
	const ajaxAction = isChangingPayment
		? 'confirm_change_payment'
		: 'update_order_status';

	const confirmArgs = {
		clientSecret,
		redirect: 'if_required',
	};

	const confirmAction = isSetupIntent
		? getStripe( client ).confirmSetup( confirmArgs )
		: getStripe( client ).confirmPayment( confirmArgs );

	const request = confirmAction
		// ToDo: Switch to an async function once it works with webpack.
		.then( ( result ) => {
			const intentId =
				( result.paymentIntent && result.paymentIntent.id ) ||
				( result.setupIntent && result.setupIntent.id ) ||
				( result.error &&
					result.error.payment_intent &&
					result.error.payment_intent.id ) ||
				( result.error.setup_intent && result.error.setup_intent.id );

			const ajaxCall = client.request( getAjaxUrl( client, ajaxAction ), {
				order_id: orderId,
				// Update the current order status nonce with the new one to ensure that the update
				// order status call works when a guest user creates an account during checkout.
				intent_id: intentId,
				payment_method_id: paymentMethodToSave || null,
				_ajax_nonce: nonce,
			} );

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
		isChangingPayment,
	};
};

/**
 * Tells the server that the intent used to change a subscription's payment method was confirmed.
 *
 * @param {WCStripeApiClient} client                 The API client.
 * @param {Object}            params                 Parameters.
 * @param {string}            params.orderId         The ID of the order (the subscription) being updated.
 * @param {string}            params.intentId        The ID of the confirmed intent.
 * @param {string}            params.paymentMethodId The ID of the payment method attached to the intent, if any.
 * @param {string}            params.nonce           The nonce the server issued for this confirmation.
 * @return {Promise} Promise for the request to the server.
 */
export const confirmChangePayment = (
	client,
	{ orderId, intentId, paymentMethodId, nonce }
) =>
	client.request( getAjaxUrl( client, 'confirm_change_payment' ), {
		order_id: orderId,
		intent_id: intentId,
		payment_method_id: paymentMethodId || null,
		_ajax_nonce: nonce,
	} );

/**
 * Process checkout and update payment intent via AJAX.
 *
 * @param {WCStripeApiClient} client          The API client.
 * @param {string}            paymentIntentId ID of payment intent to be updated.
 * @param {Object}            fields          Checkout fields.
 * @return {Promise} Promise containing redirect URL for UPE element.
 */
export const processCheckout = ( client, paymentIntentId, fields ) =>
	client
		.request( getAjaxUrl( client, 'checkout', '' ), {
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
				throw new Error( getFriendlyErrorMessage( error.statusText ) );
			}
		} );

/**
 * Updates order status, if there is an error while confirming intent.
 *
 * @param {WCStripeApiClient} client   The API client.
 * @param {string}            intentId The id of the Payment/Setup Intent.
 * @param {number}            orderId  The id of the WC_Order.
 */
export const updateFailedOrder = ( client, intentId, orderId ) => {
	client
		.request( getAjaxUrl( client, 'update_failed_order' ), {
			intent_id: intentId,
			order_id: orderId,
			_ajax_nonce: client.options?.updateFailedOrderNonce,
		} )
		.catch( () => {
			// If something goes wrong here,
			// we would still rather throw the Stripe error rather than this one.
		} );
};
