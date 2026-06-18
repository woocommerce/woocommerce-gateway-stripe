import { refreshAccount } from '../actions';
import { STORE_NAME } from '../../constants';
import { select, dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';

jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/data-controls' );

describe( 'Account actions tests', () => {
	describe( 'refreshAccount()', () => {
		let storeDispatch;

		beforeEach( () => {
			const noticesDispatch = {
				createErrorNotice: jest.fn(),
				createSuccessNotice: jest.fn(),
			};
			storeDispatch = {
				invalidateResolutionForStoreSelector: jest.fn(),
			};

			apiFetch.mockImplementation( () => {} );
			dispatch.mockImplementation( ( storeName ) => {
				if ( storeName === 'core/notices' ) {
					return noticesDispatch;
				}

				if ( storeName === STORE_NAME ) {
					return storeDispatch;
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

		it( 'invalidates settings after refreshing account data', () => {
			apiFetch.mockReturnValue( 'api response' );

			const yielded = [ ...refreshAccount() ];

			expect( yielded ).toContainEqual( 'api response' );
			expect(
				storeDispatch.invalidateResolutionForStoreSelector
			).toHaveBeenCalledWith( 'getSettings' );
		} );
	} );
} );
