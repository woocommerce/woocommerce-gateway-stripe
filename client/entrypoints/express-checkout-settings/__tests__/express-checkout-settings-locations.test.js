import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ExpressCheckoutSettingsSection from '../express-checkout-settings-section';
import {
	useExpressCheckoutButtonLocations,
	useExpressCheckoutEnabledSettings,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useExpressCheckoutEnabledSettings: jest.fn(),
	useExpressCheckoutButtonLocations: jest.fn(),
	useExpressCheckoutButtonType: jest.fn().mockReturnValue( [ 'buy' ] ),
	useExpressCheckoutButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
	useExpressCheckoutButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
} ) );

jest.mock( 'wcstripe/data/account/hooks', () => ( {
	useAccount: jest.fn().mockReturnValue( { data: {} } ),
} ) );
jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn().mockReturnValue( {} ),
	useAccountKeysPublishableKey: jest.fn().mockReturnValue( [ '' ] ),
	useAccountKeysTestPublishableKey: jest.fn().mockReturnValue( [ '' ] ),
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

describe( 'ExpressCheckoutsSettingsSection', () => {
	const globalValues = global.wc_stripe_express_checkout_params;

	beforeEach( () => {
		useExpressCheckoutEnabledSettings.mockReturnValue(
			getMockExpressCheckoutEnabledSettings( true, jest.fn() )
		);

		useExpressCheckoutButtonLocations.mockReturnValue(
			getMockExpressCheckoutLocations( true, true, true, jest.fn() )
		);

		global.wc_stripe_express_checkout_params = {
			...globalValues,
			key: 'pk_test_123',
			locale: 'en',
			is_ece_enabled: true,
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_express_checkout_params = globalValues;
	} );

	it( 'should enable express checkout locations when express checkout is enabled', () => {
		render( <ExpressCheckoutSettingsSection /> );

		const [
			checkoutCheckbox,
			productPageCheckbox,
			cartCheckbox,
		] = screen.getAllByRole( 'checkbox' );

		expect( checkoutCheckbox ).not.toBeDisabled();
		expect( checkoutCheckbox ).toBeChecked();
		expect( productPageCheckbox ).not.toBeDisabled();
		expect( productPageCheckbox ).toBeChecked();
		expect( cartCheckbox ).not.toBeDisabled();
		expect( cartCheckbox ).toBeChecked();
	} );

	it( 'should trigger an action to save the checked locations when un-checking the location checkboxes', () => {
		const updateExpressCheckoutLocationsHandler = jest.fn();
		useExpressCheckoutEnabledSettings.mockReturnValue( [
			true,
			jest.fn(),
		] );
		useExpressCheckoutButtonLocations.mockReturnValue(
			getMockExpressCheckoutLocations(
				true,
				true,
				true,
				updateExpressCheckoutLocationsHandler
			)
		);

		render( <ExpressCheckoutSettingsSection /> );

		// Uncheck each checkbox, and verify them what kind of action should have been called
		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updateExpressCheckoutLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'cart' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updateExpressCheckoutLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product', 'cart' ] );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updateExpressCheckoutLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'product' ] );
	} );

	it( 'should trigger an action to save the checked locations when checking the location checkboxes', () => {
		const updateExpressCheckoutLocationsHandler = jest.fn();
		useExpressCheckoutEnabledSettings.mockReturnValue( [
			true,
			jest.fn(),
		] );
		useExpressCheckoutButtonLocations.mockReturnValue(
			getMockExpressCheckoutLocations(
				false,
				false,
				false,
				updateExpressCheckoutLocationsHandler
			)
		);

		render( <ExpressCheckoutSettingsSection /> );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updateExpressCheckoutLocationsHandler
		).toHaveBeenLastCalledWith( [ 'cart' ] );

		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updateExpressCheckoutLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updateExpressCheckoutLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout' ] );
	} );
} );
