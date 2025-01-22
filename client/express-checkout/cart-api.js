/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
import { addQueryArgs } from '@wordpress/url';
import { getExpressCheckoutData } from './utils';

export default class ExpressCheckoutCartApi {
	// Used on product pages to interact with an anonymous cart.
	// This anonymous cart is separate from the customer's cart, which might contain additional products.
	// This functionality is also useful to calculate product/shipping pricing (and shipping needs)
	// for compatibility scenarios with other plugins (like WC Bookings, Product Add-Ons, WC Deposits, etc.).
	cartRequestHeaders = {};

	/**
	 * Makes a request to the API.
	 *
	 * @param {Object} options The options to pass to `apiFetch`.
	 * @return {Promise} Result from `apiFetch`.
	 */
	async _request( options ) {
		const response = await apiFetch( {
			...options,
			parse: false,
			path: addQueryArgs( options.path, {
				// `wc_stripe_express_checkout_params` will always be defined if this file is needed.
				// If there's an issue with it, ask yourself why this file is queued and `wcpayExpressCheckoutParams` isn't present.
				currency: getExpressCheckoutData(
					'checkout'
				).currency_code.toUpperCase(),
			} ),
			headers: {
				// the Store API nonce, which could later be overwritten in subsequent requests.
				Nonce: getExpressCheckoutData( 'nonce' ).wc_store_api,
				...this.cartRequestHeaders,
				...options.headers,
			},
		} );

		this.cartRequestHeaders = {
			// used as a reference on shortcode cart/checkout pages, where the Nonce might not be automatically added to the request.
			Nonce: response.headers.get( 'Nonce' ),
		};

		return response.json();
	}

	/**
	 * Creates an order from the cart object.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/checkout.md#process-order-and-payment
	 *
	 * @param {number} order The order ID (if paying for an existing order),
	 * @param {{
	 *          billing_address: Object,
	 *          shipping_address: Object,
	 *          customer_note: string?,
	 *          payment_method: string,
	 *          payment_data: Array,
	 *        }} paymentData Additional payment data to place the order.
	 * @return {Promise} Result of the order creation request.
	 */
	async placeOrder( order, paymentData ) {
		const path =
			order > 0
				? `/wc/store/v1/checkout/${ order }`
				: '/wc/store/v1/checkout';
		return await this._request( {
			method: 'POST',
			path,
			headers: {
				...this.cartRequestHeaders,
			},
			data: paymentData,
		} );
	}

	/**
	 * Returns the customer's cart object.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#get-cart
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
	 * Creates and returns a new cart object. The response type is the same as `getCart()`.
	 */
	useSeparateCart() {
		this.cartRequestHeaders = {};
	}

	/**
	 * Update customer data and return the full cart response, or an error.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#update-customer
	 *
	 * @param {{
	 *          billing_address: Object?,
	 *          shipping_address: Object?,
	 *        }} customerData Customer data to update.
	 * @return {Promise} Cart Response on success, or an Error Response on failure.
	 */
	async updateCustomer( customerData ) {
		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/update-customer',
			headers: this.cartRequestHeaders,
			data: customerData,
		} );
	}

	/**
	 * Selects an available shipping rate for a package, then returns the full cart response, or an error
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#select-shipping-rate
	 *
	 * @param {{rate_id: string, package_id: number}} shippingRate The selected shipping rate.
	 * @return {Promise} Cart Response on success, or an Error Response on failure.
	 */
	async selectShippingRate( shippingRate ) {
		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/select-shipping-rate',
			data: shippingRate,
		} );
	}

	/**
	 * Add an item to the cart and return the full cart response, or an error.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#add-item
	 *
	 * @return {Promise} Cart Response on success, or an Error Response on failure.
	 */
	async addProductToCart() {
		const productData = {
			// can be modified in case of variable products, WC bookings plugin, etc.
			id: jQuery( '.single_add_to_cart_button' ).val(),
			quantity: parseInt( jQuery( '.quantity .qty' ).val(), 10 ) || 1,
			// can be modified in case of variable products, WC bookings plugin, etc.
			variation: [],
		};

		return await this._request( {
			method: 'POST',
			path: '/wc/store/v1/cart/add-item',
			data: applyFilters(
				'wcstripe.express-checkout.cart-add-item',
				productData
			),
		} );
	}

	/**
	 * Removes all items from the cart and clears the cart headers.
	 * See https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/cart.md#remove-item
	 *
	 * @return {undefined}
	 */
	async emptyCart() {
		try {
			// TODO: this could be optimized, if we could cache the previous request of cart data for later reference.
			const cartData = await this._request( {
				method: 'GET',
				path: '/wc/store/v1/cart',
			} );

			const removeItemsPromises = cartData.items.map( ( item ) => {
				return this._request( {
					method: 'POST',
					path: '/wc/store/v1/cart/remove-item',
					data: {
						key: item.key,
					},
				} );
			} );

			await Promise.all( removeItemsPromises );
		} catch ( e ) {
			// let's ignore the error, it's likely not going to be relevant.
		}
	}
}
