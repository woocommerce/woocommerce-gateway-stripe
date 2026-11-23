import { expect } from '@playwright/test';
import { api, payments, products } from '../../utils';

const { retryWithBackoff } = payments;

export const createFreeTrialProduct = ( { virtual } ) =>
	api.create.product( products.freeTrialSubscriptionData( { virtual } ) );

export const getLinkButton = async ( page, isBlockPage = false ) => {
	const frameSelector = isBlockPage
		? '#express-payment-method-express_checkout_element_link iframe[name^="__privateStripeFrame"]'
		: '#wc-stripe-express-checkout-element-link iframe[name^="__privateStripeFrame"]';

	const frameLocator = page.frameLocator( frameSelector );

	return frameLocator.getByRole( 'button', {
		name: /Pay (securely )?with Link/,
	} );
};

export const getLinkPayButton = ( popup ) =>
	popup
		.getByTestId( 'pay-button' )
		.or( popup.getByRole( 'button', { name: /^(Pay|Subscribe)$/ } ) );

export const openLinkPopup = async (
	page,
	isBlockPage = false,
	{ maxRetries = 1 } = {}
) => {
	const modal = page.locator( 'iframe[src*="universal-link-modal-inner"]' );
	let isFirstAttempt = true;

	// Both known failure modes (the Stripe iframe never finishes loading; a
	// click shows Link's loading state but no popup window or modal opens) have
	// only been seen to recover after a reload, so the retry reloads the page.
	// The waits are sized so both attempts fit inside the 120s test timeout
	// most callers run under, with room for the steps around this helper.
	const link = await retryWithBackoff(
		async () => {
			if ( ! isFirstAttempt ) {
				await page.reload();
			}
			isFirstAttempt = false;

			const linkButton = await getLinkButton( page, isBlockPage );
			await expect( linkButton ).toBeVisible( { timeout: 15 * 1000 } );
			await expect( linkButton ).toBeEnabled( { timeout: 5 * 1000 } );

			let popup;
			const onPopup = ( openedPage ) => {
				popup = openedPage;
			};
			const isOpen = async () =>
				( popup && ! popup.isClosed() ) || ( await modal.isVisible() );
			page.on( 'popup', onPopup );
			try {
				await linkButton.click( { timeout: 5 * 1000 } );
				try {
					await expect
						.poll( isOpen, { timeout: 10 * 1000 } )
						.toBeTruthy();
				} catch {
					// Stripe can miss a click during iframe repositioning. Never
					// click again over an already-open modal or reload its form.
					if ( ! ( await isOpen() ) ) {
						await linkButton.click( { timeout: 5 * 1000 } );
					}
					await expect
						.poll( isOpen, { timeout: 14 * 1000 } )
						.toBeTruthy();
				}

				const popupPage = popup && ! popup.isClosed() ? popup : null;
				const content = popupPage || modal.contentFrame();
				const waitForClose = () =>
					popupPage
						? popupPage.waitForEvent( 'close', {
								timeout: 90 * 1000,
						  } )
						: modal.waitFor( {
								state: 'hidden',
								timeout: 90 * 1000,
						  } );
				return {
					content,
					waitForClose,
					close: async () => {
						if ( popupPage ) {
							await popupPage.close();
						} else {
							await content
								.getByRole( 'button', {
									name: 'Close',
									exact: true,
								} )
								.click();
							await waitForClose();
						}
					},
				};
			} finally {
				page.off( 'popup', onPopup );
			}
		},
		{ maxRetries }
	);

	// The email-first modal has no payment button until authentication finishes.
	await expect(
		link.content
			.locator( 'input[name="email"]:visible' )
			.or( getLinkPayButton( link.content ) )
			.first()
	).toBeVisible( { timeout: 60 * 1000 } );
	return link;
};

export const assertLinkModalLoads = async ( page, isBlockPage = false ) => {
	await openLinkPopup( page, isBlockPage );
};

