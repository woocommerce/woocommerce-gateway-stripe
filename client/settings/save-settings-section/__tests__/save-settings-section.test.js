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

	it( 'calls only `agenticSaveRef.current.save` and does not call `saveSettings` when agenticSaveRef is passed', async () => {
		const saveSettingsMock = jest.fn();
		const agenticSaveMock = jest.fn().mockResolvedValue();
		useSettings.mockReturnValue( {
			isSaving: false,
			isLoading: false,
			saveSettings: saveSettingsMock,
		} );

		const agenticSaveRef = {
			current: {
				save: agenticSaveMock,
			},
		};

		render( <SaveSettingsSection agenticSaveRef={ agenticSaveRef } /> );

		const saveChangesButton = screen.getByText( 'Save changes' );

		await act( async () => {
			await userEvent.click( saveChangesButton );
		} );

		expect( agenticSaveMock ).toHaveBeenCalled();
		expect( saveSettingsMock ).not.toHaveBeenCalled();
	} );

	it( 'calls onSettingsSave after saving agentic settings', async () => {
		const agenticSaveMock = jest.fn().mockResolvedValue();
		const onSettingsSaveMock = jest.fn();
		useSettings.mockReturnValue( {
			isSaving: false,
			isLoading: false,
			saveSettings: jest.fn(),
		} );

		const agenticSaveRef = {
			current: {
				save: agenticSaveMock,
			},
		};

		render(
			<SaveSettingsSection
				agenticSaveRef={ agenticSaveRef }
				onSettingsSave={ onSettingsSaveMock }
			/>
		);

		await act( async () => {
			await userEvent.click( screen.getByText( 'Save changes' ) );
		} );

		expect( agenticSaveMock ).toHaveBeenCalled();
		expect( onSettingsSaveMock ).toHaveBeenCalled();
	} );
} );
