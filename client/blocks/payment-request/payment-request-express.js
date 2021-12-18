import { __ } from '@wordpress/i18n';
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
	components,
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

	const { LoadingMask } = components;

	if ( isCustom ) {
		return (
			<LoadingMask
				isLoading={ isUpdatingPaymentRequest }
				screenReaderLabel={ __(
					'Loading payment request…',
					'woocommerce-gateway-stripe'
				) }
			>
				<CustomButton
					onButtonClicked={ ( evt ) => {
						onPaymentRequestButtonClick( evt, paymentRequest );
					} }
				/>
			</LoadingMask>
		);
	}

	if ( isBranded && shouldUseGooglePayBrand() ) {
		return (
			<LoadingMask
				isLoading={ isUpdatingPaymentRequest }
				screenReaderLabel={ __(
					'Loading payment request…',
					'woocommerce-gateway-stripe'
				) }
			>
				<GooglePayButton
					onButtonClicked={ ( evt ) => {
						onPaymentRequestButtonClick( evt, paymentRequest );
					} }
				/>
			</LoadingMask>
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
		<LoadingMask
			isLoading={ isUpdatingPaymentRequest }
			screenReaderLabel={ __(
				'Loading payment request…',
				'woocommerce-gateway-stripe'
			) }
		>
			<PaymentRequestButtonElement
				onClick={ onPaymentRequestButtonClick }
				options={ {
					style: paymentRequestButtonStyle,
					paymentRequest,
				} }
			/>
		</LoadingMask>
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
