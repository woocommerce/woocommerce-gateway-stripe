import { expect } from '@playwright/test';

/**
 * Get a new admin page with admin context.
 * @param {Browser} browser Playwright browser fixture.
 * @returns {Promise<{context: BrowserContext, page: Page}>} The admin context and page.
 */
export const getAdminPage = async ( browser ) => {
	const context = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await context.newPage();
	return { context, page };
};

/**
 * Enable or disable a payment method in Stripe settings.
 * @param {Browser} browser Playwright browser fixture.
 * @param {string} methodName The payment method name as shown in admin.
 * @param {boolean} enable Whether to enable or disable the payment method.
 */
export const togglePaymentMethod = async (
	browser,
	methodName,
	enable = true
) => {
	const { context, page } = await getAdminPage( browser );

	try {
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
		);

		const checkbox = page.getByRole( 'checkbox', {
			name: methodName,
		} );
		const isChecked = await checkbox.isChecked();

		if ( ( enable && ! isChecked ) || ( ! enable && isChecked ) ) {
			await checkbox.click();

			// When disabling, some methods show a Remove confirmation button.
			if ( ! enable ) {
				const removeButton = page.getByRole( 'button', {
					name: 'Remove',
				} );
				try {
					await removeButton.waitFor( {
						state: 'visible',
						timeout: 3000,
					} );
					await removeButton.click();
				} catch ( error ) {
					if ( error?.name !== 'TimeoutError' ) {
						throw error;
					}
					// Remove button is optional for some methods.
				}
			}

			await page.click( 'text=Save changes' );
			await expect(
				page.getByText( 'Settings saved.' ).first()
			).toBeVisible();
		}
	} finally {
		await context.close();
	}
};

/**
 * Update the store currency in WooCommerce settings.
 * @param {Browser} browser Playwright browser fixture.
 * @param {string} currency The currency to set.
 */
export const updateStoreCurrency = async ( browser, currency ) => {
	const { context, page } = await getAdminPage( browser );

	try {
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=general' );

		// Check if the store currency is already set to the desired currency.
		if (
			currency ===
			( await page.$eval( '#woocommerce_currency', ( el ) => el.value ) )
		) {
			return;
		}

		await page.selectOption( '#woocommerce_currency', { value: currency } );
		await page.click( 'text=Save changes' );
		await expect(
			page.getByText( 'Your settings have been saved.' )
		).toBeDefined();
	} finally {
		await context.close();
	}
};

/**
 * Enable or disable the Optimized Checkout feature in Stripe settings.
 *
 * @param {Browser} browser      Playwright browser fixture.
 * @param {boolean} shouldEnable Whether to enable or disable the Optimized Checkout element.
 */
export const initializeOptimizedCheckout = async (
	browser,
	shouldEnable = true
) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );

	const page = await adminContext.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);

	const checkbox = page.getByTestId( 'optimized-checkout-element-checkbox' );
	const isChecked = await checkbox.isChecked();

	const updateNeeded =
		( shouldEnable && ! isChecked ) || ( ! shouldEnable && isChecked );

	if ( updateNeeded ) {
		await checkbox.click();
		await page.click( 'text=Save changes' );
		await expect(
			page.locator(
				'.components-snackbar__content:has-text("Settings saved.")'
			)
		).toBeVisible();

		if ( shouldEnable ) {
			await expect(
				page.getByTestId( 'optimized-checkout-element-checkbox' )
			).toBeChecked();
		} else {
			await expect(
				page.getByTestId( 'optimized-checkout-element-checkbox' )
			).not.toBeChecked();
		}
	}

	await adminContext.close();
};

/**
 * Enables or disables Adaptive Pricing in the Stripe settings.
 *
 * Enabling turns on the Optimized Checkout Suite first (AP requires it);
 * disabling leaves OC on.
 *
 * @param {Browser} browser      Playwright browser fixture.
 * @param {boolean} shouldEnable Whether to enable or disable Adaptive Pricing.
 */
export const initializeAdaptivePricing = async (
	browser,
	shouldEnable = true
) => {
	if ( shouldEnable ) {
		await initializeOptimizedCheckout( browser, true );
	}

	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );

	const page = await adminContext.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings'
	);

	const checkbox = page.getByLabel(
		'Let customers pay in their local currency with Adaptive Pricing'
	);
	const isChecked = await checkbox.isChecked();

	const updateNeeded =
		( shouldEnable && ! isChecked ) || ( ! shouldEnable && isChecked );

	if ( updateNeeded ) {
		// Fail fast when an availability gate (webhooks, PMC, capture, OC)
		// is unmet, instead of timing out on the click.
		await expect( checkbox ).toBeEnabled();
		await checkbox.click();
		await page.click( 'text=Save changes' );
		await expect(
			page.locator(
				'.components-snackbar__content:has-text("Settings saved.")'
			)
		).toBeVisible();
	}

	await adminContext.close();
};

/**
 * Open the admin order edit page for an order and confirm the expected amount
 * was charged.
 *
 * Checks the following locations to ensure we are charging the expected amount:
 *  - The "Order Total" row
 *  - The "Paid" (captured) row of the order details
 *  - The sum of the "Stripe Fee" and "Stripe Payout" rows
 *
 * @param {Browser} browser       Playwright browser fixture.
 * @param {string}  orderId       The WooCommerce order ID.
 * @param {string}  expectedTotal The expected charged amount, without currency
 *                                symbol (e.g. "19.99").
 */
export const verifyOrderChargedAmount = async (
	browser,
	orderId,
	expectedTotal
) => {
	const { context, page } = await getAdminPage( browser );

	try {
		// Access via the post edit screen - we should be redirected if HPOS is enabled.
		await page.goto( `/wp-admin/post.php?post=${ orderId }&action=edit` );

		const totalElementForLabel = ( label ) =>
			page
				.locator( '.wc-order-totals tr' )
				.filter( { hasText: label } )
				.locator( '.total' );

		// The order is recorded for the expected total...
		await expect( totalElementForLabel( 'Order Total' ) ).toContainText(
			expectedTotal
		);
		// ...and that total was actually captured (the "Paid" line).
		await expect( totalElementForLabel( 'Paid' ) ).toContainText(
			expectedTotal
		);

		// Also verify that the Stripe Fee and Stripe Payout rows are present and add up to the expected amount.
		const stripeFeeElement = totalElementForLabel( 'Stripe Fee' );
		const stripePayoutElement = totalElementForLabel( 'Stripe Payout' );

		await expect( stripeFeeElement ).toContainText( '-' );
		await expect( stripePayoutElement ).not.toBeEmpty();

		const stripeFeeText = await stripeFeeElement.textContent();
		const stripePayoutText = await stripePayoutElement.textContent();

		// Extract the numeric amount from the text content - ignore - and currency symbol.
		const stripeFeeAmount = parseFloat(
			stripeFeeText.replace( /[^0-9\.]/g, '' )
		);
		const stripePayoutAmount = parseFloat(
			stripePayoutText.replace( /[^0-9\.]/g, '' )
		);

		const totalAmount = stripeFeeAmount + stripePayoutAmount;

		expect( totalAmount ).toBeCloseTo( parseFloat( expectedTotal ), 2 );
	} finally {
		await context.close();
	}
};
