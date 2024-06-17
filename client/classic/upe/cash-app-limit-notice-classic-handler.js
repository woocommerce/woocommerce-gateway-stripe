/**
 * Stripe UPE Cash App Notice Display
 *
 * This helper adds a notice to the Cash App Pay payment method in the checkout form to inform the user that the transaction may be rejected due to its amount
 * depending on their account and transaction history (and when it is higher than 2000 USD).
 */
import {
	CashAppLimitNotice,
	CashAppNoticeAmountThreshold,
} from 'wcstripe/cash-app-limit-notice-handler';

/**
 * Render the Cash App limit notice in the checkout form if the amount is above the threshold.
 */
function maybeRenderCashAppLimitNotice() {
	const amount = 0;
	if ( amount <= CashAppNoticeAmountThreshold ) {
		return;
	}

	const noticeWrapperElement = document.querySelector(
		'.woocommerce-checkout-payment'
	);
	noticeWrapperElement.insertBefore(
		CashAppLimitNotice,
		noticeWrapperElement.firstChild
	);
}

/**
 * Show the Cash App limit notice in the checkout form.
 */
export function maybeShowCashAppLimitNotice() {
	const selector = '.woocommerce-checkout-payment';
	const hasNoticeWrapperElement = document.querySelector( selector );

	// If the tokens are already loaded, update the token labels.
	if ( hasNoticeWrapperElement ) {
		maybeRenderCashAppLimitNotice();
	}
}
