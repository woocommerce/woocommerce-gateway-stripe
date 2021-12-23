/**
 * External dependencies
 */
import config from 'config';

// import { stripeUPESettingsUtils } from '../../utils/upe-settings';
import { fillUpeCard, setupProductCheckout } from '../../utils/payments';
import { buttonsUtils } from '../../utils/buttons';
import { stripeUPESettingsUtils } from '../../utils/upe-settings';
import { merchant } from '@woocommerce/e2e-utils';

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
		const card = config.get( 'cards.basic' );
		await fillUpeCard( card );

		await buttonsUtils.clickButtonWithText( 'Place order' );
		await expect( page ).toClick( '#place_order' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );

		await page.screenshot( {
			path: './test.png',
			fullPage: true,
		} );
	} );
} );
