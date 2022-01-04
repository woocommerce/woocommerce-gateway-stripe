import config from 'config';
import { merchant, shopper } from '@woocommerce/e2e-utils';
import {
	activatePaymentMethod,
	activateUpe,
	resetSettings,
} from '../../utils/upe-settings';
import {
	addNewPaymentMethod,
	removeSavedPaymentMethods,
} from '../../utils/shopper/account';
import { addBlocksCheckoutPage } from '../../utils/merchant/admin-settings';
import {
	checkUseNewPaymentMethodWCB,
	fillBillingDetailsWCB,
	fillUpeCardWCB,
	openCheckoutWCB,
} from '../../utils/shopper/blocks-checkout';
import { confirmCardAuthentication } from '../../utils/shopper/payment';

describe( 'Checkout', () => {
	beforeAll( async () => {
		await merchant.login();
		await addBlocksCheckoutPage();
		await resetSettings();
		await activateUpe();
		await activatePaymentMethod( 'card' );
		await merchant.logout();

		await shopper.login();
		await removeSavedPaymentMethods();
	} );

	afterAll( async () => {
		await shopper.logout();
	} );

	it( 'using declined card', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await openCheckoutWCB();
		await fillBillingDetailsWCB(
			config.get( 'addresses.customer.shipping' )
		);
		await checkUseNewPaymentMethodWCB();
		await fillUpeCardWCB( config.get( 'cards.declined' ) );

		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await page.waitForSelector( '.wc-block-components-notices' );

		await expect( page ).toMatch( 'Your card was declined.' );
	} );

	it( 'using card with insufficient funds', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await openCheckoutWCB();
		await fillBillingDetailsWCB(
			config.get( 'addresses.customer.shipping' )
		);
		await checkUseNewPaymentMethodWCB();
		await fillUpeCardWCB( config.get( 'cards.insufficient-funds' ) );

		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await page.waitForSelector( '.wc-block-components-notices' );

		await expect( page ).toMatch( 'Your card has insufficient funds.' );
	} );

	it( 'using expired card', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await openCheckoutWCB();
		await fillBillingDetailsWCB(
			config.get( 'addresses.customer.shipping' )
		);
		await checkUseNewPaymentMethodWCB();
		await fillUpeCardWCB( config.get( 'cards.expired' ) );

		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await page.waitForSelector( '.wc-block-components-notices' );

		await expect( page ).toMatch( 'Your card has expired.' );
	} );

	it( 'using invalid security code card', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await openCheckoutWCB();
		await fillBillingDetailsWCB(
			config.get( 'addresses.customer.shipping' )
		);
		await checkUseNewPaymentMethodWCB();
		await fillUpeCardWCB( config.get( 'cards.incorrect-security-code' ) );

		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await page.waitForSelector( '.wc-block-components-notices' );

		await expect( page ).toMatch(
			"Your card's security code is incorrect."
		);
	} );

	it( 'using generic invalid card', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await openCheckoutWCB();
		await fillBillingDetailsWCB(
			config.get( 'addresses.customer.shipping' )
		);
		await checkUseNewPaymentMethodWCB();
		await fillUpeCardWCB( config.get( 'cards.generic-error' ) );

		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await page.waitForSelector( '.wc-block-components-notices' );

		await expect( page ).toMatch(
			'An error occurred while processing your card. Try again in a little bit.'
		);
	} );

	it( 'using invalid card number', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await openCheckoutWCB();
		await fillBillingDetailsWCB(
			config.get( 'addresses.customer.shipping' )
		);
		await checkUseNewPaymentMethodWCB();
		await fillUpeCardWCB( config.get( 'cards.invalid' ) );

		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await page.waitForSelector( '.wc-block-components-notices' );

		await expect( page ).toMatch(
			'Your payment information is incomplete.'
		);
	} );
} );
