import { PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT } from './constants';
import { ExpressCheckout } from './express-checkout';
import ApplePayPreview from './apple-pay-preview';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

const stripePromise = loadStripe();

const expressCheckoutElementsPaymentMethod = {
	name: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT,
	content: <ExpressCheckout stripe={ stripePromise } />,
	edit: <ApplePayPreview />,
	canMakePayment: () => true,
	paymentMethodId: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT,
	supports: {
		features: getBlocksConfiguration()?.supports ?? [],
	},
};

export default expressCheckoutElementsPaymentMethod;
