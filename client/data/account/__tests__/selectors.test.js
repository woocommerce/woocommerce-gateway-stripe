import { getAccount, isRefreshingAccount } from '../selectors';

describe( 'Account selectors tests', () => {
	describe( 'getAccount()', () => {
		test( 'returns the value of state.account.data', () => {
			const state = {
				account: {
					data: {
						foo: 'bar',
					},
				},
			};

			expect( getAccount( state ) ).toEqual( { foo: 'bar' } );
		} );

		test.each( [ [ undefined ], [ {} ], [ { account: {} } ] ] )(
			'returns {} if key is missing (tested state: %j)',
			( state ) => {
				expect( getAccount( state ) ).toEqual( {} );
			}
		);
	} );

	describe( 'isRefreshingAccount()', () => {
		test( 'returns the value of state.account.isRefreshing', () => {
			const state = {
				account: {
					isRefreshing: true,
				},
			};

			expect( isRefreshingAccount( state ) ).toBeTruthy();
		} );

		test.each( [ [ undefined ], [ {} ], [ { account: {} } ] ] )(
			'returns false if missing (tested state: %j)',
			( state ) => {
				expect( isRefreshingAccount( state ) ).toBeFalsy();
			}
		);
	} );
} );
