import { __ } from '@wordpress/i18n';
import { callWhenElementIsAvailable } from 'wcstripe/blocks/upe/call-when-element-is-available';
import { CASH_APP_NOTICE_AMOUNT_THRESHOLD } from 'wcstripe/data/constants';

/** The class name for the limit notice element. */
const LIMIT_NOTICE_CLASSNAME = 'wc-block-checkout__payment-method-limit-notice';

/**
 * The Cash App limit notice element.
 */
export const cashAppLimitNotice = document.createElement( 'div' );
cashAppLimitNotice.classList.add( 'woocommerce-info', LIMIT_NOTICE_CLASSNAME );
cashAppLimitNotice.textContent = __(
	'Please note that, depending on your account and transaction history, Cash App Pay may reject your transaction due to its amount.'
);
cashAppLimitNotice.setAttribute( 'data-testid', 'cash-app-limit-notice' );

/**
 * Remove the Cash App limit notice from the checkout form.
 */
export function removeCashAppLimitNotice() {
	const limitNotice = document.querySelector( '.' + LIMIT_NOTICE_CLASSNAME );
	if ( limitNotice ) {
		limitNotice.remove();
	}
}

/**
 * Render the Cash App limit notice in the checkout form if the amount is above the threshold.
 *
 * @param {string} wrapperElementSelector
 * @param {boolean} appendToElement Whether to append the notice to the element.
 */
function maybeRenderCashAppLimitNotice(
	wrapperElementSelector,
	appendToElement = false
) {
	const wrapperElement = document.querySelector( wrapperElementSelector );
	if ( appendToElement ) {
		wrapperElement.appendChild( cashAppLimitNotice );
	} else {
		wrapperElement.insertBefore(
			cashAppLimitNotice,
			wrapperElement.firstChild
		);
	}
}

/**
 * Show the Cash App limit notice in the checkout form.
 *
 * @param {string} wrapperElementSelector The selector for the wrapper element.
 * @param {number} cartAmount The cart amount.
 * @param {boolean} isBlockCheckout Whether the checkout form is a block checkout.
 */
export function maybeShowCashAppLimitNotice(
	wrapperElementSelector,
	cartAmount = 0,
	isBlockCheckout = false
) {
	if ( cartAmount <= CASH_APP_NOTICE_AMOUNT_THRESHOLD ) {
		return;
	}

	const hasNoticeWrapperElement = document.querySelector(
		wrapperElementSelector
	);
	const appendToElement = isBlockCheckout;

	// If the wrapper is already loaded, insert the notice element.
	if ( hasNoticeWrapperElement ) {
		maybeRenderCashAppLimitNotice(
			wrapperElementSelector,
			appendToElement
		);
	} else if ( isBlockCheckout ) {
		callWhenElementIsAvailable(
			wrapperElementSelector,
			maybeRenderCashAppLimitNotice,
			[ wrapperElementSelector, appendToElement ]
		);
	}
}
