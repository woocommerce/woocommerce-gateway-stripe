/**
 * External dependencies
 */
import React from 'react';
import { screen, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GeneralSettingsSection from '..';

describe( 'GeneralSettingsSection', () => {
	it( 'should render the card information', () => {
		render( <GeneralSettingsSection /> );

		expect(
			screen.queryByText( 'Credit card / debit card' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Let your customers pay with major credit and debit cards without leaving your store.'
			)
		).toBeInTheDocument();
	} );

	it( 'should render the opt-in banner with action elements', () => {
		render( <GeneralSettingsSection /> );

		expect( screen.queryByTestId( 'opt-in-banner' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Enable in your store' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Learn more' ) ).toBeInTheDocument();
	} );
} );
