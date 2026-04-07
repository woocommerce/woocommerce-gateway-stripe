import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';
import { isSavePaymentMethodCheckboxChecked } from 'wcstripe/blocks/utils';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 */

/**
 * Handles the Block Checkout onPaymentSetup event for the Checkout Sessions integration.
 *
 * @param {*}       onPaymentSetup           The onPaymentSetup event, which is triggered when the payment method is being set up during the checkout process.
 * @param {string}  checkoutSessionId        The ID of the checkout session, used to associate the payment method with the session.
 * @param {string}  errorMessage             An error message to display if there was an error loading the checkout session, used to provide feedback to the user.
 * @param {Object}  hasLoadErrorRef          A ref object that indicates whether there was an error loading the checkout session, used to prevent further processing if the session failed to load.
 * @param {boolean} isPaymentElementComplete A boolean that indicates whether the Stripe Payment Element is complete, used to validate that the user has entered all required payment information before allowing them to proceed with the payment.
 */
export const usePaymentSetupHandler = (
	onPaymentSetup,
	checkoutSessionId,
	errorMessage,
	hasLoadErrorRef,
	isPaymentElementComplete
) => {
	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					const { validationStore } = window.wc?.wcBlocksData ?? {};
					if ( validationStore ) {
						const store = select( validationStore );
						const hasValidationErrors = store.hasValidationErrors();

						// Return if there is a validation error on the checkout fields.
						if ( hasValidationErrors ) {
							return;
						}
					}

					if ( hasLoadErrorRef.current ) {
						return {
							type: 'error',
							message: __(
								'There was an error loading the payment information. Please refresh the page and try again.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					if ( ! checkoutSessionId ) {
						return {
							type: 'error',
							message: __(
								'We could not initialize the payment session. Please refresh the page and try again.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					if ( errorMessage ) {
						return {
							type: 'error',
							message: errorMessage,
						};
					}

					if ( ! isPaymentElementComplete ) {
						return {
							type: 'error',
							message: __(
								'Your payment information is incomplete.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: 'stripe',
								save_payment_method:
									isSavePaymentMethodCheckboxChecked()
										? 'yes'
										: 'no',
								wc_stripe_checkout_session_id:
									checkoutSessionId,
							},
						},
					};
				}
				return handlePaymentProcessing();
			} ),
		[
			checkoutSessionId,
			errorMessage,
			hasLoadErrorRef,
			isPaymentElementComplete,
			onPaymentSetup,
		]
	);
};

/**
 * Handles the Block Checkout onCheckoutSuccess event for the Checkout Sessions integration.
 *
 * @param {*} checkoutState     The checkout state.
 * @param {*} onCheckoutSuccess The onCheckoutSuccess event.
 */
export const useCheckoutSuccessHandler = (
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
						redirect: 'if_required',
						savePaymentMethod: isSavePaymentMethodCheckboxChecked(),
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
 * @param {*}                 onCheckoutFail The onCheckoutFail event.
 * @param {EmitResponseProps} emitResponse   Various helpers for usage with observer.
 */
export const usePaymentFailHandler = ( onCheckoutFail, emitResponse ) => {
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
		[ onCheckoutFail, emitResponse ]
	);
};
