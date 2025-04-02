const { test, expect } = require( '@playwright/test' );

const addProductToCart = async ( page, productSlug = 'beanie' ) => {
	// Add a product to the cart
	await page.goto( `/product/${ productSlug }` );

	const addToCartButton = await page.getByRole( 'button', {
		name: 'Add to cart',
	} );
	await expect( addToCartButton ).toBeEnabled();
	await addToCartButton.dispatchEvent( 'click' );

	// Wait for the cart update to complete - look for success message or cart count update
	await expect(
		page.getByText( 'has been added to your cart' )
	).toBeVisible();
};

const getLinkButton = async ( page, isBlockPage = false ) => {
	let frameLocator;
	if ( isBlockPage ) {
		frameLocator = await page.frameLocator(
			'#express-payment-method-express_checkout_element_link iframe[name^="__privateStripeFrame"]'
		);
	} else {
		frameLocator = await page.frameLocator(
			'#wc-stripe-express-checkout-element-link iframe[name^="__privateStripeFrame"]'
		);
	}
	const linkButton = await frameLocator.getByRole( 'button', {
		name: 'Pay with Link',
	} );

	return linkButton;
};

const testLink = async ( page, navigateTo, isBlockPage = false ) => {
	await page.goto( navigateTo );

	const linkButton = await getLinkButton( page, isBlockPage );
	await expect( linkButton ).toBeEnabled();

	const context = await page.context();
	const [ popup ] = await Promise.all( [
		context.waitForEvent( 'page' ),
		linkButton.dispatchEvent( 'click' ),
	] );

	// Check that the payment modal gets loaded.
	await popup.waitForLoadState();

	// Back in the main window, check that Link's "Continue payment" button is visible.
	const continuePaymentButton = await page.getByRole( 'button', {
		name: 'Continue payment',
	} );
	await expect( continuePaymentButton ).toBeVisible();
};

test.describe( 'customer can use Link express checkout', () => {
	test( 'inside the product page', async ( { page } ) =>
		await testLink( page, '/product/beanie' ) );

	test( 'inside the cart page (classic)', async ( { page } ) => {
		await addProductToCart( page );
		await testLink( page, '/cart-shortcode', false );
	} );

	test( 'inside the checkout page (classic)', async ( { page } ) => {
		await addProductToCart( page );
		await testLink( page, '/checkout-shortcode', false );
	} );

	test( 'inside the cart page (block)', async ( { page } ) => {
		await addProductToCart( page );
		await testLink( page, '/cart', true );
	} );

	test( 'inside the checkout page (block)', async ( { page } ) => {
		await addProductToCart( page );
		await testLink( page, '/checkout', true );
	} );
} );

test.describe( 'express checkout and variable products', () => {
	test( 'is hidden when no product variation is selected', async ( {
		page,
	} ) => {
		await page.goto( '/product/hoodie' );

		// We want to wait for the express checkout element to be loaded,
		// before asserting that it is hidden. Immedidately asserting that it is hidden
		// might cause the test to pass only because the element is not yet loaded.
		const linkContainer = page.locator(
			'#wc-stripe-express-checkout-element-link iframe[name^="__privateStripeFrame"]'
		);
		await expect( linkContainer ).toHaveCount( 1 );
		await expect( linkContainer ).toBeHidden();
	} );

	test( 'is visible when a product variation is selected', async ( {
		page,
	} ) => {
		await page.goto( '/product/hoodie' );
		await page.getByLabel( 'Logo', { exact: true } ).selectOption( 'Yes' );
		const linkButton = await getLinkButton( page );
		await expect( linkButton ).toBeVisible();
	} );
} );
