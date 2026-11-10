import jQuery from 'jquery';
import {
	PAYMENT_INTENT_STATUS_REQUIRES_ACTION,
	PAYMENT_METHOD_BOLETO,
	PAYMENT_METHOD_CASHAPP,
	PAYMENT_METHOD_MULTIBANCO,
	PAYMENT_METHOD_WECHAT_PAY,
} from './constants';
import { blockUI } from './checkout-form';
import { __ } from '@wordpress/i18n';
import {
	getStripeServerData,
	normalizeReturnUrl,
	showErrorCheckout,
	unblockBlockCheckout,
	resetBlockCheckoutPaymentState,
} from 'wcstripe/stripe-utils';

/**
 * Resolves the post-payment destination for the voucher and wallet confirmation flows.
 *
 * The return URL travels in the URL fragment, which never reaches the server and can therefore
 * be swapped for any destination by anyone able to hand the shopper a crafted store URL. It is
 * clamped to this store's origin here, before it is used to navigate or handed to Stripe as a
 * `return_url`, and falls back to the order received page when it does not survive that check.
 *
 * @param {string} encodedReturnUrl The percent-encoded return URL taken from the fragment.
 * @param {string} orderId          The order ID taken from the fragment.
 * @return {?string} A same-origin fragment URL or the server-provided fallback, or null when neither is available.
 */
const resolvePostPaymentUrl = ( encodedReturnUrl, orderId ) => {
	let decodedReturnUrl = null;
	try {
		decodedReturnUrl = decodeURIComponent( encodedReturnUrl || '' );
	} catch ( error ) {}

	const validatedReturnUrl = normalizeReturnUrl( decodedReturnUrl );
	if ( validatedReturnUrl ) {
		return validatedReturnUrl;
	}

	const orderReceivedURL = getStripeServerData()?.orderReceivedURL;
	if ( ! orderReceivedURL ) {
		return null;
	}

	// `parseInt( 'NaN' )` round-trips back to the string 'NaN', so it has to be excluded
	// explicitly for the numeric check to reject it.
	if (
		orderId &&
		orderId !== 'NaN' &&
		orderId === String( parseInt( orderId, 10 ) )
	) {
		return orderReceivedURL + '/' + encodeURIComponent( orderId ) + '/';
	}

	return orderReceivedURL;
};

/**
 * Handles displaying the Boleto or Oxxo or Multibanco voucher to the customer and then redirecting
 * them to the order received page once they close the voucher window.
 *
 * When processing a payment for one of our voucher payment methods on the checkout or order pay page,
 * the process_payment_with_deferred_intent() function redirects the customer to a URL
 * formatted with: #wc-stripe-voucher-<order_id>:<payment_method_type>:<client_secret>:<redirect_url>.
 *
 * This function, which is hooked onto the hashchanged event, checks if the URL contains the data we need to process the voucher payment.
 *
 * @param {Object} api        The API object used to create the Stripe payment method.
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 */
export const confirmVoucherPayment = async ( api, jQueryForm ) => {
	const stripeServerData = getStripeServerData();
	const isOrderPay = stripeServerData?.isOrderPay;

	// The Order Pay page does a hard refresh when the hash changes, so we need to block the UI again.
	if ( isOrderPay ) {
		blockUI( jQueryForm );
	}

	const partials = window.location.href.match(
		/#wc-stripe-voucher-(.+):(.+):(.+):(.+)$/
	);

	if ( ! partials ) {
		jQueryForm.removeClass( 'processing' ).unblock();
		return;
	}

	// Remove the hash from the URL.
	history.replaceState(
		'',
		document.title,
		window.location.pathname + window.location.search
	);

	const orderId = partials[ 1 ];
	const clientSecret = partials[ 3 ];

	// Verify the request using the data added to the URL.
	if (
		! clientSecret ||
		( isOrderPay && orderId !== stripeServerData?.orderId )
	) {
		jQueryForm.removeClass( 'processing' ).unblock();
		return;
	}

	const paymentMethodType = partials[ 2 ];

	try {
		// Confirm the payment to tell Stripe to display the voucher to the customer.
		let confirmPayment;
		if ( paymentMethodType === PAYMENT_METHOD_BOLETO ) {
			confirmPayment = await api
				.getStripe()
				.confirmBoletoPayment( clientSecret, {} );
		} else if ( paymentMethodType === PAYMENT_METHOD_MULTIBANCO ) {
			confirmPayment = await api
				.getStripe()
				.confirmMultibancoPayment( clientSecret, {} );
		} else {
			confirmPayment = await api
				.getStripe()
				.confirmOxxoPayment( clientSecret, {} );
		}

		if ( confirmPayment.error ) {
			throw confirmPayment.error;
		}
	} catch ( error ) {
		jQueryForm.removeClass( 'processing' ).unblock();
		showErrorCheckout( error.message );
		return;
	}

	const postPaymentUrl = resolvePostPaymentUrl( partials[ 4 ], orderId );
	if ( ! postPaymentUrl ) {
		showErrorCheckout(
			__(
				'There was a problem processing the payment. Please refresh the page to try again.',
				'woocommerce-gateway-stripe'
			)
		);
		return;
	}

	window.location.href = postPaymentUrl;
};

