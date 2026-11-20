import apiFetch from '@wordpress/api-fetch';

// Matches WC_Stripe_Duplicate_Payment_Prevention::REDIRECT_QUERY_ARG, which makes the
// order-received page explain that the shopper was not charged again.
const DUPLICATE_PREVENTED_QUERY_ARG = 'wc_stripe_duplicate_prevented';

// Added by get_return_url_for_checkout_session(). Left in place, they make the order-received
// page process this tab's expired session and send the shopper back to checkout.
const CHECKOUT_SESSION_RETURN_QUERY_ARGS = [
	'order_id',
	'wc_payment_method',
	'_wpnonce',
	'wc_stripe_cs',
	'pay_for_order',
];

const PAID_ORDER_STATUSES = [ 'processing', 'completed' ];

/**
 * Returns the order-received URL when the order was paid while this tab's confirmation failed, or null.
 *
 * Paying the same order in another tab expires this tab's Checkout Session, and Stripe then fails
 * this tab's confirmation with a generic error, without a code. Only the order itself tells that
 * apart from a real failure. Any problem with the lookup counts as "not paid", so the original
 * error is shown.
 *
 * @param {string} checkoutSessionReturnUrl The return URL from process_payment(), with the order ID and key.
 * @param {string} billingEmail             The billing email, which the Store API needs for guest orders.
 * @return {Promise<string|null>} The URL to redirect to, or null.
 */
export const getAlreadyPaidOrderUrl = async (
	checkoutSessionReturnUrl,
	billingEmail
) => {
	try {
		const url = new URL( checkoutSessionReturnUrl, window.location.href );
		const orderId = url.searchParams.get( 'order_id' );
		if ( ! orderId ) {
			return null;
		}

		const query = new URLSearchParams( {
			key: url.searchParams.get( 'key' ) ?? '',
			billing_email: billingEmail ?? '',
		} );
		const order = await apiFetch( {
			path: `/wc/store/v1/order/${ orderId }?${ query.toString() }`,
		} );

		if ( ! PAID_ORDER_STATUSES.includes( order?.status ) ) {
			return null;
		}

		CHECKOUT_SESSION_RETURN_QUERY_ARGS.forEach( ( arg ) =>
			url.searchParams.delete( arg )
		);
		url.searchParams.set( DUPLICATE_PREVENTED_QUERY_ARG, '1' );
		return url.toString();
	} catch ( error ) {
		return null;
	}
};
