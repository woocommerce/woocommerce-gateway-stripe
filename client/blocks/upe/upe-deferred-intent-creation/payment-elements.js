/**
 * Internal dependencies
 */
import { Elements } from '@stripe/react-stripe-js';
import PaymentProcessor from './payment-processor';
import { getStripeServerData } from 'wcstripe/stripe-utils';

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

export const getDeferredIntentCreationUPEFields = (
	upeName,
	upeMethods,
	api,
	testingInstructions
) => {
	return (
		<PaymentElements
			paymentMethodId={ upeName }
			upeMethods={ upeMethods }
			api={ api }
			testingInstructions={ testingInstructions }
		/>
	);
};
