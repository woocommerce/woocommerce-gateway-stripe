/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( { panel: 'settings' } ),
} ) );

/**
 * Internal dependencies
 */
import SettingsTabPanel from '../';

describe( 'SettingsTabPanel', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render two tabs when mounted', () => {
		render( <SettingsTabPanel /> );
		expect(
			screen.getByRole( 'tab', { name: /Payment Methods/i } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'tab', { name: /Settings/i } )
		).toBeInTheDocument();
	} );

	it( 'should change tabs when clicking on them', () => {
		render( <SettingsTabPanel /> );
		const methodsButton = screen.getByRole( 'tab', {
			name: /Payment Methods/i,
		} );
		userEvent.click( methodsButton );

		expect(
			screen.queryByText( /The general settings sections goes here/i )
		).toBeInTheDocument();

		const settingsButton = screen.getByRole( 'tab', { name: /Settings/i } );
		userEvent.click( settingsButton );

		expect(
			screen.queryByText( /The general settings card goes here/i )
		).toBeInTheDocument();
	} );
} );
