import config from 'config';

import {
	checkUseNewPaymentMethod,
	fillUpeCard,
	setupProductCheckout,
} from '../../utils/payments';
import {
	activatePaymentMethod,
	activateUpe,
	resetSettings,
} from '../../utils/upe-settings';
import { confirmCardAuthentication } from '../../utils/payments';
import { merchant, shopper } from '@woocommerce/e2e-utils';
import {
	addNewPaymentMethod,
	removeSavedPaymentMethods,
} from '../../utils/shopper/account';

describe( 'Checkout', () => {
	beforeAll( async () => {
		await merchant.login();
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
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await checkUseNewPaymentMethod();
		await fillUpeCard( config.get( 'cards.basic' ) );

		await expect( page ).toClick( '#place_order' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );

		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'using a SCA card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await checkUseNewPaymentMethod();
		await fillUpeCard( config.get( 'cards.sca' ) );

		await expect( page ).toClick( '#place_order' );

		await confirmCardAuthentication();
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );

		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'using a saved card', async () => {
		await addNewPaymentMethod( 'basic', config.get( 'cards.basic' ) );
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await expect( page ).toClick( '#place_order' );

		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );

		await expect( page ).toMatch( 'Order received' );
	} );
} );
