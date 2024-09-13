/* global wc_stripe_express_checkout_params */

import jQuery from 'jquery';
import WCStripeAPI from '../../api';
import { getStripeServerData } from 'wcstripe/stripe-utils';

jQuery( function () {
	// Don't load if blocks checkout is being loaded.
	if (
		wc_stripe_express_checkout_params.has_block &&
		! wc_stripe_express_checkout_params.is_pay_for_order
	) {
		return;
	}

	const publishableKey =
		wc_stripe_express_checkout_params.stripe.publishable_key;
	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	const api = new WCStripeAPI(
		getStripeServerData(),
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);
	const appearance = {
		/* appearance */
	};
	const buttonHeight = Math.min(
		Math.max(
			parseInt(
				wc_stripe_express_checkout_params.button.height ?? '48',
				10
			),
			40
		),
		55
	);
	const options = {
		paymentMethods: {
			applePay: 'always',
			googlePay: 'always',
			link: 'never',
			paypal: 'never',
			amazonPay: 'never',
		},
		layout: { overflow: 'never' },
		buttonType: {
			googlePay: wc_stripe_express_checkout_params.button.type,
			applePay: wc_stripe_express_checkout_params.button.type,
		},
		// Allowed height must be 40px to 55px.
		buttonHeight,
	};
	const elements = api.getStripe().elements( {
		mode: 'payment',
		amount: 1099,
		currency: 'usd',
		appearance,
	} );
	const expressCheckoutElement = elements.create(
		'expressCheckout',
		options
	);
	expressCheckoutElement.mount( '#wc-stripe-express-checkout-button' );
} );
