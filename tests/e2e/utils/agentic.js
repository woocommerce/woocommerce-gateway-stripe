import crypto from 'crypto';
import wcApi from '@woocommerce/woocommerce-rest-api';
import playwrightConfig from '../config/playwright.config';

export const WEBHOOK_PATH = '/?wc-api=wc_stripe';
export const WEBHOOK_SECRET = 'whsec_e2e_agentic';
export const AGENTIC_PRODUCT_SKU = 'E2E-AGENTIC-1';
export const AGENTIC_OOS_PRODUCT_SKU = 'E2E-AGENTIC-OOS';
export const AGENTIC_EXCLUDED_PRODUCT_SKU = 'E2E-AGENTIC-EXCL';
export const AGENTIC_CLI_EXCLUDED_PRODUCT_SKU = 'E2E-AGENTIC-CLI-EXCL';

// Cents, matching the product price seeded in agentic-commerce.setup.js.
export const AGENTIC_PRODUCT_AMOUNT = 2499;

/**
 * Builds a Stripe-Signature header value for a webhook body, matching the
 * scheme verified by WC_Stripe_Webhook_Handler::validate_request().
 *
 * @param {string} body   The raw request body.
 * @param {string} secret The signing secret.
 * @return {string} The Stripe-Signature header value.
 */
export const stripeSignature = ( body, secret ) => {
	const timestamp = Math.floor( Date.now() / 1000 );
	const signature = crypto
		.createHmac( 'sha256', secret )
		.update( `${ timestamp }.${ body }` )
		.digest( 'hex' );

	return `t=${ timestamp },v1=${ signature }`;
};

/**
 * POSTs a delegated-checkout event to the plugin's webhook endpoint.
 *
 * @param {Object} request Playwright APIRequestContext.
 * @param {Object} event   The event payload.
 * @param {string} secret  The signing secret.
 * @return {Promise<Object>} The APIResponse.
 */
export const postAgenticHook = async ( request, event, secret ) => {
	const body = JSON.stringify( event );

	return request.post( WEBHOOK_PATH, {
		data: body,
		headers: {
			'Content-Type': 'application/json',
			'Stripe-Signature': stripeSignature( body, secret ),
		},
	} );
};

/**
 * Returns the id of the Stripe account the store is connected to, or '' when
 * the account data has not been cached yet. The webhook handler drops events
 * whose `context` does not match this id (and passes everything through when
 * it is unknown), so payloads must carry the real value.
 *
 * @return {Promise<string>} The `acct_…` id, or ''.
 */
export const getConnectedAccountId = async () => {
	const response = await agenticApi().get( 'wc_stripe/account' );

	return response.data?.account?.id ?? '';
};

/**
 * Returns the id of the product with the given SKU, or null when absent.
 *
 * @param {string} sku The product SKU.
 * @return {Promise<number|null>} The product id, or null.
 */
export const getProductIdBySku = async ( sku ) => {
	const response = await agenticApi().get( 'products', { sku } );

	return response.data[ 0 ]?.id ?? null;
};

// The API tokens come from global setup, so the client cannot be built at
// module load time.
const agenticApi = () =>
	new wcApi( {
		url: playwrightConfig.use.baseURL,
		consumerKey: process.env.CONSUMER_KEY,
		consumerSecret: process.env.CONSUMER_SECRET,
		version: 'wc/v3',
	} );

/**
 * Builds a v1.delegated_checkout.customize_checkout event. The payload shape
 * mirrors tests/phpunit/dummy-data/agentic_customize_checkout_event.json —
 * keep the two in sync when Stripe's hook payloads change.
 *
 * @param {Object} args
 * @param {string} args.skuId     The line item sku_id (a product SKU, with a
 *                                product-id fallback in the resolver).
 * @param {string} args.accountId The connected Stripe account id.
 * @return {Object} The event payload.
 */
export const customizeCheckoutEvent = ( { skuId, accountId } ) => ( {
	id: 'evt_e2e_agentic_customize',
	type: 'v1.delegated_checkout.customize_checkout',
	livemode: false,
	context: accountId,
	data: {
		checkout_session: 'cs_test_e2e_agentic',
		currency: 'usd',
		automatic_tax: { enabled: false },
		amount_subtotal: AGENTIC_PRODUCT_AMOUNT,
		amount_total: AGENTIC_PRODUCT_AMOUNT,
		total_details: {
			amount_tax: 0,
			amount_discount: 0,
			amount_shipping: 0,
		},
		discounts: [],
		line_item_details: [
			{
				id: 'li_e2e_1',
				sku_id: skuId,
				quantity: 1,
				unit_amount: AGENTIC_PRODUCT_AMOUNT,
				amount_subtotal: AGENTIC_PRODUCT_AMOUNT,
				amount_total: AGENTIC_PRODUCT_AMOUNT,
				amount_tax: 0,
				amount_discount: 0,
				tax_rates: [],
			},
		],
		shipping_details: {
			shipping_rates: [],
			address: {
				line1: '123 Test Street',
				line2: '',
				city: 'Testville',
				state: 'CA',
				postal_code: '94000',
				country: 'US',
			},
		},
		metadata: {},
	},
} );

/**
 * Builds a v1.delegated_checkout.finalize_checkout event.
 *
 * @param {Object} args Same as customizeCheckoutEvent().
 * @return {Object} The event payload.
 */
export const finalizeCheckoutEvent = ( args ) => ( {
	...customizeCheckoutEvent( args ),
	id: 'evt_e2e_agentic_finalize',
	type: 'v1.delegated_checkout.finalize_checkout',
} );
