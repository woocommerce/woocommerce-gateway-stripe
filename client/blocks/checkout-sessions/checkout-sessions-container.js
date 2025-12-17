import { CheckoutProvider } from '@stripe/react-stripe-js/checkout';
import React, { useMemo } from 'react';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';
import WCStripeAPI from 'wcstripe/api';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { loadStripe } from 'wcstripe/blocks/load-stripe';

const stripePromise = loadStripe();

const api = new WCStripeAPI(
	getBlocksConfiguration(),
	// A promise-based interface to jQuery.post.
	( url, args ) => {
		return new Promise( ( resolve, reject ) => {
			jQuery.post( url, args ).then( resolve ).fail( reject );
		} );
	}
);

export const CheckoutSessionsContainer = () => {
	const promise = useMemo( () => {
		return api.checkoutSessionsCreateSession();
	}, [] );

	const options = {
		clientSecret: promise,
		elementsOptions: {
			appearance: {},
		},
	};

	return (
		<CheckoutProvider stripe={ stripePromise } options={ options }>
			<CheckoutForm />
		</CheckoutProvider>
	);
};
