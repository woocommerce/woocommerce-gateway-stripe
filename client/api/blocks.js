import $ from 'jquery';
import {
	normalizeOrderData,
	normalizeAddress,
} from 'wcstripe/blocks/normalize';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/**
 * Construct WC AJAX endpoint URL.
 *
 * @param {string} endpoint Request endpoint URL.
 * @param {string} prefix Endpoint URI prefix (default: 'wc_stripe_').
 * @return {string} URL with interpolated endpoint.
 */
const getAjaxUrl = ( endpoint, prefix = 'wc_stripe_' ) => {
	return getBlocksConfiguration()
		?.ajax_url?.toString()
		?.replace( '%%endpoint%%', prefix + endpoint );
};

export const getCartDetails = () => {
	const data = {
		security: getBlocksConfiguration()?.nonce?.payment,
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
		security: getBlocksConfiguration()?.nonce?.shipping,
		payment_request_type: paymentRequestType,
		is_product_page: getBlocksConfiguration()?.is_product_page,
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
		security: getBlocksConfiguration()?.nonce?.update_shipping,
		shipping_method: [ shippingOption.id ],
		is_product_page: getBlocksConfiguration()?.is_product_page,
	};

	return $.ajax( {
		type: 'POST',
		data,
		url: getAjaxUrl( 'update_shipping_method' ),
	} );
};

export const createOrder = ( sourceEvent, paymentRequestType ) => {
	let data = normalizeOrderData( sourceEvent, paymentRequestType );
	data = getRequiredFieldDataFromCheckoutForm( data );

	return $.ajax( {
		type: 'POST',
		data,
		dataType: 'json',
		url: getAjaxUrl( 'create_order' ),
	} );
};

const getRequiredFieldDataFromCheckoutForm = ( data ) => {
	const checkoutForm = document.querySelector( '.wc-block-checkout' );
	// Return if cart page.
	if ( ! checkoutForm ) {
		return data;
	}

	const requiredFields = checkoutForm.querySelectorAll( '[required]' );

	if ( requiredFields.length ) {
		requiredFields.forEach( ( field ) => {
			const value = field.value;
			const id = field.id?.replace( '-', '_' );
			if ( value && ! data[ id ] ) {
				data[ id ] = value;
			}

			// if billing same as shipping is selected, copy the shipping field to billing field.
			const useSameBillingAddress = checkoutForm
				.querySelector( '.wc-block-checkout__use-address-for-billing' )
				?.querySelector( 'input' )?.checked;
			if ( useSameBillingAddress ) {
				const billingFieldName = id.replace( 'shipping_', 'billing_' );
				if ( ! data[ billingFieldName ] && data[ id ] ) {
					data[ billingFieldName ] = data[ id ];
				}
			}
		} );
	}

	return data;
};
