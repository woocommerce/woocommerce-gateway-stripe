import jQuery from 'jquery';

/**
 * What the functions in `wcstripe/api/*` need to reach the server. It is passed to them
 * explicitly, so each bundle only includes the requests it imports.
 *
 * @typedef {Object} WCStripeApiClient
 * @property {Object}       options                      Server-provided settings: publishable key, locale, AJAX URLs and nonces.
 * @property {Function}     request                      Sends a POST request, returning a promise for the parsed response.
 * @property {Promise|null} expressCheckoutNoncesPromise The express checkout nonce bundle, once it was requested.
 */

/**
 * Sends a POST request through jQuery.
 *
 * A jqXHR is thenable but reports failures through `.fail()`, so it is wrapped to give
 * callers a native promise they can `await` and `.catch()`.
 *
 * @param {string} url  The URL to post to.
 * @param {Object} args The request data.
 * @return {Promise<Object>} Promise for the parsed response.
 */
const postWithJQuery = ( url, args ) =>
	new Promise( ( resolve, reject ) => {
		jQuery.post( url, args ).then( resolve ).fail( reject );
	} );

/**
 * Creates the client that the functions in `wcstripe/api/*` take as their first argument.
 *
 * @param {Object}   options Options for the initialization.
 * @param {Function} request A function to use for AJAX requests.
 * @return {WCStripeApiClient} The client.
 */
export const createApiClient = ( options, request = postWithJQuery ) => ( {
	options,
	request,
	// Held per client rather than per module so that a failed fetch is retried by
	// the client that made it, without being affected by any other client.
	expressCheckoutNoncesPromise: null,
} );

/**
 * Construct WC AJAX endpoint URL.
 *
 * @param {WCStripeApiClient} client   The API client.
 * @param {string}            endpoint Request endpoint URL.
 * @param {string}            prefix   Endpoint URI prefix (default: 'wc_stripe_').
 * @return {string} URL with interpolated endpoint.
 */
export const getAjaxUrl = ( client, endpoint, prefix = 'wc_stripe_' ) =>
	client.options?.ajax_url
		?.toString()
		?.replace( '%%endpoint%%', prefix + endpoint );
