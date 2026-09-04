import { randomUUID } from 'crypto';
import { expect, test } from '@playwright/test';
import config from 'config';
import { api, payments, products } from '../../utils';
import {
	assertLinkModalLoads,
	fillLinkCardDetails,
	fillLinkPaymentDetails,
	fillLinkShippingAddress,
	getLinkButton,
	loginToLink,
	openLinkPopup,
	signUpForLink,
} from './utils';

const {
	clickAddToCartButton,
	emptyCart,
	selectSubscriptionOption,
	waitForOrderReceivedPage,
} = payments;

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

// Free trial carts total 0 at checkout time, which is normally a condition for
// hiding express checkout; free trials are the deliberate exception (the
// element is created with mode: 'subscription' and amount: 0). These tests pin
// that exception across the classic and Blocks cart/checkout surfaces, with and
// without shipping, since a regression there silently removes the buttons.
//
// Coverage is limited to the cart/checkout surfaces: these are APFS
// (subscribe-and-save) products, and APFS intentionally hides express checkout
// on the product page, so that surface is asserted hidden rather than driven.
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
				// Wait for the APFS subscribe/one-time selector so the assertion
				// isn't racing the page load.
				await expect(
					page.locator( '.wcsatt-options-prompt-label-subscription' )
				).toBeVisible();
				await expect( await getLinkButton( page ) ).toHaveCount( 0 );
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

	test.describe( 'completing the purchase with Link', () => {
		// The returning-account test depends on the Link account the purchase
		// test enrolls, so a failure must retry the whole group.
		test.describe.configure( { mode: 'serial' } );

		// A unique address per run: Link sandbox keeps accounts around, and an
		// already-enrolled email would flip the signup flow into a login flow.
		const linkEmail = `wc-stripe-link-e2e-${ randomUUID() }@example.com`;

		test( 'completes a free trial purchase with a new Link account @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			test.setTimeout( 240 * 1000 );
			await addSubscriptionToCart( page, virtualProductId );
			await page.goto( '/checkout' );

			const popup = await openLinkPopup( page, true );
			await signUpForLink(
				popup,
				linkEmail,
				config.get( 'addresses.customer.billing.phone' )
			);
			await fillLinkPaymentDetails(
				popup,
				config.get( 'cards.basic' ),
				config.get( 'addresses.customer.billing' )
			);

			await Promise.all( [
				popup.waitForEvent( 'close', { timeout: 90 * 1000 } ),
				popup.getByTestId( 'pay-button' ).click(),
			] );

			await waitForOrderReceivedPage( page );
		} );

		test( 'keeps the Continue button enabled for a returning Link account with a saved payment method @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			test.setTimeout( 240 * 1000 );
			await addSubscriptionToCart( page, virtualProductId );
			await page.goto( '/checkout' );

			const popup = await openLinkPopup( page, true );
			await loginToLink( popup, linkEmail );

			// The saved-payment-method sheet of a signed-in Link account with
			// a 0-amount trial cart: the sheet must remain actionable, not
			// show a dead disabled Continue button.
			await expect( popup.getByText( /4242/ ).first() ).toBeVisible( {
				timeout: 60 * 1000,
			} );
			await expect( popup.getByTestId( 'pay-button' ) ).toBeEnabled();
		} );
	} );

	test.describe( 'shipping-required trial cart with a saved Link shipping address', () => {
		// The trial-cart test depends on the shipping address the purchase
		// test saves to the Link account, so a failure must retry the whole
		// group.
		test.describe.configure( { mode: 'serial' } );

		const linkEmail = `wc-stripe-link-e2e-${ randomUUID() }@example.com`;

		test( 'saves a shipping address by completing a regular purchase @blocks @express-checkout', async ( {
			page,
		} ) => {
			test.setTimeout( 240 * 1000 );
			await page.goto( '/product/beanie' );
			await clickAddToCartButton( page );
			await expect(
				page.getByText( 'has been added to your cart' )
			).toBeVisible();
			await page.goto( '/checkout' );

			const popup = await openLinkPopup( page, true );
			await signUpForLink(
				popup,
				linkEmail,
				config.get( 'addresses.customer.billing.phone' )
			);
			await fillLinkShippingAddress(
				popup,
				config.get( 'addresses.customer.shipping' )
			);
			await fillLinkCardDetails( popup, config.get( 'cards.basic' ) );

			await Promise.all( [
				popup.waitForEvent( 'close', { timeout: 90 * 1000 } ),
				popup.getByTestId( 'pay-button' ).click(),
			] );

			await waitForOrderReceivedPage( page );
		} );

		// A free-trial subscription cart returns no shipping rates for the
		// initial cart (WC Subscriptions carries shipping on the recurring
		// cart), so the express checkout address-change handler rejects every
		// address: Link renders each saved address as "Unavailable for this
		// purchase" and the sheet cannot continue. This test asserts the
		// intended behavior and is expected to FAIL until that is fixed —
		// when it starts passing, remove the test.fail() marker.
		test( 'accepts the saved shipping address on a free-trial cart @blocks @express-checkout @subscriptions', async ( {
			page,
		} ) => {
			test.fail();
			test.setTimeout( 240 * 1000 );
			await addSubscriptionToCart( page, physicalProductId );
			await page.goto( '/checkout' );

			const popup = await openLinkPopup( page, true );
			await loginToLink( popup, linkEmail );

			await expect( popup.getByText( 'Shipping addresses' ) ).toBeVisible(
				{ timeout: 60 * 1000 }
			);
			await expect(
				popup.getByText( 'Unavailable for this purchase' )
			).toBeHidden();
			await expect( popup.getByTestId( 'pay-button' ) ).toBeEnabled();
		} );
	} );
} );
