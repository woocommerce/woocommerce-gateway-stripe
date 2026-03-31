import { expect } from '@playwright/test';

/**
 * Logs in a user with the given credentials on the provided page, with retries if login fails.
 * @param {Page} page - The Playwright page object to use for the login process.
 * @param {string} username - The username to use for the login process.
 * @param {string} password - The password to use for the login process.
 * @param {number} [retries=3] - The number of retries for the login process in case of failure.
 * @throws {Error} Will throw an error if login fails after the specified number of retries.
 * @returns {Promise<void>} - A promise that resolves when the login process is complete.
 */
export async function login( page, username, password, retries = 3 ) {
	for ( let i = 1; i <= retries; i++ ) {
		try {
			await page.goto( `/wp-login.php` );
			await page.waitForLoadState( 'networkidle' );

			// Fill and submit the login form.
			await page.fill( 'input[name="log"]', username );
			await page.fill( 'input[name="pwd"]', password );
			await page.click( 'input[value="Log In"]' );
			await page.waitForLoadState( 'networkidle' );

			// Check if we're still on the login page (login failed).
			if ( page.url().includes( 'wp-login.php' ) ) {
				const errorEl = await page.$( '#login_error' );
				if ( errorEl ) {
					const errorText = await errorEl.textContent();
					console.error( `  Login error: ${ errorText }` );
				}
				throw new Error(
					`Still on login page after submit: ${ page.url() }`
				);
			}

			// Login succeeded — we're either on wp-admin (admin) or my-account (customer).
			return;
		} catch ( e ) {
			console.error(
				`User log-in failed, Retrying... ${ i }/${ retries }.`,
				e
			);
		}
	}
	throw new Error(
		`User log-in failed for user ${ username } after ${ retries } attempts.`
	);
}
