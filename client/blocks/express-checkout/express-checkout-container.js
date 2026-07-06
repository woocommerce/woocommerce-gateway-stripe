import React from 'react';
import { Elements } from '@stripe/react-stripe-js';
import ExpressCheckoutComponent from './express-checkout-component';
import { useEffect, useMemo } from '@wordpress/element';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
	getPaymentMethodTypesForExpressMethod,
	isManualPaymentMethodCreation,
} from 'wcstripe/express-checkout/utils';
import { setElementCurrency } from 'wcstripe/express-checkout/utils/element-currency-cache';
import { transformPriceWithMinorUnits } from 'wcstripe/express-checkout/transformers/wc-to-stripe';

export const ExpressCheckoutContainer = ( props ) => {
	const { stripe, billing, expressPaymentMethod } = props;
	const hasFreeTrial = getExpressCheckoutData( 'has_free_trial' );
	const amount = transformPriceWithMinorUnits(
		billing.cartTotal.value,
		billing.currency.minorUnit
	);
	const currency = billing.currency.code.toLowerCase();

	// Record the currency the Element was created with only after commit, so the
	// server-side mismatch guard reads the currency that's actually on screen.
	useEffect( () => {
		setElementCurrency( currency );
	}, [ currency ] );

	// Memoise on the values Stripe consumes so <Elements> only updates when the
	// amount/currency change, not on every cart tick.
	const options = useMemo(
		() => ( {
			mode: hasFreeTrial ? 'subscription' : 'payment',
			...( isManualPaymentMethodCreation(
				expressPaymentMethod,
				hasFreeTrial
			) && {
				paymentMethodCreation: 'manual',
			} ),
			amount,
			currency,
			paymentMethodTypes:
				getPaymentMethodTypesForExpressMethod( expressPaymentMethod ),
			appearance: getExpressCheckoutButtonAppearance(),
			locale: getExpressCheckoutData( 'stripe' )?.locale ?? 'en',
		} ),
		[ amount, currency, expressPaymentMethod, hasFreeTrial ]
	);

	return (
		<div style={ { minHeight: '40px' } }>
			<Elements stripe={ stripe } options={ options }>
				<ExpressCheckoutComponent { ...props } />
			</Elements>
		</div>
	);
};
