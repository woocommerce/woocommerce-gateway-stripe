import { PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT } from './constants';
import { ExpressCheckoutContainer } from './express-checkout-container';
import ApplePayPreview from './apple-pay-preview';
import GooglePayPreview from './google-pay-preview';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

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
	canMakePayment: () => true,
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
			expressPaymentMethod="applelePay"
		/>
	),
	edit: <ApplePayPreview />,
	canMakePayment: () => true,
	paymentMethodId: PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT,
	supports: {
		features: getBlocksConfiguration()?.supports ?? [],
	},
} );

export { expressCheckoutElementsGooglePay, expressCheckoutElementsApplePay };
