import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { loadStripe } from '@stripe/stripe-js';
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
} );
