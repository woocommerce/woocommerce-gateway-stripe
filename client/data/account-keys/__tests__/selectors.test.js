import { getAccountKeys, isSavingAccountKeys } from '../selectors';

describe( 'Account keys selectors tests', () => {
	describe( 'getAccountKeys()', () => {
		test( 'returns the value of state.accountKeys.data', () => {
			const state = {
				accountKeys: {
					data: {
						foo: 'bar',
					},
				},
			};

			expect( getAccountKeys( state ) ).toEqual( { foo: 'bar' } );
		} );

		test.each( [ [ undefined ], [ {} ], [ { accountKeys: {} } ] ] )(
			'returns {} if key is missing (tested state: %j)',
			( state ) => {
				expect( getAccountKeys( state ) ).toEqual( {} );
			}
		);
	} );

	describe( 'isSavingAccountKeys()', () => {
		test( 'returns the value of state.accountKeys.isSaving', () => {
			const state = {
				accountKeys: {
					isSaving: true,
				},
			};

			expect( isSavingAccountKeys( state ) ).toBeTruthy();
		} );

		test.each( [ [ undefined ], [ {} ], [ { accountKeys: {} } ] ] )(
			'returns false if missing (tested state: %j)',
			( state ) => {
				expect( isSavingAccountKeys( state ) ).toBeFalsy();
			}
		);
	} );
} );
