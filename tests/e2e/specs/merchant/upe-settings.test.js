import { merchant } from '@woocommerce/e2e-utils';

import {
	activatePaymentMethod,
	activateUpe,
	deactivatePaymentMethod,
	deactivateUpe,
	resetSettings,
} from '../../utils/upe-settings';

describe( 'WooCommerce > Settings > Stripe (UPE)', () => {
	beforeAll( async () => {
		await merchant.login();
		await resetSettings();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'can activate UPE', async () => {
		await activateUpe();
	} );

	it( 'can activate UPE method', async () => {
		await activatePaymentMethod( 'giropay' );
	} );

	it( 'can deactivate UPE method', async () => {
		await deactivatePaymentMethod( 'giropay' );
	} );

	it( 'can deactivate UPE', async () => {
		await deactivateUpe();
	} );
} );
