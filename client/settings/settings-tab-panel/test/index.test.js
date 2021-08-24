/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import SettingsTabPanel from '../';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

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

	it( 'should render the Stripe payment method tab content by default', () => {
		render( <SettingsTabPanel /> );

		expect(
			screen.queryByTestId( 'settings-tab' )
		).not.toBeInTheDocument();
		expect( screen.queryByTestId( 'methods-tab' ) ).toBeInTheDocument();
	} );

	it( 'should render the general settings tab content when the URL matches', () => {
		getQuery.mockReturnValue( { panel: 'settings' } );
		render( <SettingsTabPanel /> );

		expect( screen.queryByTestId( 'settings-tab' ) ).toBeInTheDocument();
		expect( screen.queryByTestId( 'methods-tab' ) ).not.toBeInTheDocument();
	} );
} );
