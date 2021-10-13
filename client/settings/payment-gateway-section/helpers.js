import { getQuery } from '@woocommerce/navigation';

/**
 * It returns the capitalized payment gateway name. It is deliberately capitalizing only
 * the first letter to match their respective hook names in order to be reusable
 * (e.g. useIsStripeIdealEnabled, useStripeSepaName).
 *
 * @return {string} Capitalized payment gateway
 */
export const getGateway = () => {
	const { section } = getQuery();
	const regex = /stripe_([a-z0-9]+)/;

	if ( ! regex.test( section ) ) {
		throw new Error( `${ section } is not being hooked.` );
	}

	return section
		.replace( regex, '$1' )
		.replace( /^\w/, ( letter ) => letter.toUpperCase() );
};
