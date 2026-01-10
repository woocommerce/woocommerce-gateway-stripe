import {
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
	usePaymentCompleteHandler2,
	usePaymentFailHandler2,
} from 'wcstripe/blocks/upe/hooks';
import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT } from 'wcstripe/stripe-utils/constants';

const noop = () => null;

const getStripeElementOptions = () => {
	let options = {
		fields: {
			billingDetails: {
				name: 'never',
				email: 'never',
				// The phone field is optional, so it needs to be "auto" to not throw errors
				// when passing the phone parameter to create a payment method.
				phone: 'auto',
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

	if ( getBlocksConfiguration()?.isOCEnabled ) {
		const layout = {
			type:
				getBlocksConfiguration()?.OCLayout ||
				OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT,
		};
		if ( layout.type === OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT ) {
			layout.radios = false;
		}
		options = {
			...options,
			layout,
		};
	}

	return options;
};

const CheckoutForm = ( {
	api,
	stripe,
	eventRegistration: {
		onPaymentSetup,
		onCheckoutSuccess,
		onCheckoutFail,
		// onPaymentProcessing,
	},
	emitResponse,
	errorMessage,
	billing,
	onLoadError = noop,
} ) => {
	const checkoutState = useCheckout();
	const [ , setSelectedPaymentMethodType ] = useState( null );
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] =
		useState( false );
	const hasLoadErrorRef = useRef( false );
	const setHasLoadError = ( event ) => {
		hasLoadErrorRef.current = true;
		onLoadError( event );
	};

	const confirmCheckoutSession = useCallback( async () => {
		if ( checkoutState.type === 'success' ) {
			const { checkout } = checkoutState;
			if ( checkout.canConfirm ) {
				return await checkout.confirm();
			}
		}
	}, [ checkoutState ] );

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

					const billingAddress = billing.billingAddress;

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: 'stripe',
								'wc-stripe-is-deferred-intent': true,
								'wc-stripe-payment-method': '',
								save_payment_method: 'no',
								// checkout_session_id: checkout.id,

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
			api,
			errorMessage,
			onPaymentSetup,
			isPaymentElementComplete,
			billing.billingAddress,
		]
	);

	useEffect( () => {
		const placeOrderButton = document.querySelector(
			'button.wc-block-components-checkout-place-order-button'
		);
		const placeOrderListener = async ( event ) => {
			event.preventDefault();

			const confirmResult = await confirmCheckoutSession();
			if ( confirmResult?.type === 'error' ) {
				throw new Error( confirmResult.error.message );
			}
		};
		placeOrderButton.removeEventListener( 'click', placeOrderListener );
		placeOrderButton.addEventListener( 'click', placeOrderListener );
	}, [] );

	usePaymentCompleteHandler2(
		api,
		stripe,
		checkoutState,
		onCheckoutSuccess,
		emitResponse,
		false
	);

	usePaymentFailHandler2(
		api,
		stripe,
		checkoutState,
		onCheckoutFail,
		emitResponse
	);

	const onSelectedPaymentMethodChange = ( { value, complete } ) => {
		setSelectedPaymentMethodType( value.type );
		setIsPaymentElementComplete( complete );
	};

	if ( checkoutState.type === 'loading' ) {
		return <div>Loading...</div>;
	} else if ( checkoutState.type === 'error' ) {
		return <div>Error: { checkoutState.error.message }</div>;
	}

	return (
		<>
			<CurrencySelectorElement />
			<PaymentElement
				options={ getStripeElementOptions() }
				onChange={ onSelectedPaymentMethodChange }
				onLoadError={ setHasLoadError }
				className="wcstripe-payment-element"
			/>
		</>
	);
};

export default CheckoutForm;
