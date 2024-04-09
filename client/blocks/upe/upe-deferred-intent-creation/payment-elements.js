/**
 * Internal dependencies
 */
import { Elements } from '@stripe/react-stripe-js';
import PaymentProcessor from './payment-processor';
import WCStripeAPI from 'wcstripe/api';
import {
	getPaymentMethodTypes,
	initializeUPEAppearance,
} from 'wcstripe/stripe-utils';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {*}           props                 Additional props for payment processing.
 * @param {WCStripeAPI} props.api             Object containing methods for interacting with Stripe.
 * @param {string}      props.paymentMethodId The ID of the payment method.
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
const PaymentElements = ( { api, ...props } ) => {
	const stripe = api.getStripe();
	const amount = Number( getBlocksConfiguration()?.cartTotal );
	const currency = getBlocksConfiguration()?.currency.toLowerCase();
	const appearance = initializeUPEAppearance( api, 'true' );
	const options = {
		mode: amount < 1 ? 'setup' : 'payment',
		amount,
		currency,
		paymentMethodCreation: 'manual',
		paymentMethodTypes: getPaymentMethodTypes( props.paymentMethodId ),
		appearance,
	};

	// If the cart contains a subscription or the payment method supports saving, we need to use off_session setup so Stripe can display appropriate terms and conditions.
	if (
		getBlocksConfiguration()?.cartContainsSubscription ||
		props.showSaveOption
	) {
		options.setupFutureUsage = 'off_session';
	}

	return (
		<Elements stripe={ stripe } options={ options }>
			<PaymentProcessor api={ api } { ...props } />
		</Elements>
	);
};

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {string}      paymentMethodId
 * @param {Array}       upeMethods
 * @param {WCStripeAPI} api
 * @param {string}      testingInstructions
 * @param {boolean}     showSaveOption
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
export const getDeferredIntentCreationUPEFields = (
	paymentMethodId,
	upeMethods,
	api,
	testingInstructions,
	showSaveOption
) => {
	return (
		<PaymentElements
			paymentMethodId={ paymentMethodId }
			upeMethods={ upeMethods }
			api={ api }
			testingInstructions={ testingInstructions }
			showSaveOption={ showSaveOption }
		/>
	);
};
