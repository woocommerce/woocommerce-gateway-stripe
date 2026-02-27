import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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
				async ( { processingResponse: { paymentDetails } } ) => {
					if ( checkoutState.type !== 'success' ) {
						return {
							type: 'error',
							message: __(
								'Checkout is not ready for confirmation.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					const { redirect } = paymentDetails;
					const { checkout } = checkoutState;
					const confirmResult = await checkout.confirm( {
						returnUrl: redirect,
					} );
					if ( confirmResult?.type === 'error' ) {
						return {
							type: 'error',
							message:
								confirmResult.error?.message ??
								'Payment confirmation failed.',
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
					message:
						paymentDetails?.errorMessage ??
						'An error occurred during payment processing.',
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			} ),
		[ checkoutState, onCheckoutFail, emitResponse ]
	);
};
