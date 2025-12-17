import { PaymentElement } from '@stripe/react-stripe-js/checkout';

const CheckoutForm = async () => {
	// const checkoutState = useCheckout();

	// checkoutState.type === 'success'
	// const { checkout } = checkoutState;
	// const result = await checkout.confirm();
	//
	// if ( result.type === 'error' ) {
	// 	onCheckoutFail( ( { processingResponse: { paymentDetails } } ) => {
	// 		return {
	// 			type: 'failure',
	// 			message: paymentDetails.errorMessage,
	// 			messageContext: emitResponse.noticeContexts.PAYMENTS,
	// 		};
	// 	} );
	// } else {
	// 	onCheckoutSuccess( ( { processingResponse: { paymentDetails } } ) =>
	// 		confirmPayment( api, paymentDetails, emitResponse )
	// 	);
	// }

	return <PaymentElement />;
};

export default CheckoutForm;
