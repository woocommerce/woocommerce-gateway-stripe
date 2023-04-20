import { expect } from '@playwright/test';
import config from 'config';

/**
 * Empty the WC cart.
 * @param {Page} page Playwright page fixture.
 */
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
 * @param {Page} page Playwright page fixture.
 * @param {Object} card The CC info in the format provided on the test-data.
 */
export async function fillCardDetails( page, card ) {
	let isUpe = await isUpeCheckout( page );

	// blocks checkout
	if ( await page.$( '.wc-block-checkout' ) ) {
		if ( ! isUpe ) {
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
			return;
		} else {
			await page
				.frameLocator(
					'.wc-block-gateway-container iframe[name^="__privateStripeFrame"]'
				)
				.locator( '[name="number"]' )
				.fill( card.number );
			await page
				.frameLocator(
					'.wc-block-gateway-container iframe[name^="__privateStripeFrame"]'
				)
				.locator( '[name="expiry"]' )
				.fill( card.expires.month + card.expires.year );
			await page
				.frameLocator(
					'.wc-block-gateway-container iframe[name^="__privateStripeFrame"]'
				)
				.locator( '[name="cvc"]' )
				.fill( card.cvc );
			return;
		}
	}

	// regular checkout
	if ( isUpe ) {
		const frameHandle = await page.waitForSelector(
			'#payment #wc-stripe-upe-element iframe'
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
 * Checks if the checkout is using the UPE.
 * @param {Page} page Playwright page fixture.
 * @returns {boolean} True if the checkout is using the UPE, false otherwise.
 */
export async function isUpeCheckout( page ) {
	// blocks checkout
	if ( await page.$( '.wc-block-checkout' ) ) {
		try {
			await page.waitForSelector(
				'#wc-stripe-card-expiry-element iframe',
				{
					timeout: 5000,
				}
			);
			return false;
		} catch ( e ) {
			// If the card elements are not present, we assume the checkout is using the UPE.
			return true;
		}
	}

	// regular checkout
	return Boolean( await page.$( '#payment #wc-stripe-upe-form' ) );
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
 * Go to the checkout page, enter the billing information, and select the payment gateway.
 * If billingDetails are empty, they're skipped.
 * @param {Page} page Playwright page fixture.
 * @param {Object} billingDetails The billing details in the format provided on the test-data.
 */
export async function setupCheckout( page, billingDetails = null ) {
	await page.goto( '/checkout/' );

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
 * Go to the checkout page, enter the billing information, and select the payment gateway.
 * If billingDetails are empty, they're skipped.
 * @param {Page} page Playwright page fixture.
 * @param {Object} billingDetails The billing details in the format provided on the test-data.
 */
export async function setupBlocksCheckout( page, billingDetails = null ) {
	await page.goto( '/checkout-block/' );

	if ( billingDetails ) {
		await page
			.locator( '#billing-country input[type="text"]' )
			.fill( billingDetails[ 'country' ] );
		await page
			.locator(
				'#billing-country .components-form-token-field__suggestions-list > li:first-child'
			)
			.click();

		await page
			.locator( '#billing-state input[type="text"]' )
			.fill( billingDetails[ 'state' ] );
		await page
			.locator(
				'#billing-state .components-form-token-field__suggestions-list > li:first-child'
			)
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
			if ( [ 'email' ].includes( fieldName ) ) {
				await page
					.locator( `#${ fieldName }` )
					.fill( billingDetails[ fieldName ] );
				continue;
			}
			await page
				.locator( `#billing-${ fieldName }` )
				.fill( billingDetails[ fieldName ] );
		}
	}

	await page
		.locator(
			"label[for='radio-control-wc-payment-method-options-stripe']"
		)
		.click();
}