/**
 * Handles displaying the CashApp or WeChat modal to the customer and then redirecting
 * them to the order received page once they authenticate the payment.
 *
 * When processing a payment for a wallet payment method on the checkout or order pay page,
 * the process_payment_with_deferred_intent() function redirects the customer to a URL
 * formatted with: #wc-stripe-wallet-<order_id>:<payment_method_type>:<payment_intent_type>:<client_secret>:<redirect_url>:<nonce>.
 *
 * This function, which is hooked onto the hashchanged event, checks if the URL contains the data we need to process the wallet payment.
 *
 * @param {Object} api        The API object used to create the Stripe payment method.
 * @param {Object} jQueryForm The jQuery object for the form being submitted.
 */
export const confirmWalletPayment = async ( api, jQueryForm ) => {
	const isOrderPay = getStripeServerData()?.isOrderPay;
	const isChangingPayment = getStripeServerData()?.isChangingPayment;

	// The Order Pay page does a hard refresh when the hash changes, so we need to block the UI again.
	if ( isOrderPay ) {
		blockUI( jQueryForm );
	}

	const partials = window.location.href.match(
		/#wc-stripe-wallet-(.+):(.+):(.+):(.+):(.+):(.+)$/
	);

	if ( ! partials ) {
		jQueryForm.removeClass( 'processing' ).unblock();
		return;
	}

	// Remove the hash from the URL.
	history.replaceState(
		'',
		document.title,
		window.location.pathname + window.location.search
	);

	const orderId = partials[ 1 ];
	const clientSecret = partials[ 4 ];

	// Verify the request using the data added to the URL.
	if (
		! clientSecret ||
		( isOrderPay && orderId !== getStripeServerData()?.orderId )
	) {
		jQueryForm.removeClass( 'processing' ).unblock();
		return;
	}

	const paymentMethodType = partials[ 2 ];
	const intentType = partials[ 3 ];
	const returnURL = resolvePostPaymentUrl( partials[ 5 ], orderId );

	if ( ! returnURL ) {
		jQueryForm.removeClass( 'processing' ).unblock();
		showErrorCheckout(
			__(
				'There was a problem processing the payment. Please refresh the page to try again.',
				'woocommerce-gateway-stripe'
			)
		);
		unblockBlockCheckout();
		resetBlockCheckoutPaymentState();
		return;
	}

	try {
		// Confirm the payment to tell Stripe to display the modal to the customer.
		let confirmPayment;
		switch ( paymentMethodType ) {
			case PAYMENT_METHOD_WECHAT_PAY:
				confirmPayment = await api
					.getStripe()
					.confirmWechatPayPayment( clientSecret, {
						payment_method_options: {
							wechat_pay: {
								client: 'web',
							},
						},
					} );
				break;
			case PAYMENT_METHOD_CASHAPP:
				if ( intentType === 'setup_intent' ) {
					confirmPayment = await api
						.getStripe()
						.confirmCashappSetup( clientSecret, {
							return_url: returnURL,
						} );
				} else {
					confirmPayment = await api
						.getStripe()
						.confirmCashappPayment( clientSecret, {
							return_url: returnURL,
						} );
				}
				break;
			default:
				// eslint-disable-next-line no-console
				console.error( 'Invalid wallet type:', paymentMethodType );
				throw new Error( getStripeServerData()?.invalid_wallet_type );
		}

		if ( confirmPayment.error ) {
			throw confirmPayment.error;
		}

		const intentObject =
			intentType === 'setup_intent'
				? confirmPayment.setupIntent
				: confirmPayment.paymentIntent;

		if ( intentObject.last_payment_error ) {
			throw new Error( intentObject.last_payment_error.message );
		}

		// Do not redirect to the order received page if the modal is closed without payment.
		// Otherwise redirect to the order received page.
		if ( intentObject.status !== PAYMENT_INTENT_STATUS_REQUIRES_ACTION ) {
			if ( ! isChangingPayment ) {
				window.location.href = returnURL;
			}

			// If we're changing a subscription's payment method, there's an extra step needed.
			// We need to confirm the change payment intent via the confirm_change_payment AJAX request and then redirect to the return URL.
			const response = await api.request(
				api.getAjaxUrl( 'confirm_change_payment' ),
				{
					order_id: orderId,
					intent_id: intentObject.id,
					payment_method_id: intentObject.payment_method || null,
					_ajax_nonce: partials[ 6 ],
				}
			);

			if ( response.success ) {
				window.location.href = response.data.return_url;
			} else {
				throw new Error( response.data.error.message );
			}
		}
	} catch ( error ) {
		showErrorCheckout( error.message );
	} finally {
		jQueryForm.removeClass( 'processing' ).unblock();
		unblockBlockCheckout();
		resetBlockCheckoutPaymentState();
	}
};

