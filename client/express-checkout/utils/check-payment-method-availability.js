import { Component } from 'react';
import { createRoot } from 'react-dom/client';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
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

const availabilityProbes = new Map();

const getPaymentMethodsToProbe = ( paymentMethod ) => {
	const config = getBlocksConfiguration();
	const enabledMethods = {
		[ EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY ]: config.isApplePayEnabled,
		[ EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY ]:
			config.isGooglePayEnabled,
		[ EXPRESS_PAYMENT_METHOD_SETTING_LINK ]: config.isLinkEnabled,
		[ EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY ]:
			config.isAmazonPayEnabled,
	};

	// Amazon Pay uses a different payment method type and payment method
	// creation flow, so it cannot share the card wallets' Elements instance.
	const isAmazonPay =
		paymentMethod === EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY;
	return Object.keys( enabledMethods ).filter(
		( method ) =>
			enabledMethods[ method ] &&
			( method === EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY ) ===
				isAmazonPay
	);
};

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

const probePaymentMethods = ( api, elementsOptions, paymentMethods ) =>
	new Promise( ( resolve ) => {
		const containerEl = document.createElement( 'div' );
		containerEl.style.display = 'none';
		document.body.appendChild( containerEl );
		let root;
		let settled = false;

		const settleProbe = ( availablePaymentMethods ) => {
			if ( settled ) {
				return;
			}
			settled = true;
			resolve( availablePaymentMethods );

			// React can report readiness or a render error during its commit
			// phase, when synchronously unmounting another root is unsafe.
			setTimeout( () => {
				root?.unmount();
				containerEl.remove();
			} );
		};
		const failProbe = () => settleProbe( null );

		try {
			root = createRoot( containerEl );
			root.render(
				<ProbeErrorBoundary onError={ failProbe }>
					<Elements
						stripe={ api.loadStripe() }
						options={ elementsOptions }
					>
						<ExpressCheckoutElement
							onLoadError={ failProbe }
							options={ { paymentMethods } }
							onReady={ ( event ) =>
								settleProbe(
									event.availablePaymentMethods ?? {}
								)
							}
						/>
					</Elements>
				</ProbeErrorBoundary>
			);
		} catch {
			failProbe();
		}
	} );

/**
 * Checks whether a given express payment method is available in the current context.
 *
 * Compatible wallets share a probe for the same API instance and Elements
 * options. Failed probes are not cached, so a later call retries.
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

	const methods = getPaymentMethodsToProbe( paymentMethod );
	if ( ! methods.includes( paymentMethod ) ) {
		return Promise.resolve( false );
	}

	const hasFreeTrial = getExpressCheckoutData( 'has_free_trial' );
	const elementsOptions = {
		mode: hasFreeTrial ? 'subscription' : 'payment',
		...( isManualPaymentMethodCreation( paymentMethod, hasFreeTrial ) && {
			paymentMethodCreation: 'manual',
		} ),
		amount: Number(
			transformPriceWithMinorUnits(
				cart.cartTotals.total_price,
				cart.cartTotals.currency_minor_unit
			)
		),
		currency: cart.cartTotals.currency_code.toLowerCase(),
		paymentMethodTypes: [
			...new Set(
				methods.flatMap( getPaymentMethodTypesForExpressMethod )
			),
		],
	};
	const paymentMethods = {
		amazonPay: methods.includes( EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY )
			? 'auto'
			: 'never',
		applePay: methods.includes( EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY )
			? 'always'
			: 'never',
		googlePay: methods.includes( EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY )
			? 'always'
			: 'never',
		link: methods.includes( EXPRESS_PAYMENT_METHOD_SETTING_LINK )
			? 'auto'
			: 'never',
		paypal: 'never',
	};

	if ( ! availabilityProbes.has( api ) ) {
		availabilityProbes.set( api, new Map() );
	}
	const probes = availabilityProbes.get( api );
	const key = JSON.stringify( [ elementsOptions, paymentMethods ] );
	if ( ! probes.has( key ) ) {
		const probe = probePaymentMethods(
			api,
			elementsOptions,
			paymentMethods
		).then( ( availablePaymentMethods ) => {
			// An error says nothing about wallet availability. An empty ready
			// result, however, is a successful probe and can be reused.
			if ( availablePaymentMethods === null ) {
				probes.delete( key );
			}
			return availablePaymentMethods;
		} );
		probes.set( key, probe );
	}

	return probes
		.get( key )
		.then( ( available ) => Boolean( available?.[ paymentMethod ] ) );
};
