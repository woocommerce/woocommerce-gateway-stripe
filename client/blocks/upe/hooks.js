import { useEffect } from '@wordpress/element';
import confirmCardPayment from './confirm-card-payment.js';

export const usePaymentCompleteHandler = (
	api,
	stripe,
	elements,
	onCheckoutSuccess,
	emitResponse,
	shouldSavePayment
) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	useEffect(
		() =>
			onCheckoutSuccess(
				( { processingResponse: { paymentDetails } } ) => {
					return confirmCardPayment(
						api,
						paymentDetails,
						emitResponse,
						shouldSavePayment
					);
				}
			),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ elements, stripe, api, shouldSavePayment ]
	);
};

export const usePaymentFailHandler = (
	api,
	stripe,
	elements,
	onCheckoutFail,
	emitResponse
) => {
	useEffect(
		() =>
			onCheckoutFail( ( { processingResponse: { paymentDetails } } ) => {
				return {
					type: 'failure',
					message: paymentDetails.errorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			} ),
		[
			elements,
			stripe,
			api,
			onCheckoutFail,
			emitResponse.noticeContexts.PAYMENTS,
		]
	);
};
