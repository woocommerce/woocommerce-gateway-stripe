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
	await expect( linkButton ).toBeVisible( { timeout: 60 * 1000 } );
	await expect( linkButton ).toBeEnabled();

	const context = page.context();
	const [ popup ] = await Promise.all( [
		context.waitForEvent( 'page', { timeout: 60 * 1000 } ),
		linkButton.click(),
	] );

	await popup.waitForLoadState();

	await expect( popup.getByTestId( 'pay-button' ) ).toBeVisible( {
		timeout: 60 * 1000,
	} );
};
