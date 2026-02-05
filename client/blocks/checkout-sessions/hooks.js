import { useEffect } from '@wordpress/element';

export const usePaymentCompleteHandler = (
	checkoutState,
	onCheckoutSuccess
) => {
	useEffect(
		() =>
			onCheckoutSuccess(
				( { processingResponse: { paymentDetails } } ) => {
					if ( checkoutState.type !== 'success' ) {
						return {
							type: 'error',
							message: 'Checkout is not ready for confirmation.',
						};
					}

					const { redirect } = paymentDetails;
					const { checkout } = checkoutState;
					const confirmResult = checkout.confirm( {
						returnUrl: redirect,
					} );
					if ( confirmResult?.type === 'error' ) {
						return {
							type: 'error',
							message: confirmResult.error.message,
						};
					}

					// If no error, we assume success for now. This return value is never used the `confirm` is success.
					return {
						type: 'success',
					};
				}
			),
		[ onCheckoutSuccess, checkoutState ]
	);
};

export const usePaymentFailHandler = (
	checkoutState,
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
		[ checkoutState, onCheckoutFail, emitResponse.noticeContexts.PAYMENTS ]
	);
};
