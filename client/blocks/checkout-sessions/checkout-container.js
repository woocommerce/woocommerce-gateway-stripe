import { CheckoutProvider } from '@stripe/react-stripe-js/checkout';
import React, { useMemo, useRef } from 'react';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { initializeUPEAppearance } from 'wcstripe/stripe-utils';
import { getFontRulesFromPage } from 'wcstripe/styles/upe';

const stripePromise = loadStripe();

/**
 * Checkout Sessions Container component.
 *
 * @param {Object} props Component props.
 * @return {JSX.Element} The Checkout Sessions Container component.
 */
export const CheckoutContainer = ( props ) => {
	const {
		api,
		billing: { billingAddress } = {},
		setPaymentProcessorLoadErrorMessage,
		setShouldLoadStripeElements,
	} = props;

	// Capture billing address on mount only to avoid triggering repeated checkout
	// session creation API calls each time the customer updates billing fields.
	const initialBillingAddressRef = useRef( billingAddress );

	const checkoutSessionPromise = useMemo( async () => {
		const response = await api.checkoutSessionsCreateSession(
			initialBillingAddressRef.current
		);
		const clientSecret = response?.data?.client_secret;
		if ( ! clientSecret ) {
			setShouldLoadStripeElements( true );
			// eslint-disable-next-line no-console
			console.error(
				'Unable to initialize a checkout session. Please refresh the page and try again.'
			);
		}
		return clientSecret;
	}, [ api, setShouldLoadStripeElements ] );

	const providerOptions = useMemo(
		() => ( {
			clientSecret: checkoutSessionPromise,
			adaptivePricing: { allowed: true },
			elementsOptions: {
				appearance: initializeUPEAppearance( 'true' ),
				fonts: getFontRulesFromPage(),
			},
		} ),
		[ checkoutSessionPromise ]
	);

	return (
		<CheckoutProvider stripe={ stripePromise } options={ providerOptions }>
			<CheckoutForm
				{ ...props }
				onLoadError={ setPaymentProcessorLoadErrorMessage }
			/>
		</CheckoutProvider>
	);
};
