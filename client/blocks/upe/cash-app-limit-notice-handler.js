/**
 * Stripe UPE Cash App Notice Display
 *
 * This helper adds a notice to the Cash App Pay payment method in the checkout form to inform the user that the transaction may be rejected due to its amount
 * depending on their account and transaction history (and when it is higher than 2000 USD).
 */
import { __ } from '@wordpress/i18n';
import { callWhenElementIsAvailable } from 'wcstripe/blocks/upe/call-when-element-is-available';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/** The amount threshold for displaying the notice. */
const CashAppNoticeAmountThreshold = 2000;

/** The class name for the limit notice element. */
const LimitNoticeClassName = 'wc-block-checkout__payment-method-limit-notice';

/**
 * Render the Cash App limit notice in the checkout form if the amount is above the threshold.
 */
function maybeRenderCashAppLimitNotice() {
	const amount = Number( getBlocksConfiguration()?.cartTotal );
	if ( amount <= CashAppNoticeAmountThreshold ) {
		return;
	}

	const limitNotice = document.createElement( 'div' );
	limitNotice.classList.add( 'woocommerce-info', LimitNoticeClassName );
	limitNotice.textContent = __(
		'Please note that, depending on your account and transaction history, Cash App Pay may reject your transaction due to its amount.'
	);
	document
		.querySelector(
			'.wc-block-checkout__payment-method .wc-block-components-notices'
		)
		.appendChild( limitNotice );
}

/**
 * Show the Cash App limit notice in the checkout form.
 */
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

/**
 * Remove the Cash App limit notice from the checkout form.
 */
export function removeCashAppLimitNotice() {
	const limitNotice = document.querySelector( '.' + LimitNoticeClassName );
	if ( limitNotice ) {
		limitNotice.remove();
	}
}
