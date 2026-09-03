import { expect } from '@playwright/test';

export const getLinkButton = async ( page, isBlockPage = false ) => {
	const frameSelector = isBlockPage
		? '#express-payment-method-express_checkout_element_link iframe[name^="__privateStripeFrame"]'
		: '#wc-stripe-express-checkout-element-link iframe[name^="__privateStripeFrame"]';

	const frameLocator = page.frameLocator( frameSelector );

	return frameLocator.getByRole( 'button', {
		name: /Pay (securely )?with Link/,
	} );
};

export const openLinkPopup = async ( page, isBlockPage = false ) => {
	const linkButton = await getLinkButton( page, isBlockPage );
	await expect( linkButton ).toBeVisible( { timeout: 60 * 1000 } );
	await expect( linkButton ).toBeEnabled();

	// The Express Checkout Element ignores clicks that land before Stripe has
	// re-synced the iframe's position after a scroll, and Playwright scrolls the
	// button into view as part of the click itself. So the first click is
	// silently swallowed whenever the button starts below the fold, as it does
	// on the Cart block; by the second the page no longer needs to scroll.
	const context = page.context();
	let popup;
	await expect( async () => {
		[ popup ] = await Promise.all( [
			context.waitForEvent( 'page', { timeout: 10 * 1000 } ),
			linkButton.click(),
		] );
	} ).toPass( { timeout: 45 * 1000 } );

	await popup.waitForLoadState();

	await expect( popup.getByTestId( 'pay-button' ) ).toBeVisible( {
		timeout: 60 * 1000,
	} );

	return popup;
};

export const assertLinkModalLoads = async ( page, isBlockPage = false ) => {
	await openLinkPopup( page, isBlockPage );
};

/**
 * Create a new Link account from inside the Link popup.
 *
 * Only usable against Stripe sandbox/test mode, where any email can enroll
 * and no SMS verification is required at signup.
 *
 * @param {Page}   popup The Link popup page.
 * @param {string} email Email to enroll. Use a unique address per run — an
 *                       already-enrolled email triggers the login flow instead.
 * @param {string} phone US-format phone number.
 */
export const signUpForLink = async ( popup, email, phone ) => {
	await popup.locator( 'input[name="email"]' ).fill( email );

	const phoneInput = popup.locator( 'input[type="tel"]' ).first();
	await phoneInput.waitFor( { timeout: 15 * 1000 } );
	// The phone country selector defaults to the runner's locale, which would
	// reject the US-format test number.
	await popup.locator( 'select' ).first().selectOption( 'US' );
	await phoneInput.fill( phone );

	await popup.getByTestId( 'sign-up-form-submit-button' ).click();
};

/**
 * Fill the new-payment-method form shown after signing up for Link.
 *
 * @param {Page}   popup   The Link popup page.
 * @param {Object} card    Card fixture (config `cards.*`).
 * @param {Object} address Billing address fixture (config `addresses.*.billing`).
 */
export const fillLinkPaymentDetails = async ( popup, card, address ) => {
	const cardInput = popup.locator( 'input[name="cardNumber"]' );
	await cardInput.waitFor( { timeout: 30 * 1000 } );

	// Select the country first: it defaults to the runner's locale and
	// decides which address fields render.
	await popup
		.locator( 'select[name="billingAddress.country"]' )
		.selectOption( address.country_iso );

	await cardInput.fill( card.number );
	await popup
		.locator( 'input[name="cardExpiry"]' )
		.fill( `${ card.expires.month } / ${ card.expires.year }` );
	await popup.locator( 'input[name="cardCvc"]' ).fill( card.cvc );

	await popup
		.locator( 'input[name="billingAddress.name"]' )
		.fill( `${ address.first_name } ${ address.last_name }` );
	await popup
		.locator( 'input[name="billingAddress.addressLine1"]' )
		.fill( address.address_1 );
	await popup
		.locator( 'input[name="billingAddress.locality"]' )
		.fill( address.city );
	await popup
		.locator( 'input[name="billingAddress.postalCode"]' )
		.fill( address.postcode );
	await popup
		.locator( 'select[name="billingAddress.administrativeArea"]' )
		.selectOption( address.state_iso );

	// Typing into the address fields can leave an autocomplete suggestion
	// list overlaying the submit button.
	await popup.keyboard.press( 'Escape' );
};

/**
 * Log in to an existing Link account from inside the Link popup.
 *
 * Only usable against Stripe sandbox/test mode, where 000000 is accepted as
 * the SMS verification code.
 *
 * @param {Page}   popup The Link popup page.
 * @param {string} email Email of an already-enrolled Link account.
 */
export const loginToLink = async ( popup, email ) => {
	await popup.locator( 'input[name="email"]' ).fill( email );

	const otpInput = popup
		.locator( 'input[autocomplete="one-time-code"]' )
		.first();
	await otpInput.waitFor( { timeout: 30 * 1000 } );
	await otpInput.fill( '000000' );
};
