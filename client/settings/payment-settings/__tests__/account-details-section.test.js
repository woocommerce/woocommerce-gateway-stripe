import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AccountDetailsSection from '../account-details-section';
import { AccountKeysModal } from 'wcstripe/settings/payment-settings/account-keys-modal';
import { useTestMode } from 'wcstripe/data';
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

jest.mock( 'wcstripe/data', () => ( {
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

describe( 'AccountDetailsSection', () => {
	const setModalTypeMock = jest.fn();
	beforeEach( () => {
		useAccountKeysPublishableKey.mockReturnValue( [ '', jest.fn() ] );
		useAccountKeysSecretKey.mockReturnValue( [ '', jest.fn() ] );
		useAccountKeysWebhookSecret.mockReturnValue( [ '', jest.fn() ] );
		useAccountKeysTestPublishableKey.mockReturnValue( [ '', jest.fn() ] );
		useAccountKeysTestSecretKey.mockReturnValue( [ '', jest.fn() ] );
		useAccountKeysTestWebhookSecret.mockReturnValue( [ '', jest.fn() ] );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'should open live account keys modal when edit account keys clicked in live mode', () => {
		useAccount.mockReturnValue( {
			data: {
				webhook_url: 'example.com',
				account: {
					id: 'acct_123',
					testmode: false,
				},
			},
		} );
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

		render( <AccountDetailsSection setModalType={ setModalTypeMock } /> );

		const editKeysButton = screen.getByRole( 'button', {
			name: 'Edit account keys',
		} );
		userEvent.click( editKeysButton );
		expect( setModalTypeMock ).toHaveBeenCalledWith( 'live' );
	} );

	it( 'should open test account keys modal when edit account keys clicked in test mode', () => {
		useAccount.mockReturnValue( {
			data: {
				webhook_url: 'example.com',
				account: {
					id: 'acct_123',
					testmode: true,
				},
			},
		} );
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

		render( <AccountDetailsSection setModalType={ setModalTypeMock } /> );

		const editKeysButton = screen.getByRole( 'button', {
			name: /Edit account keys/i,
		} );
		userEvent.click( editKeysButton );
		expect( setModalTypeMock ).toHaveBeenCalledWith( 'test' );
	} );

	it( 'should call `testAccountKeys` when the link is clicked', () => {
		const testAccountKeysMock = jest.fn();
		useAccountKeys.mockReturnValue( {
			isValid: null,
			updateIsValidAccountKeys: jest.fn(),
			testAccountKeys: testAccountKeysMock,
		} );

		render( <AccountKeysModal /> );

		const testConnectionLink = screen.getByText( /Test connection/i );
		expect( testConnectionLink ).toBeInTheDocument();

		expect( testAccountKeysMock ).not.toHaveBeenCalled();

		userEvent.click( testConnectionLink );

		expect( testAccountKeysMock ).toHaveBeenCalled();
	} );

	it( 'should test the account keys when test connection clicked', () => {
		const updateIsValidAccountKeys = jest.fn( ( val ) => {
			expect( val ).toBe( true );
		} );

		const testAccountKeys = jest.fn( ( val ) => {
			expect( val ).toStrictEqual( {
				live: true,
				publishable: 'pk_live_dummy_publishable_key',
				secret: 'sk_live_dummy_secret',
			} );
			return true;
		} );

		useAccountKeys.mockReturnValue( {
			isValid: null,
			isTesting: null,
			updateIsValidAccountKeys,
			testAccountKeys,
		} );

		useAccountKeysSecretKey.mockReturnValue( [
			'sk_live_dummy_secret',
			jest.fn(),
		] );
		useAccountKeysPublishableKey.mockReturnValue( [
			'pk_live_dummy_publishable_key',
			jest.fn(),
		] );

		render( <AccountKeysModal /> );

		const testConnectionLink = screen.getByText( /Test connection/i );
		expect( testConnectionLink ).toBeInTheDocument();
		userEvent.click( testConnectionLink );

		useAccountKeys.mockReturnValue( {
			isValid: true,
			isTesting: false,
		} );

		render( <AccountKeysModal /> );

		const connectionSuccessfulMessage = screen.getByText(
			/Connection successful/i
		);
		expect( connectionSuccessfulMessage ).toBeInTheDocument();
	} );

	it( 'Stripe account ID and email should be displayed with a live account', () => {
		useAccount.mockReturnValue( {
			data: {
				webhook_url: 'example.com',
				account: {
					id: 'acct_123',
					email: 'test@example.com',
					testmode: false,
				},
			},
		} );
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

		render( <AccountDetailsSection setModalType={ setModalTypeMock } /> );

		const stripeAccountEmail = screen.getByText( /test@example.com/i );
		expect( stripeAccountEmail ).toBeInTheDocument();

		const stripeAccountId = screen.getByText( /acct_123/i );
		expect( stripeAccountId ).toBeInTheDocument();
	} );
} );
