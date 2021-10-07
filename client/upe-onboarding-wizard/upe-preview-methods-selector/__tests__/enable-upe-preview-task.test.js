import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import WizardTaskContext from '../../wizard/task/context';
import EnableUpePreviewTask from '../enable-upe-preview-task';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import { useManualCapture, useSettings } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useSettings: jest.fn(),
	useManualCapture: jest.fn(),
} ) );

describe( 'EnableUpePreviewTask', () => {
	it( 'disables the "Enable" button while "Manual capture" is enabled', async () => {
		const setCompletedMock = jest.fn();
		const setIsUpeEnabledMock = jest.fn().mockResolvedValue( true );
		const setIsManualCaptureEnabledMock = jest.fn();
		useManualCapture.mockReturnValue( [
			true,
			setIsManualCaptureEnabledMock,
		] );
		const saveSettingsMock = jest.fn().mockResolvedValue( true );
		useSettings.mockReturnValue( {
			saveSettings: saveSettingsMock,
			isSaving: false,
		} );

		render(
			<UpeToggleContext.Provider
				value={ {
					isUpeEnabled: false,
					setIsUpeEnabled: setIsUpeEnabledMock,
				} }
			>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock } }
				>
					<EnableUpePreviewTask />
				</WizardTaskContext.Provider>
			</UpeToggleContext.Provider>
		);

		const enableButton = screen.getByText( 'Enable' );
		expect( setCompletedMock ).not.toHaveBeenCalled();
		expect( setIsUpeEnabledMock ).not.toHaveBeenCalled();
		expect( setIsManualCaptureEnabledMock ).not.toHaveBeenCalled();
		expect( saveSettingsMock ).not.toHaveBeenCalled();
		expect( enableButton ).toBeDisabled();

		userEvent.click(
			screen.getByText( 'Enable automatic capture of payments' )
		);

		expect( enableButton ).not.toBeDisabled();
		expect(
			screen.getByText( 'Enable automatic capture of payments' )
		).toBeInTheDocument();

		userEvent.click( screen.getByText( 'Enable' ) );

		await waitFor( () =>
			expect( setIsUpeEnabledMock ).toHaveBeenCalledWith( true )
		);
		await waitFor( () => expect( saveSettingsMock ).toHaveBeenCalled() );
		expect( setIsManualCaptureEnabledMock ).toHaveBeenCalledWith( false );
		expect( setCompletedMock ).toHaveBeenCalledWith(
			true,
			'add-payment-methods'
		);
	} );

	it( 'should enable the UPE flag when clicking the "Enable" button', async () => {
		const setCompletedMock = jest.fn();
		const setIsUpeEnabledMock = jest.fn().mockResolvedValue( true );
		useManualCapture.mockReturnValue( [ false, () => null ] );
		const saveSettingsMock = jest.fn().mockResolvedValue( true );
		useSettings.mockReturnValue( {
			saveSettings: saveSettingsMock,
			isSaving: false,
		} );

		render(
			<UpeToggleContext.Provider
				value={ {
					isUpeEnabled: false,
					setIsUpeEnabled: setIsUpeEnabledMock,
				} }
			>
				<WizardTaskContext.Provider
					value={ { setCompleted: setCompletedMock } }
				>
					<EnableUpePreviewTask />
				</WizardTaskContext.Provider>
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( 'Enable automatic capture of payments' )
		).not.toBeInTheDocument();
		expect( setCompletedMock ).not.toHaveBeenCalled();
		expect( setIsUpeEnabledMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Enable' ) );

		await waitFor( () =>
			expect( setIsUpeEnabledMock ).toHaveBeenCalledWith( true )
		);
		await waitFor( () => expect( saveSettingsMock ).toHaveBeenCalled() );
		expect( setCompletedMock ).toHaveBeenCalledWith(
			true,
			'add-payment-methods'
		);
	} );
} );
