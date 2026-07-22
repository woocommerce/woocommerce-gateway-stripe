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
	let haveMore = true;
	let count = 0;
	let startAfter = undefined;

	// Ensure we loop over all webhooks. We shouldn't get >100 webhooks
	// for an account, but we should handle that edge case.
	while ( haveMore ) {
		const { data, has_more: haveMore } =
			await stripeClient.webhookEndpoints.list( {
				limit: 100,
				starting_after: startAfter,
			} );
		const matching = data.filter( ( w ) => w.url === webhookURL );
		count += matching.length;

		for ( const webhook of matching ) {
			await stripeClient.webhookEndpoints.del( webhook.id );
		}

		if ( haveMore ) {
			startAfter = data.pop()?.id;
		}
	}

	return count;
};
