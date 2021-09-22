import { getSetting } from '@woocommerce/settings';

export const getBlocksConfiguration = () => {
	const stripeServerData = getSetting( 'stripe_data', null );

	if ( ! stripeServerData ) {
		throw new Error( 'Stripe initialization data is not available' );
	}

	return stripeServerData;
};

/**
 * Creates a payment request using cart data from WooCommerce.
 *
 * @param {Object} stripe - The Stripe JS object.
 * @param {Object} cart - The cart data response from the store's AJAX API.
 *
 * @return {Object} A Stripe payment request.
 */
export const createPaymentRequestUsingCart = ( stripe, cart ) => {
	const options = {
		total: cart.order_data.total,
		currency: cart.order_data.currency,
		country: cart.order_data.country_code,
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: getBlocksConfiguration()?.checkout
			?.needs_payer_phone,
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
 * Returns the public api key for the stripe payment method
 *
 * @throws Error
 * @return {string} The public api key for the stripe payment method.
 */
export const getApiKey = () => {
	const apiKey = getBlocksConfiguration()?.key;
	if ( ! apiKey ) {
		throw new Error(
			'There is no api key available for stripe. Make sure it is available on the wc.stripe_data.stripe.key property.'
		);
	}
	return apiKey;
};
