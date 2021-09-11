/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import {
	Elements,
	ElementsConsumer,
	PaymentElement,
} from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import { confirmUpePayment } from './confirm-upe-payment';
/* eslint-disable @woocommerce/dependency-group */
import { getStripeServerData } from 'wcstripe/stripe-utils';
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

	useEffect( () => {
		if ( paymentIntentId || hasRequestedIntent ) {
			return;
		}

		async function createIntent() {
			try {
				const response = await api.createIntent(
					getStripeServerData()?.orderId
				);
				setPaymentIntentId( response.id );
				setClientSecret( response.client_secret );
			} catch ( error ) {
				setErrorMessage(
					error?.message ??
						'There was an error loading the payment gateway'
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
						message: 'Your payment information is incomplete.',
					};
				}

				if ( errorMessage ) {
					return {
						type: 'error',
						message: errorMessage,
					};
				}

				if (
					shouldSavePayment /* &&
				! paymentMethodsConfig[ selectedUPEPaymentType ].isReusable */
				) {
					return {
						type: 'error',
						message:
							'This payment method can not be saved for future use.',
					};
				}
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
		business: { name: 'Automattic' },
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
		<PaymentElement
			options={ elementOptions }
			onChange={ ( event ) => {
				setIsUpeComplete( event.complete );
				setSelectedUpePaymentType( event.value.type );
			} }
		/>
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