/**
 * Create a new Link account from inside the Link popup.
 *
 * Only usable against Stripe sandbox/test mode, where any email can enroll
 * and 000000 completes verification when requested.
 *
 * @param {Page|FrameLocator} popup The Link popup page or modal frame locator.
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
	const continueButton = popup
		.getByTestId( 'sign-up-form-submit-button' )
		.filter( { hasText: /^Continue$/ } );
	if ( await continueButton.isVisible() ) {
		await continueButton.click();
	}

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

	// Link can verify a new account before showing its address/payment form.
	// Older popup flows skip this step, so wait for either outcome.
	await expect(
		popup
			.locator(
				'input[autocomplete="one-time-code"]:visible, input[name="name"]:visible, input[name="cardNumber"]:visible'
			)
			.first()
	).toBeVisible( { timeout: 30 * 1000 } );
	const otpInput = popup
		.locator( 'input[autocomplete="one-time-code"]' )
		.first();
	if ( await otpInput.isVisible() ) {
		await otpInput.fill( '000000' );
	}
};

/**
 * Fill the new-payment-method form shown after signing up for Link.
 *
 * @param {Page|FrameLocator} popup   The Link popup page or modal frame locator.
 * @param {Object} card    Card fixture (config `cards.*`).
 * @param {Object} address Billing address fixture (config `addresses.*.billing`).
 */
export const fillLinkPaymentDetails = async ( popup, card, address ) => {
	const cardInput = popup.locator( 'input[name="cardNumber"]' );
	await expect(
		cardInput,
		'Link payment form (card number field) did not appear'
	).toBeVisible( { timeout: 30 * 1000 } );

	if ( await popup.getByTestId( 'select-button' ).isVisible() ) {
		await fillLinkCardDetails( popup, card );
		// The modal collects billing separately, using the same address form
		// as shipping rather than the popup's billingAddress-prefixed fields.
		await fillLinkShippingAddress( popup, address );
		return;
	}

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
 * @param {Page|FrameLocator} popup   The Link popup page or modal frame locator.
 * @param {Object} address Shipping address fixture (config `addresses.*.shipping`).
 */
export const fillLinkShippingAddress = async ( popup, address ) => {
	const nameInput = popup.locator( 'input[name="name"]' );
	await expect(
		nameInput,
		'Link shipping address form did not appear'
	).toBeVisible( { timeout: 30 * 1000 } );

	// Country decides the field layout, so set it before the address fields.
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

	// Blur the street field to dismiss autocomplete suggestions. Escape can
	// close the entire embedded Link modal when no suggestion list is open.
	const continueButton = popup.getByRole( 'button', {
		name: /^(Continue to payment|Continue)$/,
	} );
	await expect( async () => {
		await popup.locator( 'input[name="addressLine1"]' ).press( 'Tab' );
		await continueButton.click( { timeout: 2 * 1000 } );
	} ).toPass( { timeout: 20 * 1000 } );
};

/**
 * Fill the card-only payment form Link shows when the billing address is
 * taken from the shipping address entered in the previous step.
 *
 * @param {Page|FrameLocator} popup The Link popup page or modal frame locator.
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

	const continueButton = popup.getByTestId( 'select-button' );
	if ( await continueButton.isVisible() ) {
		await continueButton.click();
	}
};

/**
 * Log in to an existing Link account from inside the Link popup.
 *
 * Only usable against Stripe sandbox/test mode, where 000000 is accepted as
 * the SMS verification code.
 *
 * @param {Page|FrameLocator} popup The Link popup page or modal frame locator.
 * @param {string} email Email of an already-enrolled Link account.
 * @param {string} cvc Security code for the saved test card.
 */
export const loginToLink = async ( popup, email, cvc ) => {
	const emailInput = popup.locator( 'input[name="email"]' );
	await expect(
		emailInput,
		'Link login email field did not appear'
	).toBeVisible( { timeout: 30 * 1000 } );
	await emailInput.fill( email );
	const continueButton = popup
		.getByTestId( 'sign-up-form-submit-button' )
		.filter( { hasText: /^Continue$/ } );
	if ( await continueButton.isVisible() ) {
		await continueButton.click();
	}

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

	// The modal may require the saved card's CVC before showing confirmation.
	const cvcInput = popup.locator( 'input[name="cardCvc"]:visible' );
	await expect(
		cvcInput.or( getLinkPayButton( popup ) ).first()
	).toBeVisible();
	if ( await cvcInput.isVisible() ) {
		await cvcInput.fill( cvc );
		await popup.getByTestId( 'select-button' ).click();
	}
};
