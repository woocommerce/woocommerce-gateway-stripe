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
		.frameLocator(
			'#wc-stripe-card-number-element iframe[name^="__privateStripeFrame"]'
		)
		.locator( 'input[name="cardnumber"]' )
		.fill( card.number );
	await page
		.frameLocator(
			'#wc-stripe-card-expiry-element iframe[name^="__privateStripeFrame"]'
		)
		.locator( 'input[name="exp-date"]' )
		.fill( card.expires.month + card.expires.year );
	await page
		.frameLocator(
			'#wc-stripe-card-code-element iframe[name^="__privateStripeFrame"]'
		)
		.locator( 'input[name="cvc"]' )
		.fill( card.cvc );
}

/**
 * Fills in the credit card details on the legacy experience shortcode checkout page.
 * @param {Page} page Playwright page fixture.
 * @param {Object} card The CC info in the format provided on the test-data.
 */
export async function fillCreditCardDetailsShortcodeLegacy( page, card ) {
	const options = {
		multi: {
			cardNumber: {
				iFrame:
					'#stripe-card-element iframe[name^="__privateStripeFrame"]',
				selector: '[name="cardnumber"]',
			},
			cardExpiry: {
				iFrame:
					'#stripe-exp-element iframe[name^="__privateStripeFrame"]',
				selector: '[name="exp-date"]',
			},
			cardCvc: {
				iFrame:
					'#stripe-cvc-element iframe[name^="__privateStripeFrame"]',
				selector: '[name="cvc"]',
			},
		},
		upe: {
			iFrame: '#wc-stripe-upe-form iframe[name^="__privateStripeFrame"]',
			cardNumber: '[name="number"]',
			cardExpiry: '[name="expiry"]',
			cardCvc: '[name="cvc"]',
		},
	};

	const isVisible = async ( frame, selector ) => {
		return await frame.locator( selector ).isVisible( { timeout: 10000 } );
	};

	const getLocator = async (
		page,
		frameSelector,
		inputSelector,
		description
	) => {
		if ( ! ( await isVisible( page, frameSelector ) ) ) {
			throw new Error(
				`Could not find the credit card ${ description } frame using selector: ${ frameSelector }`
			);
		}

		const frameLocator = page.frameLocator( frameSelector );

		if ( ! ( await isVisible( frameLocator, inputSelector ) ) ) {
			throw new Error(
				`Could not find the credit card ${ description } form element using selector: ${ frameSelector } ${ inputSelector }`
			);
		}

		return frameLocator.locator( inputSelector );
	};

	let cardNumberLocator;
	let cardExpiryLocator;
	let cardCvcLocator;

	const isUPE = await page.isVisible( options.upe.iFrame, { timeout: 5000 } );
	if ( isUPE ) {
		// Wait for the iFrame to load.
		const frameElement = await page.waitForSelector( options.upe.iFrame );
		const frame = await frameElement.contentFrame();
		await frame.waitForLoadState( 'networkidle' );

		cardNumberLocator = await getLocator(
			page,
			options.upe.iFrame,
			options.upe.cardNumber,
			'number'
		);
		cardExpiryLocator = await getLocator(
			page,
			options.upe.iFrame,
			options.upe.cardExpiry,
			'expiration date'
		);
		cardCvcLocator = await getLocator(
			page,
			options.upe.iFrame,
			options.upe.cardCvc,
			'cvc'
		);
	} else {
		cardNumberLocator = await getLocator(
			page,
			options.multi.cardNumber.iFrame,
			options.multi.cardNumber.selector,
			'number'
		);
		cardExpiryLocator = await getLocator(
			page,
			options.multi.cardExpiry.iFrame,
			options.multi.cardExpiry.selector,
			'expiration date'
		);
		cardCvcLocator = await getLocator(
			page,
			options.multi.cardCvc.iFrame,
			options.multi.cardCvc.selector,
			'cvc'
		);
	}

	await cardNumberLocator.fill( card.number );
	await cardExpiryLocator.fill( card.expires.month + card.expires.year );
	await cardCvcLocator.fill( card.cvc );
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

		if ( billingDetails[ 'state_iso' ] ) {
			await page.selectOption(
				'#billing_state',
				billingDetails[ 'state_iso' ]
			);
		}

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
		phone: 'Phone (optional)',
		email: 'Email address',
	};

	if ( billingDetails ) {
		// Check if address form is collapsed (if Edit button exists)
		const editButton = page.locator(
			'#shipping-fields .wc-block-components-address-card__edit'
		);
		const isCollapsed = await editButton.isVisible();

		if ( isCollapsed ) {
			await editButton.click();
			// Wait for form to expand
			await page.waitForSelector( '#shipping-fields #shipping-country' );
		}

		// Make sure "Use same address for billing" is checked
		const sameAddressCheckbox = page.locator(
			'.wc-block-checkout__use-address-for-billing input[type="checkbox"]'
		);
		const isChecked = await sameAddressCheckbox.isChecked();
		if ( ! isChecked ) {
			await sameAddressCheckbox.click();
		}

		await page
			.getByLabel( 'Country/Region' )
			.selectOption( { label: billingDetails[ 'country' ] } );

		if ( billingDetails[ 'state' ] ) {
			await page
				.locator( '#shipping-state', { exact: true } )
				.selectOption( { label: billingDetails[ 'state' ] } );
		}

		// Expand the address 2 field.
		if ( ! isCollapsed ) {
			await page
				.locator(
					'.wc-block-components-address-form__address_2-toggle'
				)
				.click();
		}

		await page
			.locator( '#shipping-postcode' )
			.fill( billingDetails[ 'postcode' ] );

		for ( const fieldName of Object.keys( billingDetails ) ) {
			if (
				[
					'state',
					'country',
					'state_iso',
					'country_iso',
					'company',
					'postcode',
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

/**
 * Set up the checkout page for ACH payment.
 * @param {Page} page Playwright page fixture.
 * @param {string} checkoutType The type of checkout ('blocks' or 'shortcode').
 */
export const setupACHCheckout = async ( page, checkoutType = 'blocks' ) => {
	await emptyCart( page );
	await setupCart( page );

	if ( checkoutType === 'blocks' ) {
		await setupBlocksCheckout(
			page,
			config.get( 'addresses.customer.billing' )
		);
		// Select ACH in blocks checkout
		await page
			.locator( 'label' )
			.filter( { hasText: 'ACH Direct Debit' } )
			.click();

		// Wait for the iframe to be ready
		await page.waitForSelector(
			'#radio-control-wc-payment-method-options-stripe_us_bank_account__content iframe[src*="elements-inner-payment"]'
		);
		await page.waitForTimeout( 1000 );

		// Click "Test Institution"
		await page
			.frameLocator(
				'#radio-control-wc-payment-method-options-stripe_us_bank_account__content iframe[src*="elements-inner-payment"]'
			)
			.getByText( 'Test Institution' )
			.click();
	} else {
		await setupShortcodeCheckout(
			page,
			config.get( 'addresses.customer.billing' )
		);

		// Select ACH in shortcode checkout
		await page.getByText( 'ACH Direct Debit' ).click();
		await page.waitForTimeout( 1000 );

		// Wait for the iframe to be ready
		await page.waitForSelector(
			'.wc_payment_method.payment_method_stripe_us_bank_account iframe[src*="elements-inner-payment"]'
		);
		await page.waitForTimeout( 1000 );

		// Click "Test Institution"
		await page
			.frameLocator(
				'.wc_payment_method.payment_method_stripe_us_bank_account iframe[src*="elements-inner-payment"]'
			)
			.getByTestId( 'featured-institution-default' )
			.click();
	}
};

/**
 * Interact with the Stripe Elements iframe to fill in the bank details.
 * @param {Page} page Playwright page fixture.
 */
export const fillACHBankDetails = async ( page ) => {
	const frame = page
		.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
		.first();

	// Agree and Continue
	await frame.getByTestId( 'agree-button' ).click();

	// Click "Success ••••" button
	await frame.getByRole( 'button', { name: 'Success ••••' } ).click();

	// Click "Connect Account" button.
	await frame.getByTestId( 'select-button' ).click();

	// Skip link registration
	await frame.getByTestId( 'link-not-now-button' ).click();

	// Click "Done" button.
	await frame.getByTestId( 'done-button' ).click();
};

/**
 * Set up the checkout page for ACSS payment.
 *
 * @param {Page} page Playwright page fixture.
 * @param {string} checkoutType The type of checkout ('blocks' or 'shortcode').
 */
export const setupACSSCheckout = async ( page, checkoutType = 'blocks' ) => {
	await emptyCart( page );
	await setupCart( page );

	if ( checkoutType === 'blocks' ) {
		await setupBlocksCheckout(
			page,
			config.get( 'addresses.customer_canada.billing' )
		);

		await page.waitForTimeout( 1000 );

		// Select ACSS in blocks checkout.
		await page
			.locator( 'label' )
			.filter( { hasText: 'Pre-Authorized Debit' } )
			.click();

		await page.waitForTimeout( 1000 );
	} else {
		await setupShortcodeCheckout(
			page,
			config.get( 'addresses.customer_canada.billing' )
		);

		await page.waitForTimeout( 1000 );

		// Select ACSS in shortcode checkout.
		await page.getByText( 'Pre-Authorized Debit' ).click();

		await page.waitForTimeout( 1000 );
	}
};

/**
 * Set up the checkout page for Optimized Checkout (OC).
 *
 * @param {Page} page Playwright page fixture.
 * @param {string} checkoutType The type of checkout ('blocks' or 'shortcode').
 * @param {Object} options Optional configuration parameters.
 * @param {number} options.timeout Timeout in milliseconds for waiting operations (default: 10000).
 * @param {boolean} options.skipCartSetup Skip cart setup if it's already configured (default: false).
 * @returns {Promise<void>} Resolves when setup is complete.
 * @throws {Error} If iframe cannot be found or initialization fails.
 */
export const setupOptimizedCheckout = async (
	page,
	checkoutType = 'blocks',
	options = { timeout: 10000, skipCartSetup: false }
) => {
	if ( ! options.skipCartSetup ) {
		await emptyCart( page );
		await setupCart( page );
	}

	const selectors = {
		blocks: {
			iframe:
				'#radio-control-wc-payment-method-options-stripe__content iframe[name^="__privateStripeFrame"]',
			container:
				'#radio-control-wc-payment-method-options-stripe__content',
		},
		shortcode: {
			iframe:
				'#wc-stripe-upe-form .StripeElement iframe[name^="__privateStripeFrame"]',
			container: '#wc-stripe-upe-form',
		},
	};

	try {
		// Set up appropriate checkout type
		if ( checkoutType === 'blocks' ) {
			await setupBlocksCheckout(
				page,
				config.get( 'addresses.customer.billing' )
			);
		} else {
			await setupShortcodeCheckout(
				page,
				config.get( 'addresses.customer.billing' )
			);
		}

		// Get the correct selectors for this checkout type
		const currentSelectors = selectors[ checkoutType ];
		if ( ! currentSelectors ) {
			throw new Error(
				`Invalid checkout type: ${ checkoutType }. Must be 'blocks' or 'shortcode'.`
			);
		}

		// Wait for the Stripe iframe
		await page.waitForSelector( currentSelectors.iframe, {
			state: 'visible',
			timeout: options.timeout,
		} );

		// Get the payment frame
		const paymentFrame = await page
			.locator( currentSelectors.iframe )
			.contentFrame()
			.first();

		if ( ! paymentFrame ) {
			throw new Error(
				`Could not find Stripe payment element frame in ${ currentSelectors.container }`
			);
		}

		// Select the card payment method
		await paymentFrame.getByRole( 'button', { name: 'Card' } ).click();
	} catch ( error ) {
		throw new Error(
			`Failed to set up Optimized Checkout: ${ error.message }`
		);
	}
};

/**
 * Interact with the Stripe Elements iframe to fill in the ACSS details.
 *
 * @param {Page} page Playwright page fixture.
 */
export const fillACSSDetails = async ( page ) => {
	const outerFrameElement = await page
		.locator( 'iframe[name^="__privateStripeFrame"]' )
		.first();

	// Wait for the outer iframe to be present.
	await expect( outerFrameElement ).toBeVisible( { timeout: 5000 } );

	const outerFrame = await outerFrameElement.contentFrame();
	const innerFrameElement = await outerFrame
		.locator( 'iframe[title="Link an ACSS Debit account"]' )
		.first();

	// Wait for the inner iframe to be present.
	await expect( innerFrameElement ).toBeVisible( { timeout: 5000 } );

	const innerFrame = await innerFrameElement.contentFrame();

	// Wait for Agree button to be visible.
	await expect(
		innerFrame.getByRole( 'button', { name: 'Agree' } )
	).toBeVisible();

	await page.waitForTimeout( 1000 );

	// Agree, simulate successful payment, and agree again.
	await innerFrame.getByRole( 'button', { name: 'Agree' } ).click();

	await innerFrame.getByText( 'Simulate successful' ).click();

	await innerFrame.getByRole( 'button', { name: 'Agree' } ).click();
};

/**
 * Handles the 3DS challenge on the checkout page.
 * @param {Page} page Playwright page fixture.
 * @param {string} action The action to take on the challenge modal.
 */
export async function handleCheckout3DSChallenge( page, action = 'authorize' ) {
	const outerFrameLocator = page
		.locator( 'iframe[name^="__privateStripeFrame"]' )
		.contentFrame()
		.first();
	const innerFrameLocator = outerFrameLocator.frameLocator(
		'iframe[name="stripe-challenge-frame"]'
	);

	// Wait for the challenge modal to be ready -- the inner frame is "visible"
	// and the loading indicator is hidden.
	await expect( innerFrameLocator.owner() ).toBeVisible();
	await expect(
		outerFrameLocator.locator( '.LightboxModalLoadingIndicator' )
	).toBeHidden();

	const buttonId =
		action === 'authorize'
			? '#test-source-authorize-3ds'
			: '#test-source-fail-3ds';
	await expect( innerFrameLocator.locator( buttonId ) ).toBeVisible();
	await innerFrameLocator.locator( buttonId ).click();

	if ( action === 'fail' ) {
		await expect( innerFrameLocator.owner() ).toBeHidden();
	}
}

/**
 * This roundabout way of clicking the Place Order button is an
 * attempt to reduce the flakiness.
 * @param {Page} page Playwright page fixture.
 */
export async function clickPlaceOrder( page ) {
	// Wait for the button to be enabled (i.e. clickable), to wait
	// for any logic we are potentially depending on.
	await expect(
		page.getByRole( 'button', { name: 'Place order' } )
	).toBeEnabled();

	// Dispatch a click event, instead of clicking the button directly,
	// to reduce "missed" clicks.
	await page
		.getByRole( 'button', { name: 'Place order' } )
		.dispatchEvent( 'click' );
}

/**
 * Handles the Cash App Pay payment on the checkout page.
 * @param {Page} page Playwright page fixture.
 */
export async function handleCheckoutCashAppPay(
	page,
	paymentElementSelector = '#wc-stripe_cashapp-upe-form'
) {
	await page.getByText( 'Cash App Pay' ).click();
	await expect(
		page
			.frameLocator(
				`${ paymentElementSelector } iframe[name^="__privateStripeFrame"]`
			)
			.locator( '.__PrivateStripeElementLoader' )
	).toBeHidden();
	await expect(
		page
			.frameLocator(
				`${ paymentElementSelector } iframe[name^="__privateStripeFrame"]`
			)
			.getByText( 'Cash App Pay selected.' )
	).toBeVisible();
	await clickPlaceOrder( page );

	// Expect a modal to appear
	const simulateScanButton = await page
		.locator( 'iframe[name^="__privateStripeFrame"]' )
		.contentFrame()
		.first()
		.frameLocator( 'iframe[title="QR Code Instructions"]' )
		.getByRole( 'button', { name: 'Simulate scan' } );

	const context = await page.context();
	const [ paymentPage ] = await Promise.all( [
		context.waitForEvent( 'page' ),
		simulateScanButton.dispatchEvent( 'click' ),
	] );

	await paymentPage.waitForLoadState();
	await paymentPage
		.getByRole( 'link', { name: 'Authorize Test Payment' } )
		.click();
}

/**
 * Fill in the payment details for Optimized Checkout (OC).
 *
 * @param {Page} page Playwright page fixture.
 * @param {Object} card The CC info in the format provided on the test-data.
 * @param {string} checkoutType The type of checkout ('blocks' or 'shortcode').
 */
export const fillOCDetails = async ( page, card, checkoutType = 'blocks' ) => {
	// Determine the appropriate iframe selector based on checkout type
	const iframeSelector =
		checkoutType === 'blocks'
			? '#radio-control-wc-payment-method-options-stripe__content iframe[name^="__privateStripeFrame"]'
			: '#wc-stripe-upe-form .StripeElement iframe[name^="__privateStripeFrame"]';

	// Wait for the Stripe iframe to be visible
	await page.waitForSelector( iframeSelector, {
		state: 'visible',
		timeout: 10000,
	} );

	const paymentFrame = await page
		.locator( iframeSelector )
		.contentFrame()
		.first();

	if ( ! paymentFrame ) {
		throw new Error( 'Could not find Stripe payment element frame' );
	}

	// Fill in test card details
	await paymentFrame.locator( '[name="number"]' ).fill( card.number );
	await paymentFrame
		.locator( '[name="expiry"]' )
		.fill( card.expires.month + card.expires.year );
	await paymentFrame.locator( '[name="cvc"]' ).fill( card.cvc );
};

/**
 * Fill BLIK payment details in the checkout form.
 * @param {import('@playwright/test').Page} page
 * @param {string} code (optional) 6-digit BLIK code to use. Defaults to '123456'.
 */
export const fillBLIKDetails = async ( page, code = '123456' ) => {
	// Assumes the BLIK code input has a label or placeholder containing 'BLIK code'.
	await page.getByLabel( /blik code/i ).fill( code );
};
