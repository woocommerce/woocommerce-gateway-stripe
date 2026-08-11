import {
	getAppearance,
	getExpandedOptimizedCheckoutRules,
} from '../styles/upe';
import { getStripeServerData } from './utils';

// In-memory cache for computed appearance objects, keyed by checkout type.
// Avoids redundant getComputedStyle() calls within a single page load.
const appearanceCache = {};

/**
 * Initializes the appearance of the payment element. Returns a cached value
 * when available, otherwise computes from the current page styles and caches
 * the result for the lifetime of the page.
 *
 * @param {string}  isBlockCheckout               Whether the checkout is being used in a block context.
 * @param {boolean} shouldExpandOptimizedCheckout Whether the Optimized Checkout Suite should be expanded. Only applicable for classic checkout.
 * @param {boolean} isEditor                      Whether the appearance is being computed in the block editor (Site/Full Site Editor) preview.
 *
 * @return {Object} The appearance object for the UPE.
 */
export const initializeUPEAppearance = (
	isBlockCheckout = 'false',
	shouldExpandOptimizedCheckout = false,
	isEditor = false
) => {
	const isBlocks = isBlockCheckout === 'true';
	const location =
		( isBlocks
			? 'blocks'
			: 'classic' +
			  ( shouldExpandOptimizedCheckout ? '_expanded' : '' ) ) +
		( isEditor ? '_editor' : '' );

	// Check for custom appearance configuration from the server.
	const customServerField = isBlocks ? 'blocksAppearance' : 'appearance';
	const customAppearance = getStripeServerData()?.[ customServerField ];
	if ( customAppearance ) {
		if ( ! shouldExpandOptimizedCheckout ) {
			return customAppearance;
		}

		return {
			...customAppearance,
			rules: getExpandedOptimizedCheckoutRules(
				customAppearance.rules || {}
			),
		};
	}

	if ( appearanceCache[ location ] ) {
		return appearanceCache[ location ];
	}

	const appearance = getAppearance(
		isBlocks,
		shouldExpandOptimizedCheckout,
		isEditor
	);
	appearanceCache[ location ] = appearance;
	return appearance;
};

/**
 * Clears the in-memory appearance cache so the next call to
 * initializeUPEAppearance() re-computes from the current page styles.
 * Used after web fonts finish loading to refresh stale font families.
 */
export const invalidateAppearanceCache = () => {
	Object.keys( appearanceCache ).forEach(
		( key ) => delete appearanceCache[ key ]
	);
};
