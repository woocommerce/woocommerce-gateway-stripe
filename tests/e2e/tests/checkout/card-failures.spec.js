import { test, expect } from '@playwright/test';
import config from 'config';
import { payments } from '../../utils';

const {
	emptyCart,
	setupProductCheckout,
	setupCheckout,
	fillCardDetails,
} = payments;

test.beforeEach( async ( { page } ) => {
	await emptyCart( page );
	await setupProductCheckout( page );
	await setupCheckout( page, config.get( 'addresses.customer.billing' ) );
} );

const testCard = async ( page, cardKey ) => {
	const card = config.get( cardKey );

	await fillCardDetails( page, card );
	await page.locator( 'text=Place order' ).click();

	expect
		.soft( await page.innerText( '.woocommerce-error' ) )
		.toMatch( new RegExp( `(?:${ card.error.join( '|' ) })`, 'i' ) );
};

test.describe.configure( { mode: 'parallel' } );
test.describe( 'customer cannot checkout with invalid cards', () => {
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
