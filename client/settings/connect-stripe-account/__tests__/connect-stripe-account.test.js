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
} );
