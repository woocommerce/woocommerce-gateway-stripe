/**
 * External dependencies
 */
import React from 'react';
import { screen, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import UpeOptInBanner from '..';

describe( 'UpeOptInBanner', () => {
	it( 'should render the information', () => {
		render( <UpeOptInBanner /> );

		expect(
			screen.queryByText(
				'Enable the new Stripe payment management experience'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Spend less time managing giropay and other payment methods in an improved settings and checkout experience, now available to select merchants.'
			)
		).toBeInTheDocument();
	} );

	it( 'should render the action elements', () => {
		render( <UpeOptInBanner /> );

		expect(
			screen.queryByText( 'Enable in your store' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Learn more' ) ).toBeInTheDocument();
	} );
} );
