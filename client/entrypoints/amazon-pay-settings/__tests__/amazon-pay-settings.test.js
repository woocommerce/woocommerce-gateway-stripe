import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AmazonPaySettingsSection from '../amazon-pay-settings-section';
import {
	useAmazonPayEnabledSettings,
	useAmazonPayLocations,
	useAmazonPayButtonSize,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useAmazonPayEnabledSettings: jest.fn(),
	useAmazonPayLocations: jest.fn(),
	useAmazonPayButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
} ) );
jest.mock( 'wcstripe/data/account/hooks', () => ( {
	useAccount: jest.fn().mockReturnValue( { data: {} } ),
} ) );
jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn().mockReturnValue( {} ),
	useAccountKeysPublishableKey: jest.fn().mockReturnValue( [ '' ] ),
	useAccountKeysTestPublishableKey: jest.fn().mockReturnValue( [ '' ] ),
} ) );

describe( 'AmazonPaySettingsSection', () => {
	const globalValues = global.wc_stripe_amazon_pay_settings_params;
	beforeEach( () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		useAmazonPayLocations.mockReturnValue( [
			[ 'checkout', 'product', 'cart' ],
			jest.fn(),
		] );

		global.wc_stripe_amazon_pay_settings_params = {
			...globalValues,
			key: 'pk_test_123',
			locale: 'en',
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_amazon_pay_settings_params = globalValues;
	} );

	it( 'renders settings with defaults', () => {
		render( <AmazonPaySettingsSection /> );

		// confirm settings headings.
		expect(
			screen.queryByRole( 'heading', { name: 'Appearance' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed.
		const [ sizeRadio ] = screen.queryAllByRole( 'radio' );
		expect( sizeRadio ).toBeInTheDocument();

		// confirm default values.
		expect( screen.getByLabelText( 'Default (48 px)' ) ).toBeChecked();
	} );

	it( 'triggers the hooks when the settings are being interacted with', () => {
		const setButtonSizeMock = jest.fn();

		useAmazonPayButtonSize.mockReturnValue( [
			'default',
			setButtonSizeMock,
		] );
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		render( <AmazonPaySettingsSection /> );

		expect( setButtonSizeMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( 'Large (56 px)' ) );
		expect( setButtonSizeMock ).toHaveBeenCalledWith( 'large' );
	} );
} );
