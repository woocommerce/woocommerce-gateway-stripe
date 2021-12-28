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
import { merchant } from '@woocommerce/e2e-utils';

describe( 'Checkout', () => {
	beforeAll( async () => {
		await merchant.login();
		await resetSettings();
		await activateUpe();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'using a basic card', async () => {
		await activatePaymentMethod( 'card' );
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
		await activatePaymentMethod( 'card' );
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
} );
