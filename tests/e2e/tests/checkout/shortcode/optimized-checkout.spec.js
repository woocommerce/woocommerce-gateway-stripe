import { test, expect } from '@playwright/test';
import { randomUUID } from 'crypto';
import config from 'config';
import { payments, api, user, admin } from '../../../utils';

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
		browser,
	} ) => {
		// Disable Link so the store-level save checkbox is visible.
		// When Link is enabled, the store checkbox is hidden and Link handles save consent.
		await admin.togglePaymentMethod( browser, 'Link by Stripe', false );

		try {
			// First order - Save the payment method.
			await test.step( 'Save payment method during first checkout', async () => {
				await user.login(
					page,
					username,
					config.get( 'users.customer.password' )
				);
				await setupOptimizedCheckout( page, 'shortcode' );
				// Toggle via label to avoid flaky direct checkbox interactions.
				const savePaymentMethodCheckbox = page.locator(
					'#wc-stripe-new-payment-method'
				);
				await expect( savePaymentMethodCheckbox ).toBeAttached();
				await page
					.locator( "label[for='wc-stripe-new-payment-method']" )
					.click();
				await expect( savePaymentMethodCheckbox ).toBeChecked();
				// Then fill in the payment details.
				// This order is needed because, if we fill in the payment details and then click the checkbox, payment element will refresh and the fields will be cleared.
				await fillOCDetails(
					page,
					config.get( 'cards.basic' ),
					'shortcode'
				);
				await clickPlaceOrder( page );
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			} );

			// Second order - Use saved payment method.
			await test.step( 'Use saved payment method for second checkout', async () => {
				await emptyCart( page );
				await setupCart( page );
				await setupShortcodeCheckout(
					page,
					config.get( 'addresses.customer.billing' )
				);
				const savedTokenRadio = page.locator(
					'.woocommerce-SavedPaymentMethods-token input[id^="wc-stripe-payment-token-"]'
				);
				await expect( savedTokenRadio ).toBeVisible();
				await savedTokenRadio.click();
				await clickPlaceOrder( page );
				await page.waitForURL( '**/checkout/order-received/**' );
				await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
					'Order received'
				);
			} );
		} finally {
			// Re-enable Link after the test.
			await admin.togglePaymentMethod( browser, 'Link by Stripe', true );
		}
	} );
} );
