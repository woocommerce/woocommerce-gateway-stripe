/**
 * Build the Stripe webhook endpoint URL that the plugin registers for a site.
 *
 * @param {string} baseURL Base URL of the site under test.
 * @return {string} The webhook endpoint URL for use by Stripe.
 */
export const getStripeWebhookURL = ( baseURL ) =>
	`${ baseURL }?wc-api=wc_stripe`;

/**
 * Delete every Stripe webhook endpoint registered for the given URL.
 *
 * Cleanup uses the URL so we can always ensure the current site
 * is clean before and after any test runs.
 *
 * @param {Object} stripeClient An initialised Stripe client.
 * @param {string} webhookURL   The webhook endpoint URL to match.
 * @param {boolean} verbose     Whether to log messages to the console. Defaults to false.
 * @return {Promise<number>} The number of endpoints deleted.
 */
export const deleteStripeWebhooksByURL = async (
	stripeClient,
	webhookURL,
	verbose = false
) => {
	let count = 0;
	const webhookEndpoints = stripeClient.webhookEndpoints.list( {
		limit: 100,
	} );

	await webhookEndpoints.autoPagingEach( async ( webhook ) => {
		if ( webhook.url === webhookURL ) {
			await stripeClient.webhookEndpoints.del( webhook.id );
			count++;
		}
	} );

	if ( verbose ) {
		if ( count > 0 ) {
			console.log(
				`\u2714 Deleted existing Stripe webhooks for the site: ${ count } deleted.`
			);
		} else {
			console.log(
				'\u2714 No existing Stripe webhooks exist for this site.'
			);
		}
	}

	return count;
};
