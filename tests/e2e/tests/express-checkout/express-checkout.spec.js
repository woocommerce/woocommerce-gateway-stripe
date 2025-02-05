const { test, expect } = require( '@playwright/test' );

test.describe( 'customer can use express checkout', () => {
	test( 'Link is available inside the product page', async ( { page } ) => {
		// Navigate to a product page
		await page.goto( '/product/beanie' );

		const linkFrame = await page.frameLocator(
			'#wc-stripe-express-checkout-element-link iframe[name^="__privateStripeFrame"]'
		);

		// Wait for the button and verify it's visible
		const linkButton = linkFrame.locator( '.LinkButton' );

		const context = await page.context();
		const [ popup ] = await Promise.all( [
			context.waitForEvent( 'page', { timeout: 4000 } ),
			linkButton.dispatchEvent( 'click' ),
		] );

		// Check that the popup gets loaded.
		await popup.waitForLoadState();

		// Back in the main window, check that the "Continue payment" button is visible.
		const continuePaymentButton = await page.getByRole( 'button', {
			name: 'Continue payment',
		} );
		await expect( continuePaymentButton ).toBeVisible();
	} );
} );
