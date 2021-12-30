import {
	addNewPaymentMethod,
	removeSavedPaymentMethods,
} from '../../utils/shopper/account';
import config from 'config';
import { merchant } from '@woocommerce/e2e-utils';
import { activateUpe, resetSettings } from '../../utils/upe-settings';

describe( 'My Account', () => {
	beforeAll( async () => {
		await merchant.login();
		await resetSettings();
		await activateUpe();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should save card', async () => {
		await addNewPaymentMethod( 'basic', config.get( 'cards.basic' ) );

		await expect( page ).toMatch( 'Payment method successfully added' );
	} );

	it( 'should remove saved cards', async () => {
		await removeSavedPaymentMethods();

		await expect( page ).toMatch( 'No saved methods found.' );
	} );
} );
