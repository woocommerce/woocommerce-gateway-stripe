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
import { usePaymentCompleteHandler } from '../hooks';
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

const PaymentProcessor = ( {
	api,
	activePaymentMethod,
	eventRegistration: { onPaymentSetup, onCheckoutAfterProcessingWithSuccess },
	emitResponse,
	paymentMethodId,
	upeMethods,
	errorMessage,
	shouldSavePayment,
	fingerprint,
} ) => {
	const stripe = useStripe();
	const elements = useElements();
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] = useState(
		false
	);

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

					const paymentMethodObject = await api
						.getStripe()
						.createPaymentMethod( {
							elements,
							params: {
								billing_details: {
									name: 'James',
									email: 'james@example.com',
									phone: '+6400000000',
									address: {
										city: 'Brisbane',
										country: 'Australia',
										line1: '123 Fake Street',
										line2: '',
										postal_code: '4000',
										state: 'QLD',
									},
								},
							},
						} );

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: paymentMethodId,
								'wcpay-payment-method':
									paymentMethodObject.paymentMethod.id,
								'wcpay-fingerprint': fingerprint,
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
		]
	);

	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse,
		shouldSavePayment
	);

	const updatePaymentElementCompletionStatus = ( event ) => {
		setIsPaymentElementComplete( event.complete );
	};

	return (
		<>
			<p
				className="content"
				dangerouslySetInnerHTML={ {
					__html: 'Please enter your payment details below.',
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
