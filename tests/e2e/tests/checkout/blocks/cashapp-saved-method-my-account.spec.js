import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user, admin } from '../../../utils';

const { emptyCart, setupCart, setupBlocksCheckout, clickPlaceOrder } = payments;

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

test( 'Cash App Pay saved at checkout appears on My Account → Payment Methods @smoke @blocks', async ( {
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

	await test.step( 'checkout with Cash App Pay and save the payment method', async () => {
		await emptyCart( page );
		await setupCart( page );
		await setupBlocksCheckout(
			page,
			config.get( 'addresses.customer.billing' )
		);

		await page.getByText( 'Cash App Pay' ).click();

		await expect(
			page
				.frameLocator(
					'#wc-stripe_cashapp-upe-form iframe[name^="__privateStripeFrame"]'
				)
				.locator( '.__PrivateStripeElementLoader' )
		).toBeHidden();

		await page
			.locator( '.wc-block-components-payment-methods__save-card-info' )
			.click();

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
