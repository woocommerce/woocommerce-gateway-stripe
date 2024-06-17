import { __ } from '@wordpress/i18n';
import { callWhenElementIsAvailable } from 'wcstripe/blocks/upe/call-when-element-is-available';

/** The amount threshold for displaying the notice. */
export const CashAppNoticeAmountThreshold = 2000;

/** The class name for the limit notice element. */
const LimitNoticeClassName = 'wc-block-checkout__payment-method-limit-notice';

/**
 * The Cash App limit notice element.
 */
export const CashAppLimitNotice = document.createElement( 'div' );
CashAppLimitNotice.classList.add( 'woocommerce-info', LimitNoticeClassName );
CashAppLimitNotice.textContent = __(
	'Please note that, depending on your account and transaction history, Cash App Pay may reject your transaction due to its amount.'
);

/**
 * Remove the Cash App limit notice from the checkout form.
 */
export function removeCashAppLimitNotice() {
	const limitNotice = document.querySelector( '.' + LimitNoticeClassName );
	if ( limitNotice ) {
		limitNotice.remove();
	}
}

/**
 * Render the Cash App limit notice in the checkout form if the amount is above the threshold.
 *
 * @param {number} cartAmount
 */
function maybeRenderCashAppLimitNotice( cartAmount ) {
	if ( cartAmount <= CashAppNoticeAmountThreshold ) {
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
 *
 * @param {string} selector
 * @param {number} cartAmount
 * @param {boolean} listenToElement
 */
export function maybeShowCashAppLimitNotice(
	selector,
	cartAmount = 0,
	listenToElement = false
) {
	const hasNoticeWrapperElement = document.querySelector( selector );

	// If the wrapper is already loaded, insert the notice element.
	if ( hasNoticeWrapperElement ) {
		maybeRenderCashAppLimitNotice( cartAmount );
	} else if ( listenToElement ) {
		callWhenElementIsAvailable( selector, maybeRenderCashAppLimitNotice, [
			cartAmount,
		] );
	}
}
