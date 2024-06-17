import { __ } from '@wordpress/i18n';

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
