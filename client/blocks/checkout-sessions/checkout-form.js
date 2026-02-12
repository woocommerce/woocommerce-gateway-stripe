import {
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import { useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * No operation function.
 *
 * @return {null} Returns null.
 */
const noop = () => null;

const CheckoutForm = ( {
	components: { LoadingMask },
	onLoadError = noop,
} ) => {
	const checkoutState = useCheckout();
	const [ , setSelectedPaymentMethodType ] = useState( null );
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( null );
	const [ , setIsPaymentElementComplete ] = useState( false );
	const hasLoadErrorRef = useRef( false );
	const setHasLoadError = ( event ) => {
		hasLoadErrorRef.current = true;
		onLoadError( event );
	};

	const onSelectedPaymentMethodChange = ( { value, complete } ) => {
		setSelectedPaymentMethodType( value.type );
		setIsPaymentElementComplete( complete );
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
