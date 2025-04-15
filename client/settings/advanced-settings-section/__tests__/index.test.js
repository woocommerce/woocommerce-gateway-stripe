import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AdvancedSettings from '..';
import {
	useDebugLog,
	useIsUpeEnabled,
	useSPETitle,
	useGetSavingError,
	useSettings,
	useIsSPEEnabled,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useDebugLog: jest.fn(),
	useIsUpeEnabled: jest.fn(),
	useIsSPEEnabled: jest.fn(),
	useSPETitle: jest.fn(),
	useGetSavingError: jest.fn(),
	useSettings: jest.fn(),
} ) );

describe( 'AdvancedSettings', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { is_spe_available: false };

		useDebugLog.mockReturnValue( [ true, jest.fn() ] );
		useIsUpeEnabled.mockReturnValue( [ true, jest.fn() ] );
		useIsSPEEnabled.mockReturnValue( [ false, jest.fn() ] );
		useSPETitle.mockReturnValue( 'Stripe' );
		useGetSavingError.mockReturnValue( null );

		// Set `isLoading` to false so `LoadableSettingsSection` can render.
		useSettings.mockReturnValue( { isLoading: false } );
	} );

	it( 'renders the advanced settings section', () => {
		render( <AdvancedSettings /> );

		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Legacy checkout experience' )
		).toBeInTheDocument();
	} );

	it( 'should enable debug mode when checkbox is clicked', () => {
		const setIsLoggingCheckedMock = jest.fn();
		useDebugLog.mockReturnValue( [ false, setIsLoggingCheckedMock ] );

		render( <AdvancedSettings /> );

		const debugModeCheckbox = screen.getByLabelText( 'Log error messages' );

		expect( screen.getByText( 'Debug mode' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Log error messages' )
		).not.toBeChecked();

		userEvent.click( debugModeCheckbox );

		expect( setIsLoggingCheckedMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should not display single payment element setting if the feature flag is disabled', () => {
		render( <AdvancedSettings /> );

		expect(
			screen.queryByText( 'Enable Smart Checkout (Recommended)' )
		).not.toBeInTheDocument();
	} );

	it( 'should display single payment element setting if the feature flag is enabled', () => {
		global.wc_stripe_settings_params = { is_spe_available: true };

		render( <AdvancedSettings /> );

		expect(
			screen.queryByText( 'Enable Smart Checkout (Recommended)' )
		).toBeInTheDocument();
	} );

	it( 'should display the Smart Checkout title setting if the Smart Checkout feature is enabled', () => {
		global.wc_stripe_settings_params = { is_spe_available: true };

		useIsSPEEnabled.mockReturnValue( [ true, jest.fn() ] );

		render( <AdvancedSettings /> );

		expect(
			screen.queryByText(
				'This will appear as the title of the Smart Checkout payment element on checkout.'
			)
		).toBeInTheDocument();
		expect( screen.queryByLabelText( 'Title' ) ).toBeInTheDocument();
	} );
} );
