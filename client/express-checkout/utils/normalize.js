import { extractOrderAttributionData } from 'wcstripe/blocks/utils';

/**
 * Normalizes incoming cart total items for use as a displayItems with the Stripe api.
 *
 * @param {Array} displayItems Items to normalize.
 *
 * @return {Array} An array of PaymentItems
 */
export const normalizeLineItems = ( displayItems ) => {
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

/**
 * Normalize order data from Stripe's object to the expected format for WC.
 *
 * @param {Object} event Stripe's event object.
 * @param {string} paymentMethodId Stripe's payment method id.
 *
 * @return {Object} Order object in the format WooCommerce expects.
 */
export const normalizeOrderData = ( event, paymentMethodId ) => {
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
		'wc-stripe-is-deferred-intent': true,
		...extractOrderAttributionData(),
	};
};

/**
 * Normalize Pay for Order data from Stripe's object to the expected format for WC.
 *
 * @param {Object} event Stripe's event object.
 * @param {string} paymentMethodId Stripe's payment method id.
 *
 * @return {Object} Order object in the format WooCommerce expects.
 */
export const normalizePayForOrderData = ( event, paymentMethodId ) => {
	return {
		payment_method: 'stripe',
		'wc-stripe-is-deferred-intent': true, // Set the deferred intent flag, so the deferred intent flow is used.
		'wc-stripe-payment-method': paymentMethodId,
		express_payment_type: event?.expressPaymentType,
	};
};

/**
 * Normalize shipping address information from Stripe's address object to
 * the cart shipping address object shape.
 *
 * @param {Object} shippingAddress Stripe's shipping address item
 *
 * @return {Object} The shipping address in the shape expected by the cart.
 */
export const normalizeShippingAddress = ( shippingAddress ) => {
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
