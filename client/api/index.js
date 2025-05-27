/* global Stripe */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
import {
	getCustomerNote,
	getExpressCheckoutData,
	getExpressCheckoutAjaxURL,
	getRequiredFieldDataFromCheckoutForm,
} from 'wcstripe/express-checkout/utils';
import { getStripeServerData } from 'wcstripe/stripe-utils';
import {
	PAYMENT_INTENT_STATUS_REQUIRES_ACTION,
	PAYMENT_METHOD_CASHAPP,
} from 'wcstripe/stripe-utils/constants';

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
		const { key, locale } = this.options;
		if ( ! this.stripe ) {
			this.stripe = this.createStripe( key, locale );
		}
		return this.stripe;
	}

	createStripe( key, locale, betas = [] ) {
		const options = {
			locale,
			apiVersion: this.options.apiVersion,
		};

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
	 * @param {string} paymentMethodType The type of payment method.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	initSetupIntent( paymentMethodType ) {
		return this.request( this.getAjaxUrl( 'init_setup_intent' ), {
			payment_method_type: paymentMethodType,
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
	 * @param {number|null} orderId The id of the order if creating the intent on Order Pay page.
	 * @param {string|null} paymentMethodType The type of payment method.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	createIntent( orderId = null, paymentMethodType = null ) {
		return this.request( this.getAjaxUrl( 'create_payment_intent' ), {
			stripe_order_id: orderId,
			payment_method_type: paymentMethodType,
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
	 * Creates and confirms a setup intent.
	 *
	 * @param {Object} paymentMethod Payment method data.
	 * @param {Object} additionalData Additional data to send with the request.
	 *
	 * @return {Promise} Promise containing the setup intent.
	 */
	setupIntent( paymentMethod, additionalData = {} ) {
		return this.request(
			this.getAjaxUrl( 'create_and_confirm_setup_intent' ),
			{
				...additionalData,
				action: 'create_and_confirm_setup_intent',
				'wc-stripe-payment-method': paymentMethod.id,
				'wc-stripe-payment-type': paymentMethod.type,
				_ajax_nonce: this.options?.createAndConfirmSetupIntentNonce,
			}
		).then( ( response ) => {
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

				return this.getStripe()
					.confirmCashappSetup( response.data.client_secret, {
						return_url: returnURL,
					} )
					.then( ( confirmedSetupIntent ) => {
						const { setupIntent, error } = confirmedSetupIntent;
						if ( error ) {
							throw error;
						}

						if ( setupIntent.status === 'succeeded' ) {
							window.location.href = returnURL;
							return 'redirect_to_url';
						}

						// When the setup intent is incomplete, we need to notify the calling function that the set up didn't complete.
						return 'incomplete';
					} );
			}

			// Card Payments.
			return this.getStripe()
				.confirmSetup( {
					clientSecret: response.data.client_secret,
					redirect: 'if_required',
				} )
				.then( ( confirmedSetupIntent ) => {
					const { setupIntent, error } = confirmedSetupIntent;
					if ( error ) {
						throw error;
					}

					return setupIntent;
				} );
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
	 * @return {Object|true} An object containing the redirect URL on success and a flag indicating
	 *   if the page is the Pay for order page, or `true` if no confirmation is needed.
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
			? this.getStripe().confirmSetup( confirmArgs )
			: this.getStripe( true ).confirmPayment( confirmArgs );

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

				const ajaxCall = this.request( this.getAjaxUrl( ajaxAction ), {
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

	/**
	 * Saves the Stripe Payment Elements appearance settings in a transient on server.
	 *
	 * @param {Object} appearance      The appearance settings.
	 * @param {string} isBlockCheckout Whether the request is from the block checkout.
	 *
	 * @return {Promise} The final promise for the request to the server.
	 */
	saveAppearance( appearance, isBlockCheckout = 'false' ) {
		return this.request( this.getAjaxUrl( 'save_appearance' ), {
			appearance: JSON.stringify( appearance ),
			is_block_checkout: isBlockCheckout,
			theme_name: this.options?.theme_name,
			_ajax_nonce: this.options?.saveAppearanceNonce,
		} )
			.then( ( response ) => {
				return response.success;
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
	 * Submits shipping address to get available shipping options
	 * from Express Checkout ECE payment method.
	 *
	 * @param {Object} shippingAddress Shipping details.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutECECalculateShippingOptions( shippingAddress ) {
		return this.request(
			getExpressCheckoutAjaxURL( 'get_shipping_options' ),
			{
				security: getExpressCheckoutData( 'nonce' )?.shipping,
				is_product_page: getExpressCheckoutData( 'is_product_page' ),
				...shippingAddress,
			}
		);
	}

	/**
	 * Updates cart with selected shipping option.
	 *
	 * @param {Object} shippingOption Shipping option.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutUpdateShippingDetails( shippingOption ) {
		return this.request(
			getExpressCheckoutAjaxURL( 'update_shipping_method' ),
			{
				security: getExpressCheckoutData( 'nonce' )?.update_shipping,
				shipping_method: [ shippingOption.id ],
				is_product_page: getExpressCheckoutData( 'is_product_page' ),
			}
		);
	}

	/**
	 * Get cart items and total amount.
	 *
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutGetCartDetails() {
		return apiFetch( {
			method: 'GET',
			path: '/wc/store/v1/cart',
			security: getExpressCheckoutData( 'nonce' )?.wc_store_api,
		} );
	}

	/**
	 * Get cart items and total amount (legacy version, non-StoreAPI).
	 *
	 * @todo Remove this once WC 9.7.0 is the min. required version.
	 *
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutGetCartDetailsLegacy() {
		return this.request( getExpressCheckoutAjaxURL( 'get_cart_details' ), {
			security: getExpressCheckoutData( 'nonce' )?.get_cart_details,
		} );
	}

	/**
	 * Add product to cart from product page.
	 *
	 * @param {Object} productData Product data.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutAddToCart( productData ) {
		// Rename qty to quantity to match StoreAPI expected parameter.
		const { qty, ...rest } = productData;
		const quantity = qty ?? 1;
		const blocksApiProductData = {
			...rest,
			quantity,
		};

		const data = applyFilters(
			'wcstripe.express-checkout.cart-add-item',
			blocksApiProductData
		);
		return this.postToBlocksAPI( '/wc/store/v1/cart/add-item', data );
	}

	/**
	 * Add product to cart from product page (legacy version, non-StoreAPI).
	 *
	 * @todo Remove this once WC 9.7.0 is the min. required version.
	 *
	 * @param {Object} productData Product data.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutAddToCartLegacy( productData ) {
		return this.request( getExpressCheckoutAjaxURL( 'add_to_cart' ), {
			security: getExpressCheckoutData( 'nonce' )?.add_to_cart,
			...productData,
		} );
	}

	/**
	 * Empty the cart.
	 *
	 * @param {number} bookingId Booking ID.
	 * @return {Promise} Promise for the request to the server.
	 */
	async expressCheckoutEmptyCart( bookingId ) {
		try {
			const cartData = await apiFetch( {
				method: 'GET',
				path: '/wc/store/v1/cart',
				headers: {
					Nonce: getExpressCheckoutData( 'nonce' )?.wc_store_api,
				},
			} );
			const removeItemsPromises = cartData.items.map( ( item ) => {
				return this.postToBlocksAPI( '/wc/store/v1/cart/remove-item', {
					key: item.key,
					booking_id: bookingId,
				} );
			} );

			await Promise.all( removeItemsPromises );
		} catch ( e ) {
			// let's ignore the error, it's likely not going to be relevant.
		}
	}

	/**
	 * Empty the cart (legacy version, non-StoreAPI).
	 *
	 * @param {Object} params Parameters.
	 * @param {number} params.bookingId Booking ID.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutEmptyCartLegacy( { bookingId = null } ) {
		return this.request( getExpressCheckoutAjaxURL( 'clear_cart' ), {
			security: getExpressCheckoutData( 'nonce' )?.clear_cart,
			...( bookingId ? { booking_id: bookingId } : {} ),
		} );
	}

	/**
	 * Creates order based on Express Checkout ECE payment method.
	 *
	 * @param {Object} paymentData Order data.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutECECreateOrder( paymentData ) {
		return this.postToBlocksAPI( '/wc/store/v1/checkout', {
			...getRequiredFieldDataFromCheckoutForm( paymentData ),
			customer_note: getCustomerNote(),
		} );
	}

	/**
	 * Pays for an order based on the Express Checkout payment method.
	 *
	 * @param {number} order The order ID.
	 * @param {Object} orderDetails Order details, including order key and billing email.
	 * @param {Object} paymentData Order data.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutECEPayForOrder( order, orderDetails, paymentData ) {
		paymentData.shipping_address = orderDetails.shippingAddress;

		const billingEmail = orderDetails.billingEmail ?? '';
		const key = orderDetails.orderKey ?? '';
		const url = `/wc/store/v1/checkout/${ order }?key=${ key }&billing_email=${ billingEmail }`;
		return this.postToBlocksAPI( url, paymentData );
	}

	/**
	 * Posts data to the Blocks API.
	 *
	 * @param {string} path The path to post to.
	 * @param {Object} data The data to post.
	 * @return {Promise} The promise for the request to the server.
	 */
	postToBlocksAPI( path, data ) {
		return apiFetch( {
			method: 'POST',
			path,
			headers: {
				Nonce: getExpressCheckoutData( 'nonce' )?.wc_store_api,
			},
			data,
		} );
	}

	/**
	 * Get selected product data from variable product page.
	 *
	 * @param {Object} productData Product data.
	 * @return {Promise} Promise for the request to the server.
	 */
	expressCheckoutGetSelectedProductData( productData ) {
		return this.request(
			getExpressCheckoutAjaxURL( 'get_selected_product_data' ),
			{
				security: getExpressCheckoutData( 'nonce' )
					?.get_selected_product_data,
				...productData,
			}
		);
	}
}
