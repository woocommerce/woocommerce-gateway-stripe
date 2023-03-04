import { useEffect, useCallback, useState } from '@wordpress/element';
import { getErrorMessageForTypeAndCode } from '../../stripe-utils';
import { usePaymentIntents } from '../three-d-secure';
import { usePaymentProcessing } from './use-payment-processing';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EventRegistrationProps} EventRegistrationProps
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').BillingDataProps} BillingDataProps
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 * @typedef {import('../stripe-utils/type-defs').Stripe} Stripe
 * @typedef {import('react').Dispatch<string>} PaymentMethodIdDispatch
 */

/**
 * A custom hook for the Stripe processing and event observer logic.
 *
 * @param {EventRegistrationProps}  eventRegistration  Event registration functions.
 * @param {BillingDataProps}        billing            Various billing data items.
 * @param {string}                  paymentMethodId    Current set stripe payment method id.
 * @param {PaymentMethodIdDispatch} setPaymentMethodId Setter for stripe payment method id.
 * @param {EmitResponseProps}       emitResponse       Various helpers for usage with observer
 *                                                     response objects.
 * @param {Stripe}                  stripe             The stripe.js object.
 *
 * @return {function(Object):Object} Returns a function for handling stripe error.
 */
export const useCheckoutSubscriptions = (
	eventRegistration,
	billing,
	paymentMethodId,
	setPaymentMethodId,
	emitResponse,
	stripe
) => {
	const [ error, setError ] = useState( '' );
	const onStripeError = useCallback( ( event ) => {
		const type = event.error.type;
		const code = event.error.code || '';
		const message =
			getErrorMessageForTypeAndCode( type, code ) ?? event.error.message;
		setError( message );
		return message;
	}, [] );
	const {
		onCheckoutAfterProcessingWithSuccess,
		onPaymentProcessing,
		onCheckoutAfterProcessingWithError,
	} = eventRegistration;
	usePaymentIntents(
		stripe,
		onCheckoutAfterProcessingWithSuccess,
		setPaymentMethodId,
		emitResponse
	);
	usePaymentProcessing(
		onStripeError,
		error,
		stripe,
		billing,
		emitResponse,
		paymentMethodId,
		setPaymentMethodId,
		onPaymentProcessing
	);
	// hook into and register callbacks for events.
	useEffect( () => {
		const onError = ( { processingResponse } ) => {
			if ( processingResponse?.paymentDetails?.errorMessage ) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: processingResponse.paymentDetails.errorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			}
			// so we don't break the observers.
			return true;
		};
		const unsubscribeAfterProcessing = onCheckoutAfterProcessingWithError(
			onError
		);
		return () => {
			unsubscribeAfterProcessing();
		};
	}, [
		onCheckoutAfterProcessingWithError,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
	] );
	return onStripeError;
};
