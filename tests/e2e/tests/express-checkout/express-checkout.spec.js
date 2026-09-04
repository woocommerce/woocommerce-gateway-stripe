import { test, expect } from '@playwright/test';
import { payments } from '../../utils';
import { assertLinkModalLoads, getLinkButton } from './utils';

const { clickAddToCartButton } = payments;

const addProductToCart = async ( page, productSlug = 'beanie' ) => {
	// Add a product to the cart
	await page.goto( `/product/${ productSlug }` );

	// clickAddToCartButton already confirms the add against the Store API cart,
	// so there is no need to wait on the classic 'added to your cart' notice,
	// which block themes do not render.
	await clickAddToCartButton( page );
};

/**
 * Select a product variation attribute regardless of the active theme. Classic
 * themes render a native `<select>` per attribute; block themes render the
 * "Add to Cart with Options" block, whose attributes are radio "chip" buttons
 * labelled by the option value.
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
		await page.getByRole( 'radio', { name: value, exact: true } ).click();
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

		// The blockified "Add to Cart with Options" template does not fire the
		// variation-change jQuery events express checkout relies on, so the
		// button does not render on variable products there yet (fix in flight).
		// Skip on that template; the classic add-to-cart form still covers this.
		const isBlockified = await page
			.locator(
				'.wp-block-woocommerce-add-to-cart-with-options-variation-selector'
			)
			.count();
		test.skip(
			isBlockified > 0,
			'Blockified add-to-cart: express checkout on variable products is a known open issue.'
		);

		await selectVariation( page, 'color', 'Blue' );
		await selectVariation( page, 'Logo', 'Yes' );
		const linkButton = await getLinkButton( page );
		await expect( linkButton ).toBeVisible();
	} );
} );
