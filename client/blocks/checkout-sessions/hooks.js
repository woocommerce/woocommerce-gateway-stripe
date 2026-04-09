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
 * @param {*}       checkoutState     The checkout state.
 * @param {*}       onCheckoutSuccess The onCheckoutSuccess event.
 * @param {Object}  billing           The billing data from WooCommerce Blocks, containing billingAddress.
 * @param {boolean} isLoggedIn        Whether the customer is logged-in.
 * @param {Object}  shippingData      The shipping data from WooCommerce Blocks, containing shippingAddress.
 */
export const useCheckoutSuccessHandler = (
	checkoutState,
	onCheckoutSuccess,
	billing,
	isLoggedIn,
	shippingData
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

					const billingAddress = billing?.billingAddress;
					const shippingAddress = shippingData?.shippingAddress;

					const { redirect } = paymentDetails;
					const { checkout } = checkoutState;

					const confirmArgs = {
						billingAddress: {
							name: `${ billingAddress?.first_name ?? '' } ${
								billingAddress?.last_name ?? ''
							}`.trim(),
							address: {
								country: billingAddress?.country,
								line1: billingAddress?.address_1,
								line2: billingAddress?.address_2,
								state: billingAddress?.state,
								city: billingAddress?.city,
								postal_code: billingAddress?.postcode,
							},
						},
						returnUrl: redirect,
						redirect: 'if_required',
						savePaymentMethod: isSavePaymentMethodCheckboxChecked(),
					};

					// Only include shipping information if the min. requirement is met.
					if (
						shippingAddress?.address_1 &&
						shippingAddress?.country
					) {
						confirmArgs.shippingAddress = {
							address: {
								country: shippingAddress?.country,
								line1: shippingAddress?.address_1,
							},
						};

						// API do not accept empty values.
						if ( shippingAddress?.recipient ) {
							confirmArgs.shippingAddress.name =
								shippingAddress?.recipient.trim();
						}

						// If the shipping address name is still empty, attempt to use the billing name (it is a required parameter).
						if ( ! confirmArgs.shippingAddress.name ) {
							confirmArgs.shippingAddress.name = `${
								billingAddress?.first_name ?? ''
							} ${ billingAddress?.last_name ?? '' }`.trim();
						}

						if ( shippingAddress?.address_2 ) {
							confirmArgs.shippingAddress.address.line2 =
								shippingAddress?.address_2;
						}

						if ( shippingAddress?.state ) {
							confirmArgs.shippingAddress.address.state =
								shippingAddress?.state;
						}

						if ( shippingAddress?.city ) {
							confirmArgs.shippingAddress.address.city =
								shippingAddress?.city;
						}

						if ( shippingAddress?.postcode ) {
							confirmArgs.shippingAddress.address.postal_code =
								shippingAddress?.postcode;
						}
					}

					if ( ! checkout.email ) {
						// If checkout session doesn't have email, attempt to get it from the checkout form.
						const userEmail =
							document.getElementById( 'email' )?.value;
						if ( userEmail ) {
							confirmArgs.email = userEmail;
						}
					}

					if ( isLoggedIn ) {
						const userPhone =
							document.getElementById( 'billing-phone' )?.value ||
							document.getElementById( 'shipping-phone' )?.value;
						if ( userPhone ) {
							confirmArgs.phoneNumber = userPhone;
						}
					}

					const confirmResult = await checkout.confirm( confirmArgs );
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
		[ onCheckoutSuccess, checkoutState, billing, isLoggedIn, shippingData ]
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
