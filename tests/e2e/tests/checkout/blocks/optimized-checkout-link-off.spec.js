import { randomUUID } from 'crypto';
import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api, user } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupOptimizedCheckout,
	fillOCDetails,
	tickSavePaymentMethodBlocks,
	clickPlaceOrder,
	getCartTotal,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

// Runs against an account with Link off: with Link on, Link handles save
// consent and the store-level save checkbox these tests depend on is hidden.
test.describe( 'Optimized Checkout payment tests with Link off @blocks', () => {
	let username;

	test.beforeAll( async () => {
		const randomString = randomUUID();
		username = randomString + '.' + config.get( 'users.customer.username' );
		await api.create.customer( {
			...config.get( 'users.customer' ),
			...config.get( 'addresses.customer' ),
			email: randomString + '+' + config.get( 'users.customer.email' ),
			username,
		} );
	} );

	test( 'customer can save and reuse Optimized Checkout payment method @smoke', async ( {
		page,
		browser,
	} ) => {
		await test.step( 'Save payment method during first checkout', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
			await setupOptimizedCheckout( page, 'blocks' );
			await tickSavePaymentMethodBlocks( page );
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
			await tickSavePaymentMethodBlocks( page );
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
	} );
} );
