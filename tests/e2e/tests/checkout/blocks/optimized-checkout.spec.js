import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user, admin } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupOptimizedCheckout,
	fillOCDetails,
	clickPlaceOrder,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

test.describe( 'Optimized Checkout payment tests @blocks', () => {
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
		browser,
	} ) => {
		await setupOptimizedCheckout( page, 'blocks' );
		await fillOCDetails( page, config.get( 'cards.basic' ) );

		const expectedTotal = await getCartTotal( page );

		await clickPlaceOrder( page );

		await waitForOrderReceivedPageAndConfirmExpectedTotal(
			browser,
			page,
			expectedTotal
		);
	} );

	test( 'customer can save and reuse Optimized Checkout payment method @smoke', async ( {
		page,
		browser,
	} ) => {
		// Disable Link so the store-level save checkbox is visible for this test.
		// When Link is enabled, the store checkbox is hidden and Link handles save consent,
		// but these tests verify WC token creation which requires the store checkbox.
		await admin.togglePaymentMethod( browser, 'Link by Stripe', false );

		try {
			await test.step( 'Save payment method during first checkout', async () => {
				await user.login(
					page,
					username,
					config.get( 'users.customer.password' )
				);
				await setupOptimizedCheckout( page, 'blocks' );
				await page.getByLabel( 'Save payment information' ).click();
				await fillOCDetails( page, config.get( 'cards.basic' ) );

				const expectedTotal = await getCartTotal( page );

				await clickPlaceOrder( page );

				await waitForOrderReceivedPageAndConfirmExpectedTotal(
					browser,
					page,
					expectedTotal
				);
			} );

			await test.step( 'Save a second payment method and make it the default', async () => {
				await setupOptimizedCheckout( page, 'blocks' );
				await page.getByLabel( 'Save payment information' ).click();
				await fillOCDetails( page, config.get( 'cards.basic2' ) );

				const expectedTotal = await getCartTotal( page );

				await clickPlaceOrder( page );

				await waitForOrderReceivedPageAndConfirmExpectedTotal(
					browser,
					page,
					expectedTotal
				);

				await page.goto( '/my-account/payment-methods/' );
				const secondCardRow = page
					.locator( 'tr.payment-method' )
					.filter( { hasText: 'Visa ending in 1111' } );
				await secondCardRow
					.getByRole( 'link', { name: 'Make default' } )
					.click();
				await expect( secondCardRow ).toHaveClass(
					/default-payment-method/
				);
			} );

			await test.step( 'Use the default payment method for checkout', async () => {
				await emptyCart( page );
				await setupCart( page );
				await page.goto( '/checkout/' );

				await expect(
					page.locator(
						'input[id^="radio-control-wc-payment-method-saved-tokens-"]'
					)
				).toHaveCount( 2 );
				await expect(
					page.getByLabel( /Visa ending in 1111/ )
				).toBeChecked();

				const expectedTotal = await getCartTotal( page );

				await clickPlaceOrder( page );

				await waitForOrderReceivedPageAndConfirmExpectedTotal(
					browser,
					page,
					expectedTotal
				);
			} );
		} finally {
			// Re-enable Link after the test.
			await admin.togglePaymentMethod( browser, 'Link by Stripe', true );
		}
	} );
} );
