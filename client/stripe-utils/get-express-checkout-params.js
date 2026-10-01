/* global wc_stripe_express_checkout_params */
import { getSetting } from '@woocommerce/settings';

/**
 * Returns the filtered Express Checkout params, or null when the page has none.
 *
 * Block cart and checkout pages don't load the classic Express Checkout bundle, so the
 * `wc_stripe_express_checkout_params` global is missing there. The blocks integration
 * merges the same filtered params into its `stripe_data` setting. Checking `has_block`
 * keeps the fallback to the pages that used to get the global, so any other page that
 * carries `stripe_data` still shows no express buttons.
 *
 * @return {Object|null} The Express Checkout params.
 */
export const getExpressCheckoutParams = () => {
	// eslint-disable-next-line camelcase
	if ( typeof wc_stripe_express_checkout_params !== 'undefined' ) {
		return wc_stripe_express_checkout_params; // eslint-disable-line camelcase
	}

	const blocksData = getSetting( 'stripe_data', null );
	return blocksData?.has_block ? blocksData : null;
};
