/**
 * Stripe UPE Cash App Notice Display
 *
 * This helper adds a notice to the Cash App Pay payment method in the checkout form to inform the user that the transaction may be rejected due to its amount
 * depending on their account and transaction history (and when it is higher than 2000 USD).
 */
import { callWhenElementIsAvailable } from 'wcstripe/blocks/upe/call-when-element-is-available';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import {
	CashAppLimitNotice,
	CashAppNoticeAmountThreshold,
} from 'wcstripe/cash-app-limit-notice-handler';

/**
 * Render the Cash App limit notice in the checkout form if the amount is above the threshold.
 */
function maybeRenderCashAppLimitNotice() {
	const amount = Number( getBlocksConfiguration()?.cartTotal );
	if ( amount <= CashAppNoticeAmountThreshold ) {
		return;
	}

	document
		.querySelector(
			'.wc-block-checkout__payment-method .wc-block-components-notices'
		)
		.appendChild( CashAppLimitNotice );
}

/**
 * Show the Cash App limit notice in the checkout form.
 */
export function maybeShowCashAppLimitNotice() {
	const selector =
		'.wc-block-checkout__payment-method .wc-block-components-notices';
	const hasNoticeWrapperElement = document.querySelector( selector );

	// If the wrapper is already loaded, insert the notice element.
	if ( hasNoticeWrapperElement ) {
		maybeRenderCashAppLimitNotice();
	} else {
		callWhenElementIsAvailable( selector, maybeRenderCashAppLimitNotice );
	}
}
