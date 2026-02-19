import {
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import { useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';

/**
 * Checkout Form component.
 *
 * @param {Object}      props                        Component props.
 * @param {Object}      props.components             Object containing components to be used in the checkout form.
 * @param {JSX.Element} props.components.LoadingMask LoadingMask component to display while loading.
 * @param {Function}    props.onLoadError            Callback function to handle load errors.
 * @return {JSX.Element} The Checkout Form component.
 */
const CheckoutForm = ( { components: { LoadingMask }, onLoadError } ) => {
	const checkoutState = useCheckout();
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( null );
	const hasLoadErrorRef = useRef( false );
	const setHasLoadError = ( event ) => {
		hasLoadErrorRef.current = true;
		onLoadError( event );
	};

	const onSelectedPaymentMethodChange = ( { value } ) => {
		handleDisplayOfPaymentInstructions( value.type );
	};

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
		return <div>Error: { checkoutState.error.message }</div>;
	} else if (
		checkoutState.type === 'success' &&
		checkoutSessionId !== checkoutState.checkout.id
	) {
		const { checkout } = checkoutState;
		setCheckoutSessionId( checkout.id );
	}

	return (
		<>
			<CurrencySelectorElement />
			<PaymentElement
				options={ {
					fields: {
						billingDetails: {
							name: 'never',
							email: 'never',
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
				} }
				onChange={ onSelectedPaymentMethodChange }
				onLoadError={ setHasLoadError }
				className="wcstripe-payment-element"
			/>
		</>
	);
};

export default CheckoutForm;
