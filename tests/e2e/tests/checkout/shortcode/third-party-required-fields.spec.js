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

const FIELD_ID = 'third-party-required-field';

/**
 * Adds markup of the shape a third-party plugin contributes to classic checkout:
 * a visible `.validate-required` wrapper holding an empty control, for a field
 * WooCommerce knows nothing about and therefore will not validate server-side.
 */
const injectThirdPartyRequiredField = ( page ) =>
	page.evaluate( ( id ) => {
		const field = document.createElement( 'p' );
		field.id = id;
		field.className = 'form-row form-row-wide validate-required';
		field.innerHTML =
			'<label>Third party field <abbr class="required" title="required">*</abbr></label>' +
			'<input type="text" class="input-text" name="third_party_required_field" value="" />';
		document.querySelector( 'form.checkout' ).prepend( field );
	}, FIELD_ID );

/**
 * The gate that skips Stripe payment method creation must also stop WooCommerce
 * submitting. Returning early without stopping the submission posts the order with
 * no payment method, which creates an order and then fails it — the STRIPE-1402
 * shape. The shopper should instead stay on the form with an error, as they do
 * when the card number is incomplete.
 */
test( 'keeps the shopper on the form when a third-party required field is empty', async ( {
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

	await injectThirdPartyRequiredField( page );

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
		'Please fill in all required fields.'
	);
	expect( checkoutRequests ).toBe( 0 );
	await expect( page ).toHaveURL( /checkout/ );

	// The block must be recoverable: filling the field lets the order through.
	await page.fill( `#${ FIELD_ID } input`, 'filled in' );
	await page
		.getByRole( 'button', { name: 'Place order' } )
		.dispatchEvent( 'click' );

	await page.waitForURL( /order-received/, { timeout: 30000 } );
	await expect( page.locator( 'h1.entry-title' ) ).toHaveText(
		'Order received'
	);
} );
