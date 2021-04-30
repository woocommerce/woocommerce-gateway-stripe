/**
 * External dependencies
 */
import {
	Elements,
	PaymentRequestButtonElement,
	useStripe,
} from '@stripe/react-stripe-js';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getStripeServerData } from '../stripe-utils';
import { ThreeDSecurePaymentHandler } from '../three-d-secure';
import { GooglePayButton, shouldUseGooglePayBrand } from './branded-buttons';
import { CustomButton } from './custom-button';
import {
	getCartDetails,
	createPaymentRequest,
	updateShippingOptions,
	updateShippingDetails,
	processSourceEvent,
} from '../../api';

/**
 * @typedef {import('../stripe-utils/type-defs').Stripe} Stripe
 * @typedef {import('../stripe-utils/type-defs').StripePaymentRequest} StripePaymentRequest
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').RegisteredPaymentMethodProps} RegisteredPaymentMethodProps
 */

/**
 * @typedef {Object} WithStripe
 *
 * @property {Stripe} [stripe] Stripe api (might not be present)
 */

/**
 * @typedef {RegisteredPaymentMethodProps & WithStripe} StripeRegisteredPaymentMethodProps
 */

const usePaymentRequest = ( stripe ) => {
	const [ paymentRequest, setPaymentRequest ] = useState( null );
	const [ paymentRequestType, setPaymentRequestType ] = useState( null );
	useEffect( () => {
		// Do nothing if Stripe object isn't loaded or paymentRequest already exists.
		if ( ! stripe || paymentRequest ) {
			return;
		}

		getCartDetails().then( ( cart ) => {
			const pr = createPaymentRequest( stripe, cart );

			pr.canMakePayment().then( ( result ) => {
				if ( result ) {
					setPaymentRequest( pr );
					setPaymentRequestType(
						result.applePay ? 'apple_pay' : 'payment_request_api'
					);
				}
			} );
		} );
	}, [ paymentRequest, stripe ] );
	return [ paymentRequest, paymentRequestType ];
};

const useShippingAddressUpdateHandler = (
	paymentRequest,
	paymentRequestType
) => {
	useEffect( () => {
		// Need to use `?.` here in case paymentRequest is null.
		const shippingAddressUpdateHandler = paymentRequest?.on(
			'shippingaddresschange',
			( evt ) => {
				const { shippingAddress } = evt;
				updateShippingOptions(
					shippingAddress,
					paymentRequestType
				).then( ( response ) => {
					evt.updateWith( {
						status: response.result,
						shippingOptions: response.shipping_options,
						total: response.total,
						displayItems: response.displayItems,
					} );
				} );
			}
		);

		return () => {
			// Need to use `?.` here in case shippingAddressHandler is null.
			shippingAddressUpdateHandler?.removeAllListeners();
		};
	}, [ paymentRequest, paymentRequestType ] );
};

const usePaymentMethodUpdateHandler = (
	paymentRequest,
	paymentRequestType,
	{ onSubmit, setExpressPaymentError }
) => {
	useEffect( () => {
		const paymentMethodUpdateHandler = paymentRequest?.on(
			'source',
			( evt ) => {
				const allowPrepaidCards =
					wc_stripe_payment_request_params?.stripe
						?.allow_prepaid_card === 'yes';

				// Check if we allow prepaid cards.
				if (
					! allowPrepaidCards &&
					evt?.source?.card?.funding === 'prepaid'
				) {
					// TODO: Abort payment through blocks API somehow?
					// TODO: use the message on the global settings object?
					setExpressPaymentError(
						__(
							"Sorry, we're not accepting prepaid cards at this time.",
							'woocommerce-gateway-stripe'
						)
					);
					// wc_stripe_payment_request.abortPayment( evt, wc_stripe_payment_request.getErrorMessageHTML( wc_stripe_payment_request_params.i18n.no_prepaid_card ) );
				} else {
					processSourceEvent( evt, paymentRequestType ).then(
						( response ) => {
							if ( response.result === 'success' ) {
								console.log( 'received response:', response );
								evt.complete( 'success' ); // TODO: Is this the right place for this?
								window.location = response.redirect;
							} else {
								// TODO: Abort payment through blocks API somehow?

								evt.complete( 'fail' );
								// TODO: Report error somehow?

								// wc_stripe_payment_request.abortPayment(
								// 	evt,
								// 	response.messages
								// );
							}
						}
					);
				}
			}
		);

		return () => {
			paymentMethodUpdateHandler?.removeAllListeners();
		};
	}, [
		paymentRequest,
		paymentRequestType,
		onSubmit,
		setExpressPaymentError,
	] );
};

