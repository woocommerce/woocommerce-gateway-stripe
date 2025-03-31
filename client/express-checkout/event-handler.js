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
	const { abortPayment, elements, event, api } = params;

	if ( ! isManualPaymentMethodCreation( event.expressPaymentType ) ) {
		const billingAddress = event?.billingDetails?.address;
		if ( billingAddress ) {
			const country = billingAddress?.country ?? '';
			const state = billingAddress?.state ?? '';
			const postcode = billingAddress?.postal_code ?? '';
			const city = billingAddress?.city ?? '';
			const response = await api.expressCheckoutGetUpdatedCartTotal( {
				country,
				state,
				postcode,
				city,
			} );

			// If current amount is not equal to updated cart total, update the amount.
			if ( response.result === 'success' && response.total > 0 ) {
				elements.update( { amount: response.total } );
			}
		}

		const submitResponse = await elements.submit();
		if ( submitResponse?.error ) {
			return abortPayment( event, submitResponse?.error?.message );
		}

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
