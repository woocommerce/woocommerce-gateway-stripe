const { expect } = require( '@playwright/test' );
import { setupProductCheckout } from '../payments';

exports.WooCheckoutPage = class WooCheckoutPage {
	constructor( page ) {
		this.page = page;
	}

	/**
	 * Perform the Sign up purchase on the merchant.
	 * @param {*} emailOverride If a random prefix should be added to randomize the email.
	 */
	async performSignUpPurchase(
		page,
		billingAddress,
		randomizeEmail = false
	) {
		await setupProductCheckout( page );

		let email = randomizeEmail
			? Date.now() + '+' + billingAddress.email
			: billingAddress.email;

		await setupCheckout( page, {
			...billingAddress,
			email,
		} );

		const card = config.get( 'cards.basic' );

		await fillCardDetails( page, card );
		await fillWooPayMerchantSignUp( page, billingAddress.phone );
		await page.click( 'text=Place order' );
	}

	/**
	 * Add products to the cart, and go to the platform checkout.
	 */
	async startPlatformCheckout( page ) {
		await setupProductCheckout( page );
		await page.goto( '/checkout/' );

		const email = fs.readFileSync(
			'./tests/e2e/last-user-created.tmp',
			'utf8'
		);

		await page.fill( `.platform-checkout-billing-email-input`, email );

		const otpFrameSelector = 'iframe.platform-checkout-otp-iframe.open';

		await expect(
			page.locator( otpFrameSelector ),
			'should present OTP frame if the user exists.'
		).toBeVisible( { timeout: 15000 } );

		const otpFrame = await page
			.$( otpFrameSelector )
			.then( async ( eh ) => eh.contentFrame() );

		const code = '000000';

		await otpFrame.waitForSelector( 'input[maxlength="6"]' );
		await otpFrame.fill( 'input[maxlength="6"]', code );

		await page.waitForSelector( 'body.page-template-woopay-checkout', {
			timeout: 25000,
		} );
	}

	async fillAddressForm( page, address ) {
		await page.waitForSelector( "div[data-testid='address-fields']" );

		// Map the html field name to the field name in test.json .
		const inputMap = {
			first_name: 'firstname',
			last_name: 'lastname',
			postcode: 'postcode',
			city: 'city',
			address_1: 'addressfirstline',
			address_2: 'addresssecondline',
			phone: 'phone',
		};

		for ( const fieldName of Object.keys( inputMap ) ) {
			await page.fill(
				`div[data-testid='address-fields'] input[for='address-management-field--${ fieldName }']`,
				address[ inputMap[ fieldName ] ]
			);
		}

		// select country.
		await page.fill(
			`div[data-testid='address-fields'] div[data-testid='country-field'] input[type='text']`,
			address.country
		);

		await page.click(
			`div[data-testid='address-fields'] div[data-testid='country-field'] .components-form-token-field__suggestion:nth-of-type(1)`
		);

		// select state.
		await page.fill(
			`div[data-testid='address-fields'] div[data-testid='state-field'] input[type='text']`,
			address.state
		);

		await page.click(
			`div[data-testid='address-fields'] div[data-testid='state-field'] .components-form-token-field__suggestion:nth-of-type(1)`
		);
	}

	/**
	 * Fill in the credit card form for the platform.
	 * @param {*} card The card from the config.
	 */
	async fillCreditCardForm( page, card ) {
		await page.waitForSelector( '.components-modal__screen-overlay' );

		await page.waitForSelector( '.__PrivateStripeElement iframe' );

		await page.waitForTimeout( 1000 );

		const inputFrames = await page.$$( '.__PrivateStripeElement iframe' );

		const cardNumberInput = await inputFrames[ 0 ]
			.contentFrame()
			.then( ( contentFrame ) =>
				contentFrame.locator( '.InputElement.Input' )
			);

		const cardDateInput = await inputFrames[ 1 ]
			.contentFrame()
			.then( ( contentFrame ) =>
				contentFrame.locator( '.InputElement.Input' )
			);

		const cardCvcInput = await inputFrames[ 2 ]
			.contentFrame()
			.then( ( contentFrame ) =>
				contentFrame.locator( '.InputElement.Input' )
			);

		await cardNumberInput.type( card.number, { delay: 10 } );
		await cardDateInput.type( card.expires.month + card.expires.year, {
			delay: 10,
		} );
		await cardCvcInput.type( card.cvc, { delay: 10 } );
	}
};
