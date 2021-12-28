import {
	checkUseNewPaymentMethod,
	fillUpeCard,
	setupProductCheckout,
} from '../../utils/payments';
import config from 'config';

describe( 'Checkout with invalid cards', () => {
	it( 'using declined card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await checkUseNewPaymentMethod();

		await fillUpeCard( config.get( 'cards.declined' ) );
		await expect( page ).toClick( '#place_order' );
		await expect( page ).toMatch( 'The card was declined.' );
	} );

	it( 'using card with insufficient funds', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await checkUseNewPaymentMethod();

		await fillUpeCard( config.get( 'cards.insufficient-funds' ) );
		await expect( page ).toClick( '#place_order' );
		await expect( page ).toMatch( 'The card was declined.' );
	} );

	it( 'using expired card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await checkUseNewPaymentMethod();

		await fillUpeCard( config.get( 'cards.expired' ) );
		await expect( page ).toClick( '#place_order' );
		await expect( page ).toMatch( 'The card has expired.' );
	} );

	it( 'using invalid security code card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await checkUseNewPaymentMethod();

		await fillUpeCard( config.get( 'cards.incorrect-security-code' ) );
		await expect( page ).toClick( '#place_order' );
		await expect( page ).toMatch(
			"The card's security code is incorrect."
		);
	} );

	it( 'using generic invalid card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await checkUseNewPaymentMethod();

		await fillUpeCard( config.get( 'cards.generic-error' ) );
		await expect( page ).toClick( '#place_order' );
		await expect( page ).toMatch(
			'An error occurred while processing the card.'
		);
	} );

	it( 'using invalid card number', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await checkUseNewPaymentMethod();

		await fillUpeCard( config.get( 'cards.invalid' ) );
		await expect( page ).toClick( '#place_order' );
		await expect( page ).toMatch( 'Your card number is invalid.' );
	} );
} );
