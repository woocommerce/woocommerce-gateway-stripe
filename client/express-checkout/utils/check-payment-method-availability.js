import ReactDOM from 'react-dom';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';
import { memoize } from 'lodash';
import {
	getPaymentMethodTypesForExpressMethod,
	isManualPaymentMethodCreation,
} from 'wcstripe/express-checkout/utils';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_LINK,
} from 'wcstripe/stripe-utils/constants';

export const checkPaymentMethodIsAvailable = memoize(
	( paymentMethod, api, cart, resolve ) => {
		// Create the DIV container on the fly
		const containerEl = document.createElement( 'div' );

		// Ensure the element is hidden and doesn’t interfere with the page layout.
		containerEl.style.display = 'none';

		document.querySelector( 'body' ).appendChild( containerEl );

		const root = ReactDOM.createRoot( containerEl );

		root.render(
			<Elements
				stripe={ api.loadStripe() }
				options={ {
					mode: 'payment',
					...( isManualPaymentMethodCreation( paymentMethod ) && {
						paymentMethodCreation: 'manual',
					} ),
					amount: Number( cart.cartTotals.total_price ),
					currency: cart.cartTotals.currency_code.toLowerCase(),
					paymentMethodTypes: getPaymentMethodTypesForExpressMethod(
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
								event.availablePaymentMethods[ paymentMethod ];
						}
						resolve( canMakePayment );
						root.unmount();
						containerEl.remove();
					} }
				/>
			</Elements>
		);
	}
);
