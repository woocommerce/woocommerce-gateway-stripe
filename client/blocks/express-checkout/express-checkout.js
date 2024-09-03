/* global wc_stripe_payment_request_params */

import React from 'react';
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';

export const ExpressCheckout = ( { stripe } ) => {
	const options = {
		mode: 'payment',
		amount: 1099,
		currency: 'usd',
	};

	const buttonOptions = {
		buttonType: {
			googlePay: wc_stripe_payment_request_params.button.type,
			applePay: wc_stripe_payment_request_params.button.type,
		},
	};

	return (
		<Elements stripe={ stripe } options={ options }>
			<ExpressCheckoutElement
				options={ buttonOptions }
				onClick={ () => console.log( 'confirmed' ) }
			/>
		</Elements>
	);
};
