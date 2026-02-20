import { randomUUID } from 'crypto';
import { expect, test } from '@playwright/test';
import { admin, api, payments } from '../../utils';
import { assertLinkModalLoads } from './utils';

const { clickAddToCartButton, emptyCart } = payments;

const addProductToCartById = async ( page, productId ) => {
	await page.goto( `?p=${ productId }` );
	await clickAddToCartButton( page );
	await expect(
		page.getByText( 'has been added to your cart' )
	).toBeVisible();
};

let lowAmountProductId;
let highAmountProductId;
let linkByStripeInitiallyEnabled;

test.describe( 'express checkout with ISK in block cart/checkout', () => {
	test.beforeAll( async ( { browser } ) => {
		await admin.updateStoreCurrency( browser, 'ISK' );

		const adminContext = await browser.newContext( {
			storageState: process.env.ADMINSTATE,
		} );
		const page = await adminContext.newPage();

		try {
			await page.goto(
				'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
			);

			const linkByStripeCheckbox = page.getByLabel( 'Link by Stripe' );
			linkByStripeInitiallyEnabled =
				await linkByStripeCheckbox.isChecked();

			if ( ! linkByStripeInitiallyEnabled ) {
				await linkByStripeCheckbox.check();
				await page
					.getByRole( 'button', { name: /Save changes/i } )
					.click();
				await expect(
					page.locator(
						'.components-snackbar__content:has-text("Settings saved.")'
					)
				).toBeVisible();
			}

			await expect( linkByStripeCheckbox ).toBeChecked();
		} finally {
			await adminContext.close();
		}

		await admin.initializeOptimizedCheckout( browser, false );

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

	test.afterAll( async ( { browser } ) => {
		if ( lowAmountProductId ) {
			await api.deletePost.product( lowAmountProductId );
		}

		if ( highAmountProductId ) {
			await api.deletePost.product( highAmountProductId );
		}

		if ( undefined !== linkByStripeInitiallyEnabled ) {
			await admin.togglePaymentMethod(
				browser,
				'Link by Stripe',
				linkByStripeInitiallyEnabled
			);
		}

		await admin.updateStoreCurrency( browser, 'USD' );
		await admin.initializeOptimizedCheckout( browser, false );
	} );

	test.beforeEach( async ( { page } ) => {
		await emptyCart( page );
	} );

	test( 'loads Link express checkout in block cart for low ISK amount @blocks @express-checkout @isk', async ( {
		page,
	} ) => {
		await addProductToCartById( page, lowAmountProductId );
		await page.goto( '/cart' );
		await assertLinkModalLoads( page, true );
	} );

	test( 'loads Link express checkout in block checkout for high ISK amount @blocks @express-checkout @isk', async ( {
		page,
	} ) => {
		await addProductToCartById( page, highAmountProductId );
		await page.goto( '/checkout' );
		await assertLinkModalLoads( page, true );
	} );
} );
