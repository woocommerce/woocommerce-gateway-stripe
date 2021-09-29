import { useSelect, useDispatch } from '@wordpress/data';
import { renderHook } from '@testing-library/react-hooks';
import { useGetCapabilities, useAccount } from '../hooks';

jest.mock( '@wordpress/data' );

describe( 'Account hooks tests', () => {
	let actions;
	let selectors;

	beforeEach( () => {
		actions = {};
		selectors = {};

		const selectMock = jest.fn( ( storeName ) => {
			return storeName === 'wc/stripe' ? selectors : {};
		} );
		useDispatch.mockImplementation( ( storeName ) => {
			return storeName === 'wc/stripe' ? actions : {};
		} );
		useSelect.mockImplementation( ( cb ) => {
			return cb( selectMock );
		} );
	} );

	describe( 'useGetCapabilities()', () => {
		it( 'returns the value of getAccount().capabilities', () => {
			selectors = {
				getAccount: jest.fn( () => ( {
					capabilities: { card_payments: 'active' },
				} ) ),
			};

			const { result } = renderHook( () => useGetCapabilities() );

			expect( result.current ).toEqual( { card_payments: 'active' } );
		} );

		it( 'returns an empty object if the property is missing', () => {
			selectors = {
				getAccount: jest.fn( () => ( {} ) ),
			};

			const { result } = renderHook( () => useGetCapabilities() );

			expect( result.current ).toEqual( {} );
		} );
	} );

	describe( 'useAccount()', () => {
		beforeEach( () => {
			actions = {
				refreshAccount: jest.fn(),
			};

			selectors = {
				getAccount: jest.fn( () => ( { foo: 'bar' } ) ),
				hasFinishedResolution: jest.fn(),
				isResolving: jest.fn(),
				isRefreshingAccount: jest.fn(),
			};
		} );

		it( 'returns account from selector', () => {
			const { account, refreshAccount } = useAccount();
			refreshAccount();

			expect( account ).toEqual( { foo: 'bar' } );
			expect( actions.refreshAccount ).toHaveBeenCalled();
		} );

		it( 'returns isLoading = false when isResolving = false and hasFinishedResolution = true', () => {
			selectors.hasFinishedResolution.mockReturnValue( true );
			selectors.isResolving.mockReturnValue( false );

			const { isLoading } = useAccount();

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

				const { isLoading } = useAccount();

				expect( isLoading ).toBeTruthy();
			}
		);
	} );
} );
