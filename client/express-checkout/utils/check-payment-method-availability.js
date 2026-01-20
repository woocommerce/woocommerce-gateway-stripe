import { createRoot } from 'react-dom/client';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';
import { memoize } from 'lodash';
import {
	getExpressCheckoutData,
	getPaymentMethodTypesForExpressMethod,
	isManualPaymentMethodCreation,
} from 'wcstripe/express-checkout/utils';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_LINK,
} from 'wcstripe/stripe-utils/constants';
import { transformPriceWithMinorUnits } from 'wcstripe/express-checkout/transformers/wc-to-stripe';

export const checkPaymentMethodIsAvailable = memoize(
	( paymentMethod, api, cart ) => {
		return new Promise( ( resolve ) => {
			const hasFreeTrial = getExpressCheckoutData( 'has_free_trial' );

			// Create the DIV container on the fly
			const containerEl = document.createElement( 'div' );

			// Ensure the element is hidden and doesn’t interfere with the page layout.
			containerEl.style.display = 'none';

			document.querySelector( 'body' ).appendChild( containerEl );

			const root = createRoot( containerEl );

			const amount = transformPriceWithMinorUnits(
				cart.cartTotals.total_price,
				cart.cartTotals.currency_minor_unit
			);

			root.render(
				<Elements
					stripe={ api.loadStripe() }
					options={ {
						mode: hasFreeTrial ? 'subscription' : 'payment',
						...( isManualPaymentMethodCreation(
							paymentMethod,
							hasFreeTrial
						) && {
							paymentMethodCreation: 'manual',
						} ),
						amount: Number( amount ),
						currency: cart.cartTotals.currency_code.toLowerCase(),
						paymentMethodTypes:
							getPaymentMethodTypesForExpressMethod(
								paymentMethod
							),
					} }
				>
					<ExpressCheckoutElement
						onLoadError={ () => resolve( false ) }
						options={ {
							paymentMethods: {
								amazonPay:
									paymentMethod ===
									EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY
										? 'auto'
										: 'never',
								applePay:
									paymentMethod ===
									EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY
										? 'always'
										: 'never',
								googlePay:
									paymentMethod ===
									EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY
										? 'always'
										: 'never',
								link:
									paymentMethod ===
									EXPRESS_PAYMENT_METHOD_SETTING_LINK
										? 'auto'
										: 'never',
								paypal: 'never',
							},
						} }
						onReady={ ( event ) => {
							let canMakePayment = false;
							if ( event.availablePaymentMethods ) {
								canMakePayment =
									event.availablePaymentMethods[
										paymentMethod
									];
							}
							resolve( canMakePayment );
							root.unmount();
							containerEl.remove();
						} }
					/>
				</Elements>
			);
		} );
	}
);
