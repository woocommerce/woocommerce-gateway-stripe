import { randomUUID } from 'crypto';
import { expect, test } from '@playwright/test';
import { api, payments } from '../../utils';

const { clickAddToCartButton, emptyCart } = payments;

const getLinkButtonOnBlockPage = async ( page ) => {
	const frameLocator = await page.frameLocator(
		'#express-payment-method-express_checkout_element_link iframe[name^="__privateStripeFrame"]'
	);

	return frameLocator.getByRole( 'button', {
		name: 'Pay with Link',
	} );
};

const addProductToCartById = async ( page, productId ) => {
	await page.goto( `?p=${ productId }` );
	await clickAddToCartButton( page );
	await expect(
		page.getByText( 'has been added to your cart' )
	).toBeVisible();
};

const assertLinkModalLoads = async ( page ) => {
	const linkButton = await getLinkButtonOnBlockPage( page );
	await expect( linkButton ).toBeVisible();
	await expect( linkButton ).toBeEnabled();

	const context = await page.context();
	const [ popup ] = await Promise.all( [
		context.waitForEvent( 'page' ),
		linkButton.dispatchEvent( 'click' ),
	] );

	await popup.waitForLoadState();

	await expect(
		page.getByRole( 'button', {
			name: 'Continue payment',
		} )
	).toBeVisible();
};

let lowAmountProductId;
let highAmountProductId;

test.describe( 'express checkout with ISK in block cart/checkout', () => {
	test.beforeAll( async () => {
		lowAmountProductId = await api.create.product( {
			name: `ISK ECE Low ${ randomUUID() }`,
			type: 'simple',
			virtual: true,
			regular_price: '4500',
		} );

		highAmountProductId = await api.create.product( {
			name: `ISK ECE High ${ randomUUID() }`,
			type: 'simple',
			virtual: true,
			regular_price: '7500',
		} );
	} );

	test.afterAll( async () => {
		if ( lowAmountProductId ) {
			await api.deletePost.product( lowAmountProductId );
		}

		if ( highAmountProductId ) {
			await api.deletePost.product( highAmountProductId );
		}
	} );

	test.beforeEach( async ( { page } ) => {
		await emptyCart( page );
	} );

	test( 'loads Link express checkout in block cart for low ISK amount @blocks @express-checkout @isk', async ( {
		page,
	} ) => {
		await addProductToCartById( page, lowAmountProductId );
		await page.goto( '/cart' );
		await assertLinkModalLoads( page );
	} );

	test( 'loads Link express checkout in block checkout for high ISK amount @blocks @express-checkout @isk', async ( {
		page,
	} ) => {
		await addProductToCartById( page, highAmountProductId );
		await page.goto( '/checkout' );
		await assertLinkModalLoads( page );
	} );
} );
