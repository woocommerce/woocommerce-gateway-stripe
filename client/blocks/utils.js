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
	const disableWallets = [];

	// Prevent displaying Link in the PRBs if disabled in the plugin settings.
	if ( ! getBlocksConfiguration()?.stripe?.is_link_enabled ) {
		disableWallets.push( 'link' );
	}

	// Prevent displaying Apple Pay and Google Pay in the PRBs if disabled in the plugin settings.
	if ( ! getBlocksConfiguration()?.stripe?.is_payment_request_enabled ) {
		disableWallets.push( 'applePay', 'googlePay' );
	}

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
		disableWallets,
	};

	// Puerto Rico (PR) is the only US territory/possession that's supported by Stripe.
	// Since it's considered a US state by Stripe, we need to do some special mapping.
	if ( options.country === 'PR' ) {
		options.country = 'US';
	}

	return stripe.paymentRequest( options );
};

/**
 * Updates the given PaymentRequest using the data in the cart object.
 *
 * @param {Object} paymentRequest  The payment request object.
 * @param {Object} cart  The cart data response from the store's AJAX API.
 */
export const updatePaymentRequestUsingCart = ( paymentRequest, cart ) => {
	const options = {
		total: cart.order_data.total,
		currency: cart.order_data.currency,
		displayItems: cart.order_data.displayItems,
	};

	paymentRequest.update( options );
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
