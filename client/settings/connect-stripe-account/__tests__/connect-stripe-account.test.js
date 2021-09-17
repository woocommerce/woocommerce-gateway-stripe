import React from 'react';
import { screen, render } from '@testing-library/react';
import ConnectStripeAccount from '..';

describe( 'ConnectStripeAccount', () => {
	it( 'should render the information', () => {
		render( <ConnectStripeAccount /> );

		expect(
			screen.queryByText( 'Get started with Stripe' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Connect or create a Stripe account to accept payments directly onsite, including Payment Request buttons (such as Apple Pay and Google Pay), iDeal, SEPA, Sofort, and more international payment methods.'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Stripe’s Terms of service.' )
		).toBeInTheDocument();
	} );

	it( 'should render the buttons', () => {
		render( <ConnectStripeAccount /> );

		expect(
			screen.queryByText( 'Create or connect an account' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Enter account keys (advanced)' )
		).toBeInTheDocument();
	} );

	it( 'should have a Stripe OAuth link for "Create or connect an account" button', () => {
		render(
			<ConnectStripeAccount
				oauthUrl={
					'https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_1234&scope=read_write&state=1234'
				}
			/>
		);

		expect(
			screen.getByText( 'Create or connect an account' )
		).toHaveAttribute(
			'href',
			'https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_1234&scope=read_write&state=1234'
		);
	} );

	it( 'should not be able to click button "Create or connect an account" if OAuth URL is blank', () => {
		render( <ConnectStripeAccount oauthUrl={ '' } /> );

		expect(
			screen.getByText( 'Create or connect an account' )
		).toBeDisabled();
	} );
} );
