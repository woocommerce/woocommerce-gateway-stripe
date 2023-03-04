import { Elements, useStripe } from '@stripe/react-stripe-js';
import { usePaymentIntents } from './use-payment-intents';

const paymentMethodIdNoop = () => void null;

const Handler = ( { eventRegistration, emitResponse } ) => {
	const stripe = useStripe();
	const { onCheckoutAfterProcessingWithSuccess } = eventRegistration;
	usePaymentIntents(
		stripe,
		onCheckoutAfterProcessingWithSuccess,
		paymentMethodIdNoop,
		emitResponse
	);
	return null;
};

export const ThreeDSecurePaymentHandler = ( { stripe, ...props } ) => {
	return (
		<Elements stripe={ stripe }>
			<Handler { ...props } />
		</Elements>
	);
};
