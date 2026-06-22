/**
 * External dependencies
 */
import { getExpressCheckoutData } from './utils';
import apiFetch from '@wordpress/api-fetch';

export default class ExpressCheckoutCartApi {
	// Stores headers that are updated from response (e.g., refreshed nonce).
	cartRequestHeaders = {};

	/**
	 * Makes a request to the Store API.
	 *
	 * Uses `parse: false` so we can access response headers for nonce refresh,
	 * then manually parses the JSON body.
	 *
	 * @param {Object} options Options to pass to `apiFetch`.
	 * @return {Promise} Parsed JSON response.
	 */
	async _request( options ) {
		const response = await apiFetch( {
			...options,
			parse: false,
			headers: {
				Nonce: getExpressCheckoutData( 'nonce' )?.wc_store_api,
				'X-WCSTRIPE-EXPRESS-CHECKOUT': 'true',
				'X-WCSTRIPE-EXPRESS-CHECKOUT-NONCE':
					getExpressCheckoutData( 'nonce' )
						?.wc_store_api_express_checkout,
				...this.cartRequestHeaders,
				...options.headers,
			},
		} );

		const newNonce = response.headers.get( 'Nonce' );
		if ( newNonce ) {
			this.cartRequestHeaders = {
				Nonce: newNonce,
			};
		}

		return response.json();
	}

	/**
	 * Returns the freshest Store API nonce we already hold, without a request.
	 *
	 * The page-localized nonce can be stale or absent for guests on full-page-cached
	 * checkout (logged-in users bypass the cache, so only guests hit the nonce error).
	 * Every cart response rotates the `Nonce` header, which `updateCustomer` /
	 * `selectShippingRate` store here; prefer that over the page nonce.
	 *
	 * @return {string|undefined} The freshest Store API nonce available.
	 */
	getStoreApiNonce() {
		return (
			this.cartRequestHeaders.Nonce ||
			getExpressCheckoutData( 'nonce' )?.wc_store_api
		);
	}

	/**
	 * Returns the cart object.
	 *
	 * @return {Promise} Cart response object.
	 */
	async getCart() {
		return await this._request( {
			method: 'GET',
			path: '/wc/store/v1/cart',
		} );
	}

	/**
	 * Updates customer data (shipping/billing address) and returns the full cart response.
	 *
	 * @param {Object} customerData Customer data to update.
	 * @return {Promise} Cart response on success, or an error response on failure.
	 */
	async updateCustomer( customerData ) {
		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/update-customer',
			data: customerData,
		} );
	}

	/**
	 * Selects a shipping rate for a package, then returns the full cart response.
	 *
	 * @param {Object} shippingRate The selected shipping rate ({ rate_id, package_id }).
	 * @return {Promise} Cart response on success, or an error response on failure.
	 */
	async selectShippingRate( shippingRate ) {
		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/select-shipping-rate',
			data: shippingRate,
		} );
	}
}
