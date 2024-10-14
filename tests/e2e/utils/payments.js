import { expect } from '@playwright/test';
import config from 'config';

/**
 * Empty the WC cart.
 * @param {Page} page Playwright page fixture.
 */
export async function emptyCart( page ) {
	await page.goto( '/cart-shortcode' );

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

	await expect(
		page.locator( '.wc-empty-cart-message .cart-empty' )
	).toHaveText( 'Your cart is currently empty.' );
}

/**
 * Set up cart with `lineItems` products.
 *
 * @param {Page} page Playwright page fixture.
 * @param {any} lineItems A 2D array of line items where each line item is an array
 * that contains the product title as the first element, and the quantity as the second.
 * For example, if you want to add the products x2 "Hoodie" and x3 "Belt" then you can set this `lineItems` parameter like this:
 *
 * `[ [ "Hoodie", 2 ], [ "Belt", 3 ] ]`.
 *
 * Default value is 1 piece of `config.get( 'products.simple.name' )`.
 */
export async function setupCart(
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
 * Fills in the credit card details on the default (blocks) checkout page.
 * @param {Page} page Playwright page fixture.
 * @param {Object} card The CC info in the format provided on the test-data.
 */
export async function fillCreditCardDetails( page, card ) {
	const form = await page.frameLocator(
		'.wcstripe-payment-element iframe[name^="__privateStripeFrame"]'
	);

	await form.locator( '[name="number"]' ).fill( card.number );

	await form
		.locator( '[name="expiry"]' )
		.fill( card.expires.month + card.expires.year );

	await form.locator( '[name="cvc"]' ).fill( card.cvc );
}

/**
 * Fills in the credit card details on the shortcode checkout page.
 * @param {Page} page Playwright page fixture.
 * @param {Object} card The CC info in the format provided on the test-data.
 */
export async function fillCreditCardDetailsShortcode( page, card ) {
	const frameHandle = await page.waitForSelector(
		'.payment_method_stripe #wc-stripe-upe-form .wc-stripe-upe-element iframe'
	);

	await page
		.locator(
			'.payment_method_stripe #wc-stripe-upe-form .wc-stripe-upe-element iframe'
		)
		.scrollIntoViewIfNeeded();

	const stripeFrame = await frameHandle.contentFrame();

	await stripeFrame.fill( '[name="number"]', card.number );
	await stripeFrame.fill(
		'[name="expiry"]',
		card.expires.month + card.expires.year
	);
	await stripeFrame.fill( '[name="cvc"]', card.cvc );
}

/**
 * Fills in the credit card details on the legacy experience default (blocks) checkout page.
 * @param {Page} page Playwright page fixture.
 * @param {Object} card The CC info in the format provided on the test-data.
 */
export async function fillCreditCardDetailsLegacy( page, card ) {
	await page
		.frameLocator( '#wc-stripe-card-number-element iframe' )
		.locator( 'input[name="cardnumber"]' )
		.fill( card.number );
	await page
		.frameLocator( '#wc-stripe-card-expiry-element iframe' )
		.locator( 'input[name="exp-date"]' )
		.fill( card.expires.month + card.expires.year );
	await page
		.frameLocator( '#wc-stripe-card-code-element iframe' )
		.locator( 'input[name="cvc"]' )
		.fill( card.cvc );
}

/**
 * Fills in the credit card details on the legacy experience shortcode checkout page.
 * @param {Page} page Playwright page fixture.
 * @param {Object} card The CC info in the format provided on the test-data.
 */
export async function fillCreditCardDetailsShortcodeLegacy( page, card ) {
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

/**
 * Go to the shortcode checkout page, enter the billing information, and select the payment gateway.
 * If billingDetails are empty, they're skipped.
 * @param {Page} page Playwright page fixture.
 * @param {Object} billingDetails The billing details in the format provided on the test-data.
 */
export async function setupShortcodeCheckout( page, billingDetails = null ) {
	await page.goto( '/checkout-shortcode/' );

	if ( billingDetails ) {
		await page.selectOption(
			'#billing_country',
			billingDetails[ 'country_iso' ]
		);
		await page.selectOption(
			'#billing_state',
			billingDetails[ 'state_iso' ]
		);

		for ( const fieldName of Object.keys( billingDetails ) ) {
			if (
				[ 'state', 'country', 'state_iso', 'country_iso' ].includes(
					fieldName
				)
			) {
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

/**
 * Go to the default (blocks) checkout page, enter the billing information, and select the payment gateway.
 * If billingDetails are empty, they're skipped.
 * @param {Page} page Playwright page fixture.
 * @param {Object} billingDetails The billing details in the format provided on the test-data.
 */
export async function setupBlocksCheckout( page, billingDetails = null ) {
	await page.goto( '/checkout/' );

	const fieldNameLabelMap = {
		first_name: 'First name',
		last_name: 'Last name',
		address_1: 'Address',
		address_2: 'Apartment, suite, etc. (optional)',
		city: 'City',
		postcode: 'ZIP Code',
		phone: 'Phone (optional)',
		email: 'Email address',
	};

	if ( billingDetails ) {
		await page
			.getByLabel( 'Country/Region' )
			.selectOption( { label: billingDetails[ 'country' ] } );

		await page
			.getByLabel( 'State', { exact: true } )
			.selectOption( { label: billingDetails[ 'state' ] } );

		// Expand the address 2 field.
		await page
			.locator( '.wc-block-components-address-form__address_2-toggle' )
			.click();

		for ( const fieldName of Object.keys( billingDetails ) ) {
			if (
				[
					'state',
					'country',
					'state_iso',
					'country_iso',
					'company',
				].includes( fieldName )
			) {
				continue;
			}
			await page
				.getByLabel( fieldNameLabelMap[ fieldName ], { exact: true } )
				.fill( billingDetails[ fieldName ] );
		}
	}

	await page
		.locator(
			"label[for='radio-control-wc-payment-method-options-stripe']"
		)
		.click();
}
