/**
 * Stripe UPE saved token label updater.
 *
 * The WooCommerce Checkout Blocks default label for saved payment methods is either "brand ending in XXXX (expires XX/XX)" or "Saved token
 * for $gateway_id". Some Stripe Payment Method tokens (eg Cash App Pay) don't have a last4 property, and so the default label is "Saved
 * token for $gateway_id". There's currently no way to override this label other than using JS to update the label after the checkout form
 * is loaded.
 *
 * This will be fixed via https://github.com/woocommerce/woocommerce/issues/47941. In the meantime, this script will update the saved
 * payment method token labels based on a set of localized token label overrides provided in the blocks configuration via
 * WC_Stripe_Payment_Tokens::get_token_label_overrides_for_checkout().
 */
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { callWhenElementIsAvailable } from 'wcstripe/blocks/upe/call-when-element-is-available';

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
function updateTokenLabels() {
	if ( ! hasTokenLabelOverrides() ) {
		return;
	}

	Object.entries( getBlocksConfiguration()?.tokenLabelOverrides ).forEach(
		( [ tokenID, label ] ) => {
			const element = document.getElementById(
				`radio-control-wc-payment-method-saved-tokens-${ tokenID }__label`
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
	const hasTokenElements = document.querySelector( selector );

	// If the tokens are already loaded, update the token labels.
	if ( hasTokenElements ) {
		updateTokenLabels();
	} else {
		callWhenElementIsAvailable( selector, updateTokenLabels );
	}
}
