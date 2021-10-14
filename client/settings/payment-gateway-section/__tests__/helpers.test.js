import { getQuery } from '@woocommerce/navigation';
import { getGateway } from '../helpers';

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( { section: 'stripe_alipay' } ),
} ) );

describe( 'PaymentGatewaySection helpers', () => {
	describe( 'getGateway', () => {
		it( 'should return the gateway capitalized', () => {
			const result = getGateway();
			expect( result ).toEqual( 'Alipay' );
		} );

		it( 'should return the gateway capitalized even if it contains numbers', () => {
			getQuery.mockReturnValue( { section: 'stripe_p24' } );
			const result = getGateway();
			expect( result ).toEqual( 'P24' );
		} );

		it( 'should error if section does not match the pattern', () => {
			getQuery.mockReturnValue( { section: 'stripe' } );
			expect( () => {
				getGateway();
			} ).toThrowError( 'stripe is not being hooked.' );
		} );
	} );
} );
