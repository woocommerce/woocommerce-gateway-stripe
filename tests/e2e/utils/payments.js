import { expect } from '@playwright/test';
import config from 'config';
import { verifyOrderChargedAmount } from './admin';

/**
 * Click the primary add to cart button for the current page.
 *
 * @param {Page}   page  Playwright page fixture.
 * @param {string} label The expected text for the "Add to cart" button.
 */
export async function clickAddToCartButton( page, label = 'Add to cart' ) {
	const initialCount = await getCartItemsCount( page );
	const expectedCount = initialCount + 1;

	// Block themes submit the add via the Interactivity API, and a click that
	// lands before its scripts hydrate is silently dropped — so confirm against
	// the cart itself and re-click when the count did not move.
	await retryWithBackoff(
		async () => {
			// Skip the click when a previous slow add already landed, so a
			// retry cannot add the item twice.
			if ( ( await getCartItemsCount( page ) ) !== expectedCount ) {
				// Match the accessible name (substring, since block themes
				// append the product name, e.g. 'Add to cart: "Beanie"') OR the
				// visible text: when a WCS plan is selected, the button's text
				// becomes "Sign up" but the block product button's stale
				// aria-label keeps the accessible name at "Add to cart: ...",
				// so a role+name lookup alone cannot find it.
				const addToCartButton = page
					.getByRole( 'button', { name: label } )
					.or( page.locator( 'button', { hasText: label } ) )
					.first();
				await expect( addToCartButton ).toBeEnabled();
				await addToCartButton.click();
			}
			await expect
				.poll( () => getCartItemsCount( page ), { timeout: 5000 } )
				.toBe( expectedCount );
		},
		{ maxRetries: 2 }
	);
}

/**
 * Read the cart's total item count from the WooCommerce Store Cart API,
 * which works the same regardless of the active theme's markup.
 *
 * @param {Page} page Playwright page fixture.
 * @returns {Promise<number>} Total number of items in the cart.
 */
async function getCartItemsCount( page ) {
	const response = await page.request.get( '/wp-json/wc/store/v1/cart' );
	if ( ! response.ok() ) {
		throw new Error(
			`Failed to fetch cart: ${ response.status() } ${ response.statusText() }`
		);
	}
	return ( await response.json() ).items_count;
}

export async function selectSubscriptionOption( page ) {
	// WCS 9 plans can coexist with one-time purchases, so these tests choose
	// the recurring option before adding the product to the cart.
	const addToCartForm = page.locator( 'form.cart' ).first();
	const subscriptionOption = addToCartForm.locator(
		'.wcsatt-options-prompt-label-subscription'
	);
	const subscriptionOptionInput = addToCartForm.locator(
		'input[name="subscribe-to-action-input"][value="yes"]'
	);

	await expect( subscriptionOption ).toBeVisible();
	await subscriptionOption.click();
	await expect( subscriptionOptionInput ).toBeChecked();
}

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

	// Assert on the message text rather than the notice markup: classic themes
	// render `.wc-empty-cart-message .cart-empty`, block themes render the same
	// message inside a `wc-block-components-notice-banner`.
	await expect(
		page.getByText( 'Your cart is currently empty.' ).first()
	).toBeVisible();
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
	// Shop-page markup and the header cart counter are theme-specific, so
	// resolve each product's page through the Store API and verify additions
	// through the cart endpoint instead of scraping either.
	let cartSize = await getCartItemsCount( page );

	for ( const line of lineItems ) {
		let [ productTitle, qty ] = line;

		const response = await page.request.get(
			`/wp-json/wc/store/v1/products?search=${ encodeURIComponent(
				productTitle
			) }&per_page=20`
		);
		// The search matches substrings ("Beanie" also returns "Beanie with
		// Logo"), so pick the exact title.
		const product = ( await response.json() ).find(
			( { name } ) => name === productTitle
		);
		if ( ! product ) {
			throw new Error(
				`Could not find product "${ productTitle }" via the Store API.`
			);
		}

		await page.goto( product.permalink );

		while ( qty-- ) {
			// clickAddToCartButton verifies each add against the cart, so the
			// count only needs asserting once per click here.
			await clickAddToCartButton( page );
			await expect
				.poll( () => getCartItemsCount( page ) )
				.toBe( ++cartSize );
		}
	}
}

