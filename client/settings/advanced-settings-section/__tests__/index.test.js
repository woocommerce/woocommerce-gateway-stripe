import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AdvancedSettings from '..';
import { useDebugLog, useSettings } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useDebugLog: jest.fn(),
	useSettings: jest.fn(),
} ) );

describe( 'AdvancedSettings', () => {
	beforeEach( () => {
		useDebugLog.mockReturnValue( [ true, jest.fn() ] );

		// Set `isLoading` to false so `LoadableSettingsSection` can render.
		useSettings.mockReturnValue( { isLoading: false } );
	} );

	it( 'toggles the advanced settings section and sets focus on the first heading', () => {
		render( <AdvancedSettings /> );

		expect( screen.queryByText( 'Debug mode' ) ).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Debug mode' ) ).toHaveFocus();
	} );
} );
