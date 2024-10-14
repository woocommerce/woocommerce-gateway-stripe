import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../../utils';

const {
	emptyCart,
	setupCart,
	setupBlocksCheckout,
	fillCreditCardDetailsLegacy,
} = payments;

test.beforeEach( async ( { page } ) => {
	await emptyCart( page );
	await setupCart( page );
	await setupBlocksCheckout(
		page,
		config.get( 'addresses.customer.billing' )
	);
} );

const testCard = async ( page, cardKey ) => {
	const card = config.get( cardKey );

	await fillCreditCardDetailsLegacy( page, card );
	await page.locator( 'text=Place order' ).click();

	/**
	 * The invalid card error message is shown in the input field validation.
	 * The customer isn't allowed to place the order for this type of card failure.
	 */
	const expected = await page.innerText(
		cardKey === 'cards.declined-incorrect'
			? '.wc-card-number-element .wc-block-components-validation-error'
			: '.wc-block-store-notice.is-error .wc-block-components-notice-banner__content'
	);
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
