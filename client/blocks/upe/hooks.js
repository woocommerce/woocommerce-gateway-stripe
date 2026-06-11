import { useEffect } from '@wordpress/element';
import confirmCardPayment from './confirm-card-payment.js';

/**
 * Handles the Block Checkout onCheckoutSuccess event.
 *
 * Confirms the payment intent which was created on server and is now ready to be confirmed. The intent ID is passed in the paymentDetails object via the
 * redirect arg which will be in the following format: #wc-stripe-confirm-pi/si:{order_id}:{client_secret}:{nonce}
 *
 * @param {*} api               The api object.
 * @param {*} stripe            The Stripe object.
 * @param {*} elements          The Stripe elements object.
 * @param {*} onCheckoutSuccess The onCheckoutSuccess event.
 * @param {*} emitResponse      Various helpers for usage with observer.
 * @param {*} shouldSavePayment Whether or not to save the payment method.
 */
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
			onCheckoutSuccess( ( { processingResponse: { paymentDetails } } ) =>
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

/**
 * Handles the Block Checkout onCheckoutFail event.
 *
 * Displays the error message returned from server in the paymentDetails object in the PAYMENTS notice context container.
 *
 * @param {*} api            The api object.
 * @param {*} stripe         The Stripe object.
 * @param {*} elements       The Stripe elements object.
 * @param {*} onCheckoutFail The onCheckoutFail event.
 * @param {*} emitResponse   Various helpers for usage with observer.
 */
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
