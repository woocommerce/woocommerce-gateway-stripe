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

		// An early click must prompt, not open the wallet. Dismiss dialogs
		// immediately (an unhandled alert stalls the click action) and retry
		// the click: Stripe's iframe can swallow the first one after scroll
		// (see assertLinkModalLoads).
		let alertMessage = '';
		page.on( 'dialog', async ( dialog ) => {
			alertMessage = dialog.message();
			await dialog.dismiss();
		} );
		await expect( async () => {
			// Reset per attempt so a stale dialog from a previous retry can't
			// satisfy this one, then poll: the prompt is deferred (~100ms)
			// so the rejected wallet UI can dismiss first.
			alertMessage = '';
			await linkButton.click();
			await expect
				.poll( () => alertMessage, { timeout: 5000 } )
				.toContain( 'select your product options before proceeding' );
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

	test( 'opens the wallet sheet priced from the cart response', async ( {
		page,
	} ) => {
		// The sheet must show the add-item response's total. (Link's pre-auth
		// screen renders no line items; the breakdown is unit-covered.)
		let cart = null;
		page.on( 'response', async ( response ) => {
			if ( response.url().includes( '/wc/store/v1/cart/add-item' ) ) {
				try {
					cart = await response.json();
				} catch ( error ) {}
			}
		} );

		await page.goto( '/product/hoodie' );
		await page
			.getByLabel( 'color', { exact: true } )
			.selectOption( 'Blue' );
		await page.getByLabel( 'Logo', { exact: true } ).selectOption( 'Yes' );

		const popup = await assertLinkModalLoads( page );

		expect( cart?.totals?.total_price ).toBeTruthy();
		expect( cart.items[ 0 ].quantity ).toBe( 1 );

		// The pay button renders the formatted amount ("Pay $20.00"); its
		// digits are the total in minor units, format-independent.
		const payText = await popup.getByTestId( 'pay-button' ).innerText();
		expect( payText.replace( /\D/g, '' ) ).toContain(
			String( parseInt( cart.totals.total_price, 10 ) )
		);
	} );

	test( 'reprices the sheet after dismissing it and changing the quantity', async ( {
		page,
	} ) => {
		let cart = null;
		page.on( 'response', async ( response ) => {
			if ( response.url().includes( '/wc/store/v1/cart/add-item' ) ) {
				try {
					cart = await response.json();
				} catch ( error ) {}
			}
		} );

		await page.goto( '/product/hoodie' );
		await page
			.getByLabel( 'color', { exact: true } )
			.selectOption( 'Blue' );
		await page.getByLabel( 'Logo', { exact: true } ).selectOption( 'Yes' );

		const firstPopup = await assertLinkModalLoads( page );
		const firstTotal = parseInt( cart.totals.total_price, 10 );
		await firstPopup.close();

		// Change the quantity and re-tap immediately: nothing observes the
		// page, so the click itself must pick up the new quantity.
		await page.locator( '.quantity .qty' ).fill( '3' );
		const secondPopup = await assertLinkModalLoads( page );

		expect( cart.items[ 0 ].quantity ).toBe( 3 );
		// Not firstTotal * 3: the total carries order-level components
		// (flat-rate shipping) that don't scale with quantity.
		const secondTotal = parseInt( cart.totals.total_price, 10 );
		expect( secondTotal ).toBeGreaterThan( firstTotal );

		const payText = await secondPopup
			.getByTestId( 'pay-button' )
			.innerText();
		expect( payText.replace( /\D/g, '' ) ).toContain(
			String( secondTotal )
		);
	} );
} );
