import config from 'config';

import {
	checkUseNewPaymentMethod,
	fillUpeCard,
	setupProductCheckout,
} from '../../utils/payments';
import { stripeUPESettingsUtils } from '../../utils/upe-settings';
import { confirmCardAuthentication } from '../../utils/payments';
import { merchant } from '@woocommerce/e2e-utils';
import { addNewPaymentMethod } from '../../utils/shopper/account';

describe( 'Successfull Purchase', () => {
	beforeAll( async () => {
		await merchant.login();
		await stripeUPESettingsUtils.resetSettings();
		await stripeUPESettingsUtils.activateUpe();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'using a basic card', async () => {
		await stripeUPESettingsUtils.activatePaymentMethod( 'card' );
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await checkUseNewPaymentMethod();
		const card = config.get( 'cards.basic' );
		await fillUpeCard( card );

		await expect( page ).toClick( '#place_order' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'using a SCA card', async () => {
		await stripeUPESettingsUtils.activatePaymentMethod( 'card' );
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.sca' );

		await checkUseNewPaymentMethod();

		await fillUpeCard( card );
		await expect( page ).toClick( '#place_order' );

		await confirmCardAuthentication();

		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'save card', async () => {
		const card = config.get( 'cards.basic' );
		await addNewPaymentMethod( 'basic', card );
		await expect( page ).toMatch( 'Payment method successfully added' );
	} );
} );
