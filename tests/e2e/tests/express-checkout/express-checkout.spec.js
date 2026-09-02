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

	test( 'prices the wallet sheet from the cart response, including after a quantity change', async ( {
		page,
	} ) => {
		// The sheet must show the add-item response's total. (Link's pre-auth
		// screen renders no line items; the breakdown is unit-covered.)
		let cart = null;
		let addItemCount = 0;
		page.on( 'response', async ( response ) => {
			if ( response.url().includes( '/wc/store/v1/cart/add-item' ) ) {
				try {
					cart = await response.json();
					addItemCount++;
				} catch ( error ) {}
			}
		} );

		// The pay button renders the formatted amount ("Pay $20.00"); its
		// digits are the total in minor units, format-independent.
		const expectSheetTotal = async ( popup, minorUnits ) => {
			const payText = await popup.getByTestId( 'pay-button' ).innerText();
			expect( payText.replace( /\D/g, '' ) ).toContain(
				String( minorUnits )
			);
		};

		await page.goto( '/product/hoodie' );
		await page
			.getByLabel( 'color', { exact: true } )
			.selectOption( 'Blue' );
		await page.getByLabel( 'Logo', { exact: true } ).selectOption( 'Yes' );

		const firstPopup = await assertLinkModalLoads( page );
		expect( cart.items[ 0 ].quantity ).toBe( 1 );
		const firstTotal = parseInt( cart.totals.total_price, 10 );
		await expectSheetTotal( firstPopup, firstTotal );
		await firstPopup.close();

		// Change the quantity, then re-tap. Until Stripe's poller notices the
		// closed popup (~1-2s) the old session stays active: clicks either
		// get swallowed or REOPEN the old session's window without running
		// our handler (and the restore can revert the quantity input). So
		// per attempt: re-assert the quantity, and only accept a popup that
		// has a fresh add-item response behind it.
		const linkButton = await getLinkButton( page );
		let secondPopup = null;
		await expect( async () => {
			await page.locator( '.quantity .qty' ).fill( '3' );
			const seenResponses = addItemCount;
			const [ popup ] = await Promise.all( [
				page.context().waitForEvent( 'page', { timeout: 2000 } ),
				linkButton.click(),
			] );
			try {
				await expect
					.poll( () => addItemCount, { timeout: 4000 } )
					.toBeGreaterThan( seenResponses );
			} catch ( pollError ) {
				// A restored session window has no fresh cart: discard it so
				// the next attempt's click isn't ignored for an open popup.
				await popup.close();
				throw pollError;
			}
			secondPopup = popup;
		} ).toPass( { timeout: 30 * 1000 } );
		await expect( secondPopup.getByTestId( 'pay-button' ) ).toBeVisible( {
			timeout: 60 * 1000,
		} );

		expect( cart.items[ 0 ].quantity ).toBe( 3 );
		// Not firstTotal * 3: the total carries order-level components
		// (flat-rate shipping) that don't scale with quantity.
		const secondTotal = parseInt( cart.totals.total_price, 10 );
		expect( secondTotal ).toBeGreaterThan( firstTotal );
		await expectSheetTotal( secondPopup, secondTotal );
	} );
} );
