/**
 * External dependencies
 */
import { getPaymentMethods } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import {
	PaymentElement,
	useElements,
	useStripe,
	Elements,
} from '@stripe/react-stripe-js';
import { useEffect, useState } from 'react';
/**
 * Internal dependencies
 */
import { usePaymentCompleteHandler, usePaymentFailHandler } from '../hooks';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import WCStripeAPI from 'wcstripe/api';
import {
	maybeShowCashAppLimitNotice,
	removeCashAppLimitNotice,
} from 'wcstripe/stripe-utils/cash-app-limit-notice-handler';
import { isLinkEnabled } from 'wcstripe/stripe-utils';
import { PAYMENT_METHOD_CASHAPP } from 'wcstripe/stripe-utils/constants';

/**
 * Gets the Stripe element options.
 *
 * @return {Object} The Stripe element options.
 */
const getStripeElementOptions = () => {
	let options = {
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

	// Prefill Link customer data if available.
	if ( isLinkEnabled() ) {
		const userEmail = document.getElementById( 'email' )?.value;
		if ( userEmail ) {
			const userPhone =
				document.getElementById( 'billing-phone' )?.value ||
				document.getElementById( 'shipping-phone' )?.value;

			options = {
				...options,
				defaultValues: {
					billingDetails: {
						email: userEmail,
						phone: userPhone,
					},
				},
			};
		}
	}

	return options;
};

/**
 * Submits the payment elements to Stripe for validation.
 *
 * @param {Elements} elements
 * @return {Promise} Promise that resolves when the elements are validated.
 */
export function validateElements( elements ) {
	return elements.submit().then( ( result ) => {
		if ( result.error ) {
			throw new Error( result.error.message );
		}
	} );
}

/**
 * Renders the payment processor for the Stripe UPE payment method with deferred intent creation.
 *
 * @param {*}           args                     Additional arguments passed for payment processing on the Block Checkout.
 * @param {WCStripeAPI} args.api                 The Stripe API object.
 * @param {string}      args.activePaymentMethod The currently selected/active payment method ID.
 * @param {string}      args.description         The payment method description to display.
 * @param {string}      args.testingInstructions The testing instructions to display.
 * @param {Object}      args.eventRegistration   The checkout event emitter registration object.
 * @param {Object}      args.emitResponse        Various helpers for usage with observer response objects.
 * @param {string}      args.paymentMethodId     The UPE payment method ID.
 * @param {Array}       args.upeMethods          The UPE methods.
 * @param {string}      args.errorMessage        The error message to display.
 * @param {boolean}     args.shouldSavePayment   Whether or not to save the payment method.
 * @param {string}      args.fingerprint         The fingerprint.
 * @param {Object}      args.billing             The checkout billing data.
 *
 * @return {JSX.Element} Rendered payment processor.
 */
const PaymentProcessor = ( {
	api,
	activePaymentMethod,
	description,
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
	const [
		selectedPaymentMethodType,
		setSelectedPaymentMethodType,
	] = useState( null );
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] = useState(
		false
	);
	const testingInstructionsIfAppropriate = getBlocksConfiguration()?.testMode
		? testingInstructions
		: '';
	const paymentMethodsConfig = getBlocksConfiguration()?.paymentMethodsConfig;
	const gatewayConfig = getPaymentMethods()[ upeMethods[ paymentMethodId ] ];

	// Make sure shouldSavePayment is set to true if the cart contains a subscription.
	// shouldSavePayment might be set to false because the cart contains a subscription and so the save checkbox isn't shown.
	// If thats the case, we need to force it to true.
	shouldSavePayment =
		shouldSavePayment || getBlocksConfiguration()?.cartContainsSubscription;

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
									name: `${ billingAddress.first_name } ${ billingAddress.last_name }`.trim(),
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

					if ( paymentMethodObject.error ) {
						return {
							type: 'error',
							message: paymentMethodObject.error.message,
						};
					}

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: upeMethods[ paymentMethodId ],
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

	// Show the Cash App limit notice if the payment method is selected and the cart amount is higher than 2000 USD.
	useEffect( () => {
		if ( selectedPaymentMethodType === PAYMENT_METHOD_CASHAPP ) {
			maybeShowCashAppLimitNotice(
				'.wc-block-checkout__payment-method .wc-block-components-notices',
				Number( getBlocksConfiguration()?.cartTotal ),
				true
			);
		} else {
			removeCashAppLimitNotice();
		}
	}, [ selectedPaymentMethodType ] );

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

	const onSelectedPaymentMethodChange = ( { value, complete } ) => {
		setSelectedPaymentMethodType( value.type );
		setIsPaymentElementComplete( complete );
	};

	return (
		<>
			<p
				className="content"
				dangerouslySetInnerHTML={ {
					__html: description,
				} }
			/>
			<p
				className="content"
				dangerouslySetInnerHTML={ {
					__html: testingInstructionsIfAppropriate,
				} }
			/>
			<PaymentElement
				options={ getStripeElementOptions() }
				onChange={ onSelectedPaymentMethodChange }
				className="wcstripe-payment-element"
			/>
		</>
	);
};

export default PaymentProcessor;
