import { SHIPPING_RATES_UPPER_LIMIT_COUNT } from '../stripe-utils/constants';
import {
	normalizeShippingAddress,
	normalizeLineItems,
	isManualPaymentMethodCreation,
} from './utils';
import {
	handleConfirmationTokenFlow,
	handleManualPaymentMethodFlow,
} from './payment-flow';

/**
 * Handles changes to the shipping address in the Express Checkout flow by
 * fetching updated shipping options from the server and resolving the event.
 *
 * @param {Object} api      The WCStripeAPI instance.
 * @param {Object} event    The Stripe shipping address change event.
 * @param {Object} elements The Stripe Elements instance.
 * @return {Promise<void>} Resolves when the shipping options have been updated.
 */
export const shippingAddressChangeHandler = async ( api, event, elements ) => {
	try {
		const response = await api.expressCheckoutECECalculateShippingOptions(
			normalizeShippingAddress( event.address )
		);

		if ( response.result === 'success' ) {
			elements.update( {
				amount: response.total.amount,
			} );
			event.resolve( {
				shippingRates: response.shipping_options?.slice(
					0,
					SHIPPING_RATES_UPPER_LIMIT_COUNT
				),
				lineItems: normalizeLineItems( response.displayItems ),
			} );
		} else {
			event.reject();
		}
	} catch ( e ) {
		event.reject();
	}
};

/**
 * Handles changes to the selected shipping rate in the Express Checkout flow by
 * updating the cart with the new shipping method and resolving the event.
 *
 * @param {Object} api      The WCStripeAPI instance.
 * @param {Object} event    The Stripe shipping rate change event.
 * @param {Object} elements The Stripe Elements instance.
 * @return {Promise<void>} Resolves when the shipping rate has been updated.
 */
export const shippingRateChangeHandler = async ( api, event, elements ) => {
	try {
		const response = await api.expressCheckoutUpdateShippingDetails(
			event.shippingRate
		);

		if ( response.result === 'success' ) {
			elements.update( { amount: response.total.amount } );
			event.resolve( {
				lineItems: normalizeLineItems( response.displayItems ),
			} );
		} else {
			event.reject();
		}
	} catch ( e ) {
		event.reject();
	}
};

/**
 * Handles the confirmation step of the Express Checkout payment flow.
 * Validates the Elements, then delegates to the appropriate payment method flow
 * (confirmation token or manual payment method creation).
 *
 * @param {Object}   params                The handler parameters.
 * @param {Function} params.abortPayment   Callback to abort the payment with an error message.
 * @param {Object}   params.elements       The Stripe Elements instance.
 * @param {Object}   params.event          The Stripe confirm event.
 * @param {boolean}  params.hasFreeTrial   Whether the cart contains a free trial item.
 * @return {Promise<void>} Resolves when the payment has been confirmed or aborted.
 */
export const onConfirmHandler = async ( params ) => {
	const { abortPayment, elements, event, hasFreeTrial } = params;

	const submitResponse = await elements.submit();
	if ( submitResponse?.error ) {
		return abortPayment( event, submitResponse?.error?.message );
	}

	if (
		! isManualPaymentMethodCreation(
			event.expressPaymentType,
			hasFreeTrial
		)
	) {
		return handleConfirmationTokenFlow( params );
	}

	return handleManualPaymentMethodFlow( params );
};

/**
 * Blocks the page UI to prevent duplicate interactions during payment processing.
 */
const blockUI = () => {
	jQuery.blockUI( {
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6,
		},
	} );
};

/**
 * Unblocks the page UI after payment processing has completed or been aborted.
 */
const unblockUI = () => {
	jQuery.unblockUI();
};

/**
 * Handles the click event on the Express Checkout button by blocking the UI.
 */
export const onClickHandler = function () {
	blockUI();
};

/**
 * Handles the abort payment event by unblocking the page UI.
 */
export const onAbortPaymentHandler = () => {
	unblockUI();
};

/**
 * Handles the complete payment event by blocking the UI during redirect.
 */
export const onCompletePaymentHandler = () => {
	blockUI();
};

export const onCancelHandler = () => {
	unblockUI();
};
