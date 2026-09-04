import {
	getExpressCheckoutData,
	getExpressCheckoutErrorMessage,
	isManualPaymentMethodCreation,
} from './utils';
import {
	handleConfirmationTokenFlow,
	handleManualPaymentMethodFlow,
	handleChangePaymentMethodFlow,
} from './payment-flow';
import ExpressCheckoutCartApi from './cart-api';
import { transformStripeShippingAddressForStoreApi } from './transformers/stripe-to-wc';
import {
	transformCartTotalAmount,
	transformCartDataForDisplayItems,
	transformCartDataForShippingRates,
} from './transformers/wc-to-stripe';

/**
 * Module-scoped Cart API singleton used by the shipping handlers.
 *
 * `setCartApiHandler` is a test-only injection seam. It permanently replaces
 * the module-scoped `cartApi` reference, so tests that swap it should rely on
 * Jest's per-file module registry isolation to avoid leaking mocks into other
 * files. Production code should not reassign this.
 */
let cartApi = new ExpressCheckoutCartApi();
export const setCartApiHandler = ( handler ) => ( cartApi = handler );
export const getCartApiHandler = () => cartApi;

/**
 * Handles changes to the shipping address in the Express Checkout flow by
 * fetching updated shipping options from the server and resolving the event.
 *
 * @param {Object} event    The Stripe shipping address change event.
 * @param {Object} elements The Stripe Elements instance.
 * @return {Promise<void>} Resolves when the shipping options have been updated.
 */
export const shippingAddressChangeHandler = async ( event, elements ) => {
	try {
		const cartData = await cartApi.updateCustomer( {
			shipping_address: transformStripeShippingAddressForStoreApi(
				event.name,
				event.address
			),
		} );

		const shippingRates = transformCartDataForShippingRates( cartData );

		if ( shippingRates.length === 0 ) {
			event.reject();
			return;
		}

		elements.update( {
			amount: transformCartTotalAmount( cartData.totals ),
		} );

		event.resolve( {
			shippingRates,
			lineItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( e ) {
		event.reject();
	}
};

/**
 * Handles changes to the selected shipping rate in the Express Checkout flow by
 * updating the cart with the new shipping method and resolving the event.
 *
 * @param {Object} event    The Stripe shipping rate change event.
 * @param {Object} elements The Stripe Elements instance.
 * @return {Promise<void>} Resolves when the shipping rate has been updated.
 */
export const shippingRateChangeHandler = async ( event, elements ) => {
	try {
		const cartData = await cartApi.selectShippingRate( {
			package_id: 0,
			rate_id: event.shippingRate.id,
		} );

		elements.update( {
			amount: transformCartTotalAmount( cartData.totals ),
		} );

		event.resolve( {
			lineItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( e ) {
		event.reject();
	}
};

/**
 * Handles the confirmation step of the Express Checkout payment flow.
 * Validates the Elements, then delegates to the appropriate payment method flow
 * (confirmation token or manual payment method creation).
 *
 * @param {Object}   params              The handler parameters.
 * @param {Function} params.abortPayment Callback to abort the payment with an error message.
 * @param {Object}   params.elements     The Stripe Elements instance.
 * @param {Object}   params.event        The Stripe confirm event.
 * @param {boolean}  params.hasFreeTrial Whether the cart contains a free trial item.
 * @return {Promise<void>} Resolves when the payment has been confirmed or aborted.
 */
export const onConfirmHandler = async ( params ) => {
	const { abortPayment, elements, event, hasFreeTrial } = params;

	blockUI();

	const submitResponse = await elements.submit();
	if ( submitResponse?.error ) {
		return abortPayment(
			event,
			getExpressCheckoutErrorMessage( submitResponse?.error?.message )
		);
	}

	// For subscription change payment method, create a payment method and submit the form.
	if ( getExpressCheckoutData( 'is_change_payment_method' ) ) {
		return handleChangePaymentMethodFlow( params );
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
	document.body.classList.add( 'wc-stripe-ece-processing' );

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

	document.body.classList.remove( 'wc-stripe-ece-processing' );
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
