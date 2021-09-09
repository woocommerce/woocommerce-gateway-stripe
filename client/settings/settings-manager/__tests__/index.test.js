/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import SettingsManager from '..';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn().mockReturnValue( {} ),
	useDispatch: jest.fn().mockReturnValue( {} ),
	combineReducers: jest.fn().mockReturnValue( {} ),
	createReduxStore: jest.fn().mockReturnValue( {} ),
	register: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'SettingsManager', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render two tabs when mounted', () => {
		render( <SettingsManager /> );

		expect(
			screen.getByRole( 'tab', { name: /Payment Methods/i } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'tab', { name: /Settings/i } )
		).toBeInTheDocument();
	} );

	it( 'should render the Stripe payment method tab content by default', () => {
		render( <SettingsManager /> );

		expect(
			screen.queryByTestId( 'settings-tab' )
		).not.toBeInTheDocument();
		expect( screen.queryByTestId( 'methods-tab' ) ).toBeInTheDocument();
	} );

	it( 'should render the general settings tab content when the URL matches', () => {
		getQuery.mockReturnValue( { panel: 'settings' } );
		render( <SettingsManager /> );

		expect( screen.queryByTestId( 'settings-tab' ) ).toBeInTheDocument();
		expect( screen.queryByTestId( 'methods-tab' ) ).not.toBeInTheDocument();
	} );
} );
