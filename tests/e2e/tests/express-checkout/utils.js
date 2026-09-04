import { expect } from '@playwright/test';

export const getLinkButton = async ( page, isBlockPage = false ) => {
	const frameSelector = isBlockPage
		? '#express-payment-method-express_checkout_element_link iframe[name^="__privateStripeFrame"]'
		: '#wc-stripe-express-checkout-element-link iframe[name^="__privateStripeFrame"]';

	const frameLocator = page.frameLocator( frameSelector );

	return frameLocator.getByRole( 'button', {
		name: /Pay (securely )?with Link/,
	} );
};

export const assertLinkModalLoads = async ( page, isBlockPage = false ) => {
	const linkButton = await getLinkButton( page, isBlockPage );
	await expect( linkButton ).toBeVisible( { timeout: 60 * 1000 } );
	await expect( linkButton ).toBeEnabled();

	// The Express Checkout Element ignores clicks that land before Stripe has
	// re-synced the iframe's position after a scroll, and Playwright scrolls the
	// button into view as part of the click itself. So the first click is
	// silently swallowed whenever the button starts below the fold, as it does
	// on the Cart block; by the second the page no longer needs to scroll.
	const context = page.context();
	let popup;
	await expect( async () => {
		[ popup ] = await Promise.all( [
			context.waitForEvent( 'page', { timeout: 5 * 1000 } ),
			linkButton.click(),
		] );
	} ).toPass( { timeout: 45 * 1000 } );

	// No waitForLoadState: the pay-button expectation already polls until
	// the popup has rendered far enough to assert on.
	await expect( popup.getByTestId( 'pay-button' ) ).toBeVisible( {
		timeout: 60 * 1000,
	} );

	return popup;
};
