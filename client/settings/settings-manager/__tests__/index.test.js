import { render, screen } from '@testing-library/react';
import { getQuery } from '@woocommerce/navigation';
import SettingsManager from '..';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

jest.mock( 'wcstripe/settings/customization-options-notice', () => () => null );

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
