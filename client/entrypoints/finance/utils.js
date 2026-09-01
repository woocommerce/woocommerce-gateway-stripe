import {
	CARD_BRAND_LABELS,
	PAYMENT_METHOD_LABELS,
	WALLET_LABELS,
} from './constants';

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

/**
 * Humanises an unmapped Stripe payment method type (`us_bank_account` etc.).
 *
 * @param {string} type Stripe payment method type.
 * @return {string} A readable label.
 */
const humanize = ( type ) => String( type ).replace( /_/g, ' ' );

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

/**
 * Builds the display label for a charge's payment method.
 *
 * @param {Object} charge The `latest_charge` object from a payment intent.
 * @return {string} A readable label, or an empty string when unavailable.
 */
export const getPaymentMethodLabel = ( charge ) => {
	const details = charge?.payment_method_details;
	const type = details?.type;

	if ( ! type ) {
		return '';
	}

	if ( type === 'card' && details.card ) {
		const { brand, last4, wallet } = details.card;

		// The wallet is what the shopper actually chose, so it takes precedence
		// over the underlying card brand.
		const name =
			WALLET_LABELS[ wallet?.type ] ??
			CARD_BRAND_LABELS[ brand ] ??
			( brand ? humanize( brand ) : PAYMENT_METHOD_LABELS.card );

		return last4 ? `${ name } •••• ${ last4 }` : name;
	}

	return PAYMENT_METHOD_LABELS[ type ] ?? humanize( type );
};

/**
 * Resolves the icon key for a charge, matching client/payment-method-icons keys
 * to Stripe's payment_method_details.type values.
 *
 * @param {Object} charge The `latest_charge` object from a payment intent.
 * @return {string|undefined} The icon key, if any.
 */
export const getPaymentMethodIconKey = ( charge ) =>
	charge?.payment_method_details?.type;
