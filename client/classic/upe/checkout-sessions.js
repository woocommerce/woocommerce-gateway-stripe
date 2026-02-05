import React from 'react';
import { createRoot } from 'react-dom/client';
import {
	CheckoutProvider,
	CurrencySelectorElement,
	PaymentElement,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import { loadStripe } from '@stripe/stripe-js';
import { getStripeServerData } from '../../stripe-utils';
import { useState, useEffect } from '@wordpress/element';

const stripePromise = ( async () => {
	const stripeServerData = getStripeServerData();
	if ( ! stripeServerData?.key ) {
		return null;
	}
	return loadStripe( stripeServerData.key );
} )();

// Store checkout session ID and checkout instance globally so it can be accessed from payment-processing.js
let globalCheckoutSessionId = null;
let globalCheckoutInstance = null;

const ClassicCheckoutForm = ( { api, onComplete, onError } ) => {
	const checkoutState = useCheckout();
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] =
		useState( false );
	const [ checkoutSessionId, setCheckoutSessionId ] = useState( null );
	const [ errorMessage, setErrorMessage ] = useState( null );

	useEffect( () => {
		if ( checkoutState.type === 'success' ) {
			const sessionId = checkoutState.checkout.id;
			if ( checkoutSessionId !== sessionId ) {
				setCheckoutSessionId( sessionId );
				globalCheckoutSessionId = sessionId;
				// Store the checkout object which has the confirm method
				globalCheckoutInstance = checkoutState.checkout;
			}
		}
	}, [ checkoutState, checkoutSessionId ] );

	const onSelectedPaymentMethodChange = ( { value, complete } ) => {
		setIsPaymentElementComplete( complete );
	};

	const onLoadError = ( event ) => {
		setErrorMessage( event.error?.message );
		if ( onError ) {
			onError( event );
		}
	};

	if ( checkoutState.type === 'loading' ) {
		return <div>Loading...</div>;
	}

	if ( checkoutState.type === 'error' ) {
		return <div>Error: { checkoutState.error.message }</div>;
	}

	return (
		<>
			{ errorMessage && (
				<div className="woocommerce-error" role="alert">
					{ errorMessage }
				</div>
			) }
			<CurrencySelectorElement />
			<PaymentElement
				options={ {
					fields: {
						billingDetails: {
							name: 'never',
							email: 'never',
							phone: 'auto',
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
				} }
				onChange={ onSelectedPaymentMethodChange }
				onLoadError={ onLoadError }
				className="wcstripe-payment-element"
			/>
		</>
	);
};

export async function initializeCheckoutSessions( api, containerElement ) {
	try {
		const response = await api.checkoutSessionsCreateSession();
		const clientSecret = response.data?.client_secret;

		if ( ! clientSecret ) {
			throw new Error( 'Failed to create checkout session' );
		}

		// Create React root and mount the checkout form
		const root = createRoot( containerElement );

		const providerOptions = {
			clientSecret,
			adaptivePricing: { allowed: true },
		};

		root.render(
			<CheckoutProvider
				stripe={ stripePromise }
				options={ providerOptions }
			>
				<ClassicCheckoutForm api={ api } />
			</CheckoutProvider>
		);

		return {
			clientSecret,
			root,
		};
	} catch ( error ) {
		throw error;
	}
}

/**
 * Gets the checkout session ID from the checkout state.
 * This is a helper function that can be called after the checkout is initialized.
 *
 * @return {string|null} The checkout session ID if available.
 */
export function getCheckoutSessionId() {
	return globalCheckoutSessionId;
}

/**
 * Gets the checkout instance.
 *
 * @return {Object|null} The checkout instance if available.
 */
export function getCheckoutInstance() {
	return globalCheckoutInstance;
}
