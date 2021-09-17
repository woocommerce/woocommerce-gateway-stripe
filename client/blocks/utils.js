/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

export const getStripeServerData = () => {
	const stripeServerData = getSetting( 'stripe_data', null );

	if ( ! stripeServerData ) {
		throw new Error( 'Stripe initialization data is not available' );
	}

	return stripeServerData;
};
