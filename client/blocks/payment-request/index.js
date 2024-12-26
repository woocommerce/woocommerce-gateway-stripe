import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';
import { PAYMENT_METHOD_NAME } from './constants';
import { PaymentRequestExpress } from './payment-request-express';
import { applePayImage } from './apple-pay-preview';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import {
	getBlocksConfiguration,
	createPaymentRequestUsingCart,
} from 'wcstripe/blocks/utils';

const ApplePayPreview = () => <img src={ applePayImage } alt="" />;

const componentStripePromise = loadStripe();

const supports = {
	features: getBlocksConfiguration()?.supports ?? [],
};
if ( getBlocksConfiguration().isAdmin ?? false ) {
	supports.style = getBlocksConfiguration()?.style ?? [];
}

const paymentRequestPaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	title: 'Stripe',
	description: __(
		'This will show users the ApplePay, GooglePay, or Stripe Link button depending on their browser and logged in status.',
		'woocommerce-gateway-stripe'
	),
	gatewayId: 'stripe',
	content: <PaymentRequestExpress stripe={ componentStripePromise } />,
	edit: <ApplePayPreview />,
	canMakePayment: ( cartData ) => {
		// If in the editor context, always return true to display the `edit` prop preview.
		// https://github.com/woocommerce/woocommerce-gutenberg-products-block/issues/4101.
		if ( getBlocksConfiguration()?.isAdmin ?? false ) {
			return true;
		}

		if ( ! getBlocksConfiguration()?.shouldShowPaymentRequestButton ) {
			return false;
		}

		return loadStripe().then( ( stripe ) => {
			// Create a payment request and check if we can make a payment to determine whether to
			// show the Payment Request Button or not. This is necessary because a browser might be
			// able to load the Stripe JS object, but not support Payment Requests.
			const fakeCart = {
				order_data: {
					total: {
						label: 'Total',
						amount: parseInt(
							cartData?.cartTotals?.total_price ?? 0,
							10
						),
						pending: true,
					},
					currency: cartData?.cartTotals?.currency_code?.toLowerCase(),
					country_code: getSetting( 'baseLocation', {} )?.country,
					displayItems: [],
				},
				shipping_required: false,
			};
			const paymentRequest = createPaymentRequestUsingCart(
				stripe,
				fakeCart
			);

			return paymentRequest.canMakePayment();
		} );
	},
	paymentMethodId: 'stripe',
	supports,
};

export default paymentRequestPaymentMethod;