/**
 * Get the current cart total from the WooCommerce Store Cart API.
 *
 * Must be called before placing the order while the cart still has items.
 *
 * @param {Page} page Playwright page fixture.
 * @returns {Promise<string>} The cart total without currency symbol (e.g. "19.99").
 */
export async function getCartTotal( page ) {
	const response = await page.request.get( '/wp-json/wc/store/v1/cart' );
	if ( ! response.ok() ) {
		const responseText = await response.text();
		throw new Error(
			`Failed to fetch cart total: ${ response.status() } ${ response.statusText() } -- ${ responseText }`
		);
	}

	const { total_price: totalPrice, currency_minor_unit: minorUnit } = (
		await response.json()
	).totals;

	return ( parseInt( totalPrice, 10 ) / 10 ** minorUnit ).toFixed(
		minorUnit
	);
}

/**
 * Wait for Stripe iframe to be fully loaded and ready for interaction.
 * This helper addresses common race conditions with Stripe Elements.
 *
 * @param {Page} page Playwright page fixture.
 * @param {string} iframeSelector The selector for the Stripe iframe.
 * @param {number} timeout Maximum time to wait in milliseconds (default: 15000).
 * @returns {Promise<Frame>} The loaded Stripe frame.
 */
export async function waitForStripeReady(
	page,
	iframeSelector,
	timeout = 15000
) {
	// Wait for iframe to be present in the DOM.
	const frameHandle = await page.waitForSelector( iframeSelector, {
		state: 'attached',
		timeout,
	} );
	const stripeFrame = await frameHandle.contentFrame();

	if ( ! stripeFrame ) {
		throw new Error(
			`Could not get content frame for: ${ iframeSelector }`
		);
	}

	// Stripe iframes often keep background network activity and may never reach
	// "networkidle". Wait for the frame document instead.
	await stripeFrame.waitForSelector( 'body', {
		state: 'attached',
		timeout,
	} );

	// Additional wait for any loading indicators to disappear in parallel
	const loadingIndicators = [
		'.__PrivateStripeElementLoader',
		'.LightboxModalLoadingIndicator',
		'[data-testid="loading"]',
	];

	await Promise.all(
		loadingIndicators.map( ( indicator ) =>
			stripeFrame
				.locator( indicator )
				.waitFor( { state: 'hidden', timeout } )
				.catch( () => {} )
		)
	);

	return stripeFrame;
}

/**
 * Retry an async function with exponential backoff.
 * Useful for flaky operations like iframe interactions or API calls.
 *
 * @param {Function} fn The async function to retry.
 * @param {Object} options Retry configuration.
 * @param {number} options.maxRetries Maximum number of retries (default: 3).
 * @param {number} options.initialDelay Initial delay in milliseconds (default: 500).
 * @param {number} options.maxDelay Maximum delay in milliseconds (default: 5000).
 * @param {Function} options.shouldRetry Optional function to determine if error should trigger retry.
 * @returns {Promise<any>} The result of the function call.
 */
export async function retryWithBackoff( fn, options = {} ) {
	const {
		maxRetries = 3,
		initialDelay = 500,
		maxDelay = 5000,
		shouldRetry = () => true,
	} = options;

	let lastError;
	let delay = initialDelay;

	for ( let attempt = 0; attempt <= maxRetries; attempt++ ) {
		try {
			return await fn();
		} catch ( error ) {
			lastError = error;

			// Don't retry if we've exhausted attempts or if shouldRetry returns false
			if ( attempt === maxRetries || ! shouldRetry( error ) ) {
				break;
			}

			// Wait before retrying
			await new Promise( ( resolve ) => setTimeout( resolve, delay ) );

			// Exponential backoff with max delay cap
			delay = Math.min( delay * 2, maxDelay );
		}
	}

	throw lastError;
}

