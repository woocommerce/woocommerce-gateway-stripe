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

	it( 'using a basic card', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await openCheckoutWCB();
		await fillBillingDetailsWCB(
			config.get( 'addresses.customer.shipping' )
		);
		await checkUseNewPaymentMethodWCB();
		await fillUpeCardWCB( config.get( 'cards.basic' ) );

		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await page.waitForSelector( 'div.woocommerce-order' );

		await page.screenshot( {
			path: __dirname + `/error5.png`,
			fullPage: true,
		} );

		await expect( page ).toMatch( 'p', {
			text: 'Thank you. Your order has been received.',
		} );
	} );

	it( 'using a SCA card', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await openCheckoutWCB();
		await fillBillingDetailsWCB(
			config.get( 'addresses.customer.shipping' )
		);
		await checkUseNewPaymentMethodWCB();
		await fillUpeCardWCB( config.get( 'cards.sca' ) );

		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await confirmCardAuthentication();
		await page.waitForSelector( 'div.woocommerce-order' );
		await expect( page ).toMatch( 'p', {
			text: 'Thank you. Your order has been received.',
		} );
	} );

	it( 'using a saved card', async () => {
		await addNewPaymentMethod( 'basic', config.get( 'cards.basic' ) );

		await shopper.goToShop();
		await shopper.addToCartFromShopPage(
			config.get( 'products.simple.name' )
		);
		await openCheckoutWCB();
		await fillBillingDetailsWCB(
			config.get( 'addresses.customer.shipping' )
		);

		/*
		 * Even though the saved card is selected by default
		 * We need to click on the new payment method option and then
		 * click on the saved payment option because of a bug that happens.
		 * Make sure this bug was fixed before removing this two lines
		 */
		await checkUseNewPaymentMethodWCB();
		await expect( page ).toClick(
			'input[name="radio-control-wc-payment-method-saved-tokens"]'
		);

		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await page.waitForSelector( 'div.woocommerce-order' );
		await expect( page ).toMatch( 'p', {
			text: 'Thank you. Your order has been received.',
		} );
	} );
} );
