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
const PROBE_TIMEOUT_MS = 15000;
const MAX_CACHED_AMAZON_PAY_PROBES = 20;

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

	// Amazon Pay needs its own method type and creation flow, so use a separate probe.
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
			clearTimeout( timeout );
			resolve( availablePaymentMethods );

			// Defer root cleanup because React may emit readiness or errors during commit.
			setTimeout( () => {
				root?.unmount();
				containerEl.remove();
			} );
		};
		const failProbe = () => settleProbe( null );
		// A missing SDK event must not keep every compatible wallet waiting forever.
		const timeout = setTimeout( failProbe, PROBE_TIMEOUT_MS );

		try {
			root = createRoot( containerEl );
			// Release failed loader probes so later wallet checks can retry.
			const stripe = Promise.resolve( api.loadStripe() ).then(
				( loaded ) => {
					if ( ! loaded || loaded.error ) {
						failProbe();
						return null;
					}
					return loaded;
				},
				() => {
					failProbe();
					return null;
				}
			);
			root.render(
				<ProbeErrorBoundary onError={ failProbe }>
					<Elements stripe={ stripe } options={ elementsOptions }>
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
 * Compatible wallets share a probe for the same API instance, currency, mode,
 * and wallet configuration. Failed probes are not cached, so a later call retries.
 *
 * @param {string} paymentMethod The express payment method identifier (e.g. 'googlePay', 'applePay').
 * @param {Object} api           The WCStripeAPI instance used to load Stripe.
 * @param {Object} cart          The WooCommerce cart object containing totals and currency info.
 * @return {Promise<boolean>} Promise that resolves to true if the payment method is available, false otherwise.
 */
export const checkPaymentMethodIsAvailable = async (
	paymentMethod,
	api,
	cart
) => {
	// Before the cart hydrates, Blocks passes seeded totals with an empty
	// currency_code, which makes Stripe's elements() throw. Resolve false
	// without caching so the probe runs once the cart hydrates.
	if ( ! cart?.cartTotals?.currency_code ) {
		return Promise.resolve( false );
	}
	if (
		! getPaymentMethodsToProbe( paymentMethod ).includes( paymentMethod )
	) {
		return false;
	}

	return checkAvailabilityWithTotals(
		paymentMethod,
		api,
		Number(
			transformPriceWithMinorUnits(
				cart.cartTotals.total_price,
				cart.cartTotals.currency_minor_unit
			)
		),
		cart.cartTotals.currency_code
	);
};

const checkAvailabilityWithTotals = async (
	paymentMethod,
	api,
	amount,
	currency
) => {
	const methods = getPaymentMethodsToProbe( paymentMethod );
	if ( ! methods.includes( paymentMethod ) ) {
		return Promise.resolve( false );
	}

	const isAmazonPay =
		paymentMethod === EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY;
	const hasFreeTrial = getExpressCheckoutData( 'has_free_trial' );
	const elementsOptions = {
		mode: hasFreeTrial ? 'subscription' : 'payment',
		...( isManualPaymentMethodCreation( paymentMethod, hasFreeTrial ) && {
			paymentMethodCreation: 'manual',
		} ),
		amount,
		currency: currency.toLowerCase(),
		// Let Stripe discover card wallets without explicitly requiring Link.
		...( isAmazonPay && {
			paymentMethodTypes:
				getPaymentMethodTypesForExpressMethod( paymentMethod ),
		} ),
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
		klarna: 'never',
	};

	if ( ! availabilityProbes.has( api ) ) {
		availabilityProbes.set( api, {
			card: new Map(),
			amazonPay: new Map(),
		} );
	}
	const probes =
		availabilityProbes.get( api )[ isAmazonPay ? 'amazonPay' : 'card' ];
	const cacheOptions = { ...elementsOptions };
	// Cart total changes do not affect card wallet availability, so leave it out of the key.
	if ( ! isAmazonPay ) {
		delete cacheOptions.amount;
	}
	const key = JSON.stringify( [ cacheOptions, paymentMethods ] );
	if ( ! probes.has( key ) ) {
		const probe = probePaymentMethods(
			api,
			elementsOptions,
			paymentMethods
		).then( ( availablePaymentMethods ) => {
			// Errors are not cached, so a later call retries. An empty result is cached.
			if (
				availablePaymentMethods === null &&
				probes.get( key ) === probe
			) {
				probes.delete( key );
			}
			return availablePaymentMethods;
		} );
		probes.set( key, probe );
		// Amount-sensitive Amazon results must not grow forever or evict card discovery.
		if ( isAmazonPay && probes.size > MAX_CACHED_AMAZON_PAY_PROBES ) {
			probes.delete( probes.keys().next().value );
		}
	}

	return probes
		.get( key )
		.then( ( available ) => Boolean( available?.[ paymentMethod ] ) );
};

/**
 * Start card-wallet discovery while Blocks prepares the live cart. The server
 * amount is already in Stripe minor units; live checks still apply their guards
 * and use a different cache entry if currency, mode or enabled wallets change.
 *
 * @param {Object} api The WCStripeAPI instance used by the registered wallets.
 * @return {Promise<boolean>|undefined} The first enabled wallet's optional availability result.
 */
export const preloadPaymentMethodAvailability = ( api ) => {
	let config;
	try {
		config = getBlocksConfiguration();
	} catch {
		return;
	}

	if (
		config?.isAdmin ||
		! config?.shouldShowExpressCheckoutButton ||
		typeof document === 'undefined' ||
		! document.body ||
		typeof config.currency !== 'string' ||
		! /^[a-z]{3}$/i.test( config.currency ) ||
		! [ 'number', 'string' ].includes( typeof config.cartTotal ) ||
		config.cartTotal === ''
	) {
		return;
	}

	const amount = Number( config.cartTotal );
	if (
		! Number.isSafeInteger( amount ) ||
		amount < 0 ||
		( amount === 0 && ! getExpressCheckoutData( 'has_free_trial' ) )
	) {
		return;
	}

	// Amazon Pay depends on the live amount and must keep its separate probe.
	const [ method ] = getPaymentMethodsToProbe(
		EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY
	);
	if ( ! method ) {
		return;
	}

	return checkAvailabilityWithTotals(
		method,
		api,
		amount,
		config.currency
	).catch( () => false );
};
