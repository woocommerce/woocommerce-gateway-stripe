import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user } from '../../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	setupBECSCheckout,
	fillBECSDetails,
} = payments;

test.describe( 'BECS payment tests @blocks @becs', () => {
	let username, userEmail;

	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { browser } ) => {
		await test.step( 'Setup test environment', async () => {
			// Create test user.
			const randomString = randomUUID();
			userEmail =
				randomString + '+' + config.get( 'users.customer.email' );
			username =
				randomString + '.' + config.get( 'users.customer.username' );

			const testUser = {
				...config.get( 'users.customer' ),
				...config.get( 'addresses.customer' ),
				email: userEmail,
				username,
			};
			await api.create.customer( testUser );
		} );
	} );

	test( 'customer can pay with BECS @smoke', async ( { page } ) => {
		await setupBECSCheckout( page, 'blocks' );
		await fillBECSDetails( page );
		await page.locator( 'text=Place order' ).click();
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can save and reuse BECS payment method @smoke', async ( {
		page,
	} ) => {
		// First order - Save the payment method.
		await test.step(
			'Save payment method during first checkout',
			async () => {
				await user.login(
					page,
					username,
					config.get( 'users.customer.password' )
				);
				await setupBECSCheckout( page, 'blocks' );
				await page.getByLabel( 'Save payment information' ).click();
				await fillBECSDetails( page );
				await page.locator( 'text=Place order' ).click();
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			}
		);

		// Second order - Use saved payment method.
		await test.step(
			'Use saved payment method for second checkout',
			async () => {
				await emptyCart( page );
				await setupCart( page );
				// On block checkout page for Australian address, there is no city, instead there are a suburbs.
				// In the backend we keep this suburb value in the city field.
				// In 'setupBlocksCheckout' we find the elemnts by their labels. As there is no city field on the block checkout page,
				// we remove the city field from the billing details to prevent the 'setupBlocksCheckout' from failing when waiting for the city field
				// and add the suburb value to the city field.
				const billingDetails = {
					...config.get( 'addresses.customer_australia.billing' ),
					suburb: config.get( 'addresses.customer_australia.billing' )
						.city,
				};
				delete billingDetails.city;
				await setupBlocksCheckout( page, billingDetails );
				await page
					.locator( 'label' )
					.filter( { hasText: 'BECS Direct Debit ending in' } )
					.click();
				await page.locator( 'text=Place order' ).click();
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			}
		);
	} );
} );
