import { useSelect, useDispatch } from '@wordpress/data';
import { renderHook, act } from '@testing-library/react-hooks';
import { useAccountKeys, useAccountKeysPublishableKey } from '../hooks';
import { STORE_NAME } from '../../constants';

jest.mock( '@wordpress/data' );

describe( 'Account keys hooks tests', () => {
	let actions;
	let selectors;

	beforeEach( () => {
		actions = {};
		selectors = {};

		const selectMock = jest.fn( ( storeName ) => {
			return STORE_NAME === storeName ? selectors : {};
		} );
		useDispatch.mockImplementation( ( storeName ) => {
			return STORE_NAME === storeName ? actions : {};
		} );
		useSelect.mockImplementation( ( cb ) => {
			return cb( selectMock );
		} );
	} );

	describe( 'useAccountKeys()', () => {
		beforeEach( () => {
			actions = {
				saveAccountKeys: jest.fn(),
			};

			selectors = {
				getAccountKeys: jest.fn( () => ( { foo: 'bar' } ) ),
				getIsTestingAccountKeys: jest.fn(),
				getIsValidAccountKeys: jest.fn(),
				hasFinishedResolution: jest.fn(),
				isResolving: jest.fn(),
				isSavingAccountKeys: jest.fn(),
			};
		} );

		it( 'returns accountKeys from selector', () => {
			const { accountKeys, saveAccountKeys } = useAccountKeys();
			saveAccountKeys( 'bar' );

			expect( accountKeys ).toEqual( { foo: 'bar' } );
			expect( actions.saveAccountKeys ).toHaveBeenCalledWith( 'bar' );
		} );

		it( 'returns isLoading = false when isResolving = false and hasFinishedResolution = true', () => {
			selectors.hasFinishedResolution.mockReturnValue( true );
			selectors.isResolving.mockReturnValue( false );

			const { isLoading } = useAccountKeys();

			expect( isLoading ).toBeFalsy();
		} );

		it.each( [
			[ false, false ],
			[ true, false ],
			[ true, true ],
		] )(
			'returns isLoading = true when isResolving = %s and hasFinishedResolution = %s',
			( isResolving, hasFinishedResolution ) => {
				selectors.hasFinishedResolution.mockReturnValue(
					hasFinishedResolution
				);
				selectors.isResolving.mockReturnValue( isResolving );

				const { isLoading } = useAccountKeys();

				expect( isLoading ).toBeTruthy();
			}
		);
	} );

	describe( 'useAccountKeysPublishableKey()', () => {
		it( 'returns the value of getAccountKeys().publishable_key', () => {
			selectors = {
				getAccountKeys: jest.fn( () => ( {
					publishable_key: 'dark',
				} ) ),
			};

			const { result } = renderHook( () =>
				useAccountKeysPublishableKey()
			);
			const [ value ] = result.current;

			expect( value ).toEqual( 'dark' );
		} );

		it( 'returns an empty string if the value is missing', () => {
			selectors = {
				getAccountKeys: jest.fn( () => ( {} ) ),
			};

			const { result } = renderHook( () =>
				useAccountKeysPublishableKey()
			);
			const [ value ] = result.current;

			expect( value ).toEqual( '' );
		} );

		it( 'allows to update the store', () => {
			const updateAccountKeysValuesMock = jest.fn();
			actions = {
				updateAccountKeysValues: updateAccountKeysValuesMock,
			};

			selectors = {
				getAccountKeys: jest.fn( () => ( {} ) ),
			};

			const { result } = renderHook( () =>
				useAccountKeysPublishableKey()
			);
			const [ , handler ] = result.current;

			act( () => {
				handler( 'pk_live_xxxxx' );
			} );

			expect( updateAccountKeysValuesMock ).toHaveBeenCalledWith( {
				publishable_key: 'pk_live_xxxxx',
			} );
		} );
	} );
} );
