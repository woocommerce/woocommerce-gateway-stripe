import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../utils';

const {
	emptyCart,
	setupProductCheckout,
	setupBlocksCheckout,
	fillCardDetails,
	isUpeCheckout,
} = payments;

test.beforeEach( async ( { page } ) => {
	await emptyCart( page );
	await setupProductCheckout( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
} );

const testCard = async ( page, cardKey ) => {
	const card = config.get( cardKey );

	await fillCardDetails( page, card );
	await page.locator( 'text=Place order' ).click();

	const isUpe = await isUpeCheckout( page );

	/**
	 * The invalid card error message is shown in the input field validation.
	 * The customer isn't allowed to place the order for this type of card failure.
	 */
	let expected;
	if ( isUpe && cardKey === 'cards.declined-incorrect' ) {
		expected = await page
			.frameLocator(
				'.wc-block-gateway-container iframe[name^="__privateStripeFrame"]'
			)
			.locator( '#Field-numberError' )
			.innerText();
	} else {
		expected = await page.innerText(
			cardKey === 'cards.declined-incorrect'
				? '.wc-card-number-element .wc-block-components-validation-error'
				: '.wc-block-checkout__payment-method .woocommerce-error'
		);
	}
	expect
		.soft( expected )
		.toMatch( new RegExp( `(?:${ card.error.join( '|' ) })`, 'i' ) );
};

test.describe.configure( { mode: 'parallel' } );
test.describe( 'customer cannot checkout with invalid cards @blocks', () => {
	test( `a declined card shows the correct error message @smoke`, async ( {
		page,
	} ) => testCard( page, 'cards.declined' ) );

	test( `a card with insufficient funds shows the correct error message`, async ( {
		page,
	} ) => testCard( page, 'cards.declined-funds' ) );

	test( `a card with invalid number shows the correct error message`, async ( {
		page,
	} ) => testCard( page, 'cards.declined-incorrect' ) );

	test( `an expired card shows the correct error message`, async ( {
		page,
	} ) => testCard( page, 'cards.declined-expired' ) );

	test( `a card with incorrect CVC shows the correct error message @smoke`, async ( {
		page,
	} ) => testCard( page, 'cards.declined-cvc' ) );

	test( `an error processing the card shows the correct error message`, async ( {
		page,
	} ) => testCard( page, 'cards.declined-processing' ) );
} );
