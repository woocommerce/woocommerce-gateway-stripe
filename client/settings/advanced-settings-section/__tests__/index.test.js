/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import AdvancedSettings from '..';
import { useDevMode, useDebugLog } from '../data-mock';

jest.mock( '../data-mock', () => ( {
	useDevMode: jest.fn(),
	useDebugLog: jest.fn(),
} ) );

describe( 'AdvancedSettings', () => {
	beforeEach( () => {
		useDevMode.mockReturnValue( false );
		useDebugLog.mockReturnValue( [ true, jest.fn() ] );
	} );

	it( 'toggles the advanced settings section and sets focus on the first heading', () => {
		render( <AdvancedSettings /> );

		expect( screen.queryByText( 'Debug mode' ) ).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Debug mode' ) ).toHaveFocus();
	} );

	it( 'toggles the debug mode input when dev mode is disabled', () => {
		const setDebugLogMock = jest.fn();
		useDebugLog.mockReturnValue( [ true, setDebugLogMock ] );
		useDevMode.mockReturnValue( false );
		render( <AdvancedSettings /> );

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		const loggingCheckbox = screen.getByTestId( 'logging-checkbox' );
		expect( loggingCheckbox ).toBeEnabled();
		expect( loggingCheckbox ).toBeChecked();
		expect( setDebugLogMock ).not.toHaveBeenCalled();

		userEvent.click( loggingCheckbox );

		expect( setDebugLogMock ).toHaveBeenCalled();
	} );

	it( 'disables the debug mode input when dev mode is enabled', () => {
		useDevMode.mockReturnValue( true );
		render( <AdvancedSettings /> );

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		const loggingCheckbox = screen.getByTestId( 'logging-checkbox' );
		expect( loggingCheckbox ).toBeDisabled();
		expect( loggingCheckbox ).toBeChecked();
	} );
} );
