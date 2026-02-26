import { getErrorMessageFromNotice, normalizeOrderData } from './utils';
import { __ } from '@wordpress/i18n';

/**
 * Handles exceptions thrown during the payment flow by extracting a human-readable
 * error message and calling the abort payment callback.
 *
 * @param {Object}   event        The Stripe express checkout event.
 * @param {Object}   exception    The error or exception that was thrown.
 * @param {Function} abortPayment Callback to abort the payment with an error message.
 * @return {*} The result of calling abortPayment.
 */
const handlePaymentFlowException = ( event, exception, abortPayment ) => {
	let errorMessage;

	if ( exception.code === 'rest_invalid_param' && exception.data?.params ) {
		// Concatenate all error messages from the params.
		const errorMessages = Object.values( exception.data.params );
		errorMessage = errorMessages.join( '\n' );
	} else if ( exception.message ) {
		errorMessage = exception.message;
	} else {
		const paymentDetailsErrorMessage =
			exception.payment_result?.payment_details.find(
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

/**
 * Creates or pays for an order using the Express Checkout payment data,
 * normalizing addresses before submission.
 *
 * @param {Object} params
 * @param {Object} params.api                 The WCStripeAPI instance.
 * @param {Object} params.event               The Stripe express checkout event.
 * @param {string} params.paymentMethodId     The Stripe payment method ID (manual flow).
 * @param {string} params.confirmationTokenId The Stripe confirmation token ID (token flow).
 * @param {number} params.order               The WooCommerce order ID when paying for an existing order.
 * @param {Object} params.orderDetails        Additional order details (e.g. order key, billing email).
 * @return {Promise<{result: string, errorMessage: string|undefined, redirect: string}>} The order result.
 */
const processOrder = async ( {
	api,
	event,
	paymentMethodId,
	confirmationTokenId,
	order = 0,
	orderDetails = {},
} ) => {
	let orderResponse;

	const normalizedOrderData = normalizeOrderData( {
		event,
		paymentMethodId,
		confirmationTokenId,
	} );

	const normalizedAddress = await api.expressCheckoutNormalizeAddress(
		normalizedOrderData.billing_address,
		normalizedOrderData.shipping_address
	);

	if ( normalizedAddress ) {
		normalizedOrderData.billing_address = normalizedAddress.billing_address;
		normalizedOrderData.shipping_address =
			normalizedAddress.shipping_address;
	}

	if ( order ) {
		orderResponse = await api.expressCheckoutECEPayForOrder(
			order,
			orderDetails,
			normalizedOrderData
		);
	} else {
		orderResponse =
			await api.expressCheckoutECECreateOrder( normalizedOrderData );
	}

	// Extract redirect URL from payment_details if redirect_url is empty
	let redirectUrl = orderResponse?.payment_result?.redirect_url;
	if ( ! redirectUrl ) {
		const redirectDetail =
			orderResponse?.payment_result?.payment_details?.find(
				( detail ) => detail.key === 'redirect'
			);
		redirectUrl = redirectDetail?.value || '';
	}

	return {
		result: orderResponse?.payment_result?.payment_status,
		errorMessage: orderResponse?.payment_result?.payment_details?.find(
			( detail ) => detail.key === 'errorMessage'
		)?.value,
		redirect: redirectUrl,
	};
};

/**
 * Handles the Express Checkout payment flow using manual payment method creation.
 * Creates a Stripe payment method from the Elements, submits the order, then confirms
 * any pending payment intent.
 *
 * @param {Object}   params
 * @param {Object}   params.api             The WCStripeAPI instance.
 * @param {Object}   params.stripe          The Stripe.js instance.
 * @param {Object}   params.elements        The Stripe Elements instance.
 * @param {Function} params.completePayment Callback to complete the payment with a redirect URL.
 * @param {Function} params.abortPayment    Callback to abort the payment with an error message.
 * @param {Object}   params.event           The Stripe express checkout event.
 * @param {number}   params.order           The WooCommerce order ID when paying for an existing order.
 * @param {Object}   params.orderDetails    Additional order details.
 * @return {Promise<void>} Resolves when the payment flow has completed or been aborted.
 */
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

/**
 * Handles the Express Checkout payment flow using a Stripe confirmation token.
 * Creates a confirmation token from the Elements, submits the order, then confirms
 * any pending payment intent.
 *
 * @param {Object}   params
 * @param {Object}   params.api             The WCStripeAPI instance.
 * @param {Object}   params.stripe          The Stripe.js instance.
 * @param {Object}   params.elements        The Stripe Elements instance.
 * @param {Function} params.completePayment Callback to complete the payment with a redirect URL.
 * @param {Function} params.abortPayment    Callback to abort the payment with an error message.
 * @param {Object}   params.event           The Stripe express checkout event.
 * @param {number}   params.order           The WooCommerce order ID when paying for an existing order.
 * @param {Object}   params.orderDetails    Additional order details.
 * @return {Promise<void>} Resolves when the payment flow has completed or been aborted.
 */
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
