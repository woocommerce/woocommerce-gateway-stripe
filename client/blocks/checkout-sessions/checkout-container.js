import { CheckoutElementsProvider } from '@stripe/react-stripe-js/checkout';
import React, { useMemo } from 'react';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { initializeUPEAppearance } from 'wcstripe/stripe-utils/upe-appearance';
import { getFontRulesFromPage } from 'wcstripe/styles/upe';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

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
		setPaymentProcessorLoadErrorMessage,
		setShouldLoadStripeElements,
	} = props;

	const checkoutSessionPromise = useMemo( async () => {
		const response = await api.checkoutSessionsCreateSession();
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

	// Render an editor-safe appearance in the block editor preview, where the
	// checkout DOM does not reflect the live storefront. See STRIPE-1061.
	const isEditor = getBlocksConfiguration()?.isAdmin ?? false;

	const providerOptions = useMemo(
		() => ( {
			clientSecret: checkoutSessionPromise,
			adaptivePricing: { allowed: true },
			elementsOptions: {
				appearance: initializeUPEAppearance( 'true', false, isEditor ),
				fonts: getFontRulesFromPage(),
				savedPaymentMethod: {
					// Stripe must not list saved customer payment methods inside the Payment Element; the gateway surfaces the saved payment methods instead.
					enableRedisplay: 'never',
					// Stripe must not show the save payment method checkbox in the Payment Element; the gateway has its own save payment method checkbox.
					enableSave: 'never',
				},
			},
		} ),
		[ checkoutSessionPromise, isEditor ]
	);

	return (
		<CheckoutElementsProvider
			stripe={ stripePromise }
			options={ providerOptions }
		>
			<CheckoutForm
				{ ...props }
				onLoadError={ setPaymentProcessorLoadErrorMessage }
			/>
		</CheckoutElementsProvider>
	);
};
