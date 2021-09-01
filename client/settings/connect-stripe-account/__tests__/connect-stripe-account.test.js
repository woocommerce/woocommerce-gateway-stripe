/**
 * External dependencies
 */
import React from 'react';
import { screen, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
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
} );
