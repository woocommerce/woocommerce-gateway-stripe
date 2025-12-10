import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ManualCaptureControl from '../manual-capture-control';
import { useManualCapture } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useManualCapture: jest.fn(),
} ) );

describe( 'ManualCaptureControl', () => {
	beforeEach( () => {
		useManualCapture.mockReturnValue( [ false, () => null ] );
	} );

	it( 'should render the confirmation modal', async () => {
		const manualCaptureToggleMock = jest.fn();
		useManualCapture.mockReturnValue( [ false, manualCaptureToggleMock ] );

		render( <ManualCaptureControl /> );

		await userEvent.click(
			screen.getByLabelText(
				'Issue an authorization on checkout, and capture later'
			)
		);

		expect( manualCaptureToggleMock ).not.toHaveBeenCalled();
		expect(
			screen.queryByText( 'Enable manual capture' )
		).toBeInTheDocument();

		await userEvent.click( screen.getByText( 'Cancel' ) );

		expect(
			screen.queryByText( 'Enable manual capture' )
		).not.toBeInTheDocument();
		expect( manualCaptureToggleMock ).not.toHaveBeenCalled();
	} );

	it( 'should toggle the manual capture setting', async () => {
		const manualCaptureToggleMock = jest.fn();
		useManualCapture.mockReturnValue( [ false, manualCaptureToggleMock ] );

		render( <ManualCaptureControl /> );

		await userEvent.click(
			screen.getByLabelText(
				'Issue an authorization on checkout, and capture later'
			)
		);

		expect( manualCaptureToggleMock ).not.toHaveBeenCalled();
		expect(
			screen.queryByText( 'Enable manual capture' )
		).toBeInTheDocument();

		await userEvent.click( screen.getByText( 'Enable' ) );

		expect(
			screen.queryByText( 'Enable manual capture' )
		).not.toBeInTheDocument();
		expect( manualCaptureToggleMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should not show the modal when manual capture is already enabled', async () => {
		const manualCaptureToggleMock = jest.fn();
		useManualCapture.mockReturnValue( [ true, manualCaptureToggleMock ] );

		render( <ManualCaptureControl /> );

		await userEvent.click(
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
