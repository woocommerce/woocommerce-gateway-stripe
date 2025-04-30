/* global wc_stripe_express_checkout_params */

import { PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT } from './constants';
import { ExpressCheckoutContainer } from './express-checkout-container';
import {
	AmazonPayPreview,
	ApplePayPreview,
	GooglePayPreview,
	StripeLinkPreview,
} from './express-button-previews';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { checkPaymentMethodIsAvailable } from 'wcstripe/express-checkout/utils/check-payment-method-availability';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_LINK,
} from 'wcstripe/stripe-utils/constants';

/** @typedef {import('react')} React */

const stripePromise = loadStripe();

/**
 * Get the title for the express payment method.
 *
 * @param {string} expressPaymentMethod
 * @return {string} The title.
 */
const getTitle = ( expressPaymentMethod ) => {
	switch ( expressPaymentMethod ) {
		case EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY:
			return 'WooCommerce Stripe - Amazon Pay';
		case EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY:
			return 'WooCommerce Stripe - Apple Pay';
		case EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY:
			return 'WooCommerce Stripe - Google Pay';
		case EXPRESS_PAYMENT_METHOD_SETTING_LINK:
			return 'WooCommerce Stripe - Link by Stripe';
		default:
			return '';
	}
};

/**
 * Get the editor element for the express payment method.
 *
 * @param {string} expressPaymentMethod
 * @return {React.ReactNode} The React element for the editor.
 */
const getEditorElement = ( expressPaymentMethod ) => {
	switch ( expressPaymentMethod ) {
		case EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY:
			return <AmazonPayPreview />;
		case EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY:
			return <ApplePayPreview />;
		case EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY:
			return <GooglePayPreview />;
		case EXPRESS_PAYMENT_METHOD_SETTING_LINK:
			return <StripeLinkPreview />;
		default:
			return null;
	}
};

/**
 *
 * @param {string} expressPaymentMethod
 * @param {Object} api The Stripe API object.
 * @return {Object} The express payment method configuration.
 */
const expressCheckoutElement = ( expressPaymentMethod, api ) => {
	const name =
		PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT + '_' + expressPaymentMethod;
	const title = getTitle( expressPaymentMethod );
	const content = (
		<ExpressCheckoutContainer
			api={ api }
			stripe={ stripePromise }
			expressPaymentMethod={ expressPaymentMethod }
		/>
	);
	const edit = getEditorElement( expressPaymentMethod );
	const canMakePayment = ( { cart } ) => {
		if ( parseFloat( cart.cartTotals.total_price ) === 0.0 ) {
			return false;
		}

		if ( ! getBlocksConfiguration()?.shouldShowExpressCheckoutButton ) {
			return false;
		}

		// eslint-disable-next-line camelcase
		if ( typeof wc_stripe_express_checkout_params === 'undefined' ) {
			return false;
		}

		return checkPaymentMethodIsAvailable( expressPaymentMethod, api, cart );
	};

	const supports = {
		features: getBlocksConfiguration()?.supports ?? [],
		...( getBlocksConfiguration()?.isAdmin && {
			style: getBlocksConfiguration()?.style ?? [],
		} ),
	};

	return {
		name,
		title,
		content,
		edit,
		canMakePayment,
		paymentMethodId: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT,
		gatewayId: 'stripe',
		supports,
	};
};

const expressCheckoutElementAmazonPay = ( api ) =>
	expressCheckoutElement( EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY, api );
const expressCheckoutElementApplePay = ( api ) =>
	expressCheckoutElement( EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY, api );
const expressCheckoutElementGooglePay = ( api ) =>
	expressCheckoutElement( EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY, api );
const expressCheckoutElementStripeLink = ( api ) =>
	expressCheckoutElement( EXPRESS_PAYMENT_METHOD_SETTING_LINK, api );

export {
	expressCheckoutElementAmazonPay,
	expressCheckoutElementApplePay,
	expressCheckoutElementGooglePay,
	expressCheckoutElementStripeLink,
};
