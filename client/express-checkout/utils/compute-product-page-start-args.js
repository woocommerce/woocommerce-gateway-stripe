/**
 * Builds the args object passed to `startExpressCheckout` on a shortcode
 * product page. Extracted into a dedicated file to support testing.
 *
 * Returns `null` when the current variation isn't supported and ECE should
 * skip starting entirely.
 *
 * @param {Object}   deps
 * @param {Function} deps.getExpressCheckoutData         Reads the localized ECE params (`'product'`, `'checkout'`).
 * @param {Function} deps.resolveExpressCheckoutCurrency Threads the resolver chain; called with `(fallback, ctx)`, resolves to the currency.
 * @param {Function} deps.getSelectedProductData         AJAX call that re-fetches product totals in the resolved currency.
 * @param {Function} deps.transformLabeledDisplayItems   Stripe transformer for non-legacy display items.
 * @param {boolean}  deps.useLegacyDisplayItems          True for variations/bookings; skips the transform.
 * @return {Promise<Object|null>} Args for `startExpressCheckout`, or `null` to skip.
 */
export async function computeProductPageStartArgs( {
	getExpressCheckoutData,
	resolveExpressCheckoutCurrency,
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

	// Let any registered resolver (e.g. WCPBC) settle the visitor's currency
	// before we create the Stripe Element. Fast-path when no resolver participates.
	const resolvedCurrency = await resolveExpressCheckoutCurrency(
		localizedCurrency,
		{ buttonContext: 'product' }
	);
	const hasCurrencyChanged = resolvedCurrency !== localizedCurrency;

	let total = getExpressCheckoutData( 'product' )?.total?.amount;
	let displayItems = getExpressCheckoutData( 'product' )?.displayItems ?? [];
	let requestShipping =
		getExpressCheckoutData( 'product' )?.requestShipping ?? false;

	// If the resolver settled on a different currency, the localized product
	// data was rendered against the wrong one. The AJAX call below will now see
	// WCPBC's cookie and return converted values.
	if ( hasCurrencyChanged ) {
		let fresh;
		try {
			fresh = await getSelectedProductData();
		} catch ( e ) {
			// The re-fetch failed, so we have no trustworthy amount in the
			// resolved currency. Bail rather than render a base-currency amount
			// under the resolved-currency label; the shopper falls back to the
			// regular checkout.
			return null;
		}

		// Only trust the re-fetched payload when the server actually returned
		// amounts in the resolved currency. An error, a missing total, or a
		// currency mismatch (e.g. the zone wasn't persisted before this call)
		// would mean a base-currency amount shown as the resolved currency, so
		// bail instead of misleading the shopper.
		if (
			! fresh ||
			fresh.error ||
			fresh.currency !== resolvedCurrency ||
			fresh.total?.amount === undefined
		) {
			return null;
		}

		total = fresh.total.amount;
		displayItems = fresh.displayItems ?? [];
		requestShipping = fresh.requestShipping ?? requestShipping;
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