/**
 * Resolves the Stripe payment element frame on the default (blocks) checkout page.
 *
 * Stripe injects extra iframes alongside the payment element (bank details, the
 * ACH bank search results), so we require a visible frame to avoid picking the
 * wrong one.
 *
 * @param {Page} page Playwright page fixture.
 * @return {FrameLocator} The payment element frame.
 */
export async function getPaymentElementFrame( page ) {
	const paymentIframe = page
		.locator(
			'.wcstripe-payment-element iframe[name^="__privateStripeFrame"]'
		)
		.filter( { visible: true } )
		.first();
	await paymentIframe.waitFor( { state: 'visible', timeout: 10000 } );

	return paymentIframe.contentFrame();
}

/**
 * Fills in the credit card details on the default (blocks) checkout page.
 * @param {Page} page Playwright page fixture.
 * @param {Object} card The CC info in the format provided on the test-data.
 */
export async function fillCreditCardDetails( page, card ) {
	const form = await getPaymentElementFrame( page );

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
	// Stripe injects helper iframes (accessory target, ACH bank-search results)
	// alongside the card element, and how many are mounted races with page load
	// — matching the bare `iframe` descendant intermittently resolved to more
	// than one. Scope to the payment-input frame by its src, the same marker the
	// ACH helpers use (`iframe[src*="elements-inner-payment"]`).
	const paymentIframe = page
		.locator(
			'.payment_method_stripe #wc-stripe-upe-form .wc-stripe-upe-element iframe[src*="elements-inner-payment"]'
		)
		.first();
	await paymentIframe.waitFor( { state: 'visible' } );
	await paymentIframe.scrollIntoViewIfNeeded();

	const stripeFrame = paymentIframe.contentFrame();

	await stripeFrame.locator( '[name="number"]' ).fill( card.number );
	await stripeFrame
		.locator( '[name="expiry"]' )
		.fill( card.expires.month + card.expires.year );
	await stripeFrame.locator( '[name="cvc"]' ).fill( card.cvc );
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
				iFrame: '#stripe-card-element iframe[name^="__privateStripeFrame"]',
				selector: '[name="cardnumber"]',
			},
			cardExpiry: {
				iFrame: '#stripe-exp-element iframe[name^="__privateStripeFrame"]',
				selector: '[name="exp-date"]',
			},
			cardCvc: {
				iFrame: '#stripe-cvc-element iframe[name^="__privateStripeFrame"]',
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
		suburb: 'Suburb', // used in Australia. This field is needed in BECS tests.
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

	const rawIframeSelector = 'iframe[src*="elements-inner-payment"]';
	let iframeSelector;
	let paymentMethodContentSelector;

	if ( checkoutType === 'blocks' ) {
		paymentMethodContentSelector =
			'#radio-control-wc-payment-method-options-stripe_us_bank_account__content';
		iframeSelector = `${ paymentMethodContentSelector } ${ rawIframeSelector }`;

		await setupBlocksCheckout(
			page,
			config.get( 'addresses.customer.billing' )
		);

		// Select ACH in blocks checkout via the associated label, since the
		// underlying input can be covered by parent elements during animation.
		const achOption = page.locator(
			'#radio-control-wc-payment-method-options-stripe_us_bank_account'
		);
		const achOptionLabel = page.locator(
			"label[for='radio-control-wc-payment-method-options-stripe_us_bank_account']"
		);
		await achOption.waitFor( { state: 'attached' } );
		await expect( achOptionLabel ).toContainText( 'ACH Direct Debit' );
		await achOptionLabel.click();
		await expect( achOption ).toBeChecked();
	} else {
		paymentMethodContentSelector =
			'.wc_payment_method.payment_method_stripe_us_bank_account';
		iframeSelector = `${ paymentMethodContentSelector } ${ rawIframeSelector }`;

		await setupShortcodeCheckout(
			page,
			config.get( 'addresses.customer.billing' )
		);

		// Select ACH in shortcode checkout via the associated label, since direct
		// clicks on the hidden radio input can be intercepted.
		const achOption = page.locator(
			'#payment_method_stripe_us_bank_account'
		);
		const achOptionLabel = page.locator(
			"label[for='payment_method_stripe_us_bank_account']"
		);
		await achOption.waitFor( { state: 'attached' } );
		await expect( achOptionLabel ).toContainText( 'ACH Direct Debit' );
		await achOptionLabel.click();
		await expect( achOption ).toBeChecked();
	}

	await expect( page.locator( paymentMethodContentSelector ) ).toBeVisible();
	await waitForStripeReady( page, iframeSelector );

	// Click "Test Institution" with retry logic
	await retryWithBackoff( async () => {
		const testInstitutionButton = page
			.frameLocator( iframeSelector )
			.getByTestId( 'featured-institution-default_oauth' )
			.first();

		await expect( testInstitutionButton ).toBeVisible();
		await testInstitutionButton.dispatchEvent( 'click' );
	} );
};

