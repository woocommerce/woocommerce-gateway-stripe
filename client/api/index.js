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
} from '../blocks/stripe-utils';

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
