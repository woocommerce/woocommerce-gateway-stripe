import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
import {
	getCustomerNote,
	getExpressCheckoutData,
	getExpressCheckoutAjaxURL,
} from 'wcstripe/express-checkout/utils';

/**
 * @typedef {import('./core').WCStripeApiClient} WCStripeApiClient
 */

/**
 * Fetches the express checkout wc-ajax nonce bundle, once per client.
 *
 * Only success is memoized: a failed fetch clears the cached promise and
 * rejects, so the next lookup (e.g. the actual wallet interaction after a
 * transient failure during warm-up) retries instead of being stuck with
 * an empty bundle for the page's lifetime.
 *
 * @param {WCStripeApiClient} client The API client.
 * @return {Promise<Object>} Promise resolving to the nonce map.
 */
export const expressCheckoutFetchNonces = ( client ) => {
	if ( ! client.expressCheckoutNoncesPromise ) {
		client.expressCheckoutNoncesPromise = client
			.request(
				getExpressCheckoutAjaxURL( 'get_express_checkout_nonces' ),
				{}
			)
			.then( ( response ) => response?.data ?? {} )
			.catch( ( error ) => {
				client.expressCheckoutNoncesPromise = null;
				throw error;
			} );
	}
	return client.expressCheckoutNoncesPromise;
};

/**
 * Resolves a single express checkout wc-ajax nonce.
 *
 * Rejects when the bundle fetch fails, like any other wc-ajax request in
 * this client; the fetch memo is cleared so a later call retries.
 *
 * @param {WCStripeApiClient} client The API client.
 * @param {string}            key    Nonce key.
 * @return {Promise<string|undefined>} Promise resolving to the nonce value.
 */
export const expressCheckoutGetNonce = async ( client, key ) => {
	const nonces = await expressCheckoutFetchNonces( client );
	return nonces[ key ];
};

/**
 * Normalizes address fields in WooCommerce supported format.
 *
 * @param {WCStripeApiClient} client          The API client.
 * @param {Object}            billingAddress  Billing address.
 * @param {Object}            shippingAddress Shipping address.
 * @return {Promise} Promise for the request to the server.
 */
export const expressCheckoutNormalizeAddress = async (
	client,
	billingAddress,
	shippingAddress
) =>
	client.request( getExpressCheckoutAjaxURL( 'normalize_address' ), {
		security: await expressCheckoutGetNonce( client, 'normalize_address' ),
		data: {
			billing_address: billingAddress,
			shipping_address: shippingAddress,
		},
	} );

/**
 * Add product to cart from product page (legacy version, non-StoreAPI).
 *
 * Fallback for booking products that can't be expressed as a Store API
 * `booking_configuration` (persons / customer-defined duration); the
 * representable ones go through `expressCheckoutAddToCart`.
 *
 * @param {WCStripeApiClient} client      The API client.
 * @param {Object}            productData Product data.
 * @return {Promise} Promise for the request to the server.
 */
export const expressCheckoutAddToCartLegacy = async ( client, productData ) =>
	client.request( getExpressCheckoutAjaxURL( 'add_to_cart' ), {
		security: await expressCheckoutGetNonce( client, 'add_to_cart' ),
		...productData,
	} );

/**
 * Empty the cart (legacy version, non-StoreAPI).
 *
 * @param {WCStripeApiClient} client           The API client.
 * @param {Object}            params           Parameters.
 * @param {number}            params.bookingId Booking ID.
 * @return {Promise} Promise for the request to the server.
 */
export const expressCheckoutEmptyCartLegacy = async (
	client,
	{ bookingId = null }
) =>
	client.request( getExpressCheckoutAjaxURL( 'clear_cart' ), {
		security: await expressCheckoutGetNonce( client, 'clear_cart' ),
		...( bookingId ? { booking_id: bookingId } : {} ),
	} );

/**
 * Get selected product data from variable product page.
 *
 * @param {WCStripeApiClient} client      The API client.
 * @param {Object}            productData Product data.
 * @return {Promise} Promise for the request to the server.
 */
export const expressCheckoutGetSelectedProductData = async (
	client,
	productData
) =>
	client.request( getExpressCheckoutAjaxURL( 'get_selected_product_data' ), {
		security: await expressCheckoutGetNonce(
			client,
			'get_selected_product_data'
		),
		...productData,
	} );

/**
 * Posts data to the Store API.
 *
 * @param {string} path    The path to post to.
 * @param {Object} data    The data to post.
 * @param {Object} headers The headers for the request.
 * @return {Promise} The promise for the request to the server.
 */
const postToStoreApi = ( path, data, headers = {} ) =>
	apiFetch( {
		method: 'POST',
		path,
		headers: {
			Nonce: getExpressCheckoutData( 'nonce' )?.wc_store_api,
			...headers,
		},
		data,
	} );

/**
 * Get cart items and total amount.
 *
 * @return {Promise} Promise for the request to the server.
 */
export const expressCheckoutGetCartDetails = () =>
	apiFetch( {
		method: 'GET',
		path: '/wc/store/v1/cart',
		security: getExpressCheckoutData( 'nonce' )?.wc_store_api,
	} );

/**
 * Add product to cart from product page.
 *
 * @param {Object} productData Product data.
 * @return {Promise} Promise for the request to the server.
 */
export const expressCheckoutAddToCart = ( productData ) => {
	// Rename qty to quantity to match StoreAPI expected parameter.
	const { qty, ...rest } = productData;
	const quantity = qty ?? 1;
	const storeApiProductData = {
		...rest,
		quantity,
	};

	const data = applyFilters(
		'wcstripe.express-checkout.cart-add-item',
		storeApiProductData
	);
	return postToStoreApi( '/wc/store/v1/cart/add-item', data );
};

/**
 * Creates order based on Express Checkout ECE payment method.
 *
 * @param {Object} orderData Order data.
 * @return {Promise} Promise for the request to the server.
 */
export const expressCheckoutECECreateOrder = ( orderData ) =>
	postToStoreApi(
		'/wc/store/v1/checkout',
		{
			...orderData,
			customer_note: getCustomerNote(),
		},
		{
			'X-WCSTRIPE-EXPRESS-CHECKOUT': true,
			'X-WCSTRIPE-EXPRESS-CHECKOUT-NONCE':
				getExpressCheckoutData( 'nonce' )
					?.wc_store_api_express_checkout,
		}
	);

/**
 * Pays for an order based on the Express Checkout payment method.
 *
 * @param {number} order        The order ID.
 * @param {Object} orderDetails Order details, including order key and billing email.
 * @param {Object} paymentData  Order data.
 * @return {Promise} Promise for the request to the server.
 */
export const expressCheckoutECEPayForOrder = (
	order,
	orderDetails,
	paymentData
) => {
	paymentData.shipping_address = orderDetails.shippingAddress;

	const billingEmail = orderDetails.billingEmail ?? '';
	const key = orderDetails.orderKey ?? '';
	const url = `/wc/store/v1/checkout/${ order }?key=${ key }&billing_email=${ billingEmail }`;
	return postToStoreApi( url, paymentData );
};
