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
			await page.goto( `/wp-admin` );
			await page.waitForLoadState( 'load' );

			if ( await page.url().includes( 'wp-login.php' ) ) {
				// Wait for login form to be visible
				await page
					.locator( '#loginform' )
					.waitFor( { state: 'visible', timeout: 5000 } );

				// Fill in login credentials
				await page.locator( 'input[name="log"]' ).fill( username );
				await page.locator( 'input[name="pwd"]' ).fill( password );
				page.locator( 'input[value="Log In"]' ).click();
			}

			// Wait for either customer or admin login success
			await Promise.race( [
				// Customer login success
				expect( page.locator( 'body.logged-in' ) ).toBeVisible(),
				// Admin login success
				expect(
					page.getByRole( 'heading', { name: 'Dashboard' } )
				).toBeVisible(),
			] );

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
