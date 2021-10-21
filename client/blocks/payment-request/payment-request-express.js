import {
	Elements,
	PaymentRequestButtonElement,
	useStripe,
} from '@stripe/react-stripe-js';
import { GooglePayButton, shouldUseGooglePayBrand } from './branded-buttons';
import { CustomButton } from './custom-button';
import {
	usePaymentRequest,
	useProcessPaymentHandler,
	useShippingAddressUpdateHandler,
	useShippingOptionChangeHandler,
	useOnClickHandler,
	useCancelHandler,
} from './hooks';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

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
	billing,
	shippingData,
	onClick,
	onClose,
	setExpressPaymentError,
} ) => {
	const stripe = useStripe();
	const { needsShipping } = shippingData;

	/* Set up payment request and its event handlers. */
	const [
		paymentRequest,
		paymentRequestType,
		isUpdatingPaymentRequest,
	] = usePaymentRequest( stripe, needsShipping, billing );
	useShippingAddressUpdateHandler( paymentRequest, paymentRequestType );
	useShippingOptionChangeHandler( paymentRequest, paymentRequestType );
	useProcessPaymentHandler(
		stripe,
		paymentRequest,
		paymentRequestType,
		setExpressPaymentError
	);
	const onPaymentRequestButtonClick = useOnClickHandler(
		paymentRequestType,
		isUpdatingPaymentRequest,
		setExpressPaymentError,
		onClick
	);
	useCancelHandler( paymentRequest, onClose );

	// locale is not a valid value for the paymentRequestButton style.
	// Make sure `theme` defaults to 'dark' if it's not found in the server provided configuration.
	const {
		type = 'default',
		theme = 'dark',
		height = '48',
	} = getBlocksConfiguration()?.button;

	const paymentRequestButtonStyle = {
		paymentRequestButton: {
			type,
			theme,
			height: `${ height }px`,
		},
	};

	const isBranded = getBlocksConfiguration()?.button?.is_branded;
	const brandedType = getBlocksConfiguration()?.button?.branded_type;
	const isCustom = getBlocksConfiguration()?.button?.is_custom;

	if ( ! paymentRequest ) {
		return null;
	}

	if ( isCustom ) {
		return (
			<CustomButton
				onButtonClicked={ ( evt ) => {
					onPaymentRequestButtonClick( evt, paymentRequest );
				} }
			/>
		);
	}

	if ( isBranded && shouldUseGooglePayBrand() ) {
		return (
			<GooglePayButton
				onButtonClicked={ ( evt ) => {
					onPaymentRequestButtonClick( evt, paymentRequest );
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
		// We wrap using a div because we can't pass a 'style' prop to PaymentRequestButtonElement.
		// The pointerEvents hack here is an attempt to improve the UX while we're sending an API
		// request for the cart. Instead of the button not being clickable and showing a mouse pointer
		// that indicates that the button is clickable (the hand), instead we just show the regular
		// mouse pointer.
		// We'd prefer to just disable the ExpressPaymentButton through the Blocks API, but that's not
		// possible at the moment.
		// - @reykjalin
		<div
			style={
				isUpdatingPaymentRequest
					? {
							pointerEvents: 'none',
					  }
					: {}
			}
		>
			<PaymentRequestButtonElement
				onClick={ onPaymentRequestButtonClick }
				options={ {
					style: paymentRequestButtonStyle,
					paymentRequest,
				} }
			/>
		</div>
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
		</Elements>
	);
};
