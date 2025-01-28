import { __ } from '@wordpress/i18n';
import { getErrorMessageFromNotice, normalizeOrderData } from './utils';

const handlePaymentFlowException = ( event, exception, abortPayment ) => {
	let errorMessage;
	if ( exception.message ) {
		errorMessage = exception.message;
	} else {
		const paymentDetailsErrorMessage = exception.payment_result?.payment_details.find(
			( detail ) => detail.key === 'errorMessage'
		)?.value;
		if ( paymentDetailsErrorMessage ) {
			errorMessage = paymentDetailsErrorMessage;
		}
	}
	if ( ! errorMessage ) {
		errorMessage = __(
			'There was a problem processing the order.',
			'woocommerce-gateway-stripe'
		);
	}
	return abortPayment(
		event,
		getErrorMessageFromNotice( errorMessage ),
		true
	);
};

export const handleManualPaymentMethodFlow = async ( {
	api,
	stripe,
	elements,
	completePayment,
	abortPayment,
	event,
	order = 0,
	orderDetails = {},
} ) => {
	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );

	if ( error ) {
		return abortPayment( event, error.message );
	}

	try {
		// Kick off checkout processing step.
		const { result, errorMessage, redirect } = await processOrder( {
			api,
			event,
			paymentMethodId: paymentMethod.id,
			order,
			orderDetails,
		} );

		if ( result !== 'success' ) {
			return abortPayment(
				event,
				getErrorMessageFromNotice( errorMessage ),
				true
			);
		}

		const confirmationRequest = api.confirmIntent( redirect );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( redirect );
		} else {
			const { request } = confirmationRequest;
			const redirectUrl = await request;

			completePayment( redirectUrl );
		}
	} catch ( e ) {
		return handlePaymentFlowException( event, e, abortPayment );
	}
};

export const handleConfirmationTokenFlow = async ( {
	api,
	stripe,
	elements,
	completePayment,
	abortPayment,
	event,
	order = 0,
	orderDetails = {},
} ) => {
	// Create a ConfirmationToken that we can use later to create and confirm the payment intent.
	const { error, confirmationToken } = await stripe.createConfirmationToken( {
		elements,
		params: {
			// Required by Amazon Pay, but is not used by express checkout
			// as it uses a payment modal instead of redirection.
			return_url: window.location.href,
		},
	} );

	if ( error ) {
		return abortPayment(
			event,
			getErrorMessageFromNotice( error.message ),
			true
		);
	}

	try {
		const { result, errorMessage, redirect } = await processOrder( {
			api,
			event,
			confirmationTokenId: confirmationToken.id,
			order,
			orderDetails,
		} );

		if ( result !== 'success' ) {
			return abortPayment(
				event,
				getErrorMessageFromNotice( errorMessage ),
				true
			);
		}

		const confirmationRequest = api.confirmIntent( redirect );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( redirect );
		} else {
			const { request } = confirmationRequest;
			const redirectUrl = await request;

			completePayment( redirectUrl );
		}
	} catch ( e ) {
		return handlePaymentFlowException( event, e, abortPayment );
	}
};

const processOrder = async ( {
	api,
	event,
	paymentMethodId,
	confirmationTokenId,
	order = 0,
	orderDetails = {},
} ) => {
	let orderResponse;
	if ( order ) {
		orderResponse = await api.expressCheckoutECEPayForOrder(
			order,
			orderDetails,
			normalizeOrderData( {
				event,
				paymentMethodId,
				confirmationTokenId,
			} )
		);
	} else {
		orderResponse = await api.expressCheckoutECECreateOrder(
			normalizeOrderData( {
				event,
				paymentMethodId,
				confirmationTokenId,
			} )
		);
	}

	return {
		result: orderResponse?.payment_result?.payment_status,
		errorMessage: orderResponse?.payment_result?.payment_details?.find(
			( detail ) => detail.key === 'errorMessage'
		)?.value,
		redirect: orderResponse?.payment_result?.redirect_url,
	};
};
