import { extensionCartUpdate } from '@woocommerce/blocks-checkout';
import { CheckoutElementsProvider } from '@stripe/react-stripe-js/checkout';
import React, { useEffect, useMemo, useState } from 'react';
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
	const { setPaymentProcessorLoadErrorMessage, setShouldLoadStripeElements } =
		props;

	// Create a promise wrapper for the client secret during render,
	// but use an effect to ensure that we correctly make API calls
	// and clean up state for the component.
	const [ checkoutSessionDeferred ] = useState( () => {
		let resolve;
		const promise = new Promise( ( res ) => {
			resolve = res;
		} );
		return { promise, resolve };
	} );

	useEffect( () => {
		let cancelled = false;

		const synchronize = async () => {
			let clientSecret = null;
			let error = null;

			try {
				const response = await extensionCartUpdate( {
					namespace: 'wc-stripe/checkout-session',
					data: { action: 'sync' },
				} );
				const sessionData =
					response?.extensions?.[ 'wc-stripe/checkout-session' ];
				clientSecret =
					sessionData?.status === 'success'
						? sessionData?.client_secret
						: null;
			} catch ( e ) {
				error = e;
			}

			// If the component has been unmounted, cancelled will be true,
			// and we should stop processing the response.
			if ( cancelled ) {
				return;
			}

			if ( ! clientSecret ) {
				setShouldLoadStripeElements( true );
				// eslint-disable-next-line no-console
				console.error(
					'Unable to initialize a checkout session. Please refresh the page and try again.',
					...( error ? [ error ] : [] )
				);
			}
			checkoutSessionDeferred.resolve( clientSecret );
		};

		synchronize();

		return () => {
			cancelled = true;
		};
	}, [ checkoutSessionDeferred, setShouldLoadStripeElements ] );

	// Render an editor-safe appearance in the block editor preview, where the
	// checkout DOM does not reflect the live storefront. See STRIPE-1061.
	const isEditor = getBlocksConfiguration()?.isAdmin ?? false;

	const providerOptions = useMemo(
		() => ( {
			clientSecret: checkoutSessionDeferred.promise,
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
		[ checkoutSessionDeferred, isEditor ]
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
