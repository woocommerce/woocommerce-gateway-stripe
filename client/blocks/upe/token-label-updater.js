import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/**
 * Determines whether there are token label overrides.
 *
 * @return {boolean} Whether there are token label overrides present.
 */
function hasTokenLabelOverrides() {
	const overrides = getBlocksConfiguration()?.tokenLabelOverrides;
	return !! ( overrides && Object.keys( overrides ).length > 0 );
}

/**
 * Updates the saved payment method token label overrides.
 *
 * This function is called when the saved payment method tokens are loaded on the checkout form.
 * If there are any token label overrides passed in via JS params, it will update the labels accordingly.
 */
function updateTokenLabelOverrides() {
	if ( ! hasTokenLabelOverrides() ) {
		return;
	}

	Object.entries( getBlocksConfiguration()?.tokenLabelOverrides ).forEach(
		( [ tokenID, label ] ) => {
			const element = document.getElementById(
				`#radio-control-wc-payment-method-saved-tokens-${ tokenID }__label`
			);

			if ( element ) {
				element.innerHTML = label;
			}
		}
	);
}

/**
 * Checks for saved payment method tokens and observes the checkout form for changes.
 */
export function updateTokenLabelsWhenLoaded() {
	const selector = '[name="radio-control-wc-payment-method-saved-tokens"]';
	const loadedTokens = document.querySelector( selector );

	// If the tokens are already loaded, update the token labels.
	if ( loadedTokens ) {
		updateTokenLabelOverrides();
	} else {
		// Tokens are not loaded yet, set up an observer to trigger once they have been mounted.
		const checkoutBlock = document.querySelector(
			'[data-block-name="woocommerce/checkout"]'
		);

		if ( ! checkoutBlock ) {
			return;
		}

		const observer = new MutationObserver( ( mutationList, obs ) => {
			if ( document.querySelector( selector ) ) {
				// Tokens found, run the function and disconnect the observer.
				updateTokenLabelOverrides();
				obs.disconnect();
			}
		} );

		observer.observe( checkoutBlock, {
			childList: true,
			subtree: true,
		} );
	}
}
