import { merchant } from '@woocommerce/e2e-utils';

import { stripeUPESettingsUtils } from '../../utils/upe-settings';

describe( 'WooCommerce > Settings > Stripe (UPE)', () => {
	beforeAll( async () => {
		await merchant.login();
		//Todo: make sure upe is disabled first
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'can activate UPE', async () => {
		await stripeUPESettingsUtils.activateUpe();
	} );

	it( 'can activate UPE method', async () => {
		await stripeUPESettingsUtils.activatePaymentMethod( 'giropay' );
	} );

	it( 'can deactivate UPE method', async () => {
		await stripeUPESettingsUtils.deactivatePaymentMethod( 'giropay' );
	} );

	it( 'can deactivate UPE', async () => {
		await stripeUPESettingsUtils.deactivateUpe();
	} );
} );
