import apiFetch from '@wordpress/api-fetch';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

/**
 * Nonces learned after the page rendered. They win over the printed ones,
 * which a full-page cache can serve long after they expired, and which stop
 * matching once the Store API logs the shopper in mid-checkout.
 */
const refreshedNonces = {};

let refreshNonces = null;

/**
 * The rejection that showed the printed nonces expired, kept for as long as
 * the express checkout nonce printed alongside them is the only one known.
 * Unlike the Store API nonce, which every response renews, that one can only
 * come from a fresh bundle.
 */
let staleNonceRejection = null;

/**
 * Remembers the Store API nonces found in a nonce map, ignoring the keys it
 * doesn't carry.
 *
 * @param {Object} nonces Nonce map, e.g. the on-demand nonce bundle.
 */
export const rememberStoreApiNonces = ( nonces ) => {
	if ( nonces?.wc_store_api ) {
		refreshedNonces.wc_store_api = nonces.wc_store_api;
		// Always replaced together: a timestamp describes one specific nonce.
		refreshedNonces.wc_store_api_timestamp = nonces.wc_store_api_timestamp;
	}

	if ( nonces?.wc_store_api_express_checkout ) {
		refreshedNonces.wc_store_api_express_checkout =
			nonces.wc_store_api_express_checkout;
		staleNonceRejection = null;
	}
};

/**
 * Registers how to fetch a fresh nonce bundle after the Store API rejects a
 * nonce. The fetch needs the page's `WCStripeAPI`, which this module can't
 * reach on its own.
 *
 * @param {Function} refresher Returns a promise that settles once the fresh
 *                             nonces went through `rememberStoreApiNonces`.
 */
export const setStoreApiNonceRefresher = ( refresher ) => {
	refreshNonces = refresher;
};

const rememberNonceFromHeaders = ( headers ) => {
	if ( typeof headers?.get !== 'function' ) {
		return;
	}

	rememberStoreApiNonces( {
		wc_store_api: headers.get( 'Nonce' ),
		// `undefined` when the header is missing, never `null`: the Blocks nonce
		// middleware reads `null` as older than any nonce it already holds.
		wc_store_api_timestamp: headers.get( 'Nonce-Timestamp' ) ?? undefined,
	} );
};

const isInvalidNonceError = async ( error ) => {
	if ( error?.status !== 403 ) {
		return false;
	}

	try {
		// Cloned so the caller can still read the body when there is no retry.
		const body = await error.clone().json();
		return body?.code === 'woocommerce_rest_invalid_nonce';
	} catch ( e ) {
		return false;
	}
};

const sendRequest = async ( options, expressCheckoutContext ) => {
	const nonces = {
		...getExpressCheckoutData( 'nonce' ),
		...refreshedNonces,
	};

	// When loaded, the WooCommerce Blocks nonce middleware replaces the `Nonce`
	// header of every Store API write with the value it holds, which on a
	// cached page is as stale as the printed one. Synced on every send, not
	// when a nonce is learned, because the middleware can load afterwards. The
	// timestamp lets it keep its own nonce when that one is newer.
	if ( refreshedNonces.wc_store_api ) {
		apiFetch.setNonce?.( {
			Nonce: refreshedNonces.wc_store_api,
			'Nonce-Timestamp': refreshedNonces.wc_store_api_timestamp,
		} );
	}

	let response;
	try {
		response = await apiFetch( {
			...options,
			parse: false,
			headers: {
				Nonce: nonces.wc_store_api,
				...( expressCheckoutContext
					? {
							'X-WCSTRIPE-EXPRESS-CHECKOUT': 'true',
							'X-WCSTRIPE-EXPRESS-CHECKOUT-NONCE':
								nonces.wc_store_api_express_checkout,
					  }
					: {} ),
				...options.headers,
			},
		} );
	} catch ( error ) {
		// With `parse: false` an error response is thrown as is, and it carries
		// a fresh nonce just like a successful one.
		rememberNonceFromHeaders( error?.headers );
		throw error;
	}

	rememberNonceFromHeaders( response.headers );

	return response;
};

/**
 * Sends a Store API request with the freshest nonces known, retrying once
 * when the Store API rejects the nonce.
 *
 * Resolves with the raw response and rejects with the raw error response
 * (`parse: false`), since the nonces travel in the response headers.
 *
 * @param {Object}  options                        Options to pass to `apiFetch`.
 * @param {Object}  context                        Request context.
 * @param {boolean} context.expressCheckoutContext Whether to flag the request as an express
 *                                                 checkout one, which turns on the server-side
 *                                                 wallet address handling.
 * @return {Promise<Response>} The Store API response.
 */
export const storeApiFetch = async (
	options,
	{ expressCheckoutContext = false } = {}
) => {
	// An earlier refresh failed. The Store API nonce was renewed since, so the
	// request would be accepted and nothing would prompt another refresh.
	if ( expressCheckoutContext && staleNonceRejection ) {
		await refreshNonces?.().catch( () => {} );

		if ( staleNonceRejection ) {
			throw staleNonceRejection.clone();
		}
	}

	try {
		return await sendRequest( options, expressCheckoutContext );
	} catch ( error ) {
		if ( ! ( await isInvalidNonceError( error ) ) ) {
			throw error;
		}

		// The rejection only carried a fresh Store API nonce, so the express
		// checkout one has to be fetched.
		staleNonceRejection = error.clone();
		await refreshNonces?.().catch( () => {} );

		// Without a fresh express checkout nonce the retry would pass the Store
		// API's own check and then be handled as a regular request, with no
		// wallet address handling and no custom checkout field validation. A
		// failed request is the safer outcome.
		if ( expressCheckoutContext && staleNonceRejection ) {
			throw error;
		}

		// Resending is safe even for a checkout: the Store API checks the nonce
		// before it runs the route, so the rejected request changed nothing.
		return sendRequest( options, expressCheckoutContext );
	}
};
