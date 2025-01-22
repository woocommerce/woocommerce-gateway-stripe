/**
 * Transform shipping address information from Stripe's address object to
 * the cart shipping address object shape.
 *
 * @param {string} name Stripe's shipping address item
 * @param {Object} shippingAddress Stripe's shipping address item
 *
 * @return {Object} The shipping address in the shape expected by the cart.
 */
export const transformStripeShippingAddressForStoreApi = (
	name,
	shippingAddress
) => ( {
	first_name: name?.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
	last_name: name?.split( ' ' )?.slice( 1 )?.join( ' ' ) ?? '',
	company: shippingAddress.organization ?? '',
	address_1: shippingAddress.line1 ?? '',
	address_2: shippingAddress.line2 ?? '',
	city: shippingAddress.city ?? '',
	state: shippingAddress.state ?? '',
	postcode: shippingAddress.postal_code?.replace( ' ', '' ) ?? '',
	country: shippingAddress.country ?? '',
} );

/**
 * Transform order data from Stripe's object to the expected format for WC.
 *
 * @param {Object} paymentData Stripe's order object.
 * @param {string} paymentMethodId Stripe's payment method id.
 *
 * @return {Object} Order object in the format WooCommerce expects.
 */
export const transformStripePaymentMethodForStoreApi = (
	paymentData,
	paymentMethodId
) => {
	const name = paymentData.billingDetails?.name || '';
	const billing = paymentData.billingDetails?.address ?? {};

	const billingPhone =
		paymentData.billingDetails?.phone?.replace( /[() -]/g, '' ) ??
		paymentData.payerPhone?.replace( /[() -]/g, '' ) ??
		'';

	return {
		customer_note: paymentData.order_comments,
		billing_address: {
			first_name: name.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
			last_name: name.split( ' ' )?.slice( 1 )?.join( ' ' ) || '-',
			company: billing.organization ?? '',
			address_1: billing.line1 ?? '',
			address_2: billing.line2 ?? '',
			city: billing.city ?? '',
			state: billing.state ?? '',
			postcode: billing.postal_code ?? '',
			country: billing.country ?? '',
			email: paymentData.billingDetails?.email ?? '',
			phone: billingPhone,
		},
		// refreshing any shipping address data, now that the customer is placing the order.
		// in the case of pay-for-order, the shipping address property might not be present.
		shipping_address: paymentData.shippingAddress
			? {
					...transformStripeShippingAddressForStoreApi(
						paymentData.shippingAddress.name || '',
						paymentData.shippingAddress.address
					),
					// adding the phone number, because it might be needed.
					// Stripe doesn't provide us with a different phone number for shipping,
					// so we're going to use the same phone used for billing.
					phone: billingPhone,
			  }
			: undefined,
		payment_method: 'woocommerce_payments',
		payment_data: [
			{
				key: 'payment_method',
				value: 'card',
			},
			{
				key: 'payment_request_type',
				value: paymentData.expressPaymentType,
			},
			{
				key: 'wcpay-fraud-prevention-token',
				value: window.wcpayFraudPreventionToken ?? '',
			},
			{
				key: 'wcpay-payment-method',
				value: paymentMethodId,
			},
			{
				key: 'express_payment_type',
				value: paymentData.expressPaymentType,
			},
		],
	};
};
