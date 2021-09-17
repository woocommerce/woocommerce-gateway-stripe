/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import {
	Elements,
	ElementsConsumer,
	PaymentElement,
} from '@stripe/react-stripe-js';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { confirmUpePayment } from './confirm-upe-payment';
/* eslint-disable @woocommerce/dependency-group */
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { PAYMENT_METHOD_NAME } from 'wcstripe/blocks/credit-card/constants';
/* eslint-enable */

const UPEField = ( {
	api,
	activePaymentMethod,
	billing: { billingData },
	elements,
	emitResponse,
	eventRegistration: {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithSuccess,
	},
	shouldSavePayment,
	stripe,
} ) => {
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ selectedUpePaymentType, setSelectedUpePaymentType ] = useState(
		''
	);
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );
	const [ isUpeComplete, setIsUpeComplete ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( null );

	const paymentMethodsConfig = getBlocksConfiguration()?.paymentMethodsConfig;

	useEffect( () => {
		if ( paymentIntentId || hasRequestedIntent ) {
			return;
		}

		async function createIntent() {
			try {
				const response = await api.createIntent(
					getBlocksConfiguration()?.orderId
				);
				setPaymentIntentId( response.id );
				setClientSecret( response.client_secret );
			} catch ( error ) {
				setErrorMessage(
					error?.message ??
						__(
							'There was an error loading the payment gateway',
							'woocommerce-gateway-stripe'
						)
				);
			}
		}

		setHasRequestedIntent( true );
		createIntent();
	}, [ paymentIntentId, hasRequestedIntent, api, errorMessage ] );

	useEffect(
		() =>
			onPaymentProcessing( () => {
				if ( activePaymentMethod !== 'stripe' ) {
					return;
				}

				if ( ! isUpeComplete ) {
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
					shouldSavePayment &&
					! paymentMethodsConfig[ selectedUpePaymentType ].isReusable
				) {
					return {
						type: 'error',
						message: __(
							'This payment method can not be saved for future use.',
							'woocommerce-gateway-stripe'
						),
					};
				}

				return {
					type: 'success',
					meta: {
						paymentMethodData: {
							paymentMethod: PAYMENT_METHOD_NAME,
							wc_payment_intent_id: paymentIntentId,
						},
					},
				};
			} ),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ activePaymentMethod, isUpeComplete, shouldSavePayment ]
	);

	useEffect(
		() =>
			onCheckoutAfterProcessingWithSuccess(
				( { orderId, processingResponse: { paymentDetails } } ) => {
					async function updateIntent() {
						await api.updateIntent(
							paymentIntentId,
							orderId,
							shouldSavePayment ? 'yes' : 'no',
							selectedUpePaymentType
						);

						const paymentElement = elements.getElement(
							PaymentElement
						);

						return confirmUpePayment(
							api,
							paymentDetails.redirect_url,
							paymentDetails.payment_needed,
							paymentElement,
							billingData,
							emitResponse
						);
					}

					return updateIntent();
				}
			),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[
			api,
			elements,
			paymentIntentId,
			selectedUpePaymentType,
			shouldSavePayment,
			stripe,
		]
	);

	const elementOptions = {
		clientSecret,
		business: { name: getBlocksConfiguration()?.accountDescriptor },
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
	};

	if ( ! clientSecret ) {
		if ( errorMessage ) {
			return (
				<div className="woocommerce-error">
					<div className="components-notice__content">
						{ errorMessage }
					</div>
				</div>
			);
		}

		return null;
	}

	return (
		<div className={ 'wc-block-gateway-container' }>
			<PaymentElement
				options={ elementOptions }
				onChange={ ( event ) => {
					setIsUpeComplete( event.complete );
					setSelectedUpePaymentType( event.value.type );
				} }
			/>
		</div>
	);
};

export const UPEPaymentForm = ( { api, ...props } ) => {
	return (
		<Elements stripe={ api.getStripe() }>
			<ElementsConsumer>
				{ ( { stripe, elements } ) => (
					<UPEField
						api={ api }
						elements={ elements }
						stripe={ stripe }
						{ ...props }
					/>
				) }
			</ElementsConsumer>
		</Elements>
	);
};
