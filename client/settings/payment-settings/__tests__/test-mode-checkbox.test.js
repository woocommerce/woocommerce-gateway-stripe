import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TestModeCheckbox from '../test-mode-checkbox';
import { useTestMode } from 'wcstripe/data';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';

jest.mock( 'wcstripe/data', () => ( {
	useTestMode: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn(),
	useAccountKeysPublishableKey: jest.fn(),
	useAccountKeysSecretKey: jest.fn(),
	useAccountKeysWebhookSecret: jest.fn(),
	useAccountKeysTestPublishableKey: jest.fn(),
	useAccountKeysTestSecretKey: jest.fn(),
	useAccountKeysTestWebhookSecret: jest.fn(),
} ) );

describe( 'TestModeCheckbox', () => {
	it( 'should enable test mode when the test account keys are present', () => {
		const setTestModeMock = jest.fn();
		useTestMode.mockReturnValue( [ false, setTestModeMock ] );
		useAccountKeys.mockReturnValue( {
			accountKeys: {
				test_publishable_key: 'test_pk',
				test_secret_key: 'test_sk',
				test_webhook_secret: 'test_whs',
			},
		} );

		render( <TestModeCheckbox /> );

		const testModeCheckbox = screen.getByLabelText( 'Enable test mode' );
		expect( testModeCheckbox ).not.toBeChecked();

		userEvent.click( testModeCheckbox );

		expect( setTestModeMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should enable live mode when the account keys are present', () => {
		const setTestModeMock = jest.fn();
		useTestMode.mockReturnValue( [ true, setTestModeMock ] );
		useAccountKeys.mockReturnValue( {
			accountKeys: {
				publishable_key: 'live_pk',
				secret_key: 'live_sk',
				webhook_secret: 'live_whs',
			},
		} );

		render( <TestModeCheckbox /> );

		const testModeCheckbox = screen.getByLabelText( 'Enable test mode' );
		expect( testModeCheckbox ).toBeChecked();

		userEvent.click( testModeCheckbox );

		expect( setTestModeMock ).toHaveBeenCalledWith( false );
	} );
} );
