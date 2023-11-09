/**
 * @typedef {import('./type-defs').StripePaymentItem} StripePaymentItem
 * @typedef {import('./type-defs').StripeShippingOption} StripeShippingOption
 * @typedef {import('./type-defs').StripeShippingAddress} StripeShippingAddress
 * @typedef {import('./type-defs').StripePaymentResponse} StripePaymentResponse
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').PreparedCartTotalItem} CartTotalItem
 * @typedef {import('@woocommerce/type-defs/cart').CartShippingOption} CartShippingOption
 * @typedef {import('@woocommerce/type-defs/shipping').ShippingAddress} CartShippingAddress
 * @typedef {import('@woocommerce/type-defs/billing').BillingData} CartBillingAddress
 */

import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/**
 * Normalizes order data received upon creating an order using the store's AJAX API.
 *
 * @param {Object} sourceEvent - The source event that triggered the creation of the order.
 * @param {string} paymentRequestType - The payment request type.
 */
const normalizeOrderData = ( sourceEvent, paymentRequestType ) => {
	const { source } = sourceEvent;
	const email = source?.owner?.email;
	const phone = source?.owner?.phone;
	const billing = source?.owner?.address;
	const name = source?.owner?.name ?? sourceEvent.payerName;
	const shipping = sourceEvent?.shippingAddress;

	const data = {
		_wpnonce: getBlocksConfiguration()?.nonce?.checkout,
		billing_first_name:
			name?.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
		billing_last_name: name?.split( ' ' )?.slice( 1 )?.join( ' ' ) ?? '',
		billing_company: '',
		billing_email: email ?? sourceEvent?.payerEmail,
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

/**
 * Normalizes an address received upon updating shipping options using the store's AJAX API.
 *
 * @param {Object} address - The address that needs to be normalized.
 * @return {Object} The normalized address.
 */
const normalizeAddress = ( address ) => {
	return {
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
	};
};

export { normalizeOrderData, normalizeAddress };
