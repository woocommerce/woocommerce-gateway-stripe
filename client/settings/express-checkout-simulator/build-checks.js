import { getSetting } from '@woocommerce/settings';
import getReasonText from './get-reason-text';
import { __, sprintf } from '@wordpress/i18n';
import { PAYMENT_METHOD_UNAVAILABLE_REASONS } from 'wcstripe/stripe-utils/constants';
import { getExpressCheckoutLocationDefinitions } from 'wcstripe/settings/express-checkout-customize/locations';

/**
 * Status values for a simulator eligibility check.
 *
 * - `pass`/`fail` render a ✓/✗ icon. A `fail` whose check carries `blockingText` hides the
 *   button at every location and supplies the reason shown in the placement table.
 * - `info` is purely informational (e.g. test/live mode) and never blocks placement.
 */
export const STATUS = {
	PASS: 'pass',
	FAIL: 'fail',
	INFO: 'info',
};

/**
 * Maps the location keys a tab supports to `{ key, label, enabled }` rows for the simulator, where
 * `enabled` reflects the tab's current (reactive) location toggles. Labels come from the shared
 * location definitions so the simulator and the location checkboxes stay in sync.
 *
 * @param {string[]} keys             Location keys this tab supports, in display order.
 * @param {string[]} enabledLocations The currently enabled location keys.
 * @return {Array<Object>} Location rows for `<ExpressCheckoutSimulator locations />`.
 */
export const buildLocations = ( keys, enabledLocations ) => {
	const labels = Object.fromEntries(
		getExpressCheckoutLocationDefinitions().map( ( location ) => [
			location.key,
			location.label,
		] )
	);
	// Drop keys without a shared definition rather than render an unlabeled row.
	return keys
		.filter( ( key ) => Boolean( labels[ key ] ) )
		.map( ( key ) => ( {
			key,
			label: labels[ key ],
			enabled: enabledLocations.includes( key ),
		} ) );
};

/**
 * Builds the eligibility checks shared by all three customize tabs (Express Checkout, Amazon
 * Pay, Link). These mirror the configuration-level gates in
 * `WC_Stripe_Express_Checkout_Helper::compute_should_show_express_checkout_button()`; values
 * come from the page's localized params plus the reactive method-enabled state. Per-tab checks
 * (currency, account country, tax setup, card method) are appended by the caller.
 *
 * @param {Object}  args
 * @param {Object}  args.params        The page's localized params (`*_settings_params`).
 * @param {boolean} args.methodEnabled Whether the method is currently enabled (reactive).
 * @param {string}  args.methodLabel   Human label for the method.
 * @return {Array<Object>} Ordered check descriptors. Order is the blocking precedence.
 */
export const buildBaseChecks = ( { params, methodEnabled, methodLabel } ) => {
	const isConnected = Boolean( params?.is_account_connected );
	const isHttps = Boolean( params?.is_https );
	const isTestMode = Boolean( params?.is_test_mode );

	// SSL is only enforced in live mode, so a missing certificate in test mode is informational
	// rather than a blocker — matching the gateway's own gate.
	let httpsStatus = STATUS.PASS;
	if ( ! isHttps ) {
		httpsStatus = isTestMode ? STATUS.INFO : STATUS.FAIL;
	}

	return [
		{
			key: 'account-connected',
			label: __(
				'Stripe account connected',
				'woocommerce-gateway-stripe'
			),
			status: isConnected ? STATUS.PASS : STATUS.FAIL,
			detail: isConnected
				? ''
				: __(
						'Connect your Stripe account to accept payments.',
						'woocommerce-gateway-stripe'
				  ),
			blockingText: __(
				'Stripe account is not connected.',
				'woocommerce-gateway-stripe'
			),
		},
		{
			key: 'method-enabled',
			label: sprintf(
				/* translators: %1$s: payment method name. */
				__( '%1$s enabled', 'woocommerce-gateway-stripe' ),
				methodLabel
			),
			status: methodEnabled ? STATUS.PASS : STATUS.FAIL,
			detail: '',
			blockingText: sprintf(
				/* translators: %1$s: payment method name. */
				__( "%1$s isn't enabled.", 'woocommerce-gateway-stripe' ),
				methodLabel
			),
		},
		{
			key: 'https',
			label: __( 'HTTPS', 'woocommerce-gateway-stripe' ),
			status: httpsStatus,
			detail: isTestMode
				? __(
						'Not required in test mode.',
						'woocommerce-gateway-stripe'
				  )
				: __( 'Required in live mode.', 'woocommerce-gateway-stripe' ),
			blockingText: __(
				'The site is not served over HTTPS, which is required in live mode.',
				'woocommerce-gateway-stripe'
			),
		},
		{
			key: 'mode',
			label: __( 'Mode', 'woocommerce-gateway-stripe' ),
			status: STATUS.INFO,
			detail: isTestMode
				? __( 'Test', 'woocommerce-gateway-stripe' )
				: __( 'Live', 'woocommerce-gateway-stripe' ),
		},
	];
};

/**
 * Builds a store-currency check for methods that restrict the currency (e.g. Amazon Pay).
 * Returns null for methods that support all currencies so the row isn't shown where it would
 * always pass. The caller passes the currency list from its page's localized params, so the
 * server-side per-account list stays the single source of truth.
 *
 * @param {Object}   args
 * @param {string[]} args.currencies  Currencies the method supports for the connected account.
 * @param {string}   args.methodLabel Human label for the method.
 * @return {Object|null} A check descriptor, or null when the method supports all currencies.
 */
export const buildCurrencyCheck = ( { currencies = [], methodLabel } ) => {
	// An empty list means the method supports all currencies, so there is nothing to gate on.
	if ( currencies.length === 0 ) {
		return null;
	}

	const storeCurrency = getSetting( 'currency' )?.code;
	const supported = Boolean(
		storeCurrency && currencies.includes( storeCurrency )
	);

	return {
		key: 'currency',
		label: __( 'Store currency supported', 'woocommerce-gateway-stripe' ),
		status: supported ? STATUS.PASS : STATUS.FAIL,
		detail: sprintf(
			/* translators: %1$s: comma-separated currency codes. */
			__( 'Supported: %1$s', 'woocommerce-gateway-stripe' ),
			currencies.join( ', ' )
		),
		blockingText: getReasonText(
			PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY,
			methodLabel,
			currencies
		),
	};
};

/**
 * Builds the "card payment method enabled" check required by Apple Pay / Google Pay and Link,
 * which can only render when the card method is also enabled.
 *
 * @param {Object}  args
 * @param {boolean} args.isCardEnabled Whether the card method is enabled (reactive).
 * @param {string}  args.methodLabel   Human label for the method.
 * @return {Object} A check descriptor.
 */
export const buildCardMethodCheck = ( { isCardEnabled, methodLabel } ) => ( {
	key: 'card-method',
	label: __( 'Card payment method enabled', 'woocommerce-gateway-stripe' ),
	status: isCardEnabled ? STATUS.PASS : STATUS.FAIL,
	detail: '',
	blockingText: getReasonText(
		PAYMENT_METHOD_UNAVAILABLE_REASONS.REQUIRES_CARD_METHOD,
		methodLabel
	),
} );
