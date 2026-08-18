import {
	CurrencySelectorElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import { useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	useCheckoutSuccessHandler,
	usePaymentFailHandler,
	useSavedTokenPaymentSetupHandler,
	useCheckoutSessionTotalsSync,
} from 'wcstripe/blocks/checkout-sessions/hooks';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EventRegistrationProps} EventRegistrationProps
 */

/**
 * Checkout Sessions form for paying with a saved token under Adaptive Pricing.
 *
 * Renders only the Currency Selector Element: the saved token stands in for the
 * Payment Element, and confirm() receives its PaymentMethod id directly.
 *
 * @param {Object}                 props                             Component props.
 * @param {EmitResponseProps}      props.emitResponse                Function to emit response back to the parent component.
 * @param {EventRegistrationProps} props.eventRegistration           Object containing event registration functions.
 * @param {Object}                 props.billing                     Billing information for the checkout session.
 * @param {boolean}                props.isLoggedIn                  Whether the customer is logged-in.
 * @param {boolean}                props.isPayerPhoneRequired        Whether the payer phone information is required.
 * @param {Object}                 props.shippingData                Shipping information for the checkout session.
 * @param {Object}                 props.cartData                    Cart data containing Store API extensions.
 * @param {?JSX.Element}           props.LoadingMask                 LoadingMask component to display while loading.
 * @param {Function}               props.setShouldLoadStripeElements Callback to fall back to the store-currency saved-token flow.
 * @param {string}                 props.token                       The WooCommerce payment token id the customer selected.
 * @param {string}                 props.savedPaymentMethodId        The Stripe PaymentMethod id behind the selected token.
 * @return {JSX.Element} The saved-token checkout form.
 */
const SavedTokenCheckoutForm = ( {
	emitResponse,
	eventRegistration: { onPaymentSetup, onCheckoutSuccess, onCheckoutFail },
	billing,
	isLoggedIn,
	isPayerPhoneRequired,
	shippingData,
	cartData,
	LoadingMask,
	setShouldLoadStripeElements,
	token,
	savedPaymentMethodId,
} ) => {
	const checkoutState = useCheckout();
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( null );
	// Set when a totals resync leaves the session stale; blocks payment setup so
	// the buyer isn't charged an out-of-date total.
	const syncFailedRef = useRef( false );
	const checkoutSessionData =
		cartData?.extensions?.[ 'wc-stripe/checkout-session' ] ?? {};

	useSavedTokenPaymentSetupHandler(
		onPaymentSetup,
		checkoutSessionId,
		syncFailedRef,
		token
	);
	useCheckoutSuccessHandler(
		checkoutState,
		onCheckoutSuccess,
		billing,
		isLoggedIn,
		isPayerPhoneRequired,
		shippingData,
		savedPaymentMethodId
	);
	usePaymentFailHandler( onCheckoutFail, emitResponse );
	useCheckoutSessionTotalsSync(
		checkoutSessionId,
		checkoutState,
		syncFailedRef,
		checkoutSessionData
	);

	if ( checkoutState.type === 'loading' ) {
		return LoadingMask ? (
			<LoadingMask
				isLoading={ true }
				showSpinner={ true }
				screenReaderLabel={ __(
					'Loading payment method…',
					'woocommerce-gateway-stripe'
				) }
			/>
		) : null;
	} else if ( checkoutState.type === 'error' ) {
		// Fall back to the store-currency saved-token flow rather than
		// blocking checkout on a session failure.
		setShouldLoadStripeElements( true );
		return null;
	} else if (
		checkoutState.type === 'success' &&
		checkoutSessionId !== checkoutState.checkout?.id
	) {
		setCheckoutSessionId( checkoutState.checkout.id );
	}

	return (
		<div data-testid="wc-stripe-currency-selector">
			<CurrencySelectorElement />
		</div>
	);
};

export default SavedTokenCheckoutForm;
