import { expect } from '@playwright/test';

export const getLinkButton = async ( page, isBlockPage = false ) => {
	const frameSelector = isBlockPage
		? '#express-payment-method-express_checkout_element_link iframe[name^="__privateStripeFrame"]'
		: '#wc-stripe-express-checkout-element-link iframe[name^="__privateStripeFrame"]';

	const frameLocator = page.frameLocator( frameSelector );

	return frameLocator.getByRole( 'button', {
		name: 'Pay with Link',
	} );
};

export const assertLinkModalLoads = async ( page, isBlockPage = false ) => {
	const linkButton = await getLinkButton( page, isBlockPage );
	await expect( linkButton ).toBeVisible();
	await expect( linkButton ).toBeEnabled();

	const context = page.context();
	const [ popup ] = await Promise.all( [
		context.waitForEvent( 'page' ),
		linkButton.click(),
	] );

	await popup.waitForLoadState();

	await expect(
		page.getByRole( 'button', {
			name: 'Continue payment',
		} )
	).toBeVisible();
};
