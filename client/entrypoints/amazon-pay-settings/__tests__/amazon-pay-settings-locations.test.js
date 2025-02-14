import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AmazonPaySettingsSection from '../amazon-pay-settings-section';
import {
	useAmazonPayEnabledSettings,
	useAmazonPayLocations,
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

	it( 'should enable express checkout locations when express checkout is enabled', () => {
		render( <AmazonPaySettingsSection /> );

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
		const updateAmazonPayLocationsHandler = jest.fn();
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );
		useAmazonPayLocations.mockReturnValue( [
			[ 'checkout', 'product', 'cart' ],
			updateAmazonPayLocationsHandler,
		] );

		render( <AmazonPaySettingsSection /> );

		// Uncheck each checkbox, and verify them what kind of action should have been called
		userEvent.click( screen.getByText( 'Product page' ) );
		expect( updateAmazonPayLocationsHandler ).toHaveBeenLastCalledWith( [
			'checkout',
			'cart',
		] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect( updateAmazonPayLocationsHandler ).toHaveBeenLastCalledWith( [
			'product',
			'cart',
		] );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect( updateAmazonPayLocationsHandler ).toHaveBeenLastCalledWith( [
			'checkout',
			'product',
		] );
	} );

	it( 'should trigger an action to save the checked locations when checking the location checkboxes', () => {
		const updateAmazonPayLocationsHandler = jest.fn();
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );
		useAmazonPayLocations.mockReturnValue( [
			[],
			updateAmazonPayLocationsHandler,
		] );

		render( <AmazonPaySettingsSection /> );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect( updateAmazonPayLocationsHandler ).toHaveBeenLastCalledWith( [
			'cart',
		] );

		userEvent.click( screen.getByText( 'Product page' ) );
		expect( updateAmazonPayLocationsHandler ).toHaveBeenLastCalledWith( [
			'product',
		] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect( updateAmazonPayLocationsHandler ).toHaveBeenLastCalledWith( [
			'checkout',
		] );
	} );
} );
