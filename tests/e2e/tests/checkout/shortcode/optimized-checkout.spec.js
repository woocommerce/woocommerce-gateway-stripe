import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	setupOptimizedCheckout,
	fillOCDetails,
	clickPlaceOrder,
} = payments;

test.describe( 'Optimized Checkout payment tests @shortcode', () => {
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

	test( 'customer can pay with Optimized Checkout @smoke', async ( {
		page,
	} ) => {
		await setupOptimizedCheckout( page, 'shortcode' );
		await fillOCDetails( page, config.get( 'cards.basic' ), 'shortcode' );
		await clickPlaceOrder( page );
		await page.waitForURL( '**/checkout/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	test( 'customer can save and reuse Optimized Checkout payment method @smoke', async ( {
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
				await setupOptimizedCheckout( page, 'shortcode' );
				await fillOCDetails(
					page,
					config.get( 'cards.basic' ),
					'shortcode'
				);
				await page
					.getByRole( 'checkbox', {
						name: 'Save payment information to',
					} )
					.check( { force: true } );
				await clickPlaceOrder( page );
				await fillOCDetails(
					page,
					config.get( 'cards.basic' ),
					'shortcode'
				);
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
				await setupShortcodeCheckout(
					page,
					config.get( 'addresses.customer.billing' )
				);
				await page.getByText( 'Visa ending in 4242 (expires' ).click();
				await page.waitForTimeout( 1000 );
				await page
					.locator( '.woocommerce-SavedPaymentMethods-token' )
					.first()
					.click();
				await clickPlaceOrder( page );
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			}
		);
	} );
} );
