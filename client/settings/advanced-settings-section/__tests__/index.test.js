import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AdvancedSettings from '..';
import {
	useDebugLog,
	useGetSavingError,
	useSettings,
	useIsOCEnabled,
	useOCLayout,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useDebugLog: jest.fn(),
	useIsOCEnabled: jest.fn(),
	useOCLayout: jest.fn(),
	useGetSavingError: jest.fn(),
	useSettings: jest.fn(),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'AdvancedSettings', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { is_oc_available: false };

		useDebugLog.mockReturnValue( [ true, jest.fn() ] );
		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		useOCLayout.mockReturnValue( [ 'accordion', jest.fn() ] );
		useGetSavingError.mockReturnValue( null );

		// Set `isLoading` to false so `LoadableSettingsSection` can render.
		useSettings.mockReturnValue( { isLoading: false } );
	} );

	it( 'renders the advanced settings section', () => {
		render( <AdvancedSettings /> );

		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
	} );

	it( 'should enable debug mode when checkbox is clicked', async () => {
		const setIsLoggingCheckedMock = jest.fn();
		useDebugLog.mockReturnValue( [ false, setIsLoggingCheckedMock ] );

		render( <AdvancedSettings /> );

		const debugModeCheckbox = screen.getByLabelText( 'Log debug messages' );

		expect( screen.getByText( 'Debug mode' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Log debug messages' )
		).not.toBeChecked();

		await userEvent.click( debugModeCheckbox );

		expect( setIsLoggingCheckedMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should not display optimized checkout element setting if the feature flag is disabled', () => {
		render( <AdvancedSettings /> );

		expect(
			screen.queryByText(
				'Enable Optimized Checkout Suite (recommended)'
			)
		).not.toBeInTheDocument();
	} );

	it( 'should display optimized checkout element setting if the feature flag is enabled', () => {
		global.wc_stripe_settings_params = { is_oc_available: true };

		render( <AdvancedSettings /> );

		expect(
			screen.queryByText(
				'Enable Optimized Checkout Suite (recommended)'
			)
		).toBeInTheDocument();
	} );

	it( 'should display the Optimized Checkout layout setting if the Optimized Checkout feature is enabled', () => {
		global.wc_stripe_settings_params = { is_oc_available: true };

		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );

		render( <AdvancedSettings /> );

		expect(
			screen.queryByText(
				'Choose between a vertical accordion layout and a horizontal tabs layout to display payment methods.'
			)
		).toBeInTheDocument();
		expect( screen.queryByText( 'Layout' ) ).toBeInTheDocument();
	} );
} );
