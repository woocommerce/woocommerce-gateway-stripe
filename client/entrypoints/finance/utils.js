/**
 * Reads the params localized by WC_Stripe_Finance_UI_Controller.
 *
 * @param {string} key      Param name.
 * @param {*}      fallback Value to use when the param is absent.
 * @return {*} The param value.
 */
const getParam = ( key, fallback ) =>
	window.wc_stripe_payment_details_params?.[ key ] ?? fallback;

/**
 * Stripe's minor-unit exponent is currency-specific and does not match
 * Intl's own `maximumFractionDigits` for every code (HUF and TWD notably
 * differ), so the authoritative lists come from PHP —
 * WC_Stripe_Currency_Code::NO_DECIMAL_CURRENCY_CODES and
 * ::THREE_DECIMAL_CURRENCY_CODES — rather than being duplicated here.
 *
 * @param {string} currency Lowercase or uppercase ISO currency code.
 * @return {number} Number of decimal places for the currency.
 */
export const getCurrencyExponent = ( currency ) => {
	const code = String( currency || '' ).toUpperCase();

	if ( getParam( 'noDecimalCurrencies', [] ).includes( code ) ) {
		return 0;
	}

	if ( getParam( 'threeDecimalCurrencies', [] ).includes( code ) ) {
		return 3;
	}

	return 2;
};

const formatterCache = new Map();

const getFormatter = ( locale, currency, exponent ) => {
	const key = `${ locale }|${ currency }|${ exponent }`;

	if ( ! formatterCache.has( key ) ) {
		formatterCache.set(
			key,
			new Intl.NumberFormat( locale, {
				style: 'currency',
				currency,
				minimumFractionDigits: exponent,
				maximumFractionDigits: exponent,
			} )
		);
	}

	return formatterCache.get( key );
};

/**
 * Formats a Stripe amount for display.
 *
 * Rows can each carry a different currency, so this cannot reuse the
 * store-scoped @woocommerce/currency factory.
 *
 * @param {number} amount   Amount in the currency's minor units.
 * @param {string} currency ISO currency code as returned by Stripe (lowercase).
 * @return {string} Formatted amount, or an empty string when either input is missing.
 */
export const formatStripeAmount = ( amount, currency ) => {
	if ( typeof amount !== 'number' || ! Number.isFinite( amount ) ) {
		return '';
	}

	const code = String( currency || '' ).toUpperCase();

	if ( ! code ) {
		return '';
	}

	const exponent = getCurrencyExponent( code );
	const locale = getParam( 'locale', undefined );

	try {
		return getFormatter( locale, code, exponent ).format(
			amount / 10 ** exponent
		);
	} catch {
		// Intl throws on codes it does not recognise; showing the raw major-unit
		// value beats blanking the cell or taking the table down.
		return `${ ( amount / 10 ** exponent ).toFixed( exponent ) } ${ code }`;
	}
};

export const formatStripeTimestamp = ( timestamp ) => {
	if (
		timestamp &&
		typeof timestamp === 'number' &&
		Number.isFinite( timestamp )
	) {
		return new Date( timestamp * 1000 ).toISOString();
	}

	return '';
};
