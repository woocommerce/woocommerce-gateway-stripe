import {
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import { useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';
import { getStripeElementOptions } from 'wcstripe/blocks/utils';

/**
 * Checkout Form component.
 *
 * @param {Object}      props                             Component props.
 * @param {JSX.Element} props.LoadingMask                 LoadingMask component to display while loading.
 * @param {Function}    props.onLoadError                 Callback function to handle load errors.
 * @param {Function}    props.setShouldLoadStripeElements Callback function to set whether Stripe Elements should be loaded instead.
 * @param {string}      props.testingInstructions         Instructions to display in test mode.
 * @return {JSX.Element} The Checkout Form component.
 */
const CheckoutForm = ( {
	LoadingMask,
	onLoadError,
	setShouldLoadStripeElements,
	testingInstructions,
} ) => {
	const checkoutState = useCheckout();
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( null );
	const setHasLoadError = ( event ) => {
		onLoadError( event );
	};
	const onSelectedPaymentMethodChange = ( { value } ) => {
		handleDisplayOfPaymentInstructions( value.type );
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
