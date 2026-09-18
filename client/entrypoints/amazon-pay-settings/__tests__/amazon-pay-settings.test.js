import { render, screen } from '@testing-library/react';
import AmazonPaySettingsSection from '../amazon-pay-settings-section';
import {
	useAmazonPayEnabledSettings,
	useAmazonPayLocations,
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
	useExpressCheckoutButtonSize: jest
		.fn()
		.mockReturnValue( [ 'default', jest.fn() ] ),
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

	it( 'renders the locations section and preview, without a per-method size control', () => {
		render( <AmazonPaySettingsSection /> );

		// Location checkboxes render.
		expect(
			screen.getByRole( 'checkbox', { name: /checkout/i } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Preview' ) ).toBeInTheDocument();

		// The size control now lives on the Express Checkout settings page.
		expect(
			screen.queryByRole( 'heading', { name: 'Appearance' } )
		).not.toBeInTheDocument();
		expect( screen.queryAllByRole( 'radio' ) ).toHaveLength( 0 );
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
