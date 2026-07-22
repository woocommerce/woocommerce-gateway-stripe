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
 * @return {Promise<number>} The number of endpoints deleted.
 */
export const deleteStripeWebhooksByURL = async ( stripeClient, webhookURL ) => {
	const { data } = await stripeClient.webhookEndpoints.list();
	const matching = data.filter( ( w ) => w.url === webhookURL );

	for ( const webhook of matching ) {
		await stripeClient.webhookEndpoints.del( webhook.id );
	}

	return matching.length;
};
