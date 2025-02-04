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
		case 'amazonPay':
			return 'WooCommerce Stripe - Amazon Pay';
		case 'applePay':
			return 'WooCommerce Stripe - Apple Pay';
		case 'googlePay':
			return 'WooCommerce Stripe - Google Pay';
		case 'link':
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
		case 'amazonPay':
			return <AmazonPayPreview />;
		case 'applePay':
			return <ApplePayPreview />;
		case 'googlePay':
			return <GooglePayPreview />;
		case 'link':
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
		if ( ! getBlocksConfiguration()?.shouldShowExpressCheckoutButton ) {
			return false;
		}

		// eslint-disable-next-line camelcase
		if ( typeof wc_stripe_express_checkout_params === 'undefined' ) {
			return false;
		}

		return new Promise( ( resolve ) => {
			checkPaymentMethodIsAvailable(
				expressPaymentMethod,
				api,
				cart,
				resolve
			);
		} );
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
	expressCheckoutElement( 'amazonPay', api );
const expressCheckoutElementApplePay = ( api ) =>
	expressCheckoutElement( 'applePay', api );
const expressCheckoutElementGooglePay = ( api ) =>
	expressCheckoutElement( 'googlePay', api );
const expressCheckoutElementStripeLink = ( api ) =>
	expressCheckoutElement( 'link', api );

export {
	expressCheckoutElementAmazonPay,
	expressCheckoutElementApplePay,
	expressCheckoutElementGooglePay,
	expressCheckoutElementStripeLink,
};
