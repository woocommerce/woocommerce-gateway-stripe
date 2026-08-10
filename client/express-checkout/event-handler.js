import { isManualPaymentMethodCreation, getExpressCheckoutData } from './utils';
import {
	handleConfirmationTokenFlow,
	handleManualPaymentMethodFlow,
	handleChangePaymentMethodFlow,
} from './payment-flow';
import ExpressCheckoutCartApi from './cart-api';
import { getElementCurrency } from './utils/element-currency-cache';
import { transformStripeShippingAddressForStoreApi } from './transformers/stripe-to-wc';
import {
	transformPrice,
	transformCartDataForDisplayItems,
	transformCartDataForShippingRates,
} from './transformers/wc-to-stripe';
import { __, sprintf } from '@wordpress/i18n';

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

// An open Apple Pay / Google Pay sheet is locked to the currency it opened
// with. A country-based multi-currency plugin (e.g. Price Based on Country)
// can flip the cart's currency from the address chosen inside the sheet — the
// live sheet can't follow that, so amounts would render in the wrong currency
// and the server-side currency guard would reject the order at placement.
// Detect the drift up front and reject the address instead.
const cartCurrencyDriftedFromElement = ( cartData ) => {
	const elementCurrency = getElementCurrency();
	const cartCurrency = cartData?.totals?.currency_code?.toLowerCase();

	return Boolean(
		elementCurrency && cartCurrency && elementCurrency !== cartCurrency
	);
};

// `event.reject()` only surfaces the wallet's generic "address unsupported"
// message, so callers pass their context's error surface (the shortcode notice
// or the block's `setExpressPaymentError`) to explain the real reason.
const getCurrencyMismatchMessage = ( cartData ) =>
	sprintf(
		/* translators: 1: currency the express payment started in, 2: currency required by the selected address */
		__(
			'This express payment started in %1$s and cannot switch to %2$s for the address you selected. Choose a different shipping address, or use the regular checkout to pay in %2$s.',
			'woocommerce-gateway-stripe'
		),
		getElementCurrency().toUpperCase(),
		cartData.totals.currency_code.toUpperCase()
	);

/**
 * Handles changes to the shipping address in the Express Checkout flow by
 * fetching updated shipping options from the server and resolving the event.
 *
 * @param {Object}   event    The Stripe shipping address change event.
 * @param {Object}   elements The Stripe Elements instance.
 * @param {Function} onError  Surfaces a human-readable reason when the event is rejected.
 * @return {Promise<void>} Resolves when the shipping options have been updated.
 */
export const shippingAddressChangeHandler = async (
	event,
	elements,
	onError
) => {
	try {
		const cartData = await cartApi.updateCustomer( {
			shipping_address: transformStripeShippingAddressForStoreApi(
				event.name,
				event.address
			),
		} );

		if ( cartCurrencyDriftedFromElement( cartData ) ) {
			onError?.( getCurrencyMismatchMessage( cartData ) );
			event.reject();
			return;
		}

		const shippingRates = transformCartDataForShippingRates( cartData );

		if ( shippingRates.length === 0 ) {
			event.reject();
			return;
		}

		elements.update( {
			amount: transformPrice(
				parseInt( cartData.totals.total_price, 10 ) -
					parseInt( cartData.totals.total_refund || 0, 10 ),
				cartData.totals
			),
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
 * @param {Object}   event    The Stripe shipping rate change event.
 * @param {Object}   elements The Stripe Elements instance.
 * @param {Function} onError  Surfaces a human-readable reason when the event is rejected.
 * @return {Promise<void>} Resolves when the shipping rate has been updated.
 */
export const shippingRateChangeHandler = async ( event, elements, onError ) => {
	try {
		const cartData = await cartApi.selectShippingRate( {
			package_id: 0,
			rate_id: event.shippingRate.id,
		} );

		if ( cartCurrencyDriftedFromElement( cartData ) ) {
			onError?.( getCurrencyMismatchMessage( cartData ) );
			event.reject();
			return;
		}

		elements.update( {
			amount: transformPrice(
				parseInt( cartData.totals.total_price, 10 ) -
					parseInt( cartData.totals.total_refund || 0, 10 ),
				cartData.totals
			),
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
		return abortPayment( event, submitResponse?.error?.message );
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