const useShippingOptionChangeHandler = (
	paymentRequest,
	paymentRequestType
) => {
	useEffect( () => {
		const shippingOptionChangeHandler = paymentRequest?.on(
			'shippingoptionchange',
			( evt ) => {
				const { shippingOption } = evt;
				updateShippingDetails(
					shippingOption,
					paymentRequestType
				).then( ( response ) => {
					if ( response.result === 'success' ) {
						evt.updateWith( {
							status: 'success',
							total: response.total,
							displayItems: response.displayItems,
						} );
					}

					if ( response.result === 'fail' ) {
						evt.updateWith( { status: 'fail' } );
					}
				} );
			}
		);

		return () => {
			shippingOptionChangeHandler?.removeAllListeners();
		};
	}, [ paymentRequest, paymentRequestType ] );
};

/**
 * PaymentRequestExpressComponent
 *
 * @param {StripeRegisteredPaymentMethodProps} props Incoming props
 */
const PaymentRequestExpressComponent = ( {
	onSubmit,
	setExpressPaymentError,
} ) => {
	const stripe = useStripe();

	const [ pr, prt ] = usePaymentRequest( stripe );
	useShippingAddressUpdateHandler( pr, prt );
	useShippingOptionChangeHandler( pr, prt );
	usePaymentMethodUpdateHandler( pr, prt, {
		onSubmit,
		setExpressPaymentError,
	} );

	// locale is not a valid value for the paymentRequestButton style.
	// Make sure `theme` defaults to 'dark' if it's not found in the server provided configuration.
	const {
		type = 'default',
		theme = 'dark',
		height = '48',
	} = getStripeServerData().button;

	const paymentRequestButtonStyle = {
		paymentRequestButton: {
			type,
			theme,
			height: `${ height }px`,
		},
	};

	// Use pre-blocks settings until we merge the two distinct settings objects.
	/* global wc_stripe_payment_request_params */
	const isBranded = wc_stripe_payment_request_params.button.is_branded;
	const brandedType = wc_stripe_payment_request_params.button.branded_type;
	const isCustom = wc_stripe_payment_request_params.button.is_custom;

	if ( /* ! canMakePayment || */ ! pr ) {
		return null;
	}

	if ( isCustom ) {
		return (
			<CustomButton
				onButtonClicked={ () => {
					// onButtonClick();
					// Since we're using a custom button we must manually call
					// `paymentRequest.show()`.
					pr.show();
				} }
			/>
		);
	}

	if ( isBranded && shouldUseGooglePayBrand() ) {
		return (
			<GooglePayButton
				onButtonClicked={ () => {
					// onButtonClick();
					// Since we're using a custom button we must manually call
					// `paymentRequest.show()`.
					pr.show();
				} }
			/>
		);
	}

	if ( isBranded ) {
		// Not implemented branded buttons default to Stripe's button.
		// Apple Pay buttons can also fall back to Stripe's button, as it's already branded.
		// Set button type to default or buy, depending on branded type, to avoid issues with Stripe.
		paymentRequestButtonStyle.paymentRequestButton.type =
			brandedType === 'long' ? 'buy' : 'default';
	}

	return (
		<PaymentRequestButtonElement
			onClick={ /*onButtonClick*/ () => {} }
			options={ {
				// @ts-ignore
				style: paymentRequestButtonStyle,
				// @ts-ignore
				paymentRequest: pr,
			} }
		/>
	);
};

/**
 * PaymentRequestExpress with stripe provider
 *
 * @param {StripeRegisteredPaymentMethodProps} props
 */
export const PaymentRequestExpress = ( props ) => {
	const { stripe } = props;
	return (
		<Elements stripe={ stripe }>
			<PaymentRequestExpressComponent { ...props } />
			<ThreeDSecurePaymentHandler { ...props } />
		</Elements>
	);
};
