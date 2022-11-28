const { expect } = require( '@playwright/test' );

/**
 * Helper function to login a WP User and save the state on a given path.
 *
 */
export const loginCustomerAndSaveState = ( {
	page,
	username,
	password,
	statePath,
	retries,
} ) =>
	new Promise( ( resolve, reject ) => {
		( async () => {
			// Sign in as customer user and save state
			for ( let i = 0; i < retries; i++ ) {
				try {
					console.log( 'Trying to log-in as customer...' );
					await page.goto( `/wp-admin` );
					await page.fill( 'input[name="log"]', username );
					await page.fill( 'input[name="pwd"]', password );
					await page.click( 'text=Log In' );

					await page.goto( `/my-account` );
					await expect(
						page.locator(
							'.woocommerce-MyAccount-navigation-link--customer-logout'
						)
					).toBeVisible();
					await expect(
						page.locator(
							'div.woocommerce-MyAccount-content > p >> nth=0'
						)
					).toContainText( 'Hello' );

					await page.context().storageState( { path: statePath } );
					console.log( 'Logged-in as customer successfully.' );
					resolve();
					break;
				} catch ( e ) {
					console.log(
						`Customer log-in failed. Retrying... ${ i }/${ retries }`
					);
					console.log( e );
				}
			}

			reject();
		} )();
	} );
