/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { UPEPaymentForm } from './fields';
import { SavedTokenHandler } from './saved-token-handler';
/* eslint-disable @woocommerce/dependency-group */
import { getStripeServerData } from 'wcstripe/stripe-utils';
import { PAYMENT_METHOD_NAME } from 'wcstripe/blocks/credit-card/constants';
import WCStripeAPI from 'wcstripe/api';
/* eslint-enable */

const api = new WCStripeAPI(
	{
		key: getStripeServerData()?.key,
		locale: getStripeServerData()?.locale,
		isUPEEnabled: getStripeServerData()?.isUPEEnabled,
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
		getStripeServerData()?.title ??
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
	canMakePayment: () => api.getStripe(),
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

export default upePaymentMethod;
