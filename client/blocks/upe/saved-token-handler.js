import { usePaymentCompleteHandler } from './hooks';

export const SavedTokenHandler = ( {
	api,
	stripe,
	elements,
	eventRegistration: { onCheckoutSuccess },
	emitResponse,
} ) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutSuccess,
		emitResponse,
		false // No need to save a payment that has already been saved.
	);

	return <></>;
};
