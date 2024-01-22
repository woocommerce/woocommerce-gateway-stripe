import React from 'react';
import { render, screen } from '@testing-library/react';
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
			screen.queryByText( 'New checkout experience' )
		).toBeInTheDocument();
	} );
} );
