/**
 * External dependencies
 */
import { Elements, PaymentRequestButtonElement } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import { getStripeServerData } from '../stripe-utils';
import { useInitialization } from './use-initialization';
import { useCheckoutSubscriptions } from './use-checkout-subscriptions';
import { ThreeDSecurePaymentHandler } from '../three-d-secure';
import { GooglePayButton, shouldUseGooglePayBrand } from './branded-buttons';
import { CustomButton } from './custom-button';

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

/**
 * PaymentRequestExpressComponent
 *
 * @param {StripeRegisteredPaymentMethodProps} props Incoming props
 */
const PaymentRequestExpressComponent = ( {
	shippingData,
	billing,
	eventRegistration,
	onSubmit,
	setExpressPaymentError,
	emitResponse,
	onClick,
	onClose,
} ) => {
	const {
		paymentRequest,
		paymentRequestEventHandlers,
		clearPaymentRequestEventHandler,
		isProcessing,
		canMakePayment,
		onButtonClick,
		abortPayment,
		completePayment,
		paymentRequestType,
	} = useInitialization( {
		billing,
		shippingData,
		setExpressPaymentError,
		onClick,
		onClose,
		onSubmit,
	} );
	useCheckoutSubscriptions( {
		canMakePayment,
		isProcessing,
		eventRegistration,
		paymentRequestEventHandlers,
		clearPaymentRequestEventHandler,
		billing,
		shippingData,
		emitResponse,
		paymentRequestType,
		completePayment,
		abortPayment,
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

	if ( ! canMakePayment || ! paymentRequest ) {
		return null;
	}

	if ( isCustom ) {
		return (
			<CustomButton
				onButtonClicked={ () => {
					onButtonClick();
					// Since we're using a custom button we must manually call
					// `paymentRequest.show()`.
					paymentRequest.show();
				} }
			/>
		);
	}

	if ( isBranded && shouldUseGooglePayBrand() ) {
		return (
			<GooglePayButton
				onButtonClicked={ () => {
					onButtonClick();
					// Since we're using a custom button we must manually call
					// `paymentRequest.show()`.
					paymentRequest.show();
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
			onClick={ onButtonClick }
			options={ {
				// @ts-ignore
				style: paymentRequestButtonStyle,
				// @ts-ignore
				paymentRequest,
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
	// Make sure `locale` defaults to 'en_US' if it's not found in the server provided
	// configuration.
	const { locale = 'en_US' } = getStripeServerData().button;
	const { stripe } = props;
	return (
		<Elements stripe={ stripe } locale={ locale }>
			<PaymentRequestExpressComponent { ...props } />
			<ThreeDSecurePaymentHandler { ...props } />
		</Elements>
	);
};
