import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AccountDetailsSection from '../account-details-section';
import { useTestMode } from 'wcstripe/data';
import {
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
	useAccountKeysWebhookURL: jest.fn(),
	useAccountKeysTestWebhookURL: jest.fn(),
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

const mockRefreshAccount = jest.fn();
const mockCreateSuccessNotice = jest.fn();
jest.mock( '@wordpress/data', () => ( {
	useDispatch: ( store ) => {
		if ( store === 'wc/stripe' ) {
			return { refreshAccount: mockRefreshAccount };
		}
		if ( store === 'core/notices' ) {
			return { createSuccessNotice: mockCreateSuccessNotice };
		}
		return {};
	},
	combineReducers: jest.fn(),
	createReduxStore: jest.fn(),
	register: jest.fn(),
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

	it( 'should open live account keys modal when Configure connection clicked in live mode', () => {
		useAccount.mockReturnValue( {
			data: {
				webhook_url: 'example.com',
				account: {
					id: 'acct_123',
					testmode: false,
				},
				configured_webhook_urls: {
					live: 'example.com',
					test: 'example.com',
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
			name: 'Configure connection',
		} );
		userEvent.click( editKeysButton );
		expect( setModalTypeMock ).toHaveBeenCalledWith( 'live' );
	} );

	it( 'should open test account keys modal when Configure connection clicked in test mode', () => {
		useAccount.mockReturnValue( {
			data: {
				webhook_url: 'example.com',
				account: {
					id: 'acct_123',
					testmode: true,
				},
				configured_webhook_urls: {
					live: 'example.com',
					test: 'example.com',
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
			name: /Configure connection/i,
		} );
		userEvent.click( editKeysButton );
		expect( setModalTypeMock ).toHaveBeenCalledWith( 'test' );
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
				configured_webhook_urls: {
					live: 'example.com',
					test: 'example.com',
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

	describe( 'Refresh account functionality', () => {
		beforeEach( () => {
			useAccount.mockReturnValue( {
				data: {
					webhook_url: 'example.com',
					account: {
						id: 'acct_123',
						email: 'test@example.com',
						testmode: false,
					},
					configured_webhook_urls: {
						live: 'example.com',
						test: 'example.com',
					},
				},
			} );
			mockRefreshAccount.mockClear();
			mockCreateSuccessNotice.mockClear();
		} );

		it( 'should show refresh account option in dropdown menu', () => {
			render(
				<AccountDetailsSection setModalType={ setModalTypeMock } />
			);

			// Open the dropdown menu
			const menuButton = screen.getByLabelText(
				'Edit details or disconnect account'
			);
			userEvent.click( menuButton );

			// Check if refresh option exists
			const refreshButton = screen.getByRole( 'menuitem', {
				name: /refresh account details/i,
			} );
			expect( refreshButton ).toBeInTheDocument();
		} );

		it( 'should call refreshAccount when refresh option is clicked', async () => {
			render(
				<AccountDetailsSection setModalType={ setModalTypeMock } />
			);

			// Open the dropdown menu
			const menuButton = screen.getByLabelText(
				'Edit details or disconnect account'
			);
			userEvent.click( menuButton );

			// Click the refresh option
			const refreshButton = screen.getByRole( 'menuitem', {
				name: /refresh account details/i,
			} );
			userEvent.click( refreshButton );

			expect( mockRefreshAccount ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
