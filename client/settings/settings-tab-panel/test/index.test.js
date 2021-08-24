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
		const settingsButton = screen.getByRole( 'tab', { name: /Settings/i } );
		const methodsButton = screen.getByRole( 'tab', {
			name: /Payment Methods/i,
		} );
		userEvent.click( settingsButton );
		expect( screen.queryByText( /Settings content/i ) ).toBeInTheDocument();
		userEvent.click( methodsButton );
		expect( screen.queryByText( /Payment Methods content/i ) ).toBeInTheDocument();
	} );

	it( 'should render Settings panel when settings query param is passed', () => {
		render( <SettingsTabPanel /> );
		const settingsButton = screen.getByRole( 'tab', { name: /Settings/i } );
		expect( settingsButton ).toHaveClass( 'is-active' );
	} );
} );
