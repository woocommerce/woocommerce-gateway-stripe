/* global Stripe */
import { assertStripeJsOrigin } from './verify-stripe-js-origin';

/**
 * Where the registry is parked on `window`.
 *
 * Page scoped rather than module scoped because `upe-classic`, `upe-blocks` and
 * `express-checkout` are separate webpack entries, so a module-level cache would
 * give each bundle its own Stripe controller.
 *
 * @type {string}
 */
export const REGISTRY_KEY = '__wcStripeSharedStripeInstances';

/**
 * Serializes with object keys sorted, so callers that assemble equivalent
 * options in a different order still resolve to the same entry.
 *
 * @param {*} value The value to serialize.
 * @return {string} The stable serialization.
 */
const stableStringify = ( value ) =>
	JSON.stringify( value, ( _key, val ) =>
		val && typeof val === 'object' && ! Array.isArray( val )
			? Object.keys( val )
					.sort()
					.reduce( ( sorted, name ) => {
						sorted[ name ] = val[ name ];
						return sorted;
					}, {} )
			: val
	);

/**
 * Returns the registry entry for a key/options pair, creating an empty one on
 * first use.
 *
 * @param {string} key     The Stripe publishable API key.
 * @param {Object} options The options passed to the Stripe constructor.
 * @return {{instance: Object|null, promise: Promise<Object>|null}} The entry.
 */
const getEntry = ( key, options ) => {
	const existing = window[ REGISTRY_KEY ];

	// Duck-typed rather than `instanceof Map`: babel gives each bundle its own
	// Map polyfill, and a false negative here would silently split the cache.
	if (
		typeof existing?.get !== 'function' ||
		typeof existing?.set !== 'function'
	) {
		window[ REGISTRY_KEY ] = new Map();
	}

	const registry = window[ REGISTRY_KEY ];
	const cacheKey = `${ key }|${ stableStringify( options ) }`;
	let entry = registry.get( cacheKey );

	if ( ! entry ) {
		entry = { instance: null, promise: null };
		registry.set( cacheKey, entry );
	}

	return entry;
};

/**
 * Returns the Stripe instance shared by every WooCommerce Stripe bundle on the
 * page, constructing it from the global `Stripe` on first use.
 *
 * Instances are only shared when the constructor arguments match, so a differing
 * key, locale or developer tools setting still gets its own controller.
 *
 * @param {string} key     The Stripe publishable API key.
 * @param {Object} options The options to pass to the Stripe constructor.
 * @throws {Error} If Stripe.js was not loaded from the official origin.
 * @return {Object} The Stripe instance.
 */
export const getSharedStripeInstance = ( key, options ) => {
	const entry = getEntry( key, options );

	if ( ! entry.instance ) {
		// Asserted before anything is stored, so a provenance failure keeps
		// throwing instead of being remembered as a resolved entry. Not
		// re-asserted for a cached instance: that one was already checked, and
		// a per-call assertion would break checkout if the tag is removed later.
		assertStripeJsOrigin();

		entry.instance = new Stripe( key, options );
		entry.promise = Promise.resolve( entry.instance );
	}

	return entry.instance;
};

/**
 * Promise-returning counterpart to {@see getSharedStripeInstance}, for callers
 * that hand Stripe to `@stripe/react-stripe-js`.
 *
 * The loader is injected rather than imported so `@stripe/stripe-js` stays out
 * of the bundles that never need it.
 *
 * @param {string}   key          The Stripe publishable API key.
 * @param {Object}   options      The options to pass to the Stripe constructor.
 * @param {Function} loadStripeJs `loadStripe` from `@stripe/stripe-js`.
 * @throws {Error} If Stripe.js was not loaded from the official origin.
 * @return {Promise<Object>} Promise resolving with the Stripe instance.
 */
export const loadSharedStripeInstance = ( key, options, loadStripeJs ) => {
	const entry = getEntry( key, options );

	if ( entry.promise ) {
		return entry.promise;
	}

	// The loader is only needed when a "defer render-blocking JS" optimizer has
	// pushed Stripe.js past our own bundle: the tag is on the page, so the
	// provenance assertion passes, but `Stripe` isn't defined yet.
	if ( typeof window.Stripe === 'function' ) {
		return Promise.resolve( getSharedStripeInstance( key, options ) );
	}

	assertStripeJsOrigin();

	// Stripe.js can finish executing mid-load, letting a synchronous caller
	// construct the instance first. Both settlers defer to whatever landed on
	// the entry meanwhile, so the two paths can't hand out different controllers.
	const pending = loadStripeJs( key, options )
		.then( ( loaded ) => {
			if ( ! entry.instance ) {
				entry.instance = loaded;
			}

			return entry.instance;
		} )
		.catch( ( error ) => {
			if ( entry.promise === pending ) {
				entry.promise = null;
			}

			throw error;
		} );

	entry.promise = pending;

	return pending;
};
