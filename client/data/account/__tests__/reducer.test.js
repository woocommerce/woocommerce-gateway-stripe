import reducer from '../reducer';

describe( 'Account reducer tests', () => {
	it( 'returns the default state', () => {
		const state = reducer( undefined, { type: 'foo' } );

		expect( state ).toEqual( {
			isRefreshing: false,
			data: {},
		} );
	} );

	describe( 'SET_ACCOUNT', () => {
		it( 'sets the `data` field', () => {
			const account = {
				foo: 'bar',
			};

			const state = reducer( undefined, {
				payload: account,
				type: 'SET_ACCOUNT',
			} );

			expect( state.data ).toEqual( account );
		} );

		it( 'overwrites existing account in the `data` field', () => {
			const oldState = {
				data: {
					foo: 'bar',
				},
			};

			const newAccount = {
				baz: 'quux',
			};

			const state = reducer( oldState, {
				payload: newAccount,
				type: 'SET_ACCOUNT',
			} );

			expect( state.data ).toEqual( newAccount );
		} );

		it( 'leaves fields other than `data` unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					baz: 'quux',
				},
			};

			const newAccount = {
				quuz: 'corge',
			};

			const state = reducer( oldState, {
				payload: newAccount,
				type: 'SET_ACCOUNT',
			} );

			expect( state ).toEqual( {
				foo: 'bar',
				data: {
					quuz: 'corge',
				},
			} );
		} );
	} );

	describe( 'SET_IS_REFRESHING', () => {
		it( 'toggles isRefreshing', () => {
			const oldState = {
				isRefreshing: false,
			};

			const state = reducer( oldState, {
				isRefreshing: true,
				type: 'SET_IS_REFRESHING',
			} );

			expect( state.isRefreshing ).toBeTruthy();
		} );

		it( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				isRefreshing: true,
			};

			const state = reducer( oldState, {
				isRefreshing: false,
				type: 'SET_IS_REFRESHING',
			} );

			expect( state ).toEqual( {
				foo: 'bar',
				isRefreshing: false,
			} );
		} );
	} );
} );
