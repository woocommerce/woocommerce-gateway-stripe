import {
	getPaymentMethodTypes,
	getStripeServerData,
	getUpeSettings,
} from '../../stripe-utils';

const gatewayUPEComponents = {};

const paymentMethodsConfig = getStripeServerData()?.paymentMethodsConfig;

for ( const paymentMethodType in paymentMethodsConfig ) {
	gatewayUPEComponents[ paymentMethodType ] = {
		elements: null,
		upeElement: null,
	};
}

/**
 * Initializes the appearance of the payment element by retrieving the UPE configuration
 * from the API and saving the appearance if it doesn't exist. If the appearance already exists,
 * it is simply returned.
 *
 * @return {Object} The appearance object for the UPE.
 */
function initializeAppearance() {
	return {};
}

/**
 * Validates the Stripe elements by submitting them and handling any errors that occur during submission.
 * If an error occurs, the function removes loading effect from the provided jQuery form and thus unblocks it,
 * and shows an error message in the checkout.
 *
 * @param {Object} elements The Stripe elements object to be validated.
 * @return {Promise} Promise for the checkout submission.
 */
export function validateElements( elements ) {
	return elements.submit().then( ( result ) => {
		if ( result.error ) {
			throw new Error( result.error.message );
		}
	} );
}

/**
 * Creates a Stripe payment element with the specified payment method type and options. The function
 * retrieves the necessary data from the UPE configuration and initializes the appearance. It then creates the
 * Stripe elements and the Stripe payment element, which is attached to the gatewayUPEComponents object afterward.
 *
 * @todo Make paymentMethodType required when Split is implemented.
 *
 * @param {Object} api The API object used to create the Stripe payment element.
 * @param {string} paymentMethodType The type of Stripe payment method to create.
 * @return {Object} A promise that resolves with the created Stripe payment element.
 */
function createStripePaymentElement( api, paymentMethodType = null ) {
	const amount = Number( getStripeServerData()?.cartTotal );
	const paymentMethodTypes = getPaymentMethodTypes( paymentMethodType );
	const options = {
		mode: amount < 1 ? 'setup' : 'payment',
		currency: getStripeServerData()?.currency.toLowerCase(),
		amount,
		paymentMethodCreation: 'manual',
		paymentMethodTypes,
		appearance: initializeAppearance(),
	};

	const elements = api.getStripe().elements( options );
	const createdStripePaymentElement = elements.create( 'payment', {
		...getUpeSettings(),
		wallets: {
			applePay: 'never',
			googlePay: 'never',
		},
	} );

	// To be removed with Split PE.
	if ( paymentMethodType === null ) {
		return createdStripePaymentElement;
	}

	gatewayUPEComponents[ paymentMethodType ].elements = elements;
	gatewayUPEComponents[
		paymentMethodType
	].upeElement = createdStripePaymentElement;
	return createdStripePaymentElement;
}

/**
 * Mounts the existing Stripe Payment Element to the DOM element.
 * Creates the Stipe Payment Element instance if it doesn't exist and mounts it to the DOM element.
 *
 * @todo Make it only Split when implemented.
 *
 * @param {Object} api The API object.
 * @param {string} domElement The selector of the DOM element of particular payment method to mount the UPE element to.
 **/
export async function mountStripePaymentElement( api, domElement ) {
	/*
	 * Trigger this event to ensure the tokenization-form.js init
	 * is executed.
	 *
	 * This script handles the radio input interaction when toggling
	 * between the user's saved card / entering new card details.
	 *
	 * Ref: https://github.com/woocommerce/woocommerce/blob/2429498/assets/js/frontend/tokenization-form.js#L109
	 */
	const event = new Event( 'wc-credit-card-form-init' );
	document.body.dispatchEvent( event );

	const paymentMethodType = domElement.dataset.paymentMethodType;
	let upeElement;

	// Non-split PE. To be removed.
	if ( typeof paymentMethodType === 'undefined' ) {
		upeElement = await createStripePaymentElement( api );

		upeElement.on( 'change', ( e ) => {
			const selectedUPEPaymentType = e.value.type;
			const isPaymentMethodReusable =
				paymentMethodsConfig[ selectedUPEPaymentType ].isReusable;
			showNewPaymentMethodCheckbox( isPaymentMethodReusable );
			setSelectedUPEPaymentType( selectedUPEPaymentType );
		} );
	} else {
		// Split PE.
		if ( ! gatewayUPEComponents[ paymentMethodType ] ) {
			return;
		}

		upeElement =
			gatewayUPEComponents[ paymentMethodType ].upeElement ||
			( await createStripePaymentElement( api, paymentMethodType ) );
	}

	upeElement.mount( domElement );
}

// Set the selected UPE payment type field
function setSelectedUPEPaymentType( paymentType ) {
	document.querySelector(
		'#wc_stripe_selected_upe_payment_type'
	).value = paymentType;
}

// Show or hide save payment information checkbox
function showNewPaymentMethodCheckbox( show = true ) {
	if ( show ) {
		document.querySelector(
			'.woocommerce-SavedPaymentMethods-saveNew'
		).style.visibility = 'visible';
	} else {
		document.querySelector(
			'.woocommerce-SavedPaymentMethods-saveNew'
		).style.visibility = 'hidden';
		document
			.querySelector( 'input#wc-stripe-new-payment-method' )
			.setAttribute( 'checked', false );
		document
			.querySelector( 'input#wc-stripe-new-payment-method' )
			.dispatchEvent( new Event( 'change' ) );
	}
}
