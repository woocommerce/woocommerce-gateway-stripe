import {
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import { useEffect, useMemo, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';
import { handleDisplayOfSavingCheckbox } from 'wcstripe/optimized-checkout/handle-display-of-saving-checkbox';
import {
	getBlocksConfiguration,
	getStripeElementOptions,
} from 'wcstripe/blocks/utils';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';
import {
	useCheckoutSuccessHandler,
	usePaymentFailHandler,
	usePaymentSetupHandler,
	useCheckoutSessionTotalsSync,
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
 * @param {Object}                 props.billing                     Billing information for the checkout session.
 * @param {boolean}                props.isPayerPhoneRequired        Whether the payer phone information is required.
 * @param {Object}                 props.shippingData                Shipping information for the checkout session.
 * @param {Object}                 props.cartData                    Cart data containing Store API extensions.
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
	billing,
	isPayerPhoneRequired,
	shippingData,
	cartData,
	LoadingMask,
	onLoadError,
	setShouldLoadStripeElements,
	testingInstructions,
} ) => {
	const checkoutState = useCheckout();
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( null );
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] =
		useState( false );
	const [ selectedPaymentType, setSelectedPaymentType ] = useState( '' );
	const hasLoadErrorRef = useRef( false );
	// Live value for onPaymentSetup's once-registered callback, which would
	// otherwise close over a stale isPaymentElementComplete.
	const isCompleteRef = useRef( false );
	// Set when a totals resync leaves the session stale; blocks payment setup so
	// the buyer isn't charged an out-of-date total.
	const syncFailedRef = useRef( false );
	const checkoutSessionData =
		cartData?.extensions?.[ 'wc-stripe/checkout-session' ] ?? {};
	const setHasLoadError = ( event ) => {
		hasLoadErrorRef.current = true;
		onLoadError( event );
	};

	usePaymentSetupHandler(
		onPaymentSetup,
		checkoutSessionId,
		errorMessage,
		hasLoadErrorRef,
		isPaymentElementComplete,
		selectedPaymentType,
		isCompleteRef,
		syncFailedRef
	);
	useCheckoutSuccessHandler(
		checkoutState,
		onCheckoutSuccess,
		billing,
		!! checkoutSessionData?.save_payment_method_enabled,
		isPayerPhoneRequired,
		shippingData
	);
	usePaymentFailHandler( onCheckoutFail, emitResponse );
	useCheckoutSessionTotalsSync(
		checkoutSessionId,
		checkoutState,
		syncFailedRef,
		checkoutSessionData
	);

	const paymentMethodsConfig = getBlocksConfiguration()?.paymentMethodsConfig;

	const onSelectedPaymentMethodChange = ( { value, complete } ) => {
		handleDisplayOfPaymentInstructions( value.type, 'blocks' );
		// Hide and clear the store-level save checkbox for non-reusable
		// sub-methods. The Adaptive Pricing form renders its own Payment
		// Element instead of PaymentProcessor, so it needs this independently.
		handleDisplayOfSavingCheckbox( value.type, paymentMethodsConfig );
		setIsPaymentElementComplete( complete );
		isCompleteRef.current = complete;
		setSelectedPaymentType( value?.type ?? '' );
	};

	// The Payment Element may not emit a change event for the initially
	// selected method, so evaluate the save checkbox on mount as well (e.g.
	// card with Link enabled must start hidden).
	useEffect( () => {
		handleDisplayOfSavingCheckbox(
			selectedPaymentType || PAYMENT_METHOD_CARD,
			paymentMethodsConfig
		);
	}, [ selectedPaymentType, paymentMethodsConfig ] );

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
			{ /* Wrapped only to give e2e tests a DOM hook — classic exposes an
			     equivalent element, but via a class rather than a test id. */ }
			<div data-testid="wc-stripe-currency-selector">
				<CurrencySelectorElement />
			</div>
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
