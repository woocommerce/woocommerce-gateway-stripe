import { test, expect } from '@playwright/test';
import { admin, payments } from '../../../utils';

const { setupKlarnaCheckout, completeKlarnaPayment } = payments;

test.describe( 'Klarna payment tests @blocks', () => {
	test.beforeAll( async ( { browser } ) => {
		await test.step( 'Setup test environment', async () => {
			// Enable Klarna in admin
			await admin.togglePaymentMethod( browser, 'Klarna', true );
		} );
	} );

	test.describe.configure( { mode: 'parallel' } );

	test( 'customer can pay with Klarna @smoke', async ( { page } ) => {
		await setupKlarnaCheckout( page, 'blocks' );
		await page.locator( 'text=Place order' ).click();
		await completeKlarnaPayment( page );
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
} );
