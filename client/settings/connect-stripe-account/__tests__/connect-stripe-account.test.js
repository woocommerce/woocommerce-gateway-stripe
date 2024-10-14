import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ConnectStripeAccount from '..';
import { recordEvent } from 'wcstripe/tracking';

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

	it( 'should render both the "Create or connect an account" and "Create or connect a test account" buttons when both Stripe OAuth links are provided', () => {
		render(
			<ConnectStripeAccount
				oauthUrl="https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_1234&scope=read_write&state=1234"
				testOauthUrl="https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_5678&scope=read_write&state=5678"
			/>
		);

		expect( screen.queryByText( 'Terms of service.' ) ).toBeInTheDocument();

		expect(
			screen.getByText( 'Create or connect an account' )
		).toBeInTheDocument();

		expect(
			screen.getByText( 'Create or connect a test account instead' )
		).toBeInTheDocument();
	} );

	it( 'should render only the "Create or connect an account" button when only the Stripe OAuth link is provided', () => {
		render(
			<ConnectStripeAccount oauthUrl="https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_1234&scope=read_write&state=1234" />
		);

		expect( screen.queryByText( 'Terms of service.' ) ).toBeInTheDocument();

		expect(
			screen.getByText( 'Create or connect an account' )
		).toBeInTheDocument();

		expect(
			screen.queryByText( 'Create or connect a test account' )
		).not.toBeInTheDocument();

		expect(
			screen.queryByText( 'Create or connect a test account instead' )
		).not.toBeInTheDocument();
	} );

	it( 'should render only the "Create or connect a test account" button when only the Stripe Test OAuth link is provided', () => {
		render(
			<ConnectStripeAccount testOauthUrl="https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_5678&scope=read_write&state=5678" />
		);

		expect( screen.queryByText( 'Terms of service.' ) ).toBeInTheDocument();

		expect(
			screen.getByText( 'Create or connect a test account' )
		).toBeInTheDocument();

		// It should not have the "instead" word at the end
		expect(
			screen.queryByText( 'Create or connect a test account instead' )
		).not.toBeInTheDocument();

		expect(
			screen.queryByText( 'Create or connect an account' )
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

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_create_or_connect_account_click',
			{}
		);

		expect( window.location.assign ).toHaveBeenCalledWith( oauthUrl );

		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { assign },
		} );
	} );

	it( 'should redirect to the Stripe Test OAuth link when clicking on the "Create or connect a test account" button', () => {
		// Keep the original function at hand.
		const assign = window.location.assign;

		Object.defineProperty( window, 'location', {
			value: { assign: jest.fn() },
		} );

		const oauthUrl =
			'https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_1234&scope=read_write&state=1234';

		const testOauthUrl =
			'https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_5678&scope=read_write&state=5678';

		render(
			<ConnectStripeAccount
				oauthUrl={ oauthUrl }
				testOauthUrl={ testOauthUrl }
			/>
		);

		const connectTestAccountButton = screen.getByText(
			'Create or connect a test account instead'
		);
		userEvent.click( connectTestAccountButton );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_create_or_connect_test_account_click',
			{}
		);

		expect( window.location.assign ).toHaveBeenCalledWith( testOauthUrl );

		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { assign },
		} );
	} );
} );
