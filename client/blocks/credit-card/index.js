import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { ThreeDSecurePaymentHandler } from '../three-d-secure';
import { StripeCreditCard, getStripeCreditCardIcons } from './payment-method';
import { PAYMENT_METHOD_NAME } from './constants';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { loadStripe } from 'wcstripe/blocks/load-stripe';

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
		getBlocksConfiguration()?.title ??
		__( 'Credit / Debit Card', 'woocommerce-gateway-stripe' );

	return <PaymentMethodLabel text={ labelText } />;
};

const cardIcons = getStripeCreditCardIcons();
const supports = {
	// Use `false` as fallback values in case server provided configuration is missing.
	showSavedCards: getBlocksConfiguration()?.showSavedCards ?? false,
	showSaveOption: getBlocksConfiguration()?.showSaveOption ?? false,
	features: getBlocksConfiguration()?.supports ?? [],
};
if ( getBlocksConfiguration().isAdmin ?? false ) {
	supports.style = getBlocksConfiguration()?.style ?? [];
}
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
	supports,
};

export default stripeCcPaymentMethod;
