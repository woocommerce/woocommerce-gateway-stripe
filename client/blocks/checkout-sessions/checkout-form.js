import React, { useCallback } from 'react';
import {
	EmbeddedCheckout,
	EmbeddedCheckoutProvider,
} from '@stripe/react-stripe-js';
import { loadStripe } from 'wcstripe/blocks/load-stripe';

const stripePromise = loadStripe();

export const CheckoutForm = ( props ) => {
	const { api } = props;
	const fetchClientSecret = useCallback( () => {
		return api
			.checkoutSessionsCreateSession()
			.then( ( r ) => r.client_secret );
	}, [ api ] );

	const options = { fetchClientSecret };

	return (
		<EmbeddedCheckoutProvider stripe={ stripePromise } options={ options }>
			<EmbeddedCheckout />
		</EmbeddedCheckoutProvider>
	);
};
