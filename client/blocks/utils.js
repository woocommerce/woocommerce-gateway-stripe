/* global wc */

import { isLinkEnabled } from 'wcstripe/stripe-utils';
import { OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT } from 'wcstripe/stripe-utils/constants';

/**
 * Retrieves the Stripe blocks configuration from the WooCommerce settings.
 *
 * @throws {Error} If Stripe initialization data is not available.
 * @return {Object} The Stripe blocks configuration object.
 */
export const getBlocksConfiguration = () => {
	const stripeServerData = wc?.wcSettings?.getSetting( 'stripe_data', null );

	if ( ! stripeServerData ) {
		throw new Error( 'Stripe initialization data is not available' );
	}

	return stripeServerData;
};

/**
 * Whether manual renewal is required based on the payment method's reusability.
 *
 * It is considered required if:
 * - The payment method is not reusable and manual renewal is enabled in the configuration.
 * - The configuration explicitly requires manual renewal.
 *
 * @param {boolean} isReusablePaymentMethod
 * @return {boolean} True if manual renewal is required, false otherwise.
 */
const isManualRenewalRequired = ( isReusablePaymentMethod ) => {
	const config = getBlocksConfiguration();
	return (
		( ! isReusablePaymentMethod &&
			config?.subscriptionManualRenewalEnabled ) ||
		config?.subscriptionRequiresManualRenewal
	);
};

/**
 * Checks if the cart contains an auto-renewing subscription.
 *
 * @param {boolean} isReusablePaymentMethod Indicates if the payment method is reusable.
 * @return {boolean} True if the cart contains an auto-renewing subscription, false otherwise.
 */
const hasAutoRenewingSubscription = ( isReusablePaymentMethod ) => {
	const config = getBlocksConfiguration();
	return (
		config?.cartContainsSubscription &&
		! isManualRenewalRequired( isReusablePaymentMethod )
	);
};

/**
 * Determines if off-session payment should be set up.
 *
 * @param {boolean} shouldShowSaveOption    - Whether to show the save option.
 * @param {boolean} isPaymentMethodReusable - Whether the payment method is reusable.
 * @return {boolean} True if off-session payment should be set up, false otherwise.
 */
export const shouldSetupOffSessionPayment = (
	shouldShowSaveOption,
	isPaymentMethodReusable
) => {
	return (
		shouldShowSaveOption ||
		hasAutoRenewingSubscription( isPaymentMethodReusable ) ||
		( isPaymentMethodReusable &&
			getBlocksConfiguration()?.forceSavePaymentMethod )
	);
};

/**
 * Checks if the save payment method checkbox is checked.
 *
 * @return {boolean} True if the save payment method checkbox is checked, false otherwise.
 */
export const isSavePaymentMethodCheckboxChecked = () => {
	const checkbox = document.querySelector(
		'.wc-block-components-payment-methods__save-card-info input[type=checkbox]'
	);
	return Boolean( checkbox?.checked );
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

/**
 * Get order attribution data from the hidden inputs.
 *
 * @return {Object} Order attribution data.
 */
export const extractOrderAttributionData = () => {
	const orderAttributionWrapper = document.getElementsByTagName(
		'wc-order-attribution-inputs'
	);
	if ( ! orderAttributionWrapper.length ) {
		return {};
	}

	const orderAttributionData = {};
	const orderAttributionInputs = orderAttributionWrapper[ 0 ].children;
	for ( let i = 0; i < orderAttributionInputs.length; i++ ) {
		orderAttributionData[ orderAttributionInputs[ i ].name ] =
			orderAttributionInputs[ i ].value;
	}
	return orderAttributionData;
};

/**
 * Populate order attribution inputs with order tracking data.
 *
 * @return {void}
 */
export const populateOrderAttributionInputs = () => {
	const orderAttribution = window?.wc_order_attribution;
	if ( orderAttribution ) {
		orderAttribution.setOrderTracking(
			orderAttribution.params.allowTracking
		);
	}
};

/**
 * Add order attribution inputs to the page.
 *
 * @return {void}
 */
export const addOrderAttributionInputsIfNotExists = () => {
	const elementId = 'wc-stripe-express-checkout__order-attribution-inputs';
	if ( document.getElementById( elementId ) ) {
		return;
	}

	const orderAttributionInputs = document.createElement(
		'wc-order-attribution-inputs'
	);
	orderAttributionInputs.id = elementId;
	document.body.appendChild( orderAttributionInputs );
};

/**
 * Get the URL for a Stripe image asset.
 *
 * @param {string} imageName The name of the image file without extension
 * @return {string} The full URL to the image
 */
export const getStripeImageUrl = ( imageName ) => {
	const config = getBlocksConfiguration();
	return `${ config?.plugin_url }/assets/images/${ imageName }.svg`;
};

/**
 * Gets the Stripe element options.
 *
 * @param {boolean} forCheckoutSession Whether the options are for a checkout session. If true, it will remove options not supported by checkout sessions.
 * @return {Object} The Stripe element options.
 */
export const getStripeElementOptions = ( forCheckoutSession = false ) => {
	let options = {
		fields: {
			billingDetails: {
				name: 'never',
				email: 'never',
				// The phone field is optional, so it needs to be "auto" to not throw errors
				// when passing the phone parameter to create a payment method.
				phone: 'auto',
				address: {
					country: 'never',
					line1: 'never',
					line2: 'never',
					city: 'never',
					state: 'never',
					postalCode: 'never',
				},
			},
		},
		wallets: {
			applePay: 'never',
			googlePay: 'never',
		},
	};

	// Prefill Link customer data if available.
	if ( isLinkEnabled() && ! forCheckoutSession ) {
		const userEmail = document.getElementById( 'email' )?.value;
		if ( userEmail ) {
			const userPhone =
				document.getElementById( 'billing-phone' )?.value ||
				document.getElementById( 'shipping-phone' )?.value;

			options = {
				...options,
				defaultValues: {
					billingDetails: {
						email: userEmail,
						phone: userPhone,
					},
				},
			};
		}
	}

	const stripeServerData = getBlocksConfiguration();
	if ( stripeServerData?.shouldShowOptimizedCheckout ) {
		const layout = {
			type:
				stripeServerData?.OCLayout || OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT,
		};
		if ( layout.type === OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT ) {
			layout.radios = false;
			layout.spacedAccordionItems = false;
		}
		options = {
			...options,
			layout,
		};
	} else {
		// When Optimized Checkout is disabled, default to 'tabs' layout, as that has
		// the best default UX for individual payment methods.
		options.layout = {
			type: 'tabs',
		};
	}

	return options;
};
