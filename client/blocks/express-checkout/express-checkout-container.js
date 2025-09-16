import React from 'react';
import { Elements } from '@stripe/react-stripe-js';
import ExpressCheckoutComponent from './express-checkout-component';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
	getPaymentMethodTypesForExpressMethod,
	isManualPaymentMethodCreation,
} from 'wcstripe/express-checkout/utils';

export const ExpressCheckoutContainer = ( props ) => {
	const { stripe, billing, expressPaymentMethod } = props;
	const hasFreeTrial = getExpressCheckoutData( 'has_free_trial' );
	const options = {
		mode: hasFreeTrial ? 'subscription' : 'payment',
		...( isManualPaymentMethodCreation(
			expressPaymentMethod,
			hasFreeTrial
		) && {
			paymentMethodCreation: 'manual',
		} ),
		amount: billing.cartTotal.value,
		currency: billing.currency.code.toLowerCase(),
		paymentMethodTypes:
			getPaymentMethodTypesForExpressMethod( expressPaymentMethod ),
		appearance: getExpressCheckoutButtonAppearance(),
		locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
	};

	return (
		<div style={ { minHeight: '40px' } }>
			<Elements stripe={ stripe } options={ options }>
				<ExpressCheckoutComponent { ...props } />
			</Elements>
		</div>
	);
};
