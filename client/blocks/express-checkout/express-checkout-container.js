import React from 'react';
import { Elements } from '@stripe/react-stripe-js';
import ExpressCheckoutComponent from './express-checkout-component';
import { useMemo } from '@wordpress/element';
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
	const amount = transformPriceWithMinorUnits(
		billing.cartTotal.value,
		billing.currency.minorUnit
	);
	const currency = billing.currency.code.toLowerCase();

	// WooCommerce Blocks re-renders this subtree on every cart/totals tick. Memoise
	// on the values Stripe actually consumes so <Elements> only sees a new options
	// reference (and runs elements.update) when the amount/currency truly change —
	// keeping the live amount accurate without churning on unrelated store updates.
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
