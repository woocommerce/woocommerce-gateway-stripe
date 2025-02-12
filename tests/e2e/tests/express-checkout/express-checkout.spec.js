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

const testLink = async ( page, navigateTo, isBlockPage = false ) => {
	await page.goto( navigateTo );

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
