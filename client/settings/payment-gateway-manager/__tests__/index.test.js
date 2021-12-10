import { render, screen } from '@testing-library/react';
import { getQuery } from '@woocommerce/navigation';
import PaymentGatewayManager from '..';

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
} );
