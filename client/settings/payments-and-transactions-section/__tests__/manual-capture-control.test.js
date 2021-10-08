import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ManualCaptureControl from '../manual-capture-control';
import { useManualCapture } from 'wcstripe/data';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

jest.mock( 'wcstripe/data', () => ( {
	useManualCapture: jest.fn(),
} ) );

describe( 'ManualCaptureControl', () => {
	beforeEach( () => {
		useManualCapture.mockReturnValue( [ false, () => null ] );
	} );

	it( 'should not render the confirmation modal when UPE is disabled', () => {
		const manualCaptureToggleMock = jest.fn();
		useManualCapture.mockReturnValue( [ false, manualCaptureToggleMock ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<ManualCaptureControl />
			</UpeToggleContext.Provider>
		);

		userEvent.click(
			screen.getByLabelText(
				'Issue an authorization on checkout, and capture later'
			)
		);

		expect( manualCaptureToggleMock ).toHaveBeenCalledWith( true );
		expect(
			screen.queryByText( 'Enable manual capture' )
		).not.toBeInTheDocument();
	} );

	it( 'should render the confirmation modal when UPE is enabled', () => {
		const manualCaptureToggleMock = jest.fn();
		useManualCapture.mockReturnValue( [ false, manualCaptureToggleMock ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<ManualCaptureControl />
			</UpeToggleContext.Provider>
		);

		userEvent.click(
			screen.getByLabelText(
				'Issue an authorization on checkout, and capture later'
			)
		);

		expect( manualCaptureToggleMock ).not.toHaveBeenCalled();
		expect(
			screen.queryByText( 'Enable manual capture' )
		).toBeInTheDocument();

		userEvent.click( screen.getByText( 'Cancel' ) );

		expect(
			screen.queryByText( 'Enable manual capture' )
		).not.toBeInTheDocument();
		expect( manualCaptureToggleMock ).not.toHaveBeenCalled();
	} );

	it( 'should toggle the flag when UPE is enabled', () => {
		const manualCaptureToggleMock = jest.fn();
		useManualCapture.mockReturnValue( [ false, manualCaptureToggleMock ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<ManualCaptureControl />
			</UpeToggleContext.Provider>
		);

		userEvent.click(
			screen.getByLabelText(
				'Issue an authorization on checkout, and capture later'
			)
		);

		expect( manualCaptureToggleMock ).not.toHaveBeenCalled();
		expect(
			screen.queryByText( 'Enable manual capture' )
		).toBeInTheDocument();

		userEvent.click( screen.getByText( 'Enable' ) );

		expect(
			screen.queryByText( 'Enable manual capture' )
		).not.toBeInTheDocument();
		expect( manualCaptureToggleMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should not show the modal when manual capture is already enabled', () => {
		const manualCaptureToggleMock = jest.fn();
		useManualCapture.mockReturnValue( [ true, manualCaptureToggleMock ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<ManualCaptureControl />
			</UpeToggleContext.Provider>
		);

		userEvent.click(
			screen.getByLabelText(
				'Issue an authorization on checkout, and capture later'
			)
		);

		expect( manualCaptureToggleMock ).toHaveBeenCalledWith( false );
		expect(
			screen.queryByText( 'Enable manual capture' )
		).not.toBeInTheDocument();
	} );
} );
