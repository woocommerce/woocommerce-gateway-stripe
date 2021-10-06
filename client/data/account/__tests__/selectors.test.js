import { getAccountData, isRefreshingAccount } from '../selectors';

describe( 'Account selectors tests', () => {
	describe( 'getAccountData()', () => {
		test( 'returns the value of state.account.data', () => {
			const state = {
				account: {
					data: {
						foo: 'bar',
					},
				},
			};

			expect( getAccountData( state ) ).toEqual( { foo: 'bar' } );
		} );

		test.each( [ [ undefined ], [ {} ], [ { account: {} } ] ] )(
			'returns {} if key is missing (tested state: %j)',
			( state ) => {
				expect( getAccountData( state ) ).toEqual( {} );
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
