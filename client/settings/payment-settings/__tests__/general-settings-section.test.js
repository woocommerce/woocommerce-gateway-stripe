import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import GeneralSettingsSection from '../general-settings-section';
import { useTestMode } from 'wcstripe/data';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';

jest.mock( 'wcstripe/data', () => ( {
	useTestMode: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn(),
} ) );

describe( 'GeneralSettingsSection', () => {
	it( 'should enable stripe when stripe checkbox is clicked', () => {
		const setTestModeMock = jest.fn();
		useTestMode.mockReturnValue( [ false, setTestModeMock ] );
		useAccountKeys.mockReturnValue( {
			accountKeys: {
				test_publishable_key: 'test_pk',
				test_secret_key: 'test_sk',
				test_webhook_secret: 'test_whs',
			},
		} );

		render( <GeneralSettingsSection /> );

		const enableStripeCheckbox = screen.getByLabelText( 'Enable Stripe' );
		const testModeCheckbox = screen.getByLabelText( 'Enable test mode' );

		expect( enableStripeCheckbox ).not.toBeChecked();
		expect( testModeCheckbox ).not.toBeChecked();

		userEvent.click( enableStripeCheckbox );

		expect( enableStripeCheckbox ).toBeChecked();
		expect( testModeCheckbox ).not.toBeChecked();

		userEvent.click( testModeCheckbox );

		expect( enableStripeCheckbox ).toBeChecked();
		expect( setTestModeMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should open live account keys modal when edit account keys clicked in live mode', () => {
		const setTestModeMock = jest.fn();
		useTestMode.mockReturnValue( [ false, setTestModeMock ] );

		render( <GeneralSettingsSection /> );

		const editKeysButton = screen.getByRole( 'button', {
			text: /edit account keys/i,
		} );
		userEvent.click( editKeysButton );
		const accountKeysModal = screen.getByLabelText(
			/edit live account keys & webhooks/i
		);
		expect( accountKeysModal ).toBeInTheDocument();
	} );

	it( 'should open test account keys modal when edit account keys clicked in test mode', () => {
		const setTestModeMock = jest.fn();
		useTestMode.mockReturnValue( [ true, setTestModeMock ] );

		render( <GeneralSettingsSection /> );

		const editKeysButton = screen.getByRole( 'button', {
			text: /edit account keys/i,
		} );
		userEvent.click( editKeysButton );
		const accountKeysModal = screen.getByLabelText(
			/edit test account keys & webhooks/i
		);
		expect( accountKeysModal ).toBeInTheDocument();
	} );
} );
