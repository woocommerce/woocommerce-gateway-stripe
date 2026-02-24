import { getPaymentMethods } from '@woocommerce/blocks-registry';
import confirmCardPayment from './confirm-card-payment.js';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';
import { validateBlikCode } from 'wcstripe/stripe-utils';
import { validateElements } from 'wcstripe/blocks/upe/upe-deferred-intent-creation/payment-processor';
import { PAYMENT_METHOD_BLIK } from 'wcstripe/stripe-utils/constants';
import WCStripeAPI from 'wcstripe/api';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/**
 * Handles the Block Checkout onPaymentSetup event for the UPE integration.
 *
 * @param {string}      activePaymentMethod       The currently active payment method ID.
 * @param {WCStripeAPI} api                       The API object for interacting with Stripe and WooCommerce.
 * @param {Object}      billingAddress            Object containing the billing address details.
 * @param {Object}      elements                  The Stripe Elements object, used to validate and create the payment method.
 * @param {string}      errorMessage              Error message to display if there is an issue with the payment method setup, such as validation errors or issues loading the Stripe elements.
 * @param {Object}      hasLoadErrorRef           Ref object to track if there was an error loading the payment method.
 * @param {boolean}     isPaymentElementComplete  Boolean indicating whether the Stripe Payment Element is complete.
 * @param {*}           onPaymentSetup            The onPaymentSetup event registration function, used to register the payment setup handler with the Block Checkout.
 * @param {string|null} paymentIntentId           The ID of the payment intent, if it has been created prior to this step.
 * @param {string}      paymentMethodId           The ID of the payment method being set up.
 * @param {string}      selectedPaymentMethodType The type of the selected payment method, used to determine if special handling is needed (e.g. for BLIK).
 * @param {boolean}     shouldSavePayment         Boolean indicating whether the user has opted to save the payment method for future use.
 * @param {Array}       upeMethods                Array of UPE payment method types, used to check if the current payment method is part of the UPE integration.
 */
export const usePaymentSetupHandler = (
	activePaymentMethod,
	api,
	billingAddress,
	elements,
	errorMessage,
	hasLoadErrorRef,
	isPaymentElementComplete,
	onPaymentSetup,
	paymentIntentId,
	paymentMethodId,
	selectedPaymentMethodType,
	shouldSavePayment,
	upeMethods
) => {
	const paymentMethodsConfig = getBlocksConfiguration()?.paymentMethodsConfig;
	const gatewayConfig = getPaymentMethods()[ upeMethods[ paymentMethodId ] ];
	const isBlikSelected = selectedPaymentMethodType === PAYMENT_METHOD_BLIK;
	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					if (
						upeMethods[ paymentMethodId ] !== activePaymentMethod
					) {
						return;
					}

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

					// BLIK is a special case which is not handled through the Stripe element.
					if ( ! ( isPaymentElementComplete || isBlikSelected ) ) {
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

					// Check if user tried to save a method that isn’t reusable.
					if (
						gatewayConfig.supports.showSaveOption &&
						shouldSavePayment &&
						! paymentMethodsConfig[ paymentMethodId ].isReusable
					) {
						return {
							type: 'error',
							message:
								'This payment method cannot be saved for future use.',
						};
					}

					if ( isBlikSelected ) {
						validateBlikCode();
					} else {
						await validateElements( elements );
					}

					const params = {
						billing_details: {
							name: `${ billingAddress.first_name } ${ billingAddress.last_name }`.trim(),
							email: billingAddress.email,
							phone: billingAddress.phone || null, // Phone is optional, but an empty string is not allowed by Stripe.
							address: {
								city: billingAddress.city,
								country: billingAddress.country,
								line1: billingAddress.address_1,
								line2: billingAddress.address_2,
								postal_code: billingAddress.postcode,
								state: billingAddress.state,
							},
						},
					};
					const paymentMethodData = isBlikSelected
						? {
								billing_details: params.billing_details,
								blik: {},
								type: selectedPaymentMethodType,
						  }
						: { elements, params };
					const paymentMethodObject = await api
						.getStripe()
						.createPaymentMethod( paymentMethodData );

					if ( paymentMethodObject.error ) {
						return {
							type: 'error',
							message: paymentMethodObject.error.message,
						};
					}

					const dynamicPaymentData = isBlikSelected
						? {
								'wc-stripe-blik-code': document?.querySelector(
									'#wc-stripe-blik-code'
								)?.value,
						  }
						: {};

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								...dynamicPaymentData,
								payment_method: upeMethods[ paymentMethodId ],
								wc_payment_intent_id: paymentIntentId ?? '',
								'wc-stripe-is-deferred-intent': true,
								'wc-stripe-payment-method':
									paymentMethodObject.paymentMethod.id,
								save_payment_method: shouldSavePayment
									? 'yes'
									: 'no',
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
			activePaymentMethod,
			api,
			billingAddress,
			elements,
			errorMessage,
			gatewayConfig,
			hasLoadErrorRef,
			isBlikSelected,
			isPaymentElementComplete,
			onPaymentSetup,
			paymentIntentId,
			paymentMethodId,
			paymentMethodsConfig,
			selectedPaymentMethodType,
			shouldSavePayment,
			upeMethods,
		]
	);
};

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
export const useCheckoutSuccessHandler = (
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
export const useCheckoutFailHandler = (
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
