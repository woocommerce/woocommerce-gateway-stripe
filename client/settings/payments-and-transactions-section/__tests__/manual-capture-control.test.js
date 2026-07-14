import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ManualCaptureControl from '../manual-capture-control';
import { useManualCapture } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useManualCapture: jest.fn(),
} ) );

const agenticNote =
	/Agentic Commerce purchases follow the capture setting in your Stripe agentic commerce dashboard, not this option\./;

describe( 'ManualCaptureControl', () => {
	beforeEach( () => {
		useManualCapture.mockReturnValue( [ false, () => null ] );
		global.wc_stripe_settings_params = {
			is_agentic_commerce_merchant_enabled: false,
		};
	} );

	afterEach( () => {
		delete global.wc_stripe_settings_params;
	} );

	it( 'notes in the confirmation modal that agentic purchases follow the Stripe dashboard capture setting when agentic commerce is enabled', async () => {
		global.wc_stripe_settings_params.is_agentic_commerce_merchant_enabled = true;
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );

		render( <ManualCaptureControl /> );

		// The note is scoped to the enable-time modal, so it isn't visible up front.
		expect( screen.queryByText( agenticNote ) ).not.toBeInTheDocument();

		await userEvent.click(
			screen.getByLabelText(
				'Issue an authorization on checkout, and capture later'
			)
		);

		expect( screen.getByText( agenticNote ) ).toBeInTheDocument();
	} );

	it( 'omits the agentic capture note when agentic commerce is disabled', async () => {
		global.wc_stripe_settings_params.is_agentic_commerce_merchant_enabled = false;
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );

		render( <ManualCaptureControl /> );

		await userEvent.click(
			screen.getByLabelText(
				'Issue an authorization on checkout, and capture later'
			)
		);

		expect(
			screen.queryByText( 'Enable manual capture' )
		).toBeInTheDocument();
		expect( screen.queryByText( agenticNote ) ).not.toBeInTheDocument();
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
