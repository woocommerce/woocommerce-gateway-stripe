import { test, expect } from '@playwright/test';
import { admin, payments } from '../../../utils';

const { setupAffirmCheckout, clickPlaceOrder } = payments;

test.describe( 'Affirm payment tests @shortcode', () => {
	test.beforeAll( async ( { browser } ) => {
		await test.step( 'Setup test environment', async () => {
			// Enable Affirm in admin
			await admin.togglePaymentMethod( browser, 'Affirm', true );
		} );
	} );

	test.describe.configure( { mode: 'parallel' } );

	test( 'customer can pay with Affirm @smoke', async ( { page } ) => {
		await setupAffirmCheckout( page, 'shortcode' );
		await clickPlaceOrder( page );
		// Since we don't have control over the Affirm payment flow,
		// verify that either the current page or a popup redirects externally.
		const externalCheckoutUrl = /.*(affirm\.com|stripe\.com)/;
		const topLevelRedirectPromise = page
			.waitForURL( externalCheckoutUrl, { timeout: 30000 } )
			.then( () => true );
		const popupRedirectPromise = page
			.context()
			.waitForEvent( 'page', { timeout: 30000 } )
			.then( async ( popupPage ) => {
				await popupPage.waitForLoadState( 'domcontentloaded' );
				await expect( popupPage ).toHaveURL( externalCheckoutUrl, {
					timeout: 30000,
				} );
				return true;
			} );

		const redirected = await Promise.any( [
			topLevelRedirectPromise,
			popupRedirectPromise,
		] ).catch( () => false );

		expect( redirected ).toBeTruthy();
	} );
} );
