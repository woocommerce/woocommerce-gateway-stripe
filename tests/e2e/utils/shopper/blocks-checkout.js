import config from 'config';
import { clearAndFillInput, setCheckbox } from '@woocommerce/e2e-utils';

const baseUrl = config.get( 'url' );
const WCB_CHECKOUT = baseUrl + 'checkout-wcb/';

export async function openCheckoutWCB() {
	await page.goto( WCB_CHECKOUT, {
		waitUntil: 'networkidle0',
	} );
}

export async function fillBillingDetailsWCB( customerBillingDetails ) {
	await clearAndFillInput( '#email', customerBillingDetails.email );
	await clearAndFillInput(
		'#billing-first_name',
		customerBillingDetails.firstname
	);
	await clearAndFillInput(
		'#billing-last_name',
		customerBillingDetails.lastname
	);
	await clearAndFillInput(
		'#billing-address_1',
		customerBillingDetails.addressfirstline
	);

	await clearAndFillInput(
		'#billing-country input',
		customerBillingDetails.country
	);

	await clearAndFillInput(
		'#billing-state input',
		customerBillingDetails.state
	);

	await clearAndFillInput( '#billing-city', customerBillingDetails.city );
	await clearAndFillInput(
		'#billing-postcode',
		customerBillingDetails.postcode
	);
}

export async function fillUpeCardWCB( card ) {
	await page.waitForSelector(
		'#payment-method .StripeElement iframe[name^="__privateStripeFrame"]'
	);
	const frameHandle = await page.waitForSelector(
		'#payment-method .StripeElement iframe[name^="__privateStripeFrame"]'
	);

	const stripeFrame = await frameHandle.contentFrame();
	await stripeFrame.waitForSelector( 'input' );

	const inputs = await stripeFrame.$$( 'input' );
	const [ cardNumberInput, cardDateInput, cardCvcInput ] = inputs;

	await cardNumberInput.type( card.number, { delay: 20 } );
	await cardDateInput.type( card.expires.month + card.expires.year, {
		delay: 20,
	} );
	await cardCvcInput.type( card.cvc, { delay: 20 } );
}

/**
 * Will check the use new payment method radio button if present.
 * This is useful for when a credit card is already saved and you need to checkout with a new one
 */
export async function checkUseNewPaymentMethodWCB() {
	await page.screenshot( {
		path: __dirname + `/finish.png`,
		fullPage: true,
	} );

	if (
		( await page.$$( '.wc-block-components-radio-control__input' ) )
			.length > 1
	) {
		await page.click( '#radio-control-wc-payment-method-options-stripe' );
	}
}
