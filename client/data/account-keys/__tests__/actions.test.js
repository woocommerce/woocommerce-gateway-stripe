import { dispatch, select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { saveAccountKeys } from '../actions';

jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/data-controls' );

describe( 'Account keys actions tests', () => {
	describe( 'saveAccountKeys()', () => {
		beforeEach( () => {
			const noticesDispatch = {
				createSuccessNotice: jest.fn(),
				createErrorNotice: jest.fn(),
			};

			apiFetch.mockImplementation( () => {} );

			dispatch.mockImplementation( ( storeName ) => {
				if ( storeName === 'core/notices' ) {
					return noticesDispatch;
				}

				return { invalidateResolutionForStoreSelector: () => null };
			} );
			select.mockImplementation( () => ( {
				getAccountKeys: jest.fn(),
			} ) );
		} );

		it( 'makes POST request with the keys', () => {
			const accountKeysMock = {
				publishable_key: 'foo',
				secret_key: 'bar',
				webhook_secret: 'baz',
			};

			select.mockReturnValue( {
				getAccountKeys: () => accountKeysMock,
			} );

			const yielded = [ ...saveAccountKeys( accountKeysMock ) ];

			expect( apiFetch ).toHaveBeenCalledWith( {
				method: 'POST',
				path: '/wc/v3/wc_stripe/account_keys',
				data: accountKeysMock,
			} );
			expect( yielded ).toEqual(
				expect.arrayContaining( [
					expect.objectContaining( {
						type: 'SET_IS_SAVING_ACCOUNT_KEYS',
						isSaving: true,
					} ),
				] )
			);
			expect( yielded ).toEqual(
				expect.arrayContaining( [
					expect.objectContaining( {
						type: 'SET_IS_SAVING_ACCOUNT_KEYS',
						isSaving: true,
					} ),
				] )
			);
		} );
	} );
} );
