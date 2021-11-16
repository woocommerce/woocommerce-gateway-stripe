import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import GeneralSettingsSection from '../general-settings-section';
import {
	useIsStripeEnabled,
	useTestMode,
	useTitle,
	useUpeTitle,
	useDescription,
} from 'wcstripe/data';
import {
	useAccountKeys,
	useAccountKeysPublishableKey,
	useAccountKeysSecretKey,
	useAccountKeysWebhookSecret,
	useAccountKeysTestPublishableKey,
	useAccountKeysTestSecretKey,
	useAccountKeysTestWebhookSecret,
} from 'wcstripe/data/account-keys/hooks';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

jest.mock( 'wcstripe/data', () => ( {
	useIsStripeEnabled: jest.fn(),
	useTestMode: jest.fn(),
	useTitle: jest.fn().mockReturnValue( [] ),
	useUpeTitle: jest.fn().mockReturnValue( [] ),
	useDescription: jest.fn().mockReturnValue( [] ),
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

describe( 'GeneralSettingsSection', () => {
	it( 'should enable stripe when stripe checkbox is clicked', () => {
		const setIsStripeEnabledMock = jest.fn();
		const setTestModeMock = jest.fn();
		useIsStripeEnabled.mockReturnValue( [ false, setIsStripeEnabledMock ] );

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

		expect( setIsStripeEnabledMock ).toHaveBeenCalledWith( true );

		userEvent.click( testModeCheckbox );

		expect( setTestModeMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should open live account keys modal when edit account keys clicked in live mode', () => {
		useTestMode.mockReturnValue( [ false, jest.fn() ] );

		useAccountKeysPublishableKey.mockReturnValue( [
			'live_pk',
			jest.fn(),
		] );
		useAccountKeysSecretKey.mockReturnValue( [ 'live_sk', jest.fn() ] );
		useAccountKeysWebhookSecret.mockReturnValue( [
			'live_whs',
			jest.fn(),
		] );

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
		useTestMode.mockReturnValue( [ true, jest.fn() ] );

		useAccountKeysTestPublishableKey.mockReturnValue( [
			'test_pk',
			jest.fn(),
		] );
		useAccountKeysTestSecretKey.mockReturnValue( [ 'test_sk', jest.fn() ] );
		useAccountKeysTestWebhookSecret.mockReturnValue( [
			'test_whs',
			jest.fn(),
		] );

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

	it( 'should render gateway title and description fields when UPE is disabled', () => {
		useTitle.mockReturnValue( [ 'Title', jest.fn() ] );
		useUpeTitle.mockReturnValue( [ 'UPE title', jest.fn() ] );
		useDescription.mockReturnValue( [ 'Description', jest.fn() ] );

		render( <GeneralSettingsSection /> );

		expect( screen.getByDisplayValue( 'Title' ) ).toBeInTheDocument();
		expect( screen.getByDisplayValue( 'Description' ) ).toBeInTheDocument();
	} );

	it( 'should render UPE title field when UPE is enabled', () => {
		useTitle.mockReturnValue( [ 'Title', jest.fn() ] );
		useUpeTitle.mockReturnValue( [ 'UPE title', jest.fn() ] );
		useDescription.mockReturnValue( [ 'Description', jest.fn() ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect( screen.getByDisplayValue( 'UPE title' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Description' ) ).not.toBeInTheDocument();
	} );
} );
