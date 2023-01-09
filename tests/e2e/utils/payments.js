import { expect } from '@playwright/test';
import config from 'config';

export async function emptyCart( page ) {
	await page.goto( '/cart' );

	// Remove products if they exist
	if ( null !== ( await page.$$( '.remove' ) ) ) {
		let products = await page.$$( '.remove' );
		while ( products && 0 < products.length ) {
			for ( const product of products ) {
				await product.click();
			}
			products = await page.$$( '.remove' );
		}
	}

	// Remove coupons if they exist
	if ( null !== ( await page.$( '.woocommerce-remove-coupon' ) ) ) {
		await page.click( '.woocommerce-remove-coupon' );
	}

	await page.waitForSelector( '.cart-empty.woocommerce-info' );
	await expect( page.locator( '.cart-empty.woocommerce-info' ) ).toHaveText(
		'Your cart is currently empty.'
	);
}

/**
 * Fills in the card details on the WC checkout page (non-blocks).
 * @param {*} page
 * @param {*} card
 */
export async function fillCardDetails( page, card ) {
	if ( await page.$( '#payment #stripe-upe-element' ) ) {
		const frameHandle = await page.waitForSelector(
			'#payment #stripe-upe-element iframe'
		);

		const stripeFrame = await frameHandle.contentFrame();

		await stripeFrame.fill( '[name="number"]', card.number );
		await stripeFrame.fill(
			'[name="expiry"]',
			card.expires.month + card.expires.year
		);
		await stripeFrame.fill( '[name="cvc"]', card.cvc );
	} else {
		await page
			.frameLocator(
				'#stripe-card-element iframe[name^="__privateStripeFrame"]'
			)
			.locator( '[name="cardnumber"]' )
			.fill( card.number );
		await page
			.frameLocator(
				'#stripe-exp-element iframe[name^="__privateStripeFrame"]'
			)
			.locator( '[name="exp-date"]' )
			.fill( card.expires.month + card.expires.year );
		await page
			.frameLocator(
				'#stripe-cvc-element iframe[name^="__privateStripeFrame"]'
			)
			.locator( '[name="cvc"]' )
			.fill( card.cvc );
	}
}

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
	page,
	lineItems = [ [ config.get( 'products.simple.name' ), 1 ] ]
) {
	const cartItemsCounter = '.cart-contents .count';

	await page.goto( '/shop/' );

	// Get the current number of items in the cart
	let cartSize = await page.$eval( cartItemsCounter, ( e ) =>
		Number( e.innerText.replace( /\D/g, '' ) )
	);

	// Add items to the cart
	for ( const line of lineItems ) {
		let [ productTitle, qty ] = line;

		while ( qty-- ) {
			const addToCartXPath =
				`//li[contains(@class, "type-product") and a/h2[contains(text(), "${ productTitle }")]]` +
				'//a[contains(@class, "add_to_cart_button") and contains(@class, "ajax_add_to_cart")';
			await page.waitForSelector( `xpath=${ addToCartXPath }]` );
			await page.click( `xpath=${ addToCartXPath }]` );
			await page.waitForSelector(
				`xpath=${ addToCartXPath } and contains(@class, "added")]`
			);

			// Make sure that the number of items in the cart is incremented first before adding another item.
			await expect( page.locator( cartItemsCounter ) ).toHaveText(
				new RegExp( `${ ++cartSize } items?` )
			);
		}
	}
}

/**
 * Go to the checkout page, enter the billing information, and place the order.
 * @param {*} page Playwright page fixture.
 * @param {*} billingDetails The billing details.
 */
export async function setupCheckout(
	page,
	billingDetails,
	skipBillingFields = false
) {
	await page.goto( '/checkout/' );

	if ( ! skipBillingFields ) {
		await page.selectOption(
			'#billing_country',
			billingDetails[ 'country' ]
		);
		await page.selectOption( '#billing_state', billingDetails[ 'state' ] );

		for ( const fieldName of Object.keys( billingDetails ) ) {
			if ( [ 'state', 'country' ].includes( fieldName ) ) {
				continue;
			}
			await page.fill(
				`#billing_${ fieldName }`,
				billingDetails[ fieldName ]
			);
		}
	}

	await page.click( '.wc_payment_method.payment_method_stripe' );
}
