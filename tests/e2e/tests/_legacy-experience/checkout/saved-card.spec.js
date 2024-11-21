import { test, expect } from '@playwright/test';
import { payments, api, config } from '../../../utils';
import qit from '/qitHelpers';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcodeLegacy,
} = payments;

let username, userEmail;
test.slow();
test.beforeAll( async () => {
	// This allow multiple tests to run in parallel.
	const randomString = Date.now();
	userEmail = randomString + '+' + config.get( 'users.customer.email' );
	username = randomString + '.' + config.get( 'users.customer.username' );

	const user = {
		...config.get( 'users.customer' ),
		...config.get( 'addresses.customer' ),
		email: userEmail,
		username,
	};

	await api.create.customer( user );
} );

test( 'customer can checkout with a saved card @smoke @legacy', async ( {
	page,
} ) => {
	await test.step( 'customer login', async () => {
		await qit.loginAs(
			page,
			username,
			config.get( 'users.customer.password' )
		);
	} );

	await test.step( 'checkout and choose to save the card', async () => {
		await emptyCart( page );
		await setupCart( page );
		await setupShortcodeCheckout( page );
		await fillCreditCardDetailsShortcodeLegacy(
			page,
			config.get( 'cards.basic' )
		);

		// check box to save payment method.
		await page.locator( '#wc-stripe-new-payment-method' ).click();

		await page.locator( '#place_order' ).first().click();

		await page.waitForURL( '**/checkout/order-received/**', {
			timeout: 20000,
		} ); // Allow some extra time for the redirect to complete.
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	await test.step( 'checkout and pay with the saved card', async () => {
		await emptyCart( page );
		await setupCart( page );
		await setupShortcodeCheckout( page, null, true );

		// check that there are saved payment methods.
		await expect(
			page.locator(
				'.woocommerce-SavedPaymentMethods-token input[id^="wc-stripe-payment-token-"]'
			)
		).toHaveCount( 1 );

		await page.locator( '#place_order' ).first().click();

		await page.waitForURL( '**/checkout/order-received/**', {
			timeout: 20000,
		} ); // Allow some extra time for the redirect to complete.
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );
} );
