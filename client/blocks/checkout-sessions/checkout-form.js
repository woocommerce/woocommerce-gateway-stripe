import { useCheckout } from '@stripe/react-stripe-js/checkout';

const CheckoutForm = () => {
	const checkoutState = useCheckout();

	if ( checkoutState.type === 'loading' ) {
		return <div>Loading...</div>;
	} else if ( checkoutState.type === 'error' ) {
		return <div>Error: { checkoutState.error.message }</div>;
	}
	const { checkout } = checkoutState;
	return (
		<pre>
			{ JSON.stringify( checkout.lineItems, null, 2 ) }
			{ /* A formatted total amount */ }
			Total: { checkout.total.total.amount }
		</pre>
	);
};

export default CheckoutForm;
