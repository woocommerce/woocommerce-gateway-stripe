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

	test( 'waits for a Classic container added after document readiness', async ( {
		page,
	} ) => {
		await addProductToCart( page );
		await page.addInitScript( () => {
			if ( ! location.pathname.includes( '/checkout-shortcode' ) ) {
				return;
			}
			const observer = new MutationObserver( () => {
				const root = document.getElementById(
					'wc-stripe-express-checkout-element'
				);
				if ( root ) {
					window.lateCheckoutContainer = root;
					root.remove();
					observer.disconnect();
				}
			} );
			observer.observe( document, { childList: true, subtree: true } );
		} );
		await page.goto( '/checkout-shortcode' );
		await page.evaluate(
			() => new Promise( ( resolve ) => window.jQuery( resolve ) )
		);
		await expect(
			page.locator( '#wc-stripe-express-checkout-element' )
		).toHaveCount( 0 );
		await page.evaluate( () =>
			document
				.querySelector( 'form.checkout' )
				.prepend( window.lateCheckoutContainer )
		);
		await expect( await getLinkButton( page ) ).toBeVisible();
	} );

	test( 'rebuilds the Classic wallet iframe after updated_checkout', async ( {
		page,
	} ) => {
		await addProductToCart( page );
		const initial = page.waitForResponse(
			( response ) =>
				response.url().includes( 'wc-ajax=update_order_review' ) &&
				response.ok()
		);
		await page.goto( '/checkout-shortcode' );
		await initial;
		await expect( await getLinkButton( page ) ).toBeVisible();
		const iframe = await page
			.locator( '#wc-stripe-express-checkout-element-link iframe' )
			.elementHandle();
		const updated = page.waitForResponse(
			( response ) =>
				response.url().includes( 'wc-ajax=update_order_review' ) &&
				response.ok()
		);
		const shipping = page.locator( '#ship-to-different-address-checkbox' );
		await shipping.setChecked( ! ( await shipping.isChecked() ) );
		await updated;
		await expect
			.poll( () => iframe.evaluate( ( element ) => element.isConnected ) )
			.toBe( false );
		await expect( await getLinkButton( page ) ).toBeVisible();
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
		await page
			.getByLabel( 'color', { exact: true } )
			.selectOption( 'Blue' );
		await page.getByLabel( 'Logo', { exact: true } ).selectOption( 'Yes' );
		const linkButton = await getLinkButton( page );
		await expect( linkButton ).toBeVisible();
	} );
} );
