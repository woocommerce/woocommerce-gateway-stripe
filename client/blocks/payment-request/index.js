/* global wc_stripe_payment_request_params */

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import { PaymentRequestExpress } from './payment-request-express';
import { applePayImage } from './apple-pay-preview';
import { getStripeServerData, loadStripe } from '../stripe-utils';

const ApplePayPreview = () => <img src={ applePayImage } alt="" />;

const componentStripePromise = loadStripe();

const paymentRequestPaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	content: <PaymentRequestExpress stripe={ componentStripePromise } />,
	edit: <ApplePayPreview />,
	canMakePayment: () => {
		// If the `wc_stripe_payment_request_params` object is not available we don't support
		// payment requests.
		// eslint-disable-next-line camelcase
		if ( typeof wc_stripe_payment_request_params === 'undefined' ) {
			return false;
		}

		return loadStripe().then( ( stripe ) => {
			return stripe !== null;
		} );
	},
	paymentMethodId: 'stripe',
	supports: {
		features: getStripeServerData()?.supports ?? [],
	},
};

export default paymentRequestPaymentMethod;
