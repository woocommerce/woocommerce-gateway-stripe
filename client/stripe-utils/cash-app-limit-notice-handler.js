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
CashAppLimitNotice.setAttribute( 'data-testid', 'cash-app-limit-notice' );

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
 * @param {number} cartAmount The cart amount.
 * @param {boolean} isBlockCheckout Whether the checkout form is a block checkout.
 */
function maybeRenderCashAppLimitNotice( cartAmount, isBlockCheckout = false ) {
	if ( cartAmount <= CashAppNoticeAmountThreshold ) {
		return;
	}

	if ( isBlockCheckout ) {
		document
			.querySelector(
				'.wc-block-checkout__payment-method .wc-block-components-notices'
			)
			.appendChild( CashAppLimitNotice );
	} else {
		const noticeWrapperElement = document.querySelector(
			'.woocommerce-checkout-payment'
		);
		noticeWrapperElement.insertBefore(
			CashAppLimitNotice,
			noticeWrapperElement.firstChild
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
	const hasNoticeWrapperElement = document.querySelector(
		wrapperElementSelector
	);

	// If the wrapper is already loaded, insert the notice element.
	if ( hasNoticeWrapperElement ) {
		maybeRenderCashAppLimitNotice( cartAmount, isBlockCheckout );
	} else if ( isBlockCheckout ) {
		callWhenElementIsAvailable(
			wrapperElementSelector,
			maybeRenderCashAppLimitNotice,
			[ cartAmount, isBlockCheckout ]
		);
	}
}
