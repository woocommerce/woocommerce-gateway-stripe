/**
 * External dependencies
 */
import React from 'react';
import { screen, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GeneralSettingsSection from '..';
import UpeToggleContext from '../../upe-toggle/context';

describe( 'GeneralSettingsSection', () => {
	it( 'should render the card information', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);
		expect(
			screen.queryByText( 'Credit card / debit card' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Let your customers pay with major credit and debit cards without leaving your store.'
			)
		).toBeInTheDocument();
	} );

	it( 'should render the opt-in banner with action elements if UPE is disabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);
		expect( screen.queryByTestId( 'opt-in-banner' ) ).toBeInTheDocument();
		expect(
			screen.queryByRole( 'link', { name: 'Enable in your store' } )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Learn more' ) ).toBeInTheDocument();
	} );

	it( 'should not render the opt-in banner if UPE is enabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByTestId( 'opt-in-banner' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'link', { name: 'Enable in your store' } )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Learn more' ) ).not.toBeInTheDocument();
	} );
} );
