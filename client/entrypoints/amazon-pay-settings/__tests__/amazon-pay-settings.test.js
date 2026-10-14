import { render, screen, fireEvent } from '@testing-library/react';
import AmazonPaySettingsSection from '../amazon-pay-settings-section';
import {
	useAmazonPayEnabledSettings,
	useAmazonPayLocations,
	useExpressCheckoutButtonSize,
} from 'wcstripe/data';

const realPathToA11yModule =
	'@wordpress/components/node_modules/@wordpress/a11y';

jest.mock( realPathToA11yModule, () => ( {
	...jest.requireActual( realPathToA11yModule ),
	speak: jest.fn(),
} ) );
jest.mock( 'wcstripe/data', () => ( {
	useAmazonPayEnabledSettings: jest.fn(),
	useAmazonPayLocations: jest.fn(),
	useExpressCheckoutButtonSize: jest.fn(),
} ) );
jest.mock( 'wcstripe/data/account/hooks', () => ( {
	useAccount: jest.fn().mockReturnValue( { data: {} } ),
} ) );
jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn().mockReturnValue( {} ),
	useAccountKeysPublishableKey: jest.fn().mockReturnValue( [ '' ] ),
	useAccountKeysTestPublishableKey: jest.fn().mockReturnValue( [ '' ] ),
} ) );
jest.mock( '@woocommerce/blocks-checkout', () => {}, { virtual: true } );

describe( 'AmazonPaySettingsSection', () => {
	const globalValues = global.wc_stripe_amazon_pay_settings_params;
	beforeEach( () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );

		useAmazonPayLocations.mockReturnValue( [
			[ 'checkout', 'product', 'cart' ],
			jest.fn(),
		] );

		useExpressCheckoutButtonSize.mockReturnValue( [
			'default',
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

	it( 'renders the locations section, the shared size control, and the preview', () => {
		render( <AmazonPaySettingsSection /> );

		// Location checkboxes render.
		expect(
			screen.getByRole( 'checkbox', { name: /checkout/i } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Preview' ) ).toBeInTheDocument();

		// The shared button size can be edited from this page too.
		expect(
			screen.getByRole( 'heading', { name: 'Appearance' } )
		).toBeInTheDocument();
		expect( screen.getByText( /Small/ ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				/This size applies to all express checkout buttons/i
			)
		).toBeInTheDocument();
	} );

	it( 'updates the shared button size when a size is selected', () => {
		const setSize = jest.fn();
		useExpressCheckoutButtonSize.mockReturnValue( [ 'default', setSize ] );

		render( <AmazonPaySettingsSection /> );

		fireEvent.click( screen.getByLabelText( /Large/ ) );

		expect( setSize ).toHaveBeenCalledWith( 'large' );
	} );

	it( 'shows the appearance override notice when an override is in effect', () => {
		global.wc_stripe_amazon_pay_settings_params.is_button_style_overridden = true;

		render( <AmazonPaySettingsSection /> );

		expect( screen.getByText( /may be overridden/ ) ).toBeInTheDocument();
	} );

	it( 'hides the appearance override notice when there is no override', () => {
		global.wc_stripe_amazon_pay_settings_params.is_button_style_overridden = false;

		render( <AmazonPaySettingsSection /> );

		expect(
			screen.queryByText( /may be overridden/ )
		).not.toBeInTheDocument();
	} );
} );
