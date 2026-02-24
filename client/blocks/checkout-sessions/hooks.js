import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 */

/**
 * Handles the Block Checkout onPaymentSetup event for the Checkout Sessions integration.
 *
 * @param {*}       onPaymentSetup           The onPaymentSetup event, which is triggered when the payment method is being set up during the checkout process.
 * @param {Object}  billingAddress           The billing address information, used to create the Stripe Customer object and for validation purposes.
 * @param {string}  checkoutSessionId        The ID of the checkout session, used to associate the payment method with the session.
 * @param {string}  errorMessage             An error message to display if there was an error loading the checkout session, used to provide feedback to the user.
 * @param {Object}  hasLoadErrorRef          A ref object that indicates whether there was an error loading the checkout session, used to prevent further processing if the session failed to load.
 * @param {boolean} isPaymentElementComplete A boolean that indicates whether the Stripe Payment Element is complete, used to validate that the user has entered all required payment information before allowing them to proceed with the payment.
 */
export const usePaymentSetupHandler = (
	onPaymentSetup,
	billingAddress,
	checkoutSessionId,
	errorMessage,
	hasLoadErrorRef,
	isPaymentElementComplete
) => {
	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					if ( hasLoadErrorRef.current ) {
						return {
							type: 'error',
							message: __(
								'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					const { validationStore } = window.wc?.wcBlocksData ?? {};
					if ( validationStore ) {
						const store = select( validationStore );
						const hasValidationErrors = store.hasValidationErrors();

						// Return if there is a validation error on the checkout fields.
						if ( hasValidationErrors ) {
							return;
						}
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

					if ( errorMessage ) {
						return {
							type: 'error',
							message: errorMessage,
						};
					}

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: 'stripe',
								'wc-stripe-is-deferred-intent': true,
								save_payment_method: 'no', // TODO: Correctly handle this when supporting saved payment methods in the future.
								wc_stripe_checkout_session_id:
									checkoutSessionId,

								// The billing information here is relevant to properly create the Stripe Customer object.
								billing_email: billingAddress.email,
								billing_first_name: billingAddress.first_name,
								billing_last_name: billingAddress.last_name,
								billing_address_1: billingAddress.address_1,
								billing_address_2: billingAddress.address_2,
								billing_city: billingAddress.city,
								billing_state: billingAddress.state,
								billing_postcode: billingAddress.postcode,
								billing_country: billingAddress.country,
							},
						},
					};
				}
				return handlePaymentProcessing();
			} ),
		[
			billingAddress,
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
					} );
					if ( confirmResult?.type === 'error' ) {
						return {
							type: 'error',
							message:
								confirmResult.error?.message ??
								'Payment confirmation failed.',
						};
					}

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
