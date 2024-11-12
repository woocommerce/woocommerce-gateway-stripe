export const NAMESPACE = '/wc/v3/wc_stripe';
export const STORE_NAME = 'wc/stripe';

/**
 * The amount threshold for displaying the notice.
 *
 * @type {number} The threshold amount.
 */
export const CASH_APP_NOTICE_AMOUNT_THRESHOLD = 200000;

/**
 * Wait time in ms for a notice to be displayed in ECE before proceeding with the checkout process.
 *
 * Reasons for this value:
 * - We cannot display an alert message because it blocks the default ECE process
 * - The delay cannot be higher than 1s due to Stripe JS limitations (it times out after 1s)
 *
 * @type {number} The delay in milliseconds.
 */
export const EXPRESS_CHECKOUT_NOTICE_DELAY = 700;
