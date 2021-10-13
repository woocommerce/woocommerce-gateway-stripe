import { render, screen } from '@testing-library/react';
import { getQuery } from '@woocommerce/navigation';
import PaymentGatewayManager from '..';
import UpeToggleContext from '../../upe-toggle/context';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( { section: 'sectionX' } ),
} ) );

jest.mock( '../constants', () => ( {
	gatewaysInfo: {
		sectionX: { title: 'Section X', geography: 'Brazil' },
		sectionY: { title: 'Section Y', geography: 'Italy' },
	},
} ) );

describe( 'PaymentGatewayManager', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render a title and geography when mounted', () => {
		render( <PaymentGatewayManager /> );

		expect( screen.getByText( 'Section X' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Brazil' ) ).toBeInTheDocument();
	} );

	it( 'should render the correct information based in the section', () => {
		getQuery.mockReturnValue( { section: 'sectionX' } );
		render( <PaymentGatewayManager /> );

		expect( screen.getByText( 'Section X' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Section Y' ) ).not.toBeInTheDocument();
	} );

	it( 'should render the opt-in banner with action elements if UPE is disabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<PaymentGatewayManager />
			</UpeToggleContext.Provider>
		);
		expect( screen.queryByTestId( 'opt-in-banner' ) ).toBeInTheDocument();
	} );

	it( 'should not render the opt-in banner with action elements if UPE is enabled', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<PaymentGatewayManager />
			</UpeToggleContext.Provider>
		);
		expect(
			screen.queryByTestId( 'opt-in-banner' )
		).not.toBeInTheDocument();
	} );
} );
