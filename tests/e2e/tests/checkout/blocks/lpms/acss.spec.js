import { randomUUID } from 'crypto';
import { test, expect } from '@playwright/test';
import config from 'config';
import { payments, api, user } from '../../../../utils';

const {
	clickPlaceOrder,
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	setupACSSCheckout,
	fillACSSDetails,
	waitForOrderReceivedPage,
	waitForStripeReady,
} = payments;

test.describe( 'ACSS payment tests @blocks @acss', () => {
	let username, userEmail;

	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async () => {
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

	test( 'customer can pay with ACSS @smoke', async ( { page } ) => {
		await setupACSSCheckout( page, 'blocks' );
		// Parallel guest checkouts must not share the duplicate-payment identity.
		await page
			.getByLabel( 'Email address', { exact: true } )
			.fill( `stripe-e2e-${ randomUUID() }@example.com` );
		await expect(
			page.getByText(
				'After submission, you will need to authorize the payment with your bank.'
			)
		).toBeVisible();
		await clickPlaceOrder( page );
		await fillACSSDetails( page );
		await waitForOrderReceivedPage( page );
	} );

	test( 'customer can save and reuse ACSS payment method @smoke', async ( {
		page,
	} ) => {
		// First order - Save the payment method.
		await test.step( 'Save payment method during first checkout', async () => {
			await user.login(
				page,
				username,
				config.get( 'users.customer.password' )
			);
			await setupACSSCheckout( page, 'blocks' );
			await page.getByLabel( 'Save payment information' ).click();
			// The checkbox triggers an elements.update() with setup_future_usage;
			// submitting while it reloads sends no payment details. Match the
			// payment iframe itself, not Stripe's earlier-loading helper iframes.
			await waitForStripeReady(
				page,
				'#radio-control-wc-payment-method-options-stripe_acss_debit__content iframe[src*="elements-inner-payment"]'
			);
			await clickPlaceOrder( page );
			await fillACSSDetails( page );
			await waitForOrderReceivedPage( page );
		} );

		// Second order - Use saved payment method.
		await test.step( 'Use saved payment method for second checkout', async () => {
			await emptyCart( page );
			await setupCart( page );
			await setupBlocksCheckout(
				page,
				config.get( 'addresses.customer_canada.billing' )
			);
			await page
				.locator( 'label' )
				.filter( { hasText: 'STRIPE TEST BANK ending in' } )
				.click();
			await clickPlaceOrder( page );
			await waitForOrderReceivedPage( page );
		} );
	} );
} );
