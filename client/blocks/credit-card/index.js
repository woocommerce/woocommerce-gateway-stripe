import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { loadStripe } from '../../stripe-utils';
import { ThreeDSecurePaymentHandler } from '../three-d-secure';
import { StripeCreditCard, getStripeCreditCardIcons } from './payment-method';
import { PAYMENT_METHOD_NAME } from './constants';
import { getStripeServerData } from 'wcstripe/blocks/utils';

const stripePromise = loadStripe();

const StripeComponent = ( { RenderedComponent, ...props } ) => {
	const [ errorMessage, setErrorMessage ] = useState( '' );

	useEffect( () => {
		Promise.resolve( stripePromise ).then( ( { error } ) => {
			if ( error ) {
				setErrorMessage( error.message );
			}
		} );
	}, [ setErrorMessage ] );

	useEffect( () => {
		if ( errorMessage ) {
			throw new Error( errorMessage );
		}
	}, [ errorMessage ] );

	return <RenderedComponent stripe={ stripePromise } { ...props } />;
};

const StripeLabel = ( props ) => {
	const { PaymentMethodLabel } = props.components;

	const labelText =
		getStripeServerData()?.title ??
		__( 'Credit / Debit Card', 'woocommerce-gateway-stripe' );

	return <PaymentMethodLabel text={ labelText } />;
};

const cardIcons = getStripeCreditCardIcons();
const stripeCcPaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: <StripeLabel />,
	content: <StripeComponent RenderedComponent={ StripeCreditCard } />,
	edit: <StripeComponent RenderedComponent={ StripeCreditCard } />,
	savedTokenComponent: (
		<StripeComponent RenderedComponent={ ThreeDSecurePaymentHandler } />
	),
	icons: cardIcons,
	canMakePayment: () => stripePromise,
	ariaLabel: __(
		'Stripe Credit Card payment method',
		'woocommerce-gateway-stripe'
	),
	supports: {
		// Use `false` as fallback values in case server provided configuration is missing.
		showSavedCards: getStripeServerData()?.showSavedCards ?? false,
		showSaveOption: getStripeServerData()?.showSaveOption ?? false,
		features: getStripeServerData()?.supports ?? [],
	},
};

export default stripeCcPaymentMethod;
