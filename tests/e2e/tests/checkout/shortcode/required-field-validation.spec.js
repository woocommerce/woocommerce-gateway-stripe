import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupShortcodeCheckout,
	fillCreditCardDetailsShortcode,
	waitForStripeReady,
} = payments;

const UPE_IFRAME_SELECTOR =
	'.payment_method_stripe #wc-stripe-upe-form .wc-stripe-upe-element iframe';

/**
 * A core required field left empty must not post the order. The place-order handler blocks the
 * submission (so WooCommerce does not create an order with no payment method) and shows a notice
 * naming the empty field, instead of the single generic "Please fill in all required fields."
 * message. Filling the field lets the order through.
 */
test( 'shows a per-field notice and blocks submission when a core required field is empty', async ( {
	page,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);

	await fillCreditCardDetailsShortcode( page, config.get( 'cards.basic' ) );
	await waitForStripeReady( page, UPE_IFRAME_SELECTOR );

	// Clear a required core field after the form was filled.
	await page.fill( '#billing_first_name', '' );

	// The submission WooCommerce would make if the handler did not return false.
	let checkoutRequests = 0;
	page.on( 'request', ( request ) => {
		if ( request.url().includes( 'wc-ajax=checkout' ) ) {
			checkoutRequests++;
		}
	} );

	// A single click: a retry would hide a regression that only affects the first.
	await page
		.getByRole( 'button', { name: 'Place order' } )
		.dispatchEvent( 'click' );

	await expect( page.locator( '.woocommerce-error' ) ).toContainText(
		'First name is a required field.'
	);
	expect( checkoutRequests ).toBe( 0 );
	await expect( page ).toHaveURL( /checkout/ );

	// The block must be recoverable: refilling the field lets the order through.
	await page.fill( '#billing_first_name', 'Test' );
	await page
		.getByRole( 'button', { name: 'Place order' } )
		.dispatchEvent( 'click' );

	await page.waitForURL( /order-received/, { timeout: 30000 } );
	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
