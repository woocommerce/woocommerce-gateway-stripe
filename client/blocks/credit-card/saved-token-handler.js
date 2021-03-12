/**
 * External dependencies
 */
import { Elements, useStripe } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import { usePaymentIntents } from './use-payment-intents';

const sourceIdNoop = () => void null;

const Handler = ( { eventRegistration, emitResponse } ) => {
	const stripe = useStripe();
	const { onCheckoutAfterProcessingWithSuccess } = eventRegistration;
	usePaymentIntents(
		stripe,
		onCheckoutAfterProcessingWithSuccess,
		sourceIdNoop,
		emitResponse
	);
	return null;
};

export const SavedTokenHandler = ( { stripe, ...props } ) => {
	return (
		<Elements stripe={ stripe }>
			<Handler { ...props } />
		</Elements>
	);
};
