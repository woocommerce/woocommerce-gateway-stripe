import CurrencyFactory from '@woocommerce/currency';

// https://docs.stripe.com/currencies#zero-decimal
const ZERO_DECIMAL_CURRENCIES = [
	'bif',
	'clp',
	'djf',
	'gnf',
	'jpy',
	'kmf',
	'krw',
	'mga',
	'pyg',
	'rwf',
	'ugx',
	'vnd',
	'vuv',
	'xaf',
	'xof',
	'xpf',
];

const isZeroDecimal = ( currency ) =>
	ZERO_DECIMAL_CURRENCIES.includes( ( currency || '' ).toLowerCase() );

/**
 * Formats a Stripe minor-unit amount using the site's locale-aware currency formatter.
 *
 * Site-level separator/position settings (from window.wcSettings.currency) are inherited,
 * but the currency code and precision come from the payout itself so that mixed-currency
 * rows display correctly.
 *
 * @param {number} amount   The Stripe amount in minor units (e.g. 1050 for $10.50).
 * @param {string} currency The ISO currency code (Stripe returns lowercase).
 * @return {string} The formatted amount including the currency symbol.
 */
export const formatAmount = ( amount, currency ) => {
	const precision = isZeroDecimal( currency ) ? 0 : 2;
	const major = isZeroDecimal( currency ) ? amount : amount / 100;

	const factory = CurrencyFactory( {
		...( window?.wcSettings?.currency || {} ),
		code: ( currency || '' ).toUpperCase(),
		precision,
	} );

	return factory.formatAmount( major );
};
