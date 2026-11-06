import { test, expect } from '@playwright/test';
import { payments } from '../../utils';
import { assertLinkModalLoads, getLinkButton } from './utils';

const { clickAddToCartButton } = payments;

const addProductToCart = async ( page, productSlug = 'beanie' ) => {
	// Add a product to the cart
	await page.goto( `/product/${ productSlug }` );

	// Verified against the cart by clickAddToCartButton; block themes render
	// no "added to your cart" notice to wait on.
	await clickAddToCartButton( page );
};

/**
 * Select a variation attribute: a native `<select>` on classic themes, a radio
 * chip in the "Add to Cart with Options" block on block themes.
 *
 * @param {import('@playwright/test').Page} page           Playwright page.
 * @param {string}                          attributeLabel The attribute's label (e.g. 'color').
 * @param {string}                          value          The option to pick (e.g. 'Blue').
 */
const selectVariation = async ( page, attributeLabel, value ) => {
	const dropdown = page.getByLabel( attributeLabel, { exact: true } );
	const isSelect =
		( await dropdown.count() ) > 0 &&
		( await dropdown
			.first()
			.evaluate( ( el ) => el.tagName === 'SELECT' ) );
	if ( isSelect ) {
		await dropdown.selectOption( value );
	} else {
		// Scoped to the block so a same-named radio elsewhere can't match.
		await page
			.locator(
				'.wp-block-woocommerce-add-to-cart-with-options-variation-selector'
			)
			.getByRole( 'radio', { name: value, exact: true } )
			.click();
	}
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

		const isBlockified = await page
			.locator(
				'.wp-block-woocommerce-add-to-cart-with-options-variation-selector'
			)
			.count();

		await selectVariation( page, 'color', 'Blue' );
		await selectVariation( page, 'Logo', 'Yes' );
		const linkButton = await getLinkButton( page );

		if ( isBlockified > 0 ) {
			// The "Add to Cart with Options" block does not fire the jQuery
			// variation events express checkout listens for, so the button
			// never renders there. Pinned so it turns red once
			// https://github.com/woocommerce/woocommerce-gateway-stripe/pull/5868
			// lands; flip this to toBeVisible then.
			await expect( linkButton ).toBeHidden();
			return;
		}
		await expect( linkButton ).toBeVisible();
	} );
} );
