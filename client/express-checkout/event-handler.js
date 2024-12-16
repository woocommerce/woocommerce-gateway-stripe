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
