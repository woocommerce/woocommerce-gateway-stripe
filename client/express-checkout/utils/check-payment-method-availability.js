import { Component } from 'react';
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

/**
 * React error boundaries must be class components. This one lets a failure
 * while mounting the probe (e.g. Stripe rejecting the Elements options) be
 * reported to the caller instead of becoming an uncaught render error.
 */
class ProbeErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { hasError: false };
	}

	static getDerivedStateFromError() {
		return { hasError: true };
	}

	componentDidCatch() {
		this.props.onError();
	}

	render() {
		return this.state.hasError ? null : this.props.children;
	}
}

/**
 * Renders an invisible Stripe Express Checkout Element and waits for its ready
 * event to learn whether the given express payment method is available.
 *
 * @param {string} paymentMethod The express payment method identifier (e.g. 'googlePay', 'applePay').
 * @param {Object} api           The WCStripeAPI instance used to load Stripe.
 * @param {Object} cart          The WooCommerce cart object containing totals and currency info.
 * @return {Promise<boolean>} Promise that resolves to true if the payment method is available, false otherwise.
 */
const checkPaymentMethodAvailability = memoize(
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

			// A failed probe says nothing about the method's real availability,
			// so evict the memoized entry to let a later call retry. Unmounting
			// is deferred because React forbids unmounting a root from within
			// its own lifecycle (the error boundary's commit phase).
			const failProbe = () => {
				checkPaymentMethodAvailability.cache.delete( paymentMethod );
				resolve( false );
				setTimeout( () => {
					root.unmount();
					containerEl.remove();
				} );
			};

			root.render(
				<ProbeErrorBoundary onError={ failProbe }>
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
							currency:
								cart.cartTotals.currency_code.toLowerCase(),
							paymentMethodTypes:
								getPaymentMethodTypesForExpressMethod(
									paymentMethod
								),
						} }
					>
						<ExpressCheckoutElement
							onLoadError={ failProbe }
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
				</ProbeErrorBoundary>
			);
		} );
	}
);

/**
 * Checks whether a given express payment method is available in the current context.
 *
 * Results are memoized so the underlying probe runs only once per payment
 * method. Failed probes are not cached, so a later call retries.
 *
 * @param {string} paymentMethod The express payment method identifier (e.g. 'googlePay', 'applePay').
 * @param {Object} api           The WCStripeAPI instance used to load Stripe.
 * @param {Object} cart          The WooCommerce cart object containing totals and currency info.
 * @return {Promise<boolean>} Promise that resolves to true if the payment method is available, false otherwise.
 */
export const checkPaymentMethodIsAvailable = ( paymentMethod, api, cart ) => {
	// Before the cart hydrates, Blocks passes seeded totals with an empty
	// currency_code, which makes Stripe's elements() throw. Resolve false
	// without caching so the probe runs once the cart hydrates.
	if ( ! cart?.cartTotals?.currency_code ) {
		return Promise.resolve( false );
	}

	return checkPaymentMethodAvailability( paymentMethod, api, cart );
};
