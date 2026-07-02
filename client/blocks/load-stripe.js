import { loadStripe } from '@stripe/stripe-js';
import { getApiKey, getBlocksConfiguration } from './utils';
import { getStripeDevWidgetOptions } from 'wcstripe/stripe-utils';
import { assertStripeJsOrigin } from 'wcstripe/stripe-utils/verify-stripe-js-origin';

const stripePromise = () =>
	new Promise( ( resolve ) => {
		try {
			// The Stripe.js loader reuses an ambient window.Stripe when the
			// script tag is already on the page, so verify where it came from.
			assertStripeJsOrigin();

			// Default to the 'auto' locale so Stripe chooses the browser's locale
			// if the store's locale is not available.
			const locale = getBlocksConfiguration()?.stripe_locale ?? 'auto';
			resolve(
				loadStripe( getApiKey(), {
					locale,
					...getStripeDevWidgetOptions(),
				} )
			);
		} catch ( error ) {
			// In order to avoid showing console error publicly to users,
			// we resolve instead of rejecting when there is an error.
			resolve( { error } );
		}
	} );

export { stripePromise as loadStripe };
