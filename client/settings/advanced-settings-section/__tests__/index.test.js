import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AdvancedSettings from '..';
import {
	useDebugLog,
	useIsUpeEnabled,
	useGetSavingError,
	useSettings,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useDebugLog: jest.fn(),
	useIsUpeEnabled: jest.fn(),
	useGetSavingError: jest.fn(),
	useSettings: jest.fn(),
} ) );

describe( 'AdvancedSettings', () => {
	beforeEach( () => {
		useDebugLog.mockReturnValue( [ true, jest.fn() ] );
		useIsUpeEnabled.mockReturnValue( [ true, jest.fn() ] );
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
} );