/**
 * Interact with the Stripe Elements iframe to fill in the bank details.
 * @param {Page} page Playwright page fixture.
 */
export const fillACHBankDetails = async ( page ) => {
	const frame = page
		.frameLocator( 'iframe[name^="__privateStripeFrame"]' )
		.first();

	// Click Agree and Continue button
	let button = frame.getByTestId( 'agree-button' );
	await expect( button ).toBeVisible();
	await button.click();

	// Link registration button may or may not appear.
	await Promise.race( [
		frame
			.getByTestId( 'link-not-now-button' )
			.waitFor( { state: 'visible' } )
			.then( async () => {
				await frame.getByTestId( 'link-not-now-button' ).click();
			} ),
		frame
			.getByRole( 'button', { name: 'Success ••••' } )
			.waitFor( { state: 'visible' } ),
		frame
			.getByTestId( 'continue-button' )
			.waitFor( { state: 'visible' } )
			.then( async () => {
				await frame.getByTestId( 'continue-button' ).click();
			} ),
	] );

	// Click "Success ••••" account
	button = frame.getByRole( 'button', { name: 'Success ••••' } );
	await expect( button ).toBeVisible();
	await button.click();

	// Click Connect account button
	button = frame.getByTestId( 'select-button' );
	await expect( button ).toBeVisible();
	await button.click();

	// If link registration did not load when starting the flow, it will appear here.
	await Promise.race( [
		frame
			.getByTestId( 'link-not-now-button' )
			.waitFor( { state: 'visible' } )
			.then( async () => {
				await frame.getByTestId( 'link-not-now-button' ).click();
			} ),
		frame.getByTestId( 'done-button' ).waitFor( { state: 'visible' } ),
	] );

	// Click the done button with retry logic
	button = frame.getByTestId( 'done-button' );
	await expect( button ).toBeVisible();
	await button.click();
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

		// Select ACSS in blocks checkout.
		const acssLabel = page
			.locator( 'label' )
			.filter( { hasText: 'Pre-Authorized Debit' } );
		await acssLabel.waitFor( { state: 'visible' } );
		await acssLabel.click();
	} else {
		await setupShortcodeCheckout(
			page,
			config.get( 'addresses.customer_canada.billing' )
		);

		// Select ACSS in shortcode checkout.
		const acssLabel = page.getByText( 'Pre-Authorized Debit' );
		await acssLabel.waitFor( { state: 'visible' } );
		await acssLabel.click();
	}

	await page.waitForTimeout( 1000 );
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
			iframe: '#radio-control-wc-payment-method-options-stripe__content iframe[name^="__privateStripeFrame"]',
			container:
				'#radio-control-wc-payment-method-options-stripe__content',
		},
		shortcode: {
			iframe: '#wc-stripe-upe-form .StripeElement iframe[name^="__privateStripeFrame"]',
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

		const paymentFrame = await getOCPaymentFrame(
			page,
			currentSelectors.iframe,
			options.timeout
		);

		// Optional for Adaptive Pricing, whose element renders differently
		// across flows; fillOCDetails() expands the Card row when needed.
		if ( options.cardSelectionOptional ) {
			await paymentFrame
				.locator(
					'[role="button"]:has-text("Card"), button:has-text("Card")'
				)
				.first()
				.click( { timeout: 5000 } )
				.catch( () => {} );
		} else {
			await paymentFrame.getByRole( 'button', { name: 'Card' } ).click();
		}
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

	const buttonName = action === 'authorize' ? 'Complete' : 'Fail';
	await expect(
		innerFrameLocator.getByRole( 'button', { name: buttonName } )
	).toBeVisible();
	await innerFrameLocator.getByRole( 'button', { name: buttonName } ).click();

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

	// If we click the Place button too fast, we might sometimes get an error.
	// One way to handle this is to always wait a few seconds before clicking Place order.
	// But that would make the test flaky and slows down the test suite. So instead,
	// we check if the error message is present and if it is, we dispatch the click event again.
	const errorElement = page
		.getByLabel( 'Checkout' )
		.getByText( 'Your payment information is' );
	if ( await errorElement.isVisible() ) {
		await page
			.getByRole( 'button', { name: 'Place order' } )
			.dispatchEvent( 'click' );
	}
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
 * Resolves the visible Stripe iframe that renders the payment UI.
 *
 * Multiple visible frames can match the selector (e.g. Adaptive Pricing adds
 * a test-mode banner frame first), so pick by content, not position.
 *
 * @param {Page}   page           Playwright page fixture.
 * @param {string} iframeSelector Selector matching the container's Stripe iframes.
 * @param {number} timeout        How long to wait for the payment frame, in ms.
 * @return {FrameLocator} The payment frame.
 */
const getOCPaymentFrame = async ( page, iframeSelector, timeout = 10000 ) => {
	const candidates = page
		.locator( iframeSelector )
		.filter( { visible: true } );
	await candidates.first().waitFor( { state: 'visible', timeout } );

	const deadline = Date.now() + timeout;
	do {
		const count = await candidates.count();
		for ( let i = 0; i < count; i++ ) {
			const frame = candidates.nth( i ).contentFrame();
			const isPaymentUI = await frame
				.locator(
					'[name="number"], [role="button"]:has-text("Card"), button:has-text("Card")'
				)
				.first()
				.isVisible()
				.catch( () => false );
			if ( isPaymentUI ) {
				return frame;
			}
		}
		await page.waitForTimeout( 250 );
	} while ( Date.now() < deadline );

	// Fall back so the caller's own failure message names the missing field.
	return candidates.first().contentFrame();
};

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

	const paymentFrame = await getOCPaymentFrame( page, iframeSelector );

	// Expand the Card accordion row if its fields are not showing yet.
	if ( ! ( await paymentFrame.locator( '[name="number"]' ).isVisible() ) ) {
		await paymentFrame
			.locator(
				'[role="button"]:has-text("Card"), button:has-text("Card")'
			)
			.first()
			.click();
	}

	// Fill in test card details
	await paymentFrame.locator( '[name="number"]' ).fill( card.number );
	await paymentFrame
		.locator( '[name="expiry"]' )
		.fill( card.expires.month + card.expires.year );
	await paymentFrame.locator( '[name="cvc"]' ).fill( card.cvc );

	// For emails Link doesn't recognize, it offers signup with "Save my
	// information" pre-checked, which requires a mobile number the tests
	// don't fill and blocks the payment; opt out instead. Best-effort:
	// mandatory-save flows (e.g. subscriptions) render the checkbox only
	// transiently before re-rendering without it, so it can disappear
	// between the check and the uncheck.
	const linkSaveInfo = paymentFrame.getByRole( 'checkbox', {
		name: 'Save my information for faster checkout',
	} );
	if (
		await linkSaveInfo.isChecked( { timeout: 5000 } ).catch( () => false )
	) {
		await linkSaveInfo.uncheck( { timeout: 5000 } ).catch( () => {} );
	}
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

/**
 * Set up the checkout page for BECS payment.
 *
 * @param {Page} page Playwright page fixture.
 * @param {string} checkoutType The type of checkout ('blocks' or 'shortcode').
 */
export const setupBECSCheckout = async ( page, checkoutType = 'blocks' ) => {
	await emptyCart( page );
	await setupCart( page );

	if ( checkoutType === 'blocks' ) {
		// On block checkout page for Australian address, there is no city, instead there are a suburbs.
		// In the backend we keep this suburb value in the city field.
		// In 'setupBlocksCheckout' we find the elemnts by their labels. As there is no city field on the block checkout page,
		// we remove the city field from the billing details to prevent the 'setupBlocksCheckout' from failing when waiting for the city field
		// and add the suburb value to the city field.
		const billingDetails = {
			...config.get( 'addresses.customer_australia.billing' ),
			suburb: config.get( 'addresses.customer_australia.billing' ).city,
		};
		delete billingDetails.city;

		await setupBlocksCheckout( page, billingDetails );

		// Select BECS in blocks checkout.
		const becsLabel = page
			.locator( 'label' )
			.filter( { hasText: 'BECS Direct Debit' } );
		await becsLabel.waitFor( { state: 'visible' } );
		await becsLabel.dispatchEvent( 'click' );
	} else {
		await setupShortcodeCheckout(
			page,
			config.get( 'addresses.customer_australia.billing' )
		);

		// Select BECS in shortcode checkout.
		const becsLabel = page.getByText( 'BECS Direct Debit' );
		await becsLabel.waitFor( { state: 'visible' } );
		await becsLabel.dispatchEvent( 'click' );
		const frameHandle = await page.waitForSelector(
			'.payment_method_stripe_au_becs_debit iframe[name^="__privateStripeFrame"]'
		);
		const stripeFrame = await frameHandle.contentFrame();

		// Wait for the BECS form fields to be available.
		await expect(
			stripeFrame.locator( '[name="auBankAccountNumber"]' )
		).toBeVisible( { timeout: 30000 } );
	}
};

/**
 * Interact with the Stripe Elements iframe to fill in the BECS details.
 *
 * @param {Page} page Playwright page fixture.
 */
export const fillBECSDetails = async ( page, checkoutType = 'blocks' ) => {
	let frameHandle;
	if ( checkoutType === 'shortcode' ) {
		frameHandle = await page.waitForSelector(
			'.wc_payment_method.payment_method_stripe_au_becs_debit iframe[src*="elements-inner-payment"]'
		);
	} else {
		frameHandle = await page.waitForSelector(
			'#radio-control-wc-payment-method-options-stripe_au_becs_debit__content iframe[src*="elements-inner-payment"]'
		);
	}

	const stripeFrame = await frameHandle.contentFrame();

	// Wait for the BECS form fields to be available.
	await expect(
		stripeFrame.locator( '[name="auBankAccountNumber"]' )
	).toBeVisible( { timeout: 30000 } );

	await stripeFrame
		.locator( '[name="auBankAccountNumber"]' )
		.fill( '000123456' );
	await stripeFrame.locator( '[name="auBsb"]' ).fill( '000000' );
};

/**
 * Set up the checkout page for Affirm payment.
 *
 * @param {Page} page Playwright page fixture.
 * @param {string} checkoutType The type of checkout ('blocks' or 'shortcode').
 */
export const setupAffirmCheckout = async ( page, checkoutType = 'blocks' ) => {
	// Affirm is only available when the price is above $50.
	const lineItems = [ [ config.get( 'products.simple.name' ), 5 ] ];

	await emptyCart( page );
	await setupCart( page, lineItems );

	const isBlocks = checkoutType === 'blocks';

	// Fill billing details
	const billingDetails = config.get( 'addresses.customer.billing' );
	if ( isBlocks ) {
		await setupBlocksCheckout( page, billingDetails );
	} else {
		await setupShortcodeCheckout( page, billingDetails );
	}

	// Wait for the payment method selector to be available
	if ( isBlocks ) {
		const affirmLabel = page.locator( 'label', { hasText: 'Affirm' } );
		await affirmLabel.waitFor( { state: 'visible' } );
		await affirmLabel.click();
		await expect(
			page
				.frameLocator(
					'#radio-control-wc-payment-method-options-stripe_affirm__content iframe[name^="__privateStripeFrame"]'
				)
				.getByTestId( 'next-action-text' )
		).toBeAttached();
	} else {
		const affirmLabel = page.getByText( 'Affirm' );
		await affirmLabel.waitFor( { state: 'visible' } );
		await affirmLabel.click();
		await expect(
			page
				.frameLocator(
					'.payment_method_stripe_affirm iframe[src*="elements-inner-payment"]'
				)
				.getByTestId( 'next-action-text' )
		).toBeAttached();
	}
};

/**
 * Set up the checkout page for Klarna payment.
 *
 * @param {Page} page Playwright page fixture.
 * @param {string} checkoutType The type of checkout ('blocks' or 'shortcode').
 */
export const setupKlarnaCheckout = async ( page, checkoutType = 'blocks' ) => {
	await emptyCart( page );
	await setupCart( page );

	const isBlocks = checkoutType === 'blocks';

	// Fill billing details
	const billingDetails = config.get( 'addresses.customer.billing' );
	if ( isBlocks ) {
		await setupBlocksCheckout( page, billingDetails );
	} else {
		await setupShortcodeCheckout( page, billingDetails );
	}

	// Wait for the payment method selector to be available
	if ( isBlocks ) {
		const klarnaLabel = page.locator( 'label', { hasText: 'Klarna' } );
		await klarnaLabel.waitFor( { state: 'visible' } );
		await klarnaLabel.click();
		await expect(
			page
				.frameLocator(
					'#radio-control-wc-payment-method-options-stripe_klarna__content iframe[name^="__privateStripeFrame"]'
				)
				.getByTestId( 'next-action-text' )
		).toBeVisible();
	} else {
		const klarnaLabel = page.getByText( 'Klarna' );
		await klarnaLabel.waitFor( { state: 'visible' } );
		await klarnaLabel.click();
		await expect(
			page
				.frameLocator(
					'.payment_method_stripe_klarna iframe[src*="elements-inner-payment"]'
				)
				.getByTestId( 'next-action-text' )
		).toBeVisible();
	}
};

/**
 * Helper method to extract the order ID from a WooCommerce "Order received" page URL.
 * This is used to discover a recently purchased order ID from the order-received page URL.
 *
 * @param {string} url The order-received URL (e.g. `.../order-received/123/?key=...`).
 * @returns {string} The order ID.
 */
export const getOrderIdFromOrderReceivedUrl = ( url ) =>
	url.split( 'order-received/' )[ 1 ].split( '/' )[ 0 ];

export const waitForOrderReceivedPage = async ( page ) => {
	await page.waitForURL( '**/checkout/order-received/**' );

	// Match by role and text: classic themes render `h1.entry-title` while
	// block themes render a plain h1 in the order-confirmation template.
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Order received' } )
	).toBeVisible();
};

/**
 * Wait for the order received page to load and optionally confirm the expected total amount was charged.
 *
 * @param {Browser} browser       Playwright browser fixture.
 * @param {Page}    page          Playwright page fixture.
 * @param {string}  expectedTotal The expected total amount of the order.
 */
export const waitForOrderReceivedPageAndConfirmExpectedTotal = async (
	browser,
	page,
	expectedTotal
) => {
	await waitForOrderReceivedPage( page );

	const orderId = getOrderIdFromOrderReceivedUrl( page.url() );

	await verifyOrderChargedAmount( browser, orderId, expectedTotal );
};
