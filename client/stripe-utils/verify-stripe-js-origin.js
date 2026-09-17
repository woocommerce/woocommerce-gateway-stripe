import { __ } from '@wordpress/i18n';

/**
 * The only origin Stripe.js is allowed to be served from.
 *
 * @type {string}
 */
export const STRIPE_JS_ORIGIN = 'https://js.stripe.com';

/**
 * Verifies that the page's Stripe.js was served from the official Stripe origin.
 *
 * Only the origin is compared, so Stripe.js path/release-train changes do not
 * false-positive. The `script#stripe-js` handle (WordPress's id for the
 * `stripe` script) is authoritative: a repointed or src-stripped handle must
 * fail closed rather than fall back to another tag. Selecting `script#stripe-js`
 * (not `#stripe-js`) keeps the real handle authoritative even when a non-script
 * element shares the id, which would otherwise divert to the fallback and let an
 * earlier official-origin script mask the handle. Only when no script handle
 * exists do we fall back to any script from the official origin.
 *
 * @param {Document} [doc] The document to inspect. Defaults to the global document.
 * @return {Object} Result with `ok` (boolean), `detectedSrc` (string|null), and `detectedOrigin` (string|null).
 */
export const verifyStripeJsOrigin = ( doc = document ) => {
	const handleTag = doc.querySelector( 'script#stripe-js' );
	const tag =
		handleTag ??
		doc.querySelector( `script[src^="${ STRIPE_JS_ORIGIN }/"]` );

	if ( ! tag || ! tag.src ) {
		return { ok: false, detectedSrc: null, detectedOrigin: null };
	}

	try {
		const origin = new URL( tag.src ).origin;
		return {
			ok: origin === STRIPE_JS_ORIGIN,
			detectedSrc: tag.src,
			detectedOrigin: origin,
		};
	} catch ( error ) {
		return { ok: false, detectedSrc: tag.src, detectedOrigin: null };
	}
};

/**
 * Asserts Stripe.js came from the official origin, throwing to block payment
 * otherwise (fail closed) — defense-in-depth against skimmers that swap the
 * script for a look-alike. The thrown message stays generic because checkout
 * error handling may render it to shoppers; full diagnostics go to the console.
 *
 * @param {Document} [doc] The document to inspect. Defaults to the global document.
 * @throws {Error} If Stripe.js was not loaded from the official origin.
 */
export const assertStripeJsOrigin = ( doc = document ) => {
	const result = verifyStripeJsOrigin( doc );

	if ( result.ok ) {
		return;
	}

	const reason =
		result.detectedSrc === null
			? 'no Stripe.js script tag was found'
			: `Stripe.js was loaded from an unexpected source (${ result.detectedSrc })`;

	// eslint-disable-next-line no-console
	console.warn(
		`WooCommerce Stripe Gateway: blocking checkout — ${ reason }. Expected Stripe.js from ${ STRIPE_JS_ORIGIN }.`
	);
	throw new Error(
		__( 'Stripe.js provenance check failed.', 'woocommerce-gateway-stripe' )
	);
};
