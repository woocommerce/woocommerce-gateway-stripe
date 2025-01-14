import { __ } from '@wordpress/i18n';
import {
	getErrorMessageFromNotice,
	normalizeOrderData,
	normalizePayForOrderData,
	normalizeShippingAddress,
	normalizeLineItems,
	getExpressCheckoutData,
} from './utils';
import {
	trackExpressCheckoutButtonClick,
	trackExpressCheckoutButtonLoad,
} from './tracking';

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
				shippingRates: response.shipping_options,
				lineItems: normalizeLineItems( response.displayItems ),
			} );
		} else {
			event.reject();
		}
	} catch ( e ) {
		event.reject();
	}
};

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

const handleManualPaymentMethodFlow = async (
	api,
	stripe,
	elements,
	completePayment,
	abortPayment,
	event,
	order = 0
) => {
	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );

	if ( error ) {
		return abortPayment( event, error.message );
	}

	try {
		// Kick off checkout processing step.
		let orderResponse;
		if ( ! order ) {
			orderResponse = await api.expressCheckoutECECreateOrder(
				normalizeOrderData( event, paymentMethod.id )
			);
		} else {
			orderResponse = await api.expressCheckoutECEPayForOrder(
				order,
				normalizePayForOrderData( event, paymentMethod.id )
			);
		}

		if ( orderResponse.result !== 'success' ) {
			return abortPayment(
				event,
				getErrorMessageFromNotice( orderResponse.messages ),
				true
			);
		}

		const confirmationRequest = api.confirmIntent( orderResponse.redirect );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( orderResponse.redirect );
		} else {
			const { request } = confirmationRequest;
			const redirectUrl = await request;

			completePayment( redirectUrl );
		}
	} catch ( e ) {
		let errorMessage;
		if ( e.message ) {
			errorMessage = e.message;
		} else {
			errorMessage = __(
				'There was a problem processing the order.',
				'woocommerce-gateway-stripe'
			);
		}
		return abortPayment( event, errorMessage );
	}
};

const handleConfirmationTokenFlow = async (
	api,
	stripe,
	elements,
	completePayment,
	abortPayment,
	event,
	order = 0
) => {
	// 1. Create confirmation token.
	// 2. Create the order.
	// 3. Create the payment intent.
	// 4. Confirm the payment intent.

	// Create a ConfirmationToken using the details collected by the Express Checkout Element
	const { error, confirmationToken } = await stripe.createConfirmationToken( {
		elements,
		// TODO amazon-pay-ece: Add the payment method data, if necessary.
		params: {
			// 	payment_method_data: {
			// 		billing_details: {
			// 		name: 'Jenny Rosen',
			// 		}
			// 	},
			return_url: window.location.href,
		},
	} );

	// confirmationToken.id, e.g. "ctoken_1QhcKbIMUtoK6Gfh256uyYm9"

	if ( error ) {
		// This point is only reached if there's an immediate error when
		// confirming the payment. Show the error to your customer (for example, payment details incomplete)
		// handleError(error); // TODO amazon-pay-ece: Implement handleError
		return;
	}

	let orderResponse;
	if ( ! order ) {
		orderResponse = await api.expressCheckoutECECreateOrder(
			normalizeOrderData( event, null, confirmationToken.id )
		);
	} else {
		orderResponse = await api.expressCheckoutECEPayForOrder(
			order,
			normalizePayForOrderData( event, null, confirmationToken.id )
		);
	}

	if ( orderResponse.result !== 'success' ) {
		return abortPayment(
			event,
			getErrorMessageFromNotice( orderResponse.messages ),
			true
		);
	}

	completePayment( orderResponse.redirect );
};

export const onConfirmHandler = async (
	api,
	stripe,
	elements,
	completePayment,
	abortPayment,
	event,
	order = 0 // Order ID for the pay for order flow.
) => {
	const submitResponse = await elements.submit();
	if ( submitResponse?.error ) {
		return abortPayment( event, submitResponse?.error?.message );
	}

	// Amazon Pay does not support manual payment method creation.
	if ( event.expressPaymentType === 'amazon_pay' ) {
		return handleConfirmationTokenFlow(
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event,
			order
		);
	}

	return handleManualPaymentMethodFlow(
		api,
		stripe,
		elements,
		completePayment,
		abortPayment,
		event,
		order
	);
};

export const onReadyHandler = function ( { availablePaymentMethods } ) {
	if ( availablePaymentMethods ) {
		const enabledMethods = Object.entries( availablePaymentMethods )
			.filter( ( [ , isEnabled ] ) => isEnabled )
			.map( ( [ methodName ] ) => methodName );

		trackExpressCheckoutButtonLoad( {
			paymentMethods: enabledMethods,
			source: getExpressCheckoutData( 'button_context' ),
		} );
	}
};

const blockUI = () => {
	jQuery.blockUI( {
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6,
		},
	} );
};

const unblockUI = () => {
	jQuery.unblockUI();
};

export const onClickHandler = function ( { expressPaymentType } ) {
	blockUI();
	trackExpressCheckoutButtonClick(
		expressPaymentType,
		getExpressCheckoutData( 'button_context' )
	);
};

export const onAbortPaymentHandler = () => {
	unblockUI();
};

export const onCompletePaymentHandler = () => {
	blockUI();
};

export const onCancelHandler = () => {
	unblockUI();
};
