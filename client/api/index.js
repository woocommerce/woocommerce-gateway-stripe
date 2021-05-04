/* global wc_stripe_payment_request_params */

/**
 * External dependencies
 */
import $ from 'jquery';

const getAjaxUrl = ( endpoint ) => {
	return wc_stripe_payment_request_params.ajax_url
		.toString()
		.replace( '%%endpoint%%', 'wc_stripe_' + endpoint );
};

const getOrderDataFromSourceEvent = ( sourceEvent, paymentRequestType ) => {
	const { source } = sourceEvent;
	const email = source?.owner?.email;
	const phone = source?.owner?.phone;
	const billing = source?.owner?.address;
	const name = source?.owner?.name;
	const shipping = sourceEvent?.shippingAddress;

	const data = {
		_wpnonce: wc_stripe_payment_request_params.nonce.checkout,
		billing_first_name:
			name?.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
		billing_last_name: name?.split( ' ' )?.slice( 1 )?.join( ' ' ) ?? '',
		billing_company: '',
		billing_email: email ?? sourceEvent.payerEmail,
		billing_phone:
			phone ?? sourceEvent?.payerPhone?.replace( '/[() -]/g', '' ),
		billing_country: billing?.country ?? '',
		billing_address_1: billing?.line1 ?? '',
		billing_address_2: billing?.line2 ?? '',
		billing_city: billing?.city ?? '',
		billing_state: billing?.state ?? '',
		billing_postcode: billing?.postal_code ?? '',
		shipping_first_name: '',
		shipping_last_name: '',
		shipping_company: '',
		shipping_country: '',
		shipping_address_1: '',
		shipping_address_2: '',
		shipping_city: '',
		shipping_state: '',
		shipping_postcode: '',
		shipping_method: [ sourceEvent?.shippingOption?.id ],
		order_comments: '',
		payment_method: 'stripe',
		ship_to_different_address: 1,
		terms: 1,
		stripe_source: source.id,
		payment_request_type: paymentRequestType,
	};

	if ( shipping ) {
		data.shipping_first_name = shipping?.recipient
			?.split( ' ' )
			?.slice( 0, 1 )
			?.join( ' ' );
		data.shipping_last_name = shipping?.recipient
			?.split( ' ' )
			?.slice( 1 )
			?.join( ' ' );
		data.shipping_company = shipping?.organization;
		data.shipping_country = shipping?.country;
		data.shipping_address_1 = shipping?.addressLine?.[ 0 ] ?? '';
		data.shipping_address_2 = shipping?.addressLine?.[ 1 ] ?? '';
		data.shipping_city = shipping?.city;
		data.shipping_state = shipping?.region;
		data.shipping_postcode = shipping?.postalCode;
	}

	return data;
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

export const createPaymentRequest = ( stripe, cart ) => {
	const options = {
		total: cart.order_data.total,
		currency: cart.order_data.currency,
		country: cart.order_data.country_code,
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone:
			wc_stripe_payment_request_params.checkout.needs_payer_phone,
		requestShipping: cart.shipping_required ? true : false,
		displayItems: cart.order_data.displayItems,
	};

	// Puerto Rico (PR) is the only US territory/possession that's supported by Stripe.
	// Since it's considered a US state by Stripe, we need to do some special mapping.
	if ( options.country === 'PR' ) {
		options.country = 'US';
	}

	return stripe.paymentRequest( options );
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
		country: address.country,
		state: address.region,
		postcode: address.postalCode,
		city: address.city,
		address:
			typeof address.addressLine[ 0 ] === 'undefined'
				? ''
				: address.addressLine[ 0 ],
		address_2:
			typeof address.addressLine[ 1 ] === 'undefined'
				? ''
				: address.addressLine[ 1 ],
		payment_request_type: paymentRequestType,
		is_product_page: wc_stripe_payment_request_params.is_product_page,
	};

	return $.ajax( {
		type: 'POST',
		data,
		url: getAjaxUrl( 'get_shipping_options' ),
	} );
};

export const updateShippingDetails = ( shippingOption, paymentRequestType ) => {
	const data = {
		security: wc_stripe_payment_request_params.nonce.update_shipping,
		shipping_method: [ shippingOption.id ],
		payment_request_type: paymentRequestType,
		is_product_page: wc_stripe_payment_request_params.is_product_page,
	};

	return $.ajax( {
		type: 'POST',
		data,
		url: getAjaxUrl( 'update_shipping_method' ),
	} );
};

export const createOrder = ( sourceEvent, paymentRequestType ) => {
	const data = getOrderDataFromSourceEvent( sourceEvent, paymentRequestType );

	return $.ajax( {
		type: 'POST',
		data,
		dataType: 'json',
		url: getAjaxUrl( 'create_order' ),
	} );
};
