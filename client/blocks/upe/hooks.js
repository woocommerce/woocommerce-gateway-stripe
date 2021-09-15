/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import confirmCardPayment from './confirm-card-payment.js';

export const usePaymentCompleteHandler = (
	api,
	stripe,
	elements,
	onCheckoutAfterProcessingWithSuccess,
	emitResponse,
	shouldSavePayment
) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	useEffect(
		() =>
			onCheckoutAfterProcessingWithSuccess(
				( { processingResponse: { paymentDetails } } ) =>
					confirmCardPayment(
						api,
						paymentDetails,
						emitResponse,
						shouldSavePayment
					)
			),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ elements, stripe, api, shouldSavePayment ]
	);
};
