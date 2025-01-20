import { applyFilters } from '@wordpress/hooks';

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
 * Normalize order data from Stripe's object to the expected format for WC (when using the Blocks API).
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
		billing_address: {
			first_name: name?.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
			last_name: name?.split( ' ' )?.slice( 1 )?.join( ' ' ) ?? '-',
			company: billing?.organization ?? '',
			email: email ?? event?.payerEmail ?? '',
			phone,
			country: billing?.country ?? '',
			address_1: billing?.line1 ?? '',
			address_2: billing?.line2 ?? '',
			city: billing?.city ?? '',
			state: billing?.state ?? '',
			postcode: billing?.postal_code ?? '',
		},
		shipping_address: {
			first_name:
				shipping?.name?.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
			last_name:
				shipping?.name?.split( ' ' )?.slice( 1 )?.join( ' ' ) ?? '',
			company: shipping?.organization ?? '',
			phone,
			country: shipping?.address?.country ?? '',
			address_1: shipping?.address?.line1 ?? '',
			address_2: shipping?.address?.line2 ?? '',
			city: shipping?.address?.city ?? '',
			state: shipping?.address?.state ?? '',
			postcode: shipping?.address?.postal_code ?? '',
			method: [ event?.shippingRate?.id ?? null ],
		},
		customer_note: event.order_comments,
		payment_method: 'stripe',
		payment_data: buildBlocksAPIPaymentData(
			event?.expressPaymentType,
			paymentMethodId
		),
		extensions: applyFilters(
			'wcstripe.express-checkout.cart-place-order-extension-data',
			{}
		),
	};
};

/**
 * Normalize Pay for Order data from Stripe's object to the expected format for WC (when using Blocks API).
 *
 * @param {Object} event Stripe's event object.
 * @param {string} paymentMethodId Stripe's payment method id.
 *
 * @return {Object} Order object in the format WooCommerce expects.
 */
export const normalizePayForOrderData = ( event, paymentMethodId ) => {
	return {
		payment_method: 'stripe',
		payment_data: buildBlocksAPIPaymentData(
			event?.expressPaymentType,
			paymentMethodId
		),
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

/**
 * Builds the payment data for the Blocks API.
 *
 * @param {string} expressPaymentType The express payment type.
 * @param {string} paymentMethodId The payment method ID.
 * @return {Array} The payment data.
 */
const buildBlocksAPIPaymentData = ( expressPaymentType, paymentMethodId ) => {
	return [
		{
			key: 'payment_method',
			value: 'stripe',
		},
		{
			key: 'wc-stripe-payment-method',
			value: paymentMethodId,
		},
		{
			key: 'express_payment_type',
			value: expressPaymentType,
		},
		{
			key: 'wc-stripe-is-deferred-intent',
			value: true,
		},
	];
};
