import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DiagnosticsMode from '../diagnostics-mode';
import {
	useDiagnosticsMode,
	useDiagnosticsCaptureLimit,
	useDiagnosticsCaptureLimitPresets,
} from 'wcstripe/data';
import apiFetch from '@wordpress/api-fetch';

jest.mock( 'wcstripe/data', () => ( {
	useDiagnosticsMode: jest.fn(),
	useDiagnosticsCaptureLimit: jest.fn(),
	useDiagnosticsCaptureLimitPresets: jest.fn(),
} ) );

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

// Sidestep DiagnosticsTraces — its behavior is covered by
// diagnostics-traces.test.js. This suite focuses on DiagnosticsMode wiring
// (heading, description, toggle ↔ hook, isRecording prop passthrough).
jest.mock( '../diagnostics-traces', () => ( {
	__esModule: true,
	default: ( { isRecording } ) => (
		<div data-testid="diagnostics-traces">
			recording={ String( isRecording ) }
		</div>
	),
} ) );

describe( 'DiagnosticsMode', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		useDiagnosticsCaptureLimit.mockReturnValue( [ 10, jest.fn() ] );
		useDiagnosticsCaptureLimitPresets.mockReturnValue( [ 5, 10, 25, 50 ] );
		apiFetch.mockResolvedValue( { traces: [], count: 0 } );
	} );

	it( 'renders the section heading and description', () => {
		useDiagnosticsMode.mockReturnValue( [ false, jest.fn() ] );
		render( <DiagnosticsMode /> );

		expect(
			screen.getByText( 'Checkout diagnostics' )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				/Records structured traces of checkout sessions/i
			)
		).toBeInTheDocument();
	} );

	it( 'wires the toggle to the diagnostics-mode setter', async () => {
		const setIsDiagnosticsChecked = jest.fn();
		useDiagnosticsMode.mockReturnValue( [
			false,
			setIsDiagnosticsChecked,
		] );

		render( <DiagnosticsMode /> );

		const toggle = screen.getByLabelText( 'Capture checkout diagnostics' );
		expect( toggle ).not.toBeChecked();

		await userEvent.click( toggle );
		expect( setIsDiagnosticsChecked ).toHaveBeenCalledWith( true );
	} );

	it( 'forwards the recording state into DiagnosticsTraces', () => {
		useDiagnosticsMode.mockReturnValue( [ true, jest.fn() ] );
		render( <DiagnosticsMode /> );
		expect( screen.getByTestId( 'diagnostics-traces' ) ).toHaveTextContent(
			'recording=true'
		);
	} );
} );
