import { useCheckout, PaymentElement } from '@stripe/react-stripe-js/checkout';

const CheckoutForm = () => {
	console.log( 'Rendering CheckoutForm' );
	const checkoutState = useCheckout();

	const handleSubmit = async ( event ) => {
		// We don't want to let default form submission happen here,
		// which would refresh the page.
		event.preventDefault();

		if ( checkoutState.type === 'loading' ) {
			return <div>Loading...</div>;
		} else if ( checkoutState.type === 'error' ) {
			return <div>Error: { checkoutState.error.message }</div>;
		}

		// checkoutState.type === 'success'
		const { checkout } = checkoutState;
		const result = await checkout.confirm();

		if ( result.type === 'error' ) {
			// Show error to your customer (for example, payment details incomplete)
			console.log( result.error.message );
		} else {
			// Your customer will be redirected to your `return_url`. For some payment
			// methods like iDEAL, your customer will be redirected to an intermediate
			// site first to authorize the payment, then redirected to the `return_url`.
		}
	};

	return (
		<>
			<PaymentElement />
			<button onClick={ handleSubmit }>Submit</button>
		</>
	);
};

export default CheckoutForm;
