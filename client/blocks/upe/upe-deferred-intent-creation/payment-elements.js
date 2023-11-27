/**
 * Internal dependencies
 */
import { Elements } from '@stripe/react-stripe-js';
import PaymentProcessor from './payment-processor';
import { getStripeServerData } from 'wcstripe/stripe-utils';
import WCStripeAPI from 'wcstripe/api';

/**
 * Renders a Stripe Payment elements.
 *
 * @param {*}           props                 Additional props for payment processing.
 * @param {WCStripeAPI} props.api             Object containing methods for interacting with Stripe.
 * @param {string}      props.paymentMethodId The ID of the payment method.
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
const PaymentElements = ( { api, ...props } ) => {
	const stripe = api.getStripe();
	const amount = Number( getStripeServerData()?.cartTotal );
	const currency = getStripeServerData()?.currency.toLowerCase();

	return (
		<Elements
			stripe={ stripe }
			options={ {
				mode: amount < 1 ? 'setup' : 'payment',
				amount,
				currency,
				paymentMethodCreation: 'manual',
				paymentMethodTypes: [ props.paymentMethodId ],
			} }
		>
			<PaymentProcessor api={ api } { ...props } />
		</Elements>
	);
};

/**
 * Renders a Stripe Payment elements.
 *
 * @param {string} paymentMethodId
 * @param {Array} upeMethods
 * @param {WCStripeAPI} api
 * @param {string} testingInstructions
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
export const getDeferredIntentCreationUPEFields = (
	paymentMethodId,
	upeMethods,
	api,
	testingInstructions
) => {
	return (
		<PaymentElements
			paymentMethodId={ paymentMethodId }
			upeMethods={ upeMethods }
			api={ api }
			testingInstructions={ testingInstructions }
		/>
	);
};
