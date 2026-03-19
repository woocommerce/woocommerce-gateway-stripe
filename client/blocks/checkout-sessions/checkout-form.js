import {
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import { useMemo, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';
import { getStripeElementOptions } from 'wcstripe/blocks/utils';
import {
	useCheckoutSuccessHandler,
	usePaymentFailHandler,
	usePaymentSetupHandler,
} from 'wcstripe/blocks/checkout-sessions/hooks';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EventRegistrationProps} EventRegistrationProps
 */

/**
 * Checkout Form component.
 *
 * @param {Object}                 props                             Component props.
 * @param {EmitResponseProps}      props.emitResponse                Function to emit response back to the parent component.
 * @param {string}                 props.errorMessage                Error message to display if loading the checkout session fails.
 * @param {EventRegistrationProps} props.eventRegistration           Object containing event registration functions for payment setup, checkout success, and checkout failure.
 * @param {JSX.Element}            props.LoadingMask                 LoadingMask component to display while loading.
 * @param {Function}               props.onLoadError                 Callback function to handle load errors.
 * @param {Function}               props.setShouldLoadStripeElements Callback function to set whether Stripe Elements should be loaded instead.
 * @param {string}                 props.testingInstructions         Instructions to display in test mode.
 * @return {JSX.Element} The Checkout Form component.
 */
const CheckoutForm = ( {
	emitResponse,
	errorMessage,
	eventRegistration: { onPaymentSetup, onCheckoutSuccess, onCheckoutFail },
	LoadingMask,
	onLoadError,
	setShouldLoadStripeElements,
	testingInstructions,
} ) => {
	const checkoutState = useCheckout();
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( null );
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] =
		useState( false );
	const hasLoadErrorRef = useRef( false );
	const setHasLoadError = ( event ) => {
		hasLoadErrorRef.current = true;
		onLoadError( event );
	};

	usePaymentSetupHandler(
		onPaymentSetup,
		checkoutSessionId,
		errorMessage,
		hasLoadErrorRef,
		isPaymentElementComplete
	);
	useCheckoutSuccessHandler( checkoutState, onCheckoutSuccess );
	usePaymentFailHandler( onCheckoutFail, emitResponse );

	const onSelectedPaymentMethodChange = ( { value, complete } ) => {
		handleDisplayOfPaymentInstructions( value.type );
		setIsPaymentElementComplete( complete );
	};

	const elementOptions = useMemo( () => {
		try {
			return getStripeElementOptions( true );
		} catch {
			return {};
		}
	}, [] );

	if ( checkoutState.type === 'loading' ) {
		return (
			<LoadingMask
				isLoading={ true }
				showSpinner={ true }
				screenReaderLabel={ __(
					'Loading payment method…',
					'woocommerce-gateway-stripe'
				) }
			/>
		);
	} else if ( checkoutState.type === 'error' ) {
		setShouldLoadStripeElements( true ); // If there was an error loading the checkout session, we fallback to loading Stripe Elements.
		return <div>Error: { checkoutState.error?.message }</div>;
	} else if (
		checkoutState.type === 'success' &&
		checkoutSessionId !== checkoutState.checkout?.id
	) {
		const { checkout } = checkoutState;
		setCheckoutSessionId( checkout.id );
	}

	return (
		<>
			{ testingInstructions && (
				<p
					className="content"
					dangerouslySetInnerHTML={ {
						__html: testingInstructions,
					} }
				/>
			) }
			<CurrencySelectorElement />
			<PaymentElement
				options={ elementOptions }
				onChange={ onSelectedPaymentMethodChange }
				onLoadError={ setHasLoadError }
				className="wcstripe-payment-element"
			/>
		</>
	);
};

export default CheckoutForm;
