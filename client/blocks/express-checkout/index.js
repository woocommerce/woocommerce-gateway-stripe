/* global wc_stripe_express_checkout_params */

import { PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT } from './constants';
import { ExpressCheckoutContainer } from './express-checkout-container';
import {
	ApplePayPreview,
	GooglePayPreview,
	StripeLinkPreview,
} from './express-button-previews';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { checkPaymentMethodIsAvailable } from 'wcstripe/express-checkout/utils/check-payment-method-availability';

const stripePromise = loadStripe();

const expressCheckoutElementsGooglePay = ( api ) => ( {
	name: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT + '_googlePay',
	title: 'WooCommerce Stripe - Google Pay',
	content: (
		<ExpressCheckoutContainer
			api={ api }
			stripe={ stripePromise }
			expressPaymentMethod="googlePay"
		/>
	),
	edit: <GooglePayPreview />,
	canMakePayment: ( { cart } ) => {
		// eslint-disable-next-line camelcase
		if ( typeof wc_stripe_express_checkout_params === 'undefined' ) {
			return false;
		}

		return new Promise( ( resolve ) => {
			checkPaymentMethodIsAvailable( 'googlePay', api, cart, resolve );
		} );
	},
	paymentMethodId: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT,
	gatewayId: 'stripe',
	supports: {
		features: getBlocksConfiguration()?.supports ?? [],
	},
} );

const expressCheckoutElementsApplePay = ( api ) => ( {
	name: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT + '_applePay',
	title: 'WooCommerce Stripe - Apple Pay',
	content: (
		<ExpressCheckoutContainer
			api={ api }
			stripe={ stripePromise }
			expressPaymentMethod="applePay"
		/>
	),
	edit: <ApplePayPreview />,
	canMakePayment: ( { cart } ) => {
		// eslint-disable-next-line camelcase
		if ( typeof wc_stripe_express_checkout_params === 'undefined' ) {
			return false;
		}

		return new Promise( ( resolve ) => {
			checkPaymentMethodIsAvailable( 'applePay', api, cart, resolve );
		} );
	},
	paymentMethodId: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT,
	gatewayId: 'stripe',
	supports: {
		features: getBlocksConfiguration()?.supports ?? [],
	},
} );

const expressCheckoutElementsStripeLink = ( api ) => ( {
	name: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT + '_link',
	content: (
		<ExpressCheckoutContainer
			api={ api }
			stripe={ stripePromise }
			expressPaymentMethod="link"
		/>
	),
	edit: <StripeLinkPreview />,
	canMakePayment: ( { cart } ) => {
		// eslint-disable-next-line camelcase
		if ( typeof wc_stripe_express_checkout_params === 'undefined' ) {
			return false;
		}

		return new Promise( ( resolve ) => {
			checkPaymentMethodIsAvailable( 'link', api, cart, resolve );
		} );
	},
	paymentMethodId: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT,
	supports: {
		features: getBlocksConfiguration()?.supports ?? [],
	},
} );

export {
	expressCheckoutElementsGooglePay,
	expressCheckoutElementsApplePay,
	expressCheckoutElementsStripeLink,
};
