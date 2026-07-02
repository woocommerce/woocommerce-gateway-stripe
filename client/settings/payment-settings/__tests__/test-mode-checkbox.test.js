import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TestModeCheckbox from '../test-mode-checkbox';
import { useTestMode } from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';

// Stub @wordpress/components to avoid pulling its heavy (and, in this repo's
// node_modules, mismatched) dependency tree into the test runner.
jest.mock( '@wordpress/components', () => ( {
	CheckboxControl: ( { checked, disabled, onChange, label, help } ) => (
		<>
			<input
				type="checkbox"
				aria-label={ label }
				checked={ checked }
				disabled={ disabled }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
			<span>{ help }</span>
		</>
	),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useTestMode: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

const mockAccount = ( {
	liveConnected = false,
	testConnected = false,
} = {} ) => {
	useAccount.mockReturnValue( {
		data: {
			oauth_connections: {
				live: { connected: liveConnected },
				test: { connected: testConnected },
			},
		},
	} );
};

describe( 'TestModeCheckbox', () => {
	it( 'allows enabling test mode when a test account is connected', async () => {
		const setTestModeMock = jest.fn();
		useTestMode.mockReturnValue( [ false, setTestModeMock ] );
		mockAccount( { liveConnected: true, testConnected: true } );

		render( <TestModeCheckbox /> );

		const testModeCheckbox = screen.getByLabelText( 'Enable test mode' );
		expect( testModeCheckbox ).not.toBeChecked();
		expect( testModeCheckbox ).not.toBeDisabled();

		await userEvent.click( testModeCheckbox );

		expect( setTestModeMock ).toHaveBeenCalledWith( true );
	} );

	it( 'allows disabling test mode when a live account is connected', async () => {
		const setTestModeMock = jest.fn();
		useTestMode.mockReturnValue( [ true, setTestModeMock ] );
		mockAccount( { liveConnected: true, testConnected: true } );

		render( <TestModeCheckbox /> );

		const testModeCheckbox = screen.getByLabelText( 'Enable test mode' );
		expect( testModeCheckbox ).toBeChecked();
		expect( testModeCheckbox ).not.toBeDisabled();

		await userEvent.click( testModeCheckbox );

		expect( setTestModeMock ).toHaveBeenCalledWith( false );
	} );

	it( 'locks test mode on when no live account is connected', async () => {
		const setTestModeMock = jest.fn();
		useTestMode.mockReturnValue( [ true, setTestModeMock ] );
		mockAccount( { liveConnected: false } );

		render( <TestModeCheckbox /> );

		const testModeCheckbox = screen.getByLabelText( 'Enable test mode' );
		expect( testModeCheckbox ).toBeChecked();
		expect( testModeCheckbox ).toBeDisabled();
		// The original help text is still shown...
		expect( screen.getByText( 'test card numbers' ) ).toBeInTheDocument();
		// ...with the connect-a-live-account guidance appended.
		expect(
			screen.getByText(
				/Live mode cannot be enabled before you have connected a live Stripe account\./
			)
		).toBeInTheDocument();

		await userEvent.click( testModeCheckbox );

		expect( setTestModeMock ).not.toHaveBeenCalled();
	} );

	it( 'locks live mode on when no test account is connected', async () => {
		const setTestModeMock = jest.fn();
		useTestMode.mockReturnValue( [ false, setTestModeMock ] );
		mockAccount( { liveConnected: true, testConnected: false } );

		render( <TestModeCheckbox /> );

		const testModeCheckbox = screen.getByLabelText( 'Enable test mode' );
		expect( testModeCheckbox ).not.toBeChecked();
		expect( testModeCheckbox ).toBeDisabled();
		// The original help text is still shown...
		expect( screen.getByText( 'test card numbers' ) ).toBeInTheDocument();
		// ...with the connect-a-test-account guidance appended.
		expect(
			screen.getByText(
				/Test mode cannot be enabled before you have connected a test Stripe account\./
			)
		).toBeInTheDocument();

		await userEvent.click( testModeCheckbox );

		expect( setTestModeMock ).not.toHaveBeenCalled();
	} );
} );
