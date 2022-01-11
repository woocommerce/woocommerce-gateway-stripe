import { select, dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { refreshAccount } from '../actions';

jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/data-controls' );

describe( 'Account actions tests', () => {
	describe( 'refreshAccount()', () => {
		beforeEach( () => {
			const noticesDispatch = {
				createErrorNotice: jest.fn(),
				createSuccessNotice: jest.fn(),
			};

			apiFetch.mockImplementation( () => {} );
			dispatch.mockImplementation( ( storeName ) => {
				if ( storeName === 'core/notices' ) {
					return noticesDispatch;
				}

				return {};
			} );

			select.mockImplementation( () => {
				return {
					getAccountCapabilitiesByStatus: () => {
						return [];
					},
				};
			} );
		} );

		it( 'retrieves and stores account data', () => {
			apiFetch.mockReturnValue( 'api response' );

			const yielded = [ ...refreshAccount() ];

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/wc_stripe/account/refresh',
				method: 'POST',
			} );
			expect( yielded ).toContainEqual(
				expect.objectContaining( {
					type: 'SET_IS_REFRESHING',
					isRefreshing: true,
				} )
			);
			expect( yielded ).toContainEqual(
				expect.objectContaining( {
					type: 'SET_IS_REFRESHING',
					isRefreshing: false,
				} )
			);
		} );
	} );
} );
