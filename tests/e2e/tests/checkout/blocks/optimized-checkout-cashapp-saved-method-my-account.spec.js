import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user, admin } from '../../../utils';

const { setupOptimizedCheckout, clickPlaceOrder } = payments;

let username, userEmail;

test.beforeAll( async ( { browser } ) => {
	const randomString = randomUUID();
	userEmail = randomString + '+' + config.get( 'users.customer.email' );
	username = randomString + '.' + config.get( 'users.customer.username' );

	await api.create.customer( {
		...config.get( 'users.customer' ),
		...config.get( 'addresses.customer' ),
		email: userEmail,
		username,
	} );

	await admin.togglePaymentMethod( browser, 'Cash App Pay', true );
} );

test( 'Cash App Pay saved via Optimized Checkout appears on My Account → Payment Methods @smoke @blocks', async ( {
	page,
	browser,
} ) => {
	await test.step( 'customer login', async () => {
		await user.login(
			page,
			username,
			config.get( 'users.customer.password' )
		);
	} );

	await test.step( 'checkout via Optimized Checkout with Cash App Pay and save the payment method', async () => {
		await setupOptimizedCheckout( page, 'blocks' );

		// setupOptimizedCheckout selects Card by default; switch to Cash App Pay.
		const paymentFrame = await page
			.locator(
				'#radio-control-wc-payment-method-options-stripe__content iframe[name^="__privateStripeFrame"]'
			)
			.contentFrame()
			.first();
		await paymentFrame
			.getByRole( 'button', { name: 'Cash App Pay' } )
			.click();

		await page.getByLabel( 'Save payment information' ).click();

		await clickPlaceOrder( page );

		const simulateScanButton = page
			.locator( 'iframe[name^="__privateStripeFrame"]' )
			.contentFrame()
			.first()
			.frameLocator( 'iframe[title="QR Code Instructions"]' )
			.getByRole( 'button', { name: 'Simulate scan' } );

		const [ paymentPage ] = await Promise.all( [
			page.context().waitForEvent( 'page' ),
			simulateScanButton.dispatchEvent( 'click' ),
		] );

		await paymentPage.waitForLoadState();
		await paymentPage
			.getByRole( 'link', { name: 'Authorize Test Payment' } )
			.click();

		await page.waitForURL( '**/order-received/**' );
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
			'Order received'
		);
	} );

	await test.step( 'saved Cash App Pay token is listed on My Account → Payment Methods', async () => {
		await page.goto( '/my-account/payment-methods/' );

		const tokenRow = page
			.locator( '.woocommerce-MyAccount-paymentMethods tbody tr' )
			.filter( { hasText: 'Cash App Pay' } );
		await expect( tokenRow ).toHaveCount( 1 );
	} );
} );
