import reducer from '../reducer';
import { updateAccountKeys, updateIsSavingAccountKeys } from '../actions';

describe( 'Account keys reducer tests', () => {
	test( 'default state equals expected', () => {
		const defaultState = reducer( undefined, { type: 'foo' } );

		expect( defaultState ).toEqual( {
			isSaving: false,
			isTesting: false,
			isValid: null,
			data: {},
			savingError: null,
			isConfiguringWebhooks: false,
		} );
	} );

	describe( 'SET_ACCOUNT_KEYS', () => {
		test( 'sets the `data` field', () => {
			const accountKeys = {
				foo: 'bar',
			};

			const state = reducer(
				undefined,
				updateAccountKeys( accountKeys )
			);

			expect( state.data ).toEqual( accountKeys );
		} );

		test( 'overwrites existing accountKeys in the `data` field', () => {
			const oldState = {
				data: {
					foo: 'bar',
				},
			};

			const newAccountKeys = {
				baz: 'quux',
			};

			const state = reducer(
				oldState,
				updateAccountKeys( newAccountKeys )
			);

			expect( state.data ).toEqual( newAccountKeys );
		} );

		test( 'leaves fields other than `data` unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					baz: 'quux',
				},
				savingError: {},
			};

			const newAccountKeys = {
				quuz: 'corge',
			};

			const state = reducer(
				oldState,
				updateAccountKeys( newAccountKeys )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				data: {
					quuz: 'corge',
				},
				savingError: {},
			} );
		} );
	} );

	describe( 'SET_IS_SAVING_ACCOUNT_KEYS', () => {
		test( 'toggles isSaving', () => {
			const oldState = {
				isSaving: false,
				savingError: null,
			};

			const state = reducer(
				oldState,
				updateIsSavingAccountKeys( true, {} )
			);

			expect( state.isSaving ).toBeTruthy();
			expect( state.savingError ).toEqual( {} );
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				isSaving: false,
				savingError: {},
			};

			const state = reducer(
				oldState,
				updateIsSavingAccountKeys( true, null )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				savingError: null,
				isSaving: true,
			} );
		} );
	} );
} );
