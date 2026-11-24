import { storeApiFetch } from './store-api';

export default class ExpressCheckoutCartApi {
	/**
	 * Makes a request to the Store API, flagged as an express checkout one.
	 *
	 * @param {Object} options Options to pass to `apiFetch`.
	 * @return {Promise} Parsed JSON response.
	 */
	async _request( options ) {
		const response = await storeApiFetch( options, {
			expressCheckoutContext: true,
		} );

		return response.json();
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
