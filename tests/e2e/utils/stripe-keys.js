/**
 * Update Stripe test keys in WordPress via a custom REST endpoint.
 *
 * Used in QIT environments where WP-CLI is not available from the host.
 * The REST endpoint is provided by the qit-option-api mu-plugin
 * (installed during QIT globalSetup).
 *
 * @param {Page} page An admin-authenticated Playwright page.
 * @param {string} publishableKey The Stripe test publishable key.
 * @param {string} secretKey The Stripe test secret key.
 */
export async function updateStripeKeys( page, publishableKey, secretKey ) {
	// Navigate to wp-admin to get wpApiSettings.nonce.
	await page.goto( '/wp-admin/' );

	const result = await page.evaluate(
		async ( { pubKey, secKey } ) => {
			/* global wpApiSettings */
			const nonce = wpApiSettings?.nonce;
			if ( ! nonce ) {
				return {
					success: false,
					error: 'wpApiSettings.nonce not found',
				};
			}

			// Fetch current Stripe settings.
			const getRes = await fetch(
				'/wp-json/qit/v1/option/woocommerce_stripe_settings',
				{ headers: { 'X-WP-Nonce': nonce } }
			);

			if ( ! getRes.ok ) {
				return {
					success: false,
					error: `GET option failed: ${ getRes.status }`,
				};
			}

			const settings = await getRes.json();
			settings.test_publishable_key = pubKey;
			settings.test_secret_key = secKey;

			const putRes = await fetch(
				'/wp-json/qit/v1/option/woocommerce_stripe_settings',
				{
					method: 'PUT',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					body: JSON.stringify( settings ),
				}
			);

			return { success: putRes.ok, status: putRes.status };
		},
		{ pubKey: publishableKey, secKey: secretKey }
	);

	if ( ! result.success ) {
		throw new Error(
			`Failed to update Stripe keys via WP REST API: ${
				result.error || result.status
			}`
		);
	}
}