export const initializeVoucherAndWalletConfirmation = ( api ) => {
	const stripeServerData = getStripeServerData();
	if (
		! stripeServerData?.isOrderPay &&
		! stripeServerData?.isCheckout &&
		! stripeServerData?.isChangingPayment
	) {
		return;
	}

	// Both bundles can run on embedded checkouts. jQuery's page-wide data survives
	// separate webpack runtimes, unlike a module-local initialization flag.
	const stateKey = 'wcStripeVoucherWalletConfirmation';
	if ( jQuery.data( window, stateKey ) ) {
		return;
	}
	const state = { pendingHash: null };
	jQuery.data( window, stateKey, state );

	const maybeConfirmVoucherOrWalletPayment = async () => {
		if ( state.pendingHash ) {
			// A duplicate redirect must not leave the hash set: after cancellation,
			// redirecting to that same hash would no longer emit a hashchange event.
			if ( window.location.hash === state.pendingHash ) {
				history.replaceState(
					'',
					document.title,
					window.location.pathname + window.location.search
				);
			}
			return;
		}
		const isVoucher = window.location.hash.startsWith(
			'#wc-stripe-voucher-'
		);
		const isWallet =
			window.location.hash.startsWith( '#wc-stripe-wallet-' );
		if ( ! isVoucher && ! isWallet ) {
			return;
		}

		const useOrderReview =
			stripeServerData.isOrderPay ||
			( isWallet && stripeServerData.isChangingPayment );
		const form = jQuery(
			useOrderReview ? '#order_review' : 'form.checkout'
		);
		state.pendingHash = window.location.hash;
		try {
			await ( isVoucher ? confirmVoucherPayment : confirmWalletPayment )(
				api,
				form
			);
		} finally {
			state.pendingHash = null;
		}
	};

	jQuery( window ).on(
		'hashchange.wcStripeVoucherWallet',
		maybeConfirmVoucherOrWalletPayment
	);
	// Order-pay can arrive with the fragment already set, without a hashchange event.
	return maybeConfirmVoucherOrWalletPayment();
};
