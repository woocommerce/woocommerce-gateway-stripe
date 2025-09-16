import {
	normalizeShippingAddress,
	normalizeLineItems,
	isManualPaymentMethodCreation,
} from './utils';
import {
	handleConfirmationTokenFlow,
	handleManualPaymentMethodFlow,
} from './payment-flow';

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

export const onClickHandler = function () {
	blockUI();
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
