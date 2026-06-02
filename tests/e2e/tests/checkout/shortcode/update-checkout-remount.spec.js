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
 * WooCommerce destroys and re-renders the payment box on every
 * `updated_checkout` (shipping/address/coupon changes). The plugin re-mounts
 * the Stripe Payment Element asynchronously in response, leaving a short window
 * where the element is detached. Submitting the order during that window used
 * to send an empty payment method, so the order failed and the customer had to
 * refresh.
 *
 * This test lands a submission deterministically inside that window by
 * submitting the checkout form from a one-shot `updated_checkout` handler. That
 * handler fires right after WooCommerce replaces the payment box DOM, while the
 * plugin's re-mount is still in flight — exactly the moment the fix guards.
 *
 * Without the fix the order never completes (empty payment method) and the
 * navigation below times out. With the fix the submission waits for the
 * re-mount and the order goes through.
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

	// Make sure the Payment Element is fully mounted first, so the race we
	// exercise is between the *re*-mount and the submission — not between the
	// initial mount and the submission.
	await waitForStripeReady( page, UPE_IFRAME_SELECTOR );

	// Arm a one-shot listener: the moment WooCommerce finishes re-rendering the
	// payment box (and the plugin kicks off its async Payment Element re-mount),
	// submit the checkout form. The plugin binds its own `updated_checkout`
	// handler at page load, so it runs before this one and has already
	// registered the in-flight re-mount by the time we submit.
	await page.evaluate( () => {
		window.jQuery( document.body ).one( 'updated_checkout', () => {
			window.jQuery( 'form.checkout' ).trigger( 'submit' );
		} );
	} );

	// Trigger a checkout update, exactly as a shipping/address change would.
	await page.evaluate( () => {
		window.jQuery( document.body ).trigger( 'update_checkout' );
	} );

	await page.waitForURL( /order-received/, { timeout: 30000 } );
	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
