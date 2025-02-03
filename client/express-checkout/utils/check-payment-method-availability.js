import ReactDOM from 'react-dom';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';
import { camelCase, memoize } from 'lodash';
import {
	getPaymentMethodTypesForExpressMethod,
	isManualPaymentMethodCreation,
} from 'wcstripe/express-checkout/utils';
import {
	PAYMENT_METHOD_APPLE_PAY,
	PAYMENT_METHOD_GOOGLE_PAY,
	PAYMENT_METHOD_LINK,
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
							amazonPay: 'never',
							applePay:
								paymentMethod ===
								camelCase( PAYMENT_METHOD_APPLE_PAY )
									? 'always'
									: 'never',
							googlePay:
								paymentMethod ===
								camelCase( PAYMENT_METHOD_GOOGLE_PAY )
									? 'always'
									: 'never',
							link:
								paymentMethod === PAYMENT_METHOD_LINK
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
