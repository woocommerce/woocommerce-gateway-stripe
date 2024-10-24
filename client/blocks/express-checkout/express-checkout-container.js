import React from 'react';
import { Elements } from '@stripe/react-stripe-js';
import ExpressCheckoutComponent from './express-checkout-component';
import { getPaymentMethodTypesForExpressMethod } from 'wcstripe/express-checkout/utils';

export const ExpressCheckoutContainer = ( props ) => {
	const { stripe, billing, expressPaymentMethod } = props;
	const options = {
		mode: 'payment',
		paymentMethodCreation: 'manual',
		amount: billing.cartTotal.value,
		currency: billing.currency.code.toLowerCase(),
		paymentMethodTypes: getPaymentMethodTypesForExpressMethod(
			expressPaymentMethod
		),
	};

	return (
		<div style={ { minHeight: '40px' } }>
			<Elements stripe={ stripe } options={ options }>
				<ExpressCheckoutComponent { ...props } />
			</Elements>
		</div>
	);
};
