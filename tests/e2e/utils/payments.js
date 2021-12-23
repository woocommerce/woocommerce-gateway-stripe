import config from 'config';

import { shopper, uiUnblocked } from '@woocommerce/e2e-utils';

/**
 * Set up checkout with any number of products.
 *
 * @param {any} billingDetails Values to be entered into the 'Billing details' form in the Checkout page
 * @param {any} lineItems A 2D array of line items where each line item is an array
 * that contains the product title as the first element, and the quantity as the second.
 * For example, if you want to checkout the products x2 "Hoodie" and x3 "Belt" then you can set this `lineItems` parameter like this:
 *
 * `[ [ "Hoodie", 2 ], [ "Belt", 3 ] ]`.
 *
 * Default value is 1 piece of `config.get( 'products.simple.name' )`.
 */
export async function setupProductCheckout(
	billingDetails,
	lineItems = [ [ config.get( 'products.simple.name' ), 1 ] ]
) {
	const cartItemsCounter = '.cart-contents .count';

	await shopper.goToShop();

	// Get the current number of items in the cart
	let cartSize = await page.$eval( cartItemsCounter, ( e ) =>
		Number( e.innerText.replace( /\D/g, '' ) )
	);

	// Add items to the cart
	for ( const line of lineItems ) {
		let [ productTitle, qty ] = line;

		while ( qty-- ) {
			await shopper.addToCartFromShopPage( productTitle );

			// Make sure that the number of items in the cart is incremented first before adding another item.
			await expect( page ).toMatchElement( cartItemsCounter, {
				text: new RegExp( `${ ++cartSize } items?` ),
				timeout: 30000,
			} );
		}
	}

	await setupCheckout( billingDetails );
}

// Set up checkout
export async function setupCheckout( billingDetails ) {
	await shopper.goToCheckout();
	await uiUnblocked();
	await shopper.fillBillingDetails( billingDetails );
	// Woo core blocks and refreshes the UI after 1s after each key press in a text field or immediately after a select
	// field changes. Need to wait to make sure that all key presses were processed by that mechanism.
	await page.waitFor( 1000 );
	await uiUnblocked();
	await expect( page ).toClick( '.wc_payment_method.payment_method_stripe' );
}

export async function fillUpeCard( card ) {
	const frameHandle = await page.waitForSelector(
		'.wc_payment_method.payment_method_stripe iframe'
	);

	const stripeFrame = await frameHandle.contentFrame();

	const cardNumberInput = await stripeFrame.waitForSelector(
		'[name="number"]',
		{ timeout: 30000 }
	);

	await cardNumberInput.type( card.number, { delay: 20 } );

	const cardDateInput = await stripeFrame.waitForSelector(
		'[name="expiry"]'
	);

	await cardDateInput.type( card.expires.month + card.expires.year, {
		delay: 20,
	} );

	const cardCvcInput = await stripeFrame.waitForSelector( '[name="cvc"]' );
	await cardCvcInput.type( card.cvc, { delay: 20 } );
}
