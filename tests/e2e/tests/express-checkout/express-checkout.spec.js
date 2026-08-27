import { test, expect } from '@playwright/test';
import { payments } from '../../utils';
import { assertLinkModalLoads, getLinkButton } from './utils';

const { clickAddToCartButton } = payments;

const addProductToCart = async ( page, productSlug = 'beanie' ) => {
	// Add a product to the cart
	await page.goto( `/product/${ productSlug }` );

	await clickAddToCartButton( page );

	// Wait for the cart update to complete - look for success message or cart count update
	await expect(
		page.getByText( 'has been added to your cart' )
	).toBeVisible();
};

const testLink = async ( page, navigateTo, isBlockPage = false ) => {
	await page.goto( navigateTo );
	await assertLinkModalLoads( page, isBlockPage );
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
	test( 'is visible before a variation is selected and prompts for options on click', async ( {
		page,
	} ) => {
		await page.goto( '/product/hoodie' );

		const linkButton = await getLinkButton( page );
		await expect( linkButton ).toBeVisible( { timeout: 60 * 1000 } );

		// Clicking before completing the attribute selection must not open
		// the wallet; it prompts for the missing options instead. The
		// handler must be registered before the click and dismiss
		// immediately: an unhandled alert freezes the page and stalls the
		// click action itself. The first click can also be silently
		// swallowed while Stripe re-syncs the iframe position after
		// scroll-into-view (see assertLinkModalLoads), so retry until the
		// prompt is captured.
		let alertMessage = '';
		page.on( 'dialog', async ( dialog ) => {
			alertMessage = dialog.message();
			await dialog.dismiss();
		} );
		await expect( async () => {
			await linkButton.click();
			expect( alertMessage ).toContain(
				'select your product options before proceeding'
			);
		} ).toPass( { timeout: 45 * 1000 } );
	} );

	test( 'is visible when a product variation is selected', async ( {
		page,
	} ) => {
		await page.goto( '/product/hoodie' );
		await page
			.getByLabel( 'color', { exact: true } )
			.selectOption( 'Blue' );
		await page.getByLabel( 'Logo', { exact: true } ).selectOption( 'Yes' );
		const linkButton = await getLinkButton( page );
		await expect( linkButton ).toBeVisible();
	} );
} );
