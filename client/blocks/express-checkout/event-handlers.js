import { __ } from '@wordpress/i18n';
import { getErrorMessageFromNotice } from './utils';
import {
	normalizeECEOrderData,
	normalizeECEPayForOrderData,
	normalizeShippingAddress,
	normalizeLineItems,
} from 'wcstripe/blocks/normalize';
import {
	updateECEShippingOptions,
	updateECEShippingDetails,
	expressCheckoutCreateOrder,
	expressCheckoutPayForOrder,
} from 'wcstripe/api/blocks';

export const shippingAddressChangeHandler = async ( api, event, elements ) => {
	try {
		const response = await updateECEShippingOptions(
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
		const response = await updateECEShippingDetails( event.shippingRate );

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
	const { error: submitError } = await elements.submit();
	if ( submitError ) {
		return abortPayment( event, submitError.message );
	}

	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );

	if ( error ) {
		return abortPayment( event, error.message );
	}

	try {
		let orderResponse;
		if ( ! order ) {
			orderResponse = await expressCheckoutCreateOrder(
				normalizeECEOrderData( event, paymentMethod.id )
			);
		} else {
			orderResponse = await expressCheckoutPayForOrder(
				order,
				normalizeECEPayForOrderData( event, paymentMethod.id )
			);
		}

		if ( orderResponse.result !== 'success' ) {
			return abortPayment(
				event,
				getErrorMessageFromNotice( orderResponse.messages )
			);
		}

		const confirmationRequest = api.confirmIntent( orderResponse.redirect );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( orderResponse.redirect );
		} else {
			const redirectUrl = await confirmationRequest;

			completePayment( redirectUrl );
		}
	} catch ( e ) {
		return abortPayment(
			event,
			e.message ??
				__(
					'There was a problem processing the order.',
					'woocommerce-payments'
				)
		);
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
