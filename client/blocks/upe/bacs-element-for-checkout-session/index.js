import React, { useEffect, useState } from 'react';

const CheckoutSession = ( {
	eventRegistration: { onPaymentSetup },
	paymentMethodName,
	upeMethods,
	api,
	...props
} ) => {
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( '' );
	const [ bacsTokenId, setBacsTokenId ] = useState( '' );
	const { activePaymentMethod } = props;
	useEffect( () => {
		const urlParams = new URLSearchParams( window.location.search );
		const checkoutSessionIdFromURL = urlParams.get( 'checkout_session_id' );
		if ( checkoutSessionIdFromURL ) {
			setCheckoutSessionId( checkoutSessionIdFromURL );
		}
	}, [] );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( () => {
			if ( upeMethods[ paymentMethodName ] !== activePaymentMethod ) {
				return;
			}

			const setup = {
				type: 'success',
				meta: {
					paymentMethodData: {
						'wc-stripe-new-payment-method': false,
						'wc-stripe-payment-token': `${ bacsTokenId }`,
						isSavedToken: true,
						payment_method: upeMethods[ paymentMethodName ],
						token: `${ bacsTokenId }`,
						'wc-stripe_bacs_debit-payment-token': `${ bacsTokenId }`,
					},
				},
			};
			return setup;
		} );
		return unsubscribe;
	}, [
		activePaymentMethod,
		bacsTokenId,
		onPaymentSetup,
		paymentMethodName,
		upeMethods,
	] );

	const createCheckoutSession = async ( e ) => {
		e.preventDefault();
		try {
			const response = await api.createCheckoutSession();
			window.location.href = response.checkout_session_url;
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( 'Error creating checkout session:', err );
		}
	};

	const attachPaymentMethod = async ( e ) => {
		e.preventDefault();
		try {
			const response = await api.attachPaymentMethodToCustomer(
				checkoutSessionId
			);
			// Store the payment method to be sent at checkout..
			setBacsTokenId( response.bacs_token_id );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error(
				'Error attaching the payment method to customer:',
				err
			);
		}
	};

	return (
		<>
			<button onClick={ createCheckoutSession }>
				Create a Checkout Session
			</button>{ ' ' }
			<button onClick={ attachPaymentMethod }>
				Attach payment method to customer
			</button>
		</>
	);
};

export const getCheckoutSessionElementForBacs = (
	paymentMethod,
	upeMethods,
	api
) => {
	return (
		<CheckoutSession
			paymentMethodName={ paymentMethod }
			api={ api }
			upeMethods={ upeMethods }
		/>
	);
};
