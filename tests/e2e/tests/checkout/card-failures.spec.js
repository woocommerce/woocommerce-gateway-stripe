const { test, expect } = require( '@playwright/test' );
import config from 'config';
const {
	emptyCart,
	setupProductCheckout,
	setupCheckout,
	fillCardDetails,
} = require( '../../utils/payments' );

test.beforeEach( async ( { page } ) => {
	await emptyCart( page );
	await setupProductCheckout( page );
	await setupCheckout( page, config.get( 'addresses.customer.billing' ) );
} );

// Checkout failures (with various cards)
test( 'customer cannot checkout with invalid cards @smoke', async ( {
	page,
} ) => {
	const cards = [
		config.get( 'cards.declined' ),
		config.get( 'cards.declined-funds' ),
		config.get( 'cards.declined-incorrect' ),
		config.get( 'cards.declined-expired' ),
		config.get( 'cards.declined-cvc' ),
		config.get( 'cards.declined-processing' ),
	];

	for ( const card of cards ) {
		await fillCardDetails( page, card );
		await page.locator( 'text=Place order' ).click();

		expect
			.soft( await page.innerText( '.woocommerce-error' ) )
			.toBe( card.error );
	}
} );
