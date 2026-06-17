/**
 * The only origin Stripe.js is allowed to be served from.
 *
 * @type {string}
 */
export const STRIPE_JS_ORIGIN = 'https://js.stripe.com';

/**
 * Inspects the Stripe.js script tag on the page and verifies that it was
 * loaded from the official Stripe origin.
 *
 * Only the origin is compared (not the full URL), so Stripe.js path changes
 * (`/v3/`, `/clover/stripe.js`, named release trains) do not cause mismatches.
 *
 * The `#stripe-js` id is the fingerprint WordPress renders for the `stripe`
 * script handle, so a repointed handle is read by id regardless of its
 * current src. It is looked up explicitly (not via a selector list) so it
 * always takes precedence: `querySelector` on a comma-separated selector
 * returns the first match in document order, so a legitimate js.stripe.com
 * tag inserted earlier in the DOM could otherwise mask a repointed handle.
 * The handle match is only honored when it is actually a `<script>` with a
 * src — an unrelated element that happens to share the `stripe-js` id must not
 * fail the check closed while a legitimate script is present. Only when no
 * usable handle tag exists do we fall back to any script served from the
 * official origin.
 *
 * @param {Document} [doc] The document to inspect. Defaults to the global document.
 * @return {Object} Result object with `ok` (boolean), `detectedSrc` (string|null), and `detectedOrigin` (string|null).
 */
export const verifyStripeJsOrigin = ( doc = document ) => {
	const handleTag = doc.querySelector( '#stripe-js' );
	const tag =
		handleTag?.tagName === 'SCRIPT' && handleTag.src
			? handleTag
			: doc.querySelector( 'script[src^="https://js.stripe.com/"]' );

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
 * Asserts that Stripe.js was loaded from the official Stripe origin,
 * blocking payment processing otherwise (fail closed).
 *
 * This is a defense-in-depth measure against checkout skimmers that swap the
 * Stripe.js script for a look-alike hosted on an attacker-controlled origin.
 *
 * The full diagnostics (including the detected src) go to the console only;
 * the thrown message may be rendered to shoppers by existing checkout error
 * handling, so it stays generic.
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
			: `Stripe.js was loaded from an unexpected origin (${ result.detectedSrc })`;

	// eslint-disable-next-line no-console
	console.warn(
		`WooCommerce Stripe Gateway: blocking checkout — ${ reason }. Expected Stripe.js from ${ STRIPE_JS_ORIGIN }.`
	);
	throw new Error( 'Stripe.js provenance check failed.' );
};
