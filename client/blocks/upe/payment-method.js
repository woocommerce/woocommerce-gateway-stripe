import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { UPEPaymentForm } from './fields';
import { SavedTokenHandler } from './saved-token-handler';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { PAYMENT_METHOD_NAME } from 'wcstripe/blocks/credit-card/constants';
import WCStripeAPI from 'wcstripe/api';

const api = new WCStripeAPI(
	{
		key: getBlocksConfiguration()?.key,
		locale: getBlocksConfiguration()?.locale,
		isUPEEnabled: getBlocksConfiguration()?.isUPEEnabled,
	},
	// A promise-based interface to jQuery.post.
	( url, args ) => {
		return new Promise( ( resolve, reject ) => {
			jQuery.post( url, args ).then( resolve ).fail( reject );
		} );
	}
);

const StripeComponent = ( { RenderedComponent, ...props } ) => {
	const [ errorMessage, setErrorMessage ] = useState( '' );

	const stripe = api.getStripe();

	useEffect( () => {
		Promise.resolve( stripe ).then( ( { error } ) => {
			if ( error ) {
				setErrorMessage( error.message );
			}
		} );
	}, [ setErrorMessage, stripe ] );

	useEffect( () => {
		if ( errorMessage ) {
			throw new Error( errorMessage );
		}
	}, [ errorMessage ] );

	return <RenderedComponent stripe={ stripe } { ...props } />;
};

const StripeLabel = ( props ) => {
	const { PaymentMethodLabel } = props.components;

	const labelText =
		getBlocksConfiguration()?.title ??
		__( 'Credit / Debit Card', 'woocommerce-gateway-stripe' );

	return <PaymentMethodLabel text={ labelText } />;
};

const upePaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: <StripeLabel />,
	content: (
		<StripeComponent RenderedComponent={ UPEPaymentForm } api={ api } />
	),
	edit: <StripeComponent RenderedComponent={ UPEPaymentForm } api={ api } />,
	savedTokenComponent: (
		<StripeComponent RenderedComponent={ SavedTokenHandler } api={ api } />
	),
	canMakePayment: () => !! api.getStripe(),
	ariaLabel: __(
		'Stripe Credit Card payment method',
		'woocommerce-gateway-stripe'
	),
	supports: {
		// Use `false` as fallback values in case server provided configuration is missing.
		showSavedCards: getBlocksConfiguration()?.showSavedCards ?? false,
		showSaveOption: getBlocksConfiguration()?.showSaveOption ?? false,
		features: getBlocksConfiguration()?.supports ?? [],
	},
};

export default upePaymentMethod;
