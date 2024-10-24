import ReactDOM from 'react-dom';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';
import { memoize } from 'lodash';

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
					paymentMethodCreation: 'manual',
					amount: Number( cart.cartTotals.total_price ),
					currency: cart.cartTotals.currency_code.toLowerCase(),
				} }
			>
				<ExpressCheckoutElement
					onLoadError={ () => resolve( false ) }
					options={ {
						paymentMethods: {
							amazonPay: 'never',
							applePay:
								paymentMethod === 'applePay'
									? 'always'
									: 'never',
							googlePay:
								paymentMethod === 'googlePay'
									? 'always'
									: 'never',
							link: 'never',
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
