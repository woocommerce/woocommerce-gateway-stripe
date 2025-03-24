import React, { useEffect, useState } from 'react';

const CheckoutSession = ( {
	eventRegistration: { onPaymentSetup },
	paymentMethodName,
	upeMethods,
	api,
	activePaymentMethod,
} ) => {
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( '' );

	useEffect( () => {
		const urlParams = new URLSearchParams( window.location.search );
		const checkoutSessionIdFromURL = urlParams.get( 'checkout_session_id' );
		if ( checkoutSessionIdFromURL ) {
			setCheckoutSessionId( checkoutSessionIdFromURL );
		}
	}, [] );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( () => {
			async function handlePaymentProcessing() {
				if ( upeMethods[ paymentMethodName ] !== activePaymentMethod ) {
					return;
				}

				// Attach payment method to customer.
				const response = await api.attachPaymentMethodToCustomer(
					checkoutSessionId
				);

				if ( ! response.success ) {
					return {
						type: 'error',
						message: response.data.message,
					};
				}

				const setup = {
					type: 'success',
					meta: {
						paymentMethodData: {
							'wc-stripe-new-payment-method': false,
							'wc-stripe-payment-token': `${ response.data.bacs_token_id }`,
							isSavedToken: true,
							payment_method: upeMethods[ paymentMethodName ],
							token: `${ response.data.bacs_token_id }`,
							'wc-stripe_bacs_debit-payment-token': `${ response.data.bacs_token_id }`,
						},
					},
				};
				return setup;
			}

			return handlePaymentProcessing();
		} );
		return unsubscribe;
	}, [
		activePaymentMethod,
		api,
		checkoutSessionId,
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

	return (
		<button onClick={ createCheckoutSession }>
			Create a Checkout Session
		</button>
	);
};

export const getCheckoutSessionElementForBacs = (
	paymentMethod,
	upeMethods,
	api
) => {
	// Select Bacs if the `checkout_session_id` query string is defined.
	const targetNode = document.querySelector( '.wc-block-checkout' );
	if ( targetNode ) {
		const observer = new MutationObserver( () => {
			const bacsRadioButtonSelector =
				'#radio-control-wc-payment-method-options-stripe_bacs_debit';
			const radioButton = document.querySelector(
				bacsRadioButtonSelector
			);
			if ( radioButton ) {
				radioButton.click();
				observer.disconnect();
			}
		} );
		observer.observe( targetNode, { childList: true, subtree: true } );
	}

	return (
		<CheckoutSession
			paymentMethodName={ paymentMethod }
			api={ api }
			upeMethods={ upeMethods }
		/>
	);
};
