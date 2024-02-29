import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ConnectStripeAccount from '..';
import {
	useAccountKeys,
	useAccountKeysPublishableKey,
	useAccountKeysSecretKey,
	useAccountKeysWebhookSecret,
} from 'wcstripe/data/account-keys/hooks';
import { useAccount } from 'wcstripe/data/account';
import { recordEvent } from 'wcstripe/tracking';

jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn(),
	useAccountKeysPublishableKey: jest.fn(),
	useAccountKeysSecretKey: jest.fn(),
	useAccountKeysWebhookSecret: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'ConnectStripeAccount', () => {
	it( 'should render the information', () => {
		render( <ConnectStripeAccount /> );

		expect(
			screen.queryByText( 'Get started with Stripe' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Connect or create a Stripe account to accept payments directly onsite, including Payment Request buttons (such as Apple Pay and Google Pay), iDEAL, SEPA, and more international payment methods.'
			)
		).toBeInTheDocument();
	} );

	it( 'should render both the Connect Account and Enter keys buttons when the Stripe OAuth link is provided', () => {
		render(
			<ConnectStripeAccount oauthUrl="https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_1234&scope=read_write&state=1234" />
		);

		expect( screen.queryByText( 'Terms of service.' ) ).toBeInTheDocument();

		expect(
			screen.getByText( 'Create or connect an account' )
		).toBeInTheDocument();

		expect(
			screen.queryByText( 'Enter account keys (advanced)' )
		).not.toBeInTheDocument();
	} );

	it( 'should redirect to the Stripe OAuth link when clicking on the "Create or connect an account" button', () => {
		// Keep the original function at hand.
		const assign = window.location.assign;

		Object.defineProperty( window, 'location', {
			value: { assign: jest.fn() },
		} );

		const oauthUrl =
			'https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_1234&scope=read_write&state=1234';

		render( <ConnectStripeAccount oauthUrl={ oauthUrl } /> );

		const connectAccountButton = screen.getByText(
			'Create or connect an account'
		);
		userEvent.click( connectAccountButton );

		expect( window.location.assign ).toHaveBeenCalledWith( oauthUrl );

		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { assign },
		} );
	} );

	it( 'should record a "wcstripe_create_or_connect_account_click" Track event when clicking on the Connect account button', () => {
		render(
			<ConnectStripeAccount oauthUrl="https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_1234&scope=read_write&state=1234" />
		);

		const connectAccountButton = screen.getByText(
			'Create or connect an account'
		);
		userEvent.click( connectAccountButton );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_create_or_connect_account_click',
			{}
		);
	} );

	it( 'should only have the "Enter account keys" button if OAuth URL is blank', () => {
		render( <ConnectStripeAccount oauthUrl="" /> );

		expect(
			screen.queryByText( 'Terms of service.' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Create or connect an account' )
		).not.toBeInTheDocument();
		expect(
			screen.getByText( 'Enter account keys (advanced)' )
		).toBeInTheDocument();
	} );

	it( 'should open the live account keys modal when clicking "enter acccount keys"', () => {
		useAccountKeys.mockReturnValue( {
			accountKeys: {
				publishable_key: 'live_pk',
				secret_key: 'live_sk',
				webhook_secret: 'live_whs',
			},
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
		useAccount.mockReturnValue( {
			data: { webhook_url: 'example.com' },
		} );

		render( <ConnectStripeAccount oauthUrl="" /> );
		const accountKeysButton = screen.queryByText( /enter account keys/i );
		userEvent.click( accountKeysButton );
		expect(
			screen.queryByText( /edit live account keys & webhooks/i )
		).toBeInTheDocument();
	} );

	it( 'should record a "wcstripe_enter_account_keys_click" Track event when clicking on the Enter account keys button', () => {
		render( <ConnectStripeAccount oauthUrl="" /> );

		const accountKeysButton = screen.queryByText( /enter account keys/i );
		userEvent.click( accountKeysButton );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_enter_account_keys_click',
			{}
		);
	} );
} );
