import { expect, test } from '@playwright/test';
import { api, payments, products } from '../../utils';
import { assertLinkModalLoads } from './utils';

const { clickAddToCartButton, emptyCart, selectSubscriptionOption } = payments;

let virtualProductId;
let physicalProductId;

const createFreeTrialProduct = ( { virtual } ) =>
	api.create.product( products.freeTrialSubscriptionData( { virtual } ) );

// APFS products offer a one-time vs subscription choice, so pick the
// subscription option before adding to the cart (mirrors the subscription
// purchase specs).
const addSubscriptionToCart = async ( page, productId ) => {
	await page.goto( `?p=${ productId }` );
	await selectSubscriptionOption( page );
	await clickAddToCartButton( page, 'Sign up' );
	await expect(
		page.getByText( 'has been added to your cart' )
	).toBeVisible();
};

// Free trial carts total 0 at checkout time, which normally hides express
// checkout; free trials are the deliberate exception (the element is created
// with mode: 'subscription' and amount: 0). These tests assert the Link button
// renders on the cart/checkout surfaces for both a virtual (no shipping) and a
// physical (needs shipping) free-trial product, since a regression there
// silently removes the buttons. They only cover that the button appears — the
// shipping-address code path (the same code as #5889) runs after Link login and
// is covered by the enrollment tests in free-trial-link.spec.js.
//
// The product page is asserted hidden rather than driven: these are APFS
// (subscribe-and-save) products, and APFS intentionally suppresses express
// checkout on the product page.
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

	// Both product variants run the same cart/checkout coverage. The product
	// IDs are read lazily because they are assigned in beforeAll, after these
	// blocks are collected.
	const productVariants = [
		{
			title: 'without shipping (virtual product)',
			id: () => virtualProductId,
		},
		{
			title: 'with shipping (physical product)',
			id: () => physicalProductId,
		},
	];

	for ( const variant of productVariants ) {
		test.describe( variant.title, () => {
			test( 'hides express checkout on the product page @express-checkout @subscriptions', async ( {
				page,
			} ) => {
				await page.goto( `?p=${ variant.id() }` );
				// Wait for the APFS subscribe/one-time selector so the page has
				// rendered.
				await expect(
					page.locator( '.wcsatt-options-prompt-label-subscription' )
				).toBeVisible();
				// APFS suppresses express checkout server-side, so its wrapper is
				// never rendered. Assert on the server-rendered wrapper rather
				// than the async Link iframe, so this can't pass just because the
				// iframe hasn't mounted yet.
				await expect(
					page.locator( '#wc-stripe-express-checkout-element' )
				).toHaveCount( 0 );
			} );

			test( 'loads Link on the classic cart page @express-checkout @subscriptions', async ( {
				page,
			} ) => {
				await addSubscriptionToCart( page, variant.id() );
				await page.goto( '/cart-shortcode' );
				await assertLinkModalLoads( page );
			} );

			test( 'loads Link on the classic checkout page @express-checkout @subscriptions', async ( {
				page,
			} ) => {
				await addSubscriptionToCart( page, variant.id() );
				await page.goto( '/checkout-shortcode' );
				await assertLinkModalLoads( page );
			} );

			test( 'loads Link on the block cart page @blocks @express-checkout @subscriptions', async ( {
				page,
			} ) => {
				await addSubscriptionToCart( page, variant.id() );
				await page.goto( '/cart' );
				await assertLinkModalLoads( page, true );
			} );

			test( 'loads Link on the block checkout page @blocks @express-checkout @subscriptions', async ( {
				page,
			} ) => {
				await addSubscriptionToCart( page, variant.id() );
				await page.goto( '/checkout' );
				await assertLinkModalLoads( page, true );
			} );
		} );
	}
} );
