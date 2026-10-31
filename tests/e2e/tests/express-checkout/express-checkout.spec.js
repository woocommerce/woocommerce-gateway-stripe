import { test, expect } from '@playwright/test';
import { payments } from '../../utils';
import { assertLinkModalLoads, getLinkButton, openLinkPopup } from './utils';

const { clickAddToCartButton, getCartTotal } = payments;

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

	test( 'discovers Link before the first classic checkout recalculation finishes', async ( {
		page,
	} ) => {
		await addProductToCart( page );
		let releaseCheckoutUpdate;
		const checkoutUpdate = new Promise( ( resolve ) => {
			releaseCheckoutUpdate = resolve;
		} );
		// Holding the response makes early discovery independent of server speed.
		await page.route(
			'**/*wc-ajax=update_order_review*',
			async ( route ) => {
				await checkoutUpdate;
				await route.continue();
			}
		);
		const updateRequest = page.waitForRequest( ( request ) =>
			request.url().includes( 'wc-ajax=update_order_review' )
		);
		try {
			await page.goto( '/checkout-shortcode' );
			await updateRequest;
			const linkButton = await getLinkButton( page );
			await expect( linkButton ).toBeVisible();
		} finally {
			releaseCheckoutUpdate();
		}
	} );

	test( 'recovers express checkout after update_order_review fails', async ( {
		page,
	} ) => {
		await addProductToCart( page );
		await page.goto( '/checkout-shortcode' );
		await expect( await getLinkButton( page ) ).toBeVisible();
		await page.route(
			'**/*wc-ajax=update_order_review*',
			( route ) => route.abort( 'failed' ),
			{ times: 1 }
		);
		const postcode = page.locator( '#billing_postcode' );
		await postcode.fill(
			( await postcode.inputValue() ) === '90210' ? '90211' : '90210'
		);
		await postcode.press( 'Tab' );
		const error = page.locator(
			'#wc-stripe-express-checkout-refresh-error'
		);
		await expect( error ).toBeVisible();
		await expect(
			page.locator( '#wc-stripe-express-checkout-element' )
		).toBeHidden();
		const update = page.waitForResponse(
			( response ) =>
				response.url().includes( 'wc-ajax=update_order_review' ) &&
				response.ok()
		);
		await error
			.getByRole( 'button', { name: 'Retry', exact: true } )
			.click();
		await update;
		await expect( error ).toHaveCount( 0 );
		await expect( await getLinkButton( page ) ).toBeVisible();
		await assertLinkModalLoads( page );
	} );

	test( 'inside the cart page (block)', async ( { page } ) => {
		await addProductToCart( page );
		await testLink( page, '/cart', true );
	} );

	test( 'after quantity changes in the cart page (block)', async ( {
		page,
	} ) => {
		await addProductToCart( page );
		await page.goto( '/cart' );

		const linkButton = await getLinkButton( page, true );
		await expect( linkButton ).toBeVisible();
		await expect( linkButton ).toBeEnabled();

		const initialTotal = await getCartTotal( page );
		const quantityInput = page.locator(
			'.wc-block-components-quantity-selector__input'
		);
		const displayedTotal = page
			.locator(
				'.wc-block-components-totals-footer-item .wc-block-components-totals-item__value'
			)
			.last();
		await expect( quantityInput ).toHaveValue( '1' );
		await expect( displayedTotal ).toContainText( initialTotal );

		for ( const quantity of [ '2', '1' ] ) {
			await quantityInput.fill( quantity );
			await quantityInput.blur();
			if ( quantity === '2' ) {
				await expect
					.poll( () => getCartTotal( page ) )
					.not.toBe( initialTotal );
			} else {
				await expect
					.poll( () => getCartTotal( page ) )
					.toBe( initialTotal );
			}
			await expect( displayedTotal ).toContainText(
				await getCartTotal( page )
			);
			await expect( quantityInput ).toBeEnabled();
			await expect( quantityInput ).toHaveValue( quantity );
			await expect( linkButton ).toBeVisible();
			await expect( linkButton ).toBeEnabled();
		}

		// Reloading would reset availability and hide a stale-cache failure.
		const popup = await openLinkPopup( page, true, { maxRetries: 0 } );
		await popup.close();
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
		await page
			.getByLabel( 'color', { exact: true } )
			.selectOption( 'Blue' );
		await page.getByLabel( 'Logo', { exact: true } ).selectOption( 'Yes' );
		const linkButton = await getLinkButton( page );
		await expect( linkButton ).toBeVisible();
	} );
} );
