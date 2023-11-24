/**
 * External dependencies
 */

import {
	getPaymentMethods,
	// eslint-disable-next-line import/no-unresolved
} from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import {
	PaymentElement,
	useElements,
	useStripe,
} from '@stripe/react-stripe-js';
import { useEffect, useState } from 'react';
/**
 * Internal dependencies
 */
import { usePaymentCompleteHandler, usePaymentFailHandler } from '../hooks';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

const getStripeElementOptions = () => {
	const options = {
		fields: {
			billingDetails: {
				name: 'never',
				email: 'never',
				phone: 'never',
				address: {
					country: 'never',
					line1: 'never',
					line2: 'never',
					city: 'never',
					state: 'never',
					postalCode: 'never',
				},
			},
		},
		wallets: {
			applePay: 'never',
			googlePay: 'never',
		},
	};

	return options;
};

export function validateElements( elements ) {
	return elements.submit().then( ( result ) => {
		if ( result.error ) {
			throw new Error( result.error.message );
		}
	} );
}

const PaymentProcessor = ( {
	api,
	activePaymentMethod,
	testingInstructions,
	eventRegistration: { onPaymentSetup, onCheckoutSuccess, onCheckoutFail },
	emitResponse,
	paymentMethodId,
	upeMethods,
	errorMessage,
	shouldSavePayment,
	fingerprint,
	billing,
} ) => {
	const stripe = useStripe();
	const elements = useElements();
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] = useState(
		false
	);
	const testingInstructionsIfAppropriate = getBlocksConfiguration()?.testMode
		? testingInstructions
		: '';
	const paymentMethodsConfig = getBlocksConfiguration()?.paymentMethodsConfig;
	const gatewayConfig = getPaymentMethods()[ upeMethods[ paymentMethodId ] ];

	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					if (
						upeMethods[ paymentMethodId ] !== activePaymentMethod
					) {
						return;
					}

					if ( ! isPaymentElementComplete ) {
						return {
							type: 'error',
							message: __(
								'Your payment information is incomplete.',
								'woocommerce-payments'
							),
						};
					}

					if ( errorMessage ) {
						return {
							type: 'error',
							message: errorMessage,
						};
					}

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

					await validateElements( elements );

					const billingAddress = billing.billingAddress;
					const paymentMethodObject = await api
						.getStripe()
						.createPaymentMethod( {
							elements,
							params: {
								billing_details: {
									name: billingAddress.first_name,
									email: billingAddress.email,
									phone: billingAddress.phone,
									address: {
										city: billingAddress.city,
										country: billingAddress.country,
										line1: billingAddress.address_1,
										line2: billingAddress.address_2,
										postal_code: billingAddress.postcode,
										state: billingAddress.state,
									},
								},
							},
						} );
					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								stripe_source:
									paymentMethodObject.paymentMethod.id,
								// The billing information here is relevant to properly create the
								// Stripe Customer object.
								billing_email: 'james.allan@automattic.com',
								billing_first_name: 'james',
								billing_last_name: 'allan',
								'wc-stripe-is-deferred-intent': true,
								paymentMethod: upeMethods[ paymentMethodId ],
								paymentRequestType: 'cc',
								payment_method: 'stripe',
								wc_stripe_selected_upe_payment_type: paymentMethodId,
								'wc-stripe-new-payment-method':
									paymentMethodObject.paymentMethod.id,
								'wc-stripe-payment-method':
									paymentMethodObject.paymentMethod.id,
							},
						},
					};
				}
				return handlePaymentProcessing();
			} ),
		[
			activePaymentMethod,
			api,
			elements,
			fingerprint,
			gatewayConfig,
			paymentMethodId,
			paymentMethodsConfig,
			shouldSavePayment,
			upeMethods,
			errorMessage,
			onPaymentSetup,
			isPaymentElementComplete,
			billing.billingAddress,
		]
	);

	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutSuccess,
		emitResponse,
		shouldSavePayment
	);

	usePaymentFailHandler(
		api,
		stripe,
		elements,
		onCheckoutFail,
		emitResponse
	);

	const updatePaymentElementCompletionStatus = ( event ) => {
		setIsPaymentElementComplete( event.complete );
	};

	return (
		<>
			<p
				className="content"
				dangerouslySetInnerHTML={ {
					__html: testingInstructionsIfAppropriate,
				} }
			/>
			<PaymentElement
				options={ getStripeElementOptions() }
				onChange={ updatePaymentElementCompletionStatus }
				className="wcpay-payment-element"
			/>
		</>
	);
};

export default PaymentProcessor;
