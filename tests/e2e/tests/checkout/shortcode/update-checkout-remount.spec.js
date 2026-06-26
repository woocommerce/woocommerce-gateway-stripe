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
 * Regression test for the UPE re-mount race (issue #5490 / PR #5512).
 *
 * Submitting from a one-shot `updated_checkout` handler lands the submission
 * deterministically while the plugin's async Payment Element re-mount is in
 * flight. Without the fix the order posts an empty payment method and the
 * navigation below times out; with it the submission waits for the re-mount.
 */
test( 'completes a classic checkout order submitted during a checkout re-render', async ( {
	page,
} ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupShortcodeCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);

	await fillCreditCardDetailsShortcode( page, config.get( 'cards.basic' ) );

	// Ensure the element is fully mounted first, so the race exercised is
	// re-mount vs submission, not initial mount vs submission.
	await waitForStripeReady( page, UPE_IFRAME_SELECTOR );

	// Submit the moment WooCommerce finishes re-rendering the payment box.
	// The plugin's own `updated_checkout` handler binds at page load, so it
	// has already registered the in-flight re-mount by the time we submit.
	await page.evaluate( () => {
		window.jQuery( document.body ).one( 'updated_checkout', () => {
			window.jQuery( 'form.checkout' ).trigger( 'submit' );
		} );
	} );

	// Trigger a checkout update, as a shipping/address change would.
	await page.evaluate( () => {
		window.jQuery( document.body ).trigger( 'update_checkout' );
	} );

	await page.waitForURL( /order-received/, { timeout: 30000 } );
	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
