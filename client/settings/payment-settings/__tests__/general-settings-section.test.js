import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { loadStripe } from '@stripe/stripe-js';
import GeneralSettingsSection from '../general-settings-section';
import { AccountKeysModal } from 'wcstripe/settings/payment-settings/account-keys-modal';
import {
	useDebugLog,
	useIsStripeEnabled,
	useEnabledPaymentMethodIds,
	useTestMode,
	useUpeTitle,
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
import { useAccount } from 'wcstripe/data/account';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

jest.mock( 'wcstripe/data', () => ( {
	useDebugLog: jest.fn(),
	useIsStripeEnabled: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useTestMode: jest.fn(),
	useUpeTitle: jest.fn().mockReturnValue( [] ),
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

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( '@stripe/stripe-js', () => ( {
	loadStripe: jest.fn(),
} ) );

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

describe( 'GeneralSettingsSection', () => {
	beforeEach( () => {
		useDebugLog.mockReturnValue( [ false, jest.fn() ] );
	} );

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
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay' ],
			jest.fn(),
		] );

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
		useAccount.mockReturnValue( {
			data: { webhook_url: 'example.com' },
		} );

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

	it( 'should test the account keys when test connection clicked', () => {
		return new Promise( ( resolve ) => {
			const updateIsTestingAccountKeysExpectations = ( function* () {
				// Because updateIsTestingAccountKeys is called asynchronously and
				// from within a finally-block, a generator function in combination
				// with the resolve() call are necessary to properly test things.
				yield true;
				return false;
			} )();
			const updateIsTestingAccountKeys = jest.fn( ( val ) => {
				const expected = updateIsTestingAccountKeysExpectations.next();
				expect( val ).toBe( expected.value );
				// Note, ultimately, the test ends here.
				if ( expected.done ) {
					resolve();
				}
			} );
			const updateIsValidAccountKeysExpectations = ( function* () {
				yield null;
				return true;
			} )();
			const updateIsValidAccountKeys = jest.fn( ( val ) => {
				expect( val ).toBe(
					updateIsValidAccountKeysExpectations.next().value
				);
			} );
			useAccount.mockReturnValue( {
				data: { webhook_url: 'example.com' },
			} );
			useAccountKeys.mockReturnValue( {
				isValid: null,
				updateIsTestingAccountKeys,
				updateIsValidAccountKeys,
			} );

			apiFetch.mockReturnValue( { id: 'random_token_id' } );
			loadStripe.mockReturnValue( {
				createToken: jest.fn( () => ( {
					token: { id: 'random_token_id' },
				} ) ),
			} );
			useAccountKeysWebhookSecret.mockReturnValue( [ '', jest.fn() ] );
			useAccountKeysSecretKey.mockReturnValue( [
				'sk_live_',
				jest.fn(),
			] );
			useAccountKeysPublishableKey.mockReturnValue( [
				'pk_live_',
				jest.fn(),
			] );

			render( <AccountKeysModal /> );

			const testConnectionLink = screen.getByText( /Test connection/i );
			expect( testConnectionLink ).toBeInTheDocument();
			userEvent.click( testConnectionLink );
		} );
	} );

	it( 'should render UPE title field when UPE is enabled', () => {
		useUpeTitle.mockReturnValue( [ 'UPE title', jest.fn() ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect( screen.getByDisplayValue( 'UPE title' ) ).toBeInTheDocument();
	} );

	it( 'should enable debug mode when checkbox is clicked', () => {
		const setIsLoggingCheckedMock = jest.fn();
		useDebugLog.mockReturnValue( [ false, setIsLoggingCheckedMock ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const debugModeCheckbox = screen.getByLabelText( 'Log error messages' );

		expect( screen.getByText( 'Debug mode' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Log error messages' )
		).not.toBeChecked();

		userEvent.click( debugModeCheckbox );

		expect( setIsLoggingCheckedMock ).toHaveBeenCalledWith( true );
	} );
} );
