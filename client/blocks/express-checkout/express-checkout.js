/* global wc_stripe_express_checkout_params */

import React from 'react';
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';

export const ExpressCheckout = ( props ) => {
	const { stripe } = props;
	const options = {
		mode: 'payment',
		amount: 1099,
		currency: 'usd',
	};

	const buttonOptions = {
		buttonType: {
			// eslint-disable-next-line camelcase
			googlePay: wc_stripe_express_checkout_params.button.type,
			// eslint-disable-next-line camelcase
			applePay: wc_stripe_express_checkout_params.button.type,
		},
	};

	return (
		<div style={ { minHeight: '40px' } }>
			<Elements stripe={ stripe } options={ options }>
				<ExpressCheckoutElement
					options={ buttonOptions }
					onClick={ () => {} }
				/>
			</Elements>
		</div>
	);
};
