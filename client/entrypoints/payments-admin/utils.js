import { __, sprintf } from '@wordpress/i18n';

/**
 * Reads the params localized by WC_Stripe_Payments_UI_Controller.
 *
 * @param {string} key      Param name.
 * @param {*}      fallback Value to use when the param is absent.
 * @return {*} The param value.
 */
const getParam = ( key, fallback ) =>
	window.wc_stripe_admin_payments_params?.[ key ] ?? fallback;

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
 * Formats a Stripe timestamp for display.
 *
 * @param {number} timestamp A Unix timestamp in milliseconds.
 * @return {string} A ISO string representation of the timestamp. Returns '' if the timestamp is invalid.
 */
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
 * Gets the default account currency from the localized parameters.
 *
 * @return {string} The default account currency.
 */
export const getDefaultAccountCurrency = () =>
	getParam( 'defaultAccountCurrency', '' );

/**
 * Whether the current user dismissed the Instant Payouts banner.
 *
 * @return {boolean} True when the banner is dismissed.
 */
export const isInstantPayoutsBannerDismissed = () =>
	getParam( 'isInstantPayoutsBannerDismissed', 'no' ) === 'yes';

/**
 * Builds the Instant Payouts availability message from a Stripe balance.
 * Only the default account currency is promoted.
 *
 * @param {?Object} balance The Stripe balance object.
 * @return {?string} The message, or null when no instant payout is available.
 */
export const getInstantPayoutsMessage = ( balance ) => {
	const defaultAccountCurrency = getDefaultAccountCurrency();
	if ( ! defaultAccountCurrency || ! balance?.instant_available?.length ) {
		return null;
	}

	const available = balance.instant_available.find(
		( entry ) => entry?.currency === defaultAccountCurrency
	);
	if ( ! available?.amount || ! Number.isInteger( available.amount ) ) {
		return null;
	}

	return sprintf(
		/* translators: %1$s: The amount of money available for an instant payout. e.g. $123.45, €123.45 */
		__(
			'You currently have %1$s available.',
			'woocommerce-gateway-stripe'
		),
		formatStripeAmount( available.amount, available.currency )
	);
};

/**
 * Gets the Stripe Dashboard payouts URL for the balance's mode.
 * Falls back to the test Dashboard unless the balance is explicitly live.
 *
 * @param {?Object} balance The Stripe balance object.
 * @return {string} The payouts URL.
 */
export const getPayoutsDashboardUrl = ( balance ) =>
	balance?.livemode === true
		? 'https://dashboard.stripe.com/payouts/'
		: 'https://dashboard.stripe.com/test/payouts/';
