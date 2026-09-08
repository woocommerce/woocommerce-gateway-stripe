import { expect } from '@playwright/test';
import { api, payments, products } from '../../utils';

const { clickAddToCartButton, retryWithBackoff, selectSubscriptionOption } =
	payments;

export const createFreeTrialProduct = ( { virtual } ) =>
	api.create.product( products.freeTrialSubscriptionData( { virtual } ) );

// APFS products offer a one-time vs subscription choice, so pick the
// subscription option before adding to the cart (mirrors the subscription
// purchase specs).
export const addSubscriptionToCart = async ( page, productId ) => {
	await page.goto( `?p=${ productId }` );
	await selectSubscriptionOption( page );
	await clickAddToCartButton( page, 'Sign up' );
	await expect(
		page.getByText( 'has been added to your cart' )
	).toBeVisible();
};

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
	const context = page.context();
	let isFirstAttempt = true;

	// Both known failure modes (the Stripe iframe never finishes loading; a
	// click shows Link's loading state but no popup window opens) have only
	// been seen to recover after a reload, so the retry reloads the page.
	// The waits are sized so both attempts fit inside the 120s test timeout
	// most callers run under, with room for the steps around this helper.
	const popup = await retryWithBackoff(
		async () => {
			if ( ! isFirstAttempt ) {
				await page.reload();
			}
			isFirstAttempt = false;

			const linkButton = await getLinkButton( page, isBlockPage );
			await expect( linkButton ).toBeVisible( { timeout: 15 * 1000 } );
			await expect( linkButton ).toBeEnabled( { timeout: 5 * 1000 } );

			// The first click is silently ignored when Playwright's
			// scroll-into-view outpaces Stripe's iframe position re-sync, so
			// allow a second one. The click timeout stays below the popup
			// wait so an unfinished click can't outlive its own popup window
			// and land on a later page state.
			let lastClickError;
			for ( let click = 0; click < 2; click++ ) {
				try {
					const [ newPage ] = await Promise.all( [
						context.waitForEvent( 'page', {
							timeout: 12 * 1000,
						} ),
						linkButton.click( { timeout: 5 * 1000 } ),
					] );
					return newPage;
				} catch ( error ) {
					lastClickError = error;
				}
			}
			throw new Error(
				'The Link button was clicked but its popup did not open.',
				{ cause: lastClickError }
			);
		},
		{ maxRetries: 1 }
	);

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
	const emailInput = popup.locator( 'input[name="email"]' );
	await expect(
		emailInput,
		'Link sign-up email field did not appear'
	).toBeVisible( { timeout: 30 * 1000 } );
	await emailInput.fill( email );

	const phoneInput = popup.locator( 'input[type="tel"]' ).first();
	await expect(
		phoneInput,
		'Link sign-up phone field did not appear — the email may already be enrolled, which shows the login flow instead'
	).toBeVisible( { timeout: 15 * 1000 } );
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
	await expect(
		cardInput,
		'Link payment form (card number field) did not appear'
	).toBeVisible( { timeout: 30 * 1000 } );

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
 * Fill the shipping address form shown after signing up for Link with a
 * shipping-required cart, and continue to the payment step.
 *
 * @param {Page}   popup   The Link popup page.
 * @param {Object} address Shipping address fixture (config `addresses.*.shipping`).
 */
export const fillLinkShippingAddress = async ( popup, address ) => {
	const nameInput = popup.locator( 'input[name="name"]' );
	await expect(
		nameInput,
		'Link shipping address form did not appear'
	).toBeVisible( { timeout: 30 * 1000 } );

	// Country decides the field layout, so set it first. Fill the street last so
	// focus stays on it: typing into it opens an async Google suggestion overlay
	// that can cover "Continue to payment", and it only closes with Escape while
	// the street field is focused.
	await popup
		.locator( 'select[name="country"]' )
		.selectOption( address.country_iso );
	await nameInput.fill( `${ address.first_name } ${ address.last_name }` );
	await popup.locator( 'input[name="locality"]' ).fill( address.city );
	await popup.locator( 'input[name="postalCode"]' ).fill( address.postcode );
	await popup
		.locator( 'select[name="administrativeArea"]' )
		.selectOption( address.state_iso );
	await popup
		.locator( 'input[name="addressLine1"]' )
		.fill( address.address_1 );

	// Retry Escape + click so a late-appearing suggestion overlay can't
	// intercept the click: if it does, the click throws, Escape clears it, and
	// the next attempt lands. This avoids depending on Stripe's internal overlay
	// markup — it only targets the button's accessible name.
	const continueButton = popup.getByRole( 'button', {
		name: 'Continue to payment',
	} );
	await expect( async () => {
		await popup.keyboard.press( 'Escape' );
		await continueButton.click( { timeout: 2 * 1000 } );
	} ).toPass( { timeout: 20 * 1000 } );
};

/**
 * Fill the card-only payment form Link shows when the billing address is
 * taken from the shipping address entered in the previous step.
 *
 * @param {Page}   popup The Link popup page.
 * @param {Object} card  Card fixture (config `cards.*`).
 */
export const fillLinkCardDetails = async ( popup, card ) => {
	const cardInput = popup.locator( 'input[name="cardNumber"]' );
	await expect(
		cardInput,
		'Link payment form (card number field) did not appear'
	).toBeVisible( { timeout: 30 * 1000 } );
	await cardInput.fill( card.number );
	await popup
		.locator( 'input[name="cardExpiry"]' )
		.fill( `${ card.expires.month } / ${ card.expires.year }` );
	await popup.locator( 'input[name="cardCvc"]' ).fill( card.cvc );
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
	const emailInput = popup.locator( 'input[name="email"]' );
	await expect(
		emailInput,
		'Link login email field did not appear'
	).toBeVisible( { timeout: 30 * 1000 } );
	await emailInput.fill( email );

	const otpInput = popup
		.locator( 'input[autocomplete="one-time-code"]' )
		.first();
	await expect(
		otpInput,
		'Link OTP field did not appear — expected an already-enrolled account for this email'
	).toBeVisible( { timeout: 30 * 1000 } );
	// Sandbox Link accepts any 6-digit passcode except a few reserved error
	// codes, so 000000 authenticates. See the sandbox OTP table at
	// https://docs.stripe.com/payments/link/payment-element-link#test-the-integration
	await otpInput.fill( '000000' );
};
