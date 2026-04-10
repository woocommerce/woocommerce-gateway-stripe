import React from 'react';
import { Elements } from '@stripe/react-stripe-js';
import ExpressCheckoutComponent from './express-checkout-component';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
	getPaymentMethodTypesForExpressMethod,
	isManualPaymentMethodCreation,
} from 'wcstripe/express-checkout/utils';
import { transformPriceWithMinorUnits } from 'wcstripe/express-checkout/transformers/wc-to-stripe';

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
		amount: transformPriceWithMinorUnits(
			billing.cartTotal.value,
			billing.currency.minorUnit
		),
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
