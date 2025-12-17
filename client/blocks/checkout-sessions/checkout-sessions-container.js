import { CheckoutProvider } from '@stripe/react-stripe-js/checkout';
import React, { useMemo } from 'react';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';
import { loadStripe } from 'wcstripe/blocks/load-stripe';

const stripePromise = loadStripe();

export const CheckoutSessionsContainer = ( props ) => {
	const { api } = props;
	const promise = useMemo( () => {
		return api
			.checkoutSessionsCreateSession()
			.then( ( r ) => r.client_secret );
	}, [ api ] );

	return (
		<CheckoutProvider
			stripe={ stripePromise }
			options={ { clientSecret: promise } }
		>
			<CheckoutForm />
		</CheckoutProvider>
	);
};
