/**
 * Stripe UPE Cash App Notice Display
 *
 * This helper adds a notice to the Cash App Pay payment method in the checkout form to inform the user that the transaction may be rejected due to its amount
 * depending on their account and transaction history (and when it is higher than 2000 USD).
 */
import { __ } from '@wordpress/i18n';
import { callWhenElementIsAvailable } from 'wcstripe/blocks/upe/call-when-element-is-available';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

function maybeRenderCashAppLimitNotice() {
	const amount = Number( getBlocksConfiguration()?.cartTotal );
	if ( amount <= 2000 ) {
		return;
	}

	const limitNotice = document.createElement( 'div' );
	limitNotice.classList.add(
		'woocommerce-info',
		'wc-block-checkout__payment-method-limit-notice'
	);
	limitNotice.textContent = __(
		'Please note that, depending on your account and transaction history, Cash App Pay may reject your transaction due to its amount.'
	);
	document
		.querySelector(
			'.wc-block-checkout__payment-method .wc-block-components-notices'
		)
		.appendChild( limitNotice );
}

export function maybeShowCashAppLimitNotice() {
	const selector =
		'.wc-block-checkout__payment-method .wc-block-components-notices';
	const hasNoticeElement = document.querySelector( selector );

	// If the tokens are already loaded, update the token labels.
	if ( hasNoticeElement ) {
		maybeRenderCashAppLimitNotice();
	} else {
		callWhenElementIsAvailable( selector, maybeRenderCashAppLimitNotice );
	}
}
