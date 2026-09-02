import { expect, test } from '@playwright/test';
import { api, payments, products } from '../../utils';
import { setProductType } from '../../utils/wp-cli';
import { assertLinkModalLoads } from './utils';

const { clickAddToCartButton, emptyCart } = payments;

let virtualProductId;
let physicalProductId;

const createFreeTrialProduct = async ( { virtual } ) => {
	const productId = await api.create.product(
		products.freeTrialSubscriptionData( { virtual } )
	);
	await setProductType( productId, 'subscription' );
	return productId;
};

const addProductToCartById = async ( page, productId ) => {
	await page.goto( `?p=${ productId }` );
	await clickAddToCartButton( page );
	await expect(
		page.getByText( 'has been added to your cart' )
	).toBeVisible();
};

// Free trial carts total 0 at checkout time, which is normally a condition for
// hiding express checkout; free trials are the deliberate exception (the
// element is created with mode: 'subscription' and amount: 0). These tests pin
// that exception across the classic and Blocks surfaces, with and without
// shipping, since a regression here silently removes the buttons.
test.describe( 'express checkout with free trial subscriptions', () => {
	test.beforeAll( async () => {
		virtualProductId = await createFreeTrialProduct( { virtual: true } );
		physicalProductId = await createFreeTrialProduct( { virtual: false } );
	} );

	test.afterAll( async () => {
		if ( virtualProductId ) {
			await api.deletePost.product( virtualProductId );
		}

		if ( physicalProductId ) {
			await api.deletePost.product( physicalProductId );
		}
	} );

	test.beforeEach( async ( { page } ) => {
		await emptyCart( page );
	} );

	test.describe( 'without shipping (virtual product)', () => {
		test( 'loads Link on the product page @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			await page.goto( `?p=${ virtualProductId }` );
			await assertLinkModalLoads( page );
		} );

		test( 'loads Link on the classic checkout page @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			await addProductToCartById( page, virtualProductId );
			await page.goto( '/checkout-shortcode' );
			await assertLinkModalLoads( page );
		} );

		test( 'loads Link on the block cart page @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			await addProductToCartById( page, virtualProductId );
			await page.goto( '/cart' );
			await assertLinkModalLoads( page, true );
		} );

		test( 'loads Link on the block checkout page @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			await addProductToCartById( page, virtualProductId );
			await page.goto( '/checkout' );
			await assertLinkModalLoads( page, true );
		} );
	} );

	test.describe( 'with shipping (physical product)', () => {
		test( 'loads Link on the product page @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			await page.goto( `?p=${ physicalProductId }` );
			await assertLinkModalLoads( page );
		} );

		test( 'loads Link on the classic checkout page @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			await addProductToCartById( page, physicalProductId );
			await page.goto( '/checkout-shortcode' );
			await assertLinkModalLoads( page );
		} );

		test( 'loads Link on the block cart page @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			await addProductToCartById( page, physicalProductId );
			await page.goto( '/cart' );
			await assertLinkModalLoads( page, true );
		} );

		test( 'loads Link on the block checkout page @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			await addProductToCartById( page, physicalProductId );
			await page.goto( '/checkout' );
			await assertLinkModalLoads( page, true );
		} );
	} );
} );
