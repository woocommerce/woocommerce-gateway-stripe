/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { Elements, PaymentElement } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
/* eslint-disable @woocommerce/dependency-group */
import { getStripeServerData } from 'wcstripe/stripe-utils';
/* eslint-enable */

const UPEField = ( { api } ) => {
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( null );

	useEffect( () => {
		if ( paymentIntentId || hasRequestedIntent ) {
			return;
		}

		async function createIntent() {
			try {
				const response = await api.createIntent(
					getStripeServerData()?.orderId
				);
				setPaymentIntentId( response.id );
				setClientSecret( response.client_secret );
			} catch ( error ) {
				setErrorMessage(
					error?.message ??
						'There was an error loading the payment gateway'
				);
			}
		}

		setHasRequestedIntent( true );
		createIntent();
	}, [ paymentIntentId, hasRequestedIntent, api, errorMessage ] );

	const elementOptions = {
		clientSecret,
		business: { name: 'Automattic' },
		fields: {
			billingDetails: {
				name: 'never',
				email: 'never',
				phone: 'never',
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
	};

	if ( ! clientSecret ) {
		if ( errorMessage ) {
			return (
				<div className="woocommerce-error">
					<div className="components-notice__content">
						{ errorMessage }
					</div>
				</div>
			);
		}

		return null;
	}

	return (
		<PaymentElement
			options={ elementOptions }
			onChange={ ( event ) => console.log( event ) }
		/>
	);
};

export const UPEPaymentForm = ( { api } ) => {
	return (
		<Elements stripe={ api.getStripe() }>
			<UPEField api={ api } />
		</Elements>
	);
};
