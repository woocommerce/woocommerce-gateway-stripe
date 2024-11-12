import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaymentRequestsSettingsSection from '../payment-request-settings-section';
import PaymentRequestButtonPreview from '../payment-request-button-preview';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	usePaymentRequestLocations: jest.fn(),
	usePaymentRequestButtonType: jest.fn().mockReturnValue( [ 'buy' ] ),
	usePaymentRequestButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
	usePaymentRequestButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
} ) );

jest.mock( 'wcstripe/data/account/hooks', () => ( {
	useAccount: jest.fn().mockReturnValue( { data: {} } ),
} ) );
jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn().mockReturnValue( {} ),
	useAccountKeysPublishableKey: jest.fn().mockReturnValue( [ '' ] ),
	useAccountKeysTestPublishableKey: jest.fn().mockReturnValue( [ '' ] ),
} ) );

jest.mock( '../payment-request-button-preview' );
PaymentRequestButtonPreview.mockImplementation( () => '<></>' );

jest.mock( '../utils/utils', () => ( {
	getPaymentRequestData: jest.fn().mockReturnValue( {
		publishableKey: 'pk_test_123',
		accountId: '0001',
		locale: 'en',
	} ),
} ) );

const getMockPaymentRequestEnabledSettings = (
	isEnabled,
	updateIsPaymentRequestEnabledHandler
) => [ isEnabled, updateIsPaymentRequestEnabledHandler ];

const getMockPaymentRequestLocations = (
	isCheckoutEnabled,
	isProductPageEnabled,
	isCartEnabled,
	updatePaymentRequestLocationsHandler
) => [
	[
		isCheckoutEnabled && 'checkout',
		isProductPageEnabled && 'product',
		isCartEnabled && 'cart',
	].filter( Boolean ),
	updatePaymentRequestLocationsHandler,
];

describe( 'PaymentRequestsSettingsSection', () => {
	const globalValues = global.wc_stripe_payment_request_settings_params;

	beforeEach( () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( true, jest.fn() )
		);

		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations( true, true, true, jest.fn() )
		);

		global.wc_stripe_payment_request_settings_params = {
			...globalValues,
			key: 'pk_test_123',
			locale: 'en',
			is_ece_enabled: true,
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_payment_request_settings_params = globalValues;
	} );

	it( 'should enable express checkout locations when express checkout is enabled', () => {
		render( <PaymentRequestsSettingsSection /> );

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
		const updatePaymentRequestLocationsHandler = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue( [ true, jest.fn() ] );
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations(
				true,
				true,
				true,
				updatePaymentRequestLocationsHandler
			)
		);

		render( <PaymentRequestsSettingsSection /> );

		// Uncheck each checkbox, and verify them what kind of action should have been called
		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'cart' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product', 'cart' ] );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout', 'product' ] );
	} );

	it( 'should trigger an action to save the checked locations when checking the location checkboxes', () => {
		const updatePaymentRequestLocationsHandler = jest.fn();
		usePaymentRequestEnabledSettings.mockReturnValue( [ true, jest.fn() ] );
		usePaymentRequestLocations.mockReturnValue(
			getMockPaymentRequestLocations(
				false,
				false,
				false,
				updatePaymentRequestLocationsHandler
			)
		);

		render( <PaymentRequestsSettingsSection /> );

		userEvent.click( screen.getByText( 'Cart' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'cart' ] );

		userEvent.click( screen.getByText( 'Product page' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'product' ] );

		userEvent.click( screen.getByText( 'Checkout' ) );
		expect(
			updatePaymentRequestLocationsHandler
		).toHaveBeenLastCalledWith( [ 'checkout' ] );
	} );
} );
