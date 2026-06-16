import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	fillCreditCardDetails,
	handleCheckout3DSChallenge,
	clickPlaceOrder,
	handleCheckoutCashAppPay,
	getCartTotal,
	waitForOrderReceivedPage,
	waitForOrderReceivedPageAndConfirmExpectedTotal,
} = payments;

test.beforeAll( 'enable Cash App Pay', async ( { browser } ) => {
	const adminContext = await browser.newContext( {
		storageState: process.env.ADMINSTATE,
	} );
	const page = await adminContext.newPage();

	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe&panel=methods'
	);
	await page.getByLabel( 'Cash App Pay' ).check();
	await page.click( 'text=Save changes' );

	await expect( page.getByText( 'Settings saved.' ) ).toBeDefined();
	await expect( page.getByLabel( 'Cash App Pay' ) ).toBeChecked();
} );

test.beforeEach( async ( { page } ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
} );
/**
 * When retrying payments, we will reuse a compatible payment intent, if the order already has one.
 *
 * This test verifies that the same payment method type can be used when retrying a payment, e.g.
 * chaging from one credit card to another.
 */
test( 'customer can retry payment, with a different card @smoke', async ( {
	page,
	browser,
} ) => {
	const expectedTotal = await getCartTotal( page );

	await fillCreditCardDetails( page, config.get( 'cards.declined' ) );
	await clickPlaceOrder( page );

	// Expect the order to fail
	await expect(
		page.locator( '.wc-block-store-notice.is-error' )
	).toBeVisible();

	// Change to a working card
	await fillCreditCardDetails( page, config.get( 'cards.basic' ) );
	await clickPlaceOrder( page );

	await waitForOrderReceivedPageAndConfirmExpectedTotal(
		browser,
		page,
		expectedTotal
	);
} );

/**
 * When retrying payments, we will reuse a compatible payment intent, if the order already has one.
 *
 * This test verifies that the same payment method type can be used when retrying the same payment,
 * after changing the billing details.
 */
test( 'customer can retry payment, with changed billing details @smoke', async ( {
	page,
	browser,
} ) => {
	await fillCreditCardDetails( page, config.get( 'cards.3ds' ) );
	await clickPlaceOrder( page );

	// Fail the 3DS challenge
	await handleCheckout3DSChallenge( page, 'fail' );

	// Change billing details
	await page.getByLabel( 'ZIP Code' ).fill( '12345' );

	// Get cart total after changing the zip/post code to ensure current taxes and shipping are applied.
	const expectedTotal = await getCartTotal( page );

	// Retry the payment
	await clickPlaceOrder( page );

	// Complete the 3DS challenge
	await handleCheckout3DSChallenge( page );

	await waitForOrderReceivedPageAndConfirmExpectedTotal(
		browser,
		page,
		expectedTotal
	);
} );

/**
 * This test verifies that a different payment method type can be used when retrying a payment
 * for the same order.
 */
test( 'customer can retry payment, using a different payment method @smoke', async ( {
	page,
} ) => {
	await fillCreditCardDetails( page, config.get( 'cards.declined' ) );
	await clickPlaceOrder( page );

	// Expect the order to fail
	await expect(
		page.locator( '.wc-block-store-notice.is-error' )
	).toBeVisible();

	// Change to Cash App Pay
	await handleCheckoutCashAppPay( page, '.wcstripe-payment-element' );

	// Expect the order to succeed
	await waitForOrderReceivedPage( page );

	// No charged-amount verification here: Cash App Pay is not a synchronous
	// card capture, so the order has no "Paid"/Stripe Fee/Payout rows to check.
} );
