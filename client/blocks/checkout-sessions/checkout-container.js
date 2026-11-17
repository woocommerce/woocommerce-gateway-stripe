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
	const {
		cartData,
		setPaymentProcessorLoadErrorMessage,
		setShouldLoadStripeElements,
	} = props;

	// The session the provider is currently bound to; null until the initial sync settles.
	// Held in state so the replacement check re-runs once it settles, in case a newer
	// session reached the cart while the initial sync was still in flight.
	const [ mountedSessionId, setMountedSessionId ] = useState( null );
	const [ replacementSession, setReplacementSession ] = useState( null );

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
			let sessionId = null;
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
				sessionId = clientSecret
					? sessionData?.session_id ?? null
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
			setMountedSessionId( sessionId );
		};

		synchronize();

		return () => {
			cancelled = true;
		};
	}, [ checkoutSessionDeferred, setShouldLoadStripeElements ] );

	const embeddedSession =
		cartData?.extensions?.[ 'wc-stripe/checkout-session' ];
	const embeddedSessionId =
		embeddedSession?.status === 'success'
			? embeddedSession?.session_id
			: null;
	const embeddedClientSecret =
		embeddedSession?.status === 'success'
			? embeddedSession?.client_secret
			: null;

	// The server replaces the session when it can't be updated in place (e.g. a billing
	// country change altered the excluded payment methods, which Stripe only accepts at
	// creation). The provider can't switch client secrets, so it is remounted on the new one.
	useEffect( () => {
		if (
			! mountedSessionId ||
			! embeddedSessionId ||
			! embeddedClientSecret ||
			embeddedSessionId === mountedSessionId
		) {
			return;
		}

		setMountedSessionId( embeddedSessionId );
		setReplacementSession( {
			id: embeddedSessionId,
			clientSecret: embeddedClientSecret,
		} );
	}, [ mountedSessionId, embeddedSessionId, embeddedClientSecret ] );

	// Render an editor-safe appearance in the block editor preview, where the
	// checkout DOM does not reflect the live storefront. See STRIPE-1061.
	const isEditor = getBlocksConfiguration()?.isAdmin ?? false;

	const providerOptions = useMemo(
		() => ( {
			clientSecret:
				replacementSession?.clientSecret ??
				checkoutSessionDeferred.promise,
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
		[ checkoutSessionDeferred, isEditor, replacementSession ]
	);

	return (
		<CheckoutElementsProvider
			key={ replacementSession?.id ?? 'initial' }
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
