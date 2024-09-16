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

/**
 * Normalize order data from Stripe's ECE object to the expected format for WC.
 *
 * @param {Object} event Stripe's event object.
 * @param {string} paymentMethodId Stripe payment method id.
 *
 * @return {Object} Order object in the format WooCommerce expects.
 */
const normalizeECEOrderData = ( event, paymentMethodId ) => {
	const name = event?.billingDetails?.name;
	const email = event?.billingDetails?.email ?? '';
	const billing = event?.billingDetails?.address ?? {};
	const shipping = event?.shippingAddress ?? {};

	const phone =
		event?.billingDetails?.phone?.replace( /[() -]/g, '' ) ??
		event?.payerPhone?.replace( /[() -]/g, '' ) ??
		'';

	return {
		billing_first_name:
			name?.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
		billing_last_name: name?.split( ' ' )?.slice( 1 )?.join( ' ' ) ?? '-',
		billing_company: billing?.organization ?? '',
		billing_email: email ?? event?.payerEmail ?? '',
		billing_phone: phone,
		billing_country: billing?.country ?? '',
		billing_address_1: billing?.line1 ?? '',
		billing_address_2: billing?.line2 ?? '',
		billing_city: billing?.city ?? '',
		billing_state: billing?.state ?? '',
		billing_postcode: billing?.postal_code ?? '',
		shipping_first_name:
			shipping?.name?.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
		shipping_last_name:
			shipping?.name?.split( ' ' )?.slice( 1 )?.join( ' ' ) ?? '',
		shipping_company: shipping?.organization ?? '',
		shipping_phone: phone,
		shipping_country: shipping?.address?.country ?? '',
		shipping_address_1: shipping?.address?.line1 ?? '',
		shipping_address_2: shipping?.address?.line2 ?? '',
		shipping_city: shipping?.address?.city ?? '',
		shipping_state: shipping?.address?.state ?? '',
		shipping_postcode: shipping?.address?.postal_code ?? '',
		shipping_method: [ event?.shippingRate?.id ?? null ],
		order_comments: '',
		payment_method: 'stripe',
		ship_to_different_address: 1,
		terms: 1,
		'wc-stripe-payment-method': paymentMethodId,
		express_checkout_type: event?.expressPaymentType,
		express_payment_type: event?.expressPaymentType,
	};
};

/**
 * Normalize Pay for Order data from Stripe's ECE object to the expected format for WC.
 *
 * @param {Object} event Stripe's event object.
 * @param {string} paymentMethodId Stripe payment method id.
 *
 * @return {Object} Order object in the format WooCommerce expects.
 */
const normalizeECEPayForOrderData = ( event, paymentMethodId ) => {
	return {
		payment_method: 'stripe',
		'wcstripe-payment-method': paymentMethodId,
		express_payment_type: event?.expressPaymentType,
	};
};

/**
 * Normalize shipping address information from Stripe's address object to
 * the cart shipping address object format.
 *
 * @param {Object} shippingAddress Stripe's shipping address item
 *
 * @return {Object} The shipping address in the format expected by the cart.
 */
const normalizeShippingAddress = ( shippingAddress ) => {
	return {
		first_name:
			shippingAddress?.recipient
				?.split( ' ' )
				?.slice( 0, 1 )
				?.join( ' ' ) ?? '',
		last_name:
			shippingAddress?.recipient?.split( ' ' )?.slice( 1 )?.join( ' ' ) ??
			'',
		company: '',
		address_1: shippingAddress?.addressLine?.[ 0 ] ?? '',
		address_2: shippingAddress?.addressLine?.[ 1 ] ?? '',
		city: shippingAddress?.city ?? '',
		state: shippingAddress?.state ?? '',
		country: shippingAddress?.country ?? '',
		postcode: shippingAddress?.postal_code ?? '',
	};
};

/**
 * Normalizes incoming cart total items for use as a displayItems with the Stripe API.
 *
 * @param {Array} displayItems Items to normalize.
 *
 * @return {Array} An array of items.
 */
const normalizeLineItems = ( displayItems ) => {
	return displayItems.map( ( displayItem ) => {
		let amount = displayItem?.amount ?? displayItem?.value;
		if ( displayItem.key === 'total_discount' ) {
			amount = -amount;
		}

		return {
			name: displayItem.label,
			amount,
		};
	} );
};

export {
	normalizeOrderData,
	normalizeAddress,
	normalizeECEOrderData,
	normalizeECEPayForOrderData,
	normalizeShippingAddress,
	normalizeLineItems,
};
