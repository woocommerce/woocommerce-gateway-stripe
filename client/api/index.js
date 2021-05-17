/* global wc_stripe_payment_request_params */

/**
 * External dependencies
 */
import $ from 'jquery';

/**
 * Internal dependencies
 */
import { normalizeOrderData, normalizeAddress } from '../blocks/stripe-utils';

const getAjaxUrl = ( endpoint ) => {
	return wc_stripe_payment_request_params.ajax_url
		.toString()
		.replace( '%%endpoint%%', 'wc_stripe_' + endpoint );
};

export const getCartDetails = () => {
	const data = {
		security: wc_stripe_payment_request_params.nonce.payment,
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
		security: wc_stripe_payment_request_params.nonce.shipping,
		payment_request_type: paymentRequestType,
		is_product_page: wc_stripe_payment_request_params.is_product_page,
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
		security: wc_stripe_payment_request_params.nonce.update_shipping,
		shipping_method: [ shippingOption.id ],
		is_product_page: wc_stripe_payment_request_params.is_product_page,
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
