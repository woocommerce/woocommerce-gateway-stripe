import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ExpressCheckoutSettingsSection from '../express-checkout-settings-section';
import {
	useExpressCheckoutEnabledSettings,
	useExpressCheckoutLocations,
	useExpressCheckoutButtonType,
	useExpressCheckoutButtonSize,
	useExpressCheckoutButtonTheme,
} from 'wcstripe/data';
import ExpressCheckoutButtonPreview from 'wcstripe/entrypoints/express-checkout-settings/express-checkout-button-preview';

const realPathToA11yModule =
	'@wordpress/components/node_modules/@wordpress/a11y';

jest.mock( realPathToA11yModule, () => ( {
	...jest.requireActual( realPathToA11yModule ),
	speak: jest.fn(),
} ) );
jest.mock( 'wcstripe/data', () => ( {
	useExpressCheckoutEnabledSettings: jest.fn(),
	useExpressCheckoutLocations: jest.fn(),
	useExpressCheckoutButtonType: jest.fn().mockReturnValue( [ 'buy' ] ),
	useExpressCheckoutButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
	useExpressCheckoutButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
	useEnabledPaymentMethodIds: jest.fn().mockReturnValue( [ [] ] ),
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

jest.mock( '../express-checkout-button-preview' );
ExpressCheckoutButtonPreview.mockImplementation( () => '<></>' );

jest.mock( '../utils/utils', () => ( {
	getPaymentRequestData: jest.fn().mockReturnValue( {
		publishableKey: 'pk_test_123',
		accountId: '0001',
		locale: 'en',
	} ),
} ) );

const getMockExpressCheckoutEnabledSettings = (
	isEnabled,
	updateIsExpressCheckoutEnabledHandler
) => [ isEnabled, updateIsExpressCheckoutEnabledHandler ];

const getMockExpressCheckoutLocations = (
	isCheckoutEnabled,
	isProductPageEnabled,
	isCartEnabled,
	updateExpressCheckoutLocationsHandler
) => [
	[
		isCheckoutEnabled && 'checkout',
		isProductPageEnabled && 'product',
		isCartEnabled && 'cart',
	].filter( Boolean ),
	updateExpressCheckoutLocationsHandler,
];

describe( 'ExpressCheckoutSettingsSection', () => {
	const globalValues = global.wc_stripe_express_checkout_settings_params;
	beforeEach( () => {
		useExpressCheckoutEnabledSettings.mockReturnValue(
			getMockExpressCheckoutEnabledSettings( true, jest.fn() )
		);

		useExpressCheckoutLocations.mockReturnValue(
			getMockExpressCheckoutLocations( true, true, true, jest.fn() )
		);

		global.wc_stripe_express_checkout_settings_params = {
			...globalValues,
			key: 'pk_test_123',
			locale: 'en',
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_express_checkout_settings_params = globalValues;
	} );

	it( 'renders settings with defaults', () => {
		render( <ExpressCheckoutSettingsSection /> );

		// confirm settings headings.
		expect(
			screen.queryByRole( 'heading', { name: 'Call to action' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'heading', { name: 'Appearance' } )
		).toBeInTheDocument();

		// confirm radio button groups displayed.
		const [ ctaRadio, sizeRadio, themeRadio ] =
			screen.queryAllByRole( 'radio' );

		expect( ctaRadio ).toBeInTheDocument();
		expect( sizeRadio ).toBeInTheDocument();
		expect( themeRadio ).toBeInTheDocument();

		// confirm default values.
		expect( screen.getByLabelText( 'Buy' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Default (48 px)' ) ).toBeChecked();
		expect( screen.getByLabelText( /Dark/ ) ).toBeChecked();
	} );

	it( 'triggers the hooks when the settings are being interacted with', async () => {
		const setButtonTypeMock = jest.fn();
		const setButtonSizeMock = jest.fn();
		const setButtonThemeMock = jest.fn();

		useExpressCheckoutButtonType.mockReturnValue( [
			'buy',
			setButtonTypeMock,
		] );
		useExpressCheckoutButtonSize.mockReturnValue( [
			'default',
			setButtonSizeMock,
		] );
		useExpressCheckoutButtonTheme.mockReturnValue( [
			'dark',
			setButtonThemeMock,
		] );
		useExpressCheckoutEnabledSettings.mockReturnValue( [
			true,
			jest.fn(),
		] );

		render( <ExpressCheckoutSettingsSection /> );

		expect( setButtonTypeMock ).not.toHaveBeenCalled();
		expect( setButtonSizeMock ).not.toHaveBeenCalled();
		expect( setButtonThemeMock ).not.toHaveBeenCalled();

		await userEvent.click( screen.getByLabelText( /Light/ ) );

		expect( setButtonThemeMock ).toHaveBeenCalledWith( 'light' );

		await userEvent.click( screen.getByLabelText( 'Book' ) );

		expect( setButtonTypeMock ).toHaveBeenCalledWith( 'book' );

		await userEvent.click( screen.getByLabelText( 'Large (56 px)' ) );

		expect( setButtonSizeMock ).toHaveBeenCalledWith( 'large' );
	} );

	it( 'shows the appearance override notice when an override is in effect', () => {
		global.wc_stripe_express_checkout_settings_params.is_button_style_overridden = true;

		render( <ExpressCheckoutSettingsSection /> );

		expect( screen.getByText( /may be overridden/ ) ).toBeInTheDocument();
	} );

	it( 'hides the appearance override notice when there is no override', () => {
		global.wc_stripe_express_checkout_settings_params.is_button_style_overridden = false;

		render( <ExpressCheckoutSettingsSection /> );

		expect(
			screen.queryByText( /may be overridden/ )
		).not.toBeInTheDocument();
	} );
} );
