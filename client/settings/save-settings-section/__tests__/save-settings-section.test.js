import React from 'react';
import { screen, render, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SaveSettingsSection from '..';
import { useSettings } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useSettings: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'SaveSettingsSection', () => {
	it( 'should render the save button', () => {
		render( <SaveSettingsSection /> );

		expect( screen.queryByText( 'Save changes' ) ).toBeInTheDocument();
	} );

	it( 'disables the button when loading data', () => {
		useSettings.mockReturnValue( {
			isLoading: true,
		} );

		render( <SaveSettingsSection /> );

		expect( screen.getByText( 'Save changes' ) ).toBeDisabled();
	} );

	it( 'disables the button when saving data', () => {
		useSettings.mockReturnValue( {
			isSaving: true,
		} );

		render( <SaveSettingsSection /> );

		expect( screen.getByText( 'Save changes' ) ).toBeDisabled();
	} );

	it( 'calls `saveSettings` when the button is clicked', async () => {
		const saveSettingsMock = jest.fn();
		useSettings.mockReturnValue( {
			isSaving: false,
			isLoading: false,
			saveSettings: saveSettingsMock,
		} );

		render( <SaveSettingsSection /> );

		const saveChangesButton = screen.getByText( 'Save changes' );

		expect( saveSettingsMock ).not.toHaveBeenCalled();
		expect( saveChangesButton ).not.toBeDisabled();

		await userEvent.click( saveChangesButton );

		expect( saveSettingsMock ).toHaveBeenCalled();
	} );

	it( 'calls custom `onSave` and skips `saveSettings` and `onSettingsSave` when onSave is passed', async () => {
		const saveSettingsMock = jest.fn();
		const onSettingsSaveMock = jest.fn();
		const customSaveMock = jest.fn().mockResolvedValue();
		useSettings.mockReturnValue( {
			isSaving: false,
			isLoading: false,
			saveSettings: saveSettingsMock,
		} );

		render(
			<SaveSettingsSection
				onSettingsSave={ onSettingsSaveMock }
				onSave={ customSaveMock }
			/>
		);

		const saveChangesButton = screen.getByText( 'Save changes' );

		await act( async () => {
			await userEvent.click( saveChangesButton );
		} );

		expect( customSaveMock ).toHaveBeenCalled();
		expect( saveSettingsMock ).not.toHaveBeenCalled();
		expect( onSettingsSaveMock ).not.toHaveBeenCalled();
	} );

	it( 'disables the button and sets busy state while custom onSave is executing', async () => {
		let resolveSave;
		const customSaveMock = jest.fn(
			() =>
				new Promise( ( resolve ) => {
					resolveSave = resolve;
				} )
		);
		useSettings.mockReturnValue( {
			isSaving: false,
			isLoading: false,
			saveSettings: jest.fn(),
		} );

		render( <SaveSettingsSection onSave={ customSaveMock } /> );

		const saveChangesButton = screen.getByText( 'Save changes' );
		expect( saveChangesButton ).not.toBeDisabled();

		act( () => {
			userEvent.click( saveChangesButton );
		} );

		expect( saveChangesButton ).toBeDisabled();

		await act( async () => {
			resolveSave();
		} );

		expect( saveChangesButton ).not.toBeDisabled();
	} );
} );
