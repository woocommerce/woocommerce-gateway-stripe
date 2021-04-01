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
	const { theme = 'dark' } = getStripeServerData().button;

	const paymentRequestButtonStyle = {
		paymentRequestButton: {
			type: 'default',
			theme,
			height: '48px',
		},
	};

	return canMakePayment && paymentRequest ? (
		<PaymentRequestButtonElement
			onClick={ onButtonClick }
			options={ {
				// @ts-ignore
				style: paymentRequestButtonStyle,
				// @ts-ignore
				paymentRequest,
			} }
		/>
	) : null;
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
