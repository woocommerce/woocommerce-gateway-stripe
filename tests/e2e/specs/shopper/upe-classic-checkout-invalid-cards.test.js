import {
	checkUseNewPaymentMethod,
	fillUpeCard,
	setupProductCheckout,
} from '../../utils/shopper/classic-checkout';
import config from 'config';
import { merchant } from '@woocommerce/e2e-utils';
import { activateUpe, resetSettings } from '../../utils/upe-settings';

describe( 'Checkout with invalid cards', () => {
	beforeAll( async () => {
		await merchant.login();
		await resetSettings();
		await activateUpe();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

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
