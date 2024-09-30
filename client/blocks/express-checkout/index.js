/* global wc_stripe_express_checkout_params */

import { PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT } from './constants';
import { ExpressCheckoutContainer } from './express-checkout-container';
import ApplePayPreview from './apple-pay-preview';
import GooglePayPreview from './google-pay-preview';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { checkPaymentMethodIsAvailable } from 'wcstripe/express-checkout/utils/check-payment-method-availability';

const stripePromise = loadStripe();

const expressCheckoutElementsGooglePay = ( api ) => ( {
	name: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT + '_googlePay',
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
	supports: {
		features: getBlocksConfiguration()?.supports ?? [],
	},
} );

const expressCheckoutElementsApplePay = ( api ) => ( {
	name: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT + '_applePay',
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
	supports: {
		features: getBlocksConfiguration()?.supports ?? [],
	},
} );

export { expressCheckoutElementsGooglePay, expressCheckoutElementsApplePay };
