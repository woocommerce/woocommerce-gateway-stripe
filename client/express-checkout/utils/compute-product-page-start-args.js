/**
 * Builds the args object passed to `startExpressCheckout` on a shortcode
 * product page. Extracted from the entrypoint so the resolver-aware start path
 * is testable without standing up the entire jQuery/Stripe.js shell.
 *
 * Returns `null` when the current variation isn't supported and ECE should
 * skip starting entirely.
 *
 * @param {Object}   deps
 * @param {Function} deps.getExpressCheckoutData         Reads the localized ECE params (`'product'`, `'checkout'`).
 * @param {Function} deps.resolveExpressCheckoutCurrency Threads the resolver chain; called with `(fallback, ctx)`.
 * @param {Function} deps.getResolvedCurrency            Reads the cached resolved currency post-resolve.
 * @param {Function} deps.getSelectedProductData         AJAX call that re-fetches product totals in the resolved currency.
 * @param {Function} deps.transformLabeledDisplayItems   Stripe transformer for non-legacy display items.
 * @param {boolean}  deps.useLegacyDisplayItems          True for variations/bookings; skips the transform.
 * @return {Promise<Object|null>} Args for `startExpressCheckout`, or `null` to skip.
 */
export async function computeProductPageStartArgs( {
	getExpressCheckoutData,
	resolveExpressCheckoutCurrency,
	getResolvedCurrency,
	getSelectedProductData,
	transformLabeledDisplayItems,
	useLegacyDisplayItems,
} ) {
	const isProductSupported =
		getExpressCheckoutData( 'product' )?.validVariationSelected ?? true;
	if ( ! isProductSupported ) {
		return null;
	}

	const localizedCurrency = (
		getExpressCheckoutData( 'product' )?.currency || ''
	).toLowerCase();

	// let any registered resolver (e.g. WCPBC) settle the visitor's currency
	// before we create the Stripe Element. fast-path when no resolver participates.
	await resolveExpressCheckoutCurrency( localizedCurrency, {
		buttonContext: 'product',
	} );

	const resolvedCurrency = getResolvedCurrency( localizedCurrency );
	const hasCurrencyChanged = resolvedCurrency !== localizedCurrency;

	let total = getExpressCheckoutData( 'product' )?.total.amount;
	let displayItems = getExpressCheckoutData( 'product' )?.displayItems ?? [];
	let requestShipping =
		getExpressCheckoutData( 'product' )?.requestShipping ?? false;

	// if the resolver settled on a different currency, the localized product
	// data was rendered against the wrong one. the AJAX call below will now see
	// WCPBC's cookie and return converted values.
	if ( hasCurrencyChanged ) {
		try {
			const fresh = await getSelectedProductData();
			if ( fresh && ! fresh.error ) {
				total = fresh.total.amount;
				displayItems = fresh.displayItems ?? [];
				requestShipping = fresh.requestShipping ?? requestShipping;
			}
		} catch ( e ) {
			// fall back to the localized data. ECE may render in the wrong
			// currency, but it won't fail silently at confirmation.
		}
	}

	return {
		total,
		currency: resolvedCurrency,
		requestShipping,
		requestPhone:
			getExpressCheckoutData( 'checkout' )?.needs_payer_phone ?? false,
		displayItems: useLegacyDisplayItems
			? displayItems
			: transformLabeledDisplayItems( displayItems ),
	};
}
