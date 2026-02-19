import { useEffect } from '@wordpress/element';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 */

/**
 * Handles the Block Checkout onCheckoutSuccess event for the Checkout Sessions integration.
 *
 * @param {*} checkoutState     The checkout state.
 * @param {*} onCheckoutSuccess The onCheckoutSuccess event.
 */
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
							message: __( 'Checkout is not ready for confirmation.', 'woocommerce-gateway-stripe' ),
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

					// If no error, we assume success for now. This return value is never used, as the `confirm` call indicates success.
					return {
						type: 'success',
					};
				}
			),
		[ onCheckoutSuccess, checkoutState ]
	);
};

/**
 * Handles the Block Checkout onCheckoutFail event for the Checkout Sessions integration.
 *
 * @param {*}                 checkoutState  The checkout state.
 * @param {*}                 onCheckoutFail The onCheckoutFail event.
 * @param {EmitResponseProps} emitResponse   Various helpers for usage with observer.
 */
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
