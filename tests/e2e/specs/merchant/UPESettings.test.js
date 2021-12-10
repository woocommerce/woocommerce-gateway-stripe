import { merchant } from '@woocommerce/e2e-utils';

import { stripeSettingsUtils } from '../../utils/settings';

describe( 'WooCommerce > Settings > Stripe (UPE)', () => {
	beforeAll( async () => {
		await merchant.login();
		//Todo: make sure upe is disabled first
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'can activate UPE', async () => {
		await stripeSettingsUtils.activateUpe();
	} );
} );
