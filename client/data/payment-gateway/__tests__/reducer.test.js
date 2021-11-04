import reducer from '../reducer';
import {
	updatePaymentGateway,
	updateIsSavingPaymentGateway,
	updatePaymentGatewayValues,
} from '../actions';

describe( 'Payment gateway reducer tests', () => {
	test( 'default state equals expected', () => {
		const defaultState = reducer( undefined, { type: 'foo' } );

		expect( defaultState ).toEqual( {
			isSaving: false,
			data: {},
			savingError: null,
		} );
	} );

	describe( 'SET_PAYMENT_GATEWAY', () => {
		test( 'sets the `data` field', () => {
			const settings = {
				foo: 'bar',
			};

			const state = reducer(
				undefined,
				updatePaymentGateway( settings )
			);

			expect( state.data ).toEqual( settings );
		} );

		test( 'overwrites existing payment gateway settings in the `data` field', () => {
			const oldState = {
				data: {
					foo: 'bar',
				},
			};

			const newSettings = {
				baz: 'quux',
			};

			const state = reducer(
				oldState,
				updatePaymentGateway( newSettings )
			);

			expect( state.data ).toEqual( newSettings );
		} );

		test( 'leaves fields other than `data` unchanged', () => {
			const oldState = {
				foo: 'bar',
				data: {
					baz: 'quux',
				},
				savingError: {},
			};

			const newSettings = {
				quuz: 'corge',
			};

			const state = reducer(
				oldState,
				updatePaymentGateway( newSettings )
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

	describe( 'SET_PAYMENT_GATEWAY_VALUES', () => {
		test( 'adds specified new fields to the `data` subtree', () => {
			const oldState = {
				data: {
					foo: 'bar',
				},
			};

			const payload = { baz: 'quux', quuz: 'corge' };

			const state = reducer(
				oldState,
				updatePaymentGatewayValues( payload )
			);

			expect( state.data ).toEqual( {
				foo: 'bar',
				baz: 'quux',
				quuz: 'corge',
			} );
		} );

		test( 'overwrites existing settings in the `data` subtree', () => {
			const oldState = {
				data: {
					foo: 'bar',
				},
			};

			const state = reducer(
				oldState,
				updatePaymentGatewayValues( { foo: 'baz' } )
			);

			expect( state.data ).toEqual( { foo: 'baz' } );
		} );

		test( 'changes only `data` fields specified in payload and leaves others unchanged', () => {
			const oldState = {
				quuz: 'corge',
				data: {
					foo: 'bar',
					baz: 'quux',
				},
				savingError: null,
			};

			const state = reducer(
				oldState,
				updatePaymentGatewayValues( { foo: 'baz' } )
			);

			const expectedState = {
				quuz: 'corge',
				data: {
					foo: 'baz',
					baz: 'quux',
				},
				savingError: null,
			};

			expect( state ).toEqual( expectedState );
		} );

		test( 'sets savingError to null', () => {
			const oldState = {
				data: {
					baz: 'quux',
				},
				savingError: 'baz',
			};

			const payload = {
				quuz: 'corge',
			};

			const state = reducer(
				oldState,
				updatePaymentGatewayValues( payload )
			);

			expect( state.savingError ).toBeNull();
		} );
	} );

	describe( 'SET_IS_SAVING', () => {
		test( 'toggles isSaving', () => {
			const oldState = {
				isSaving: false,
				savingError: null,
			};

			const state = reducer(
				oldState,
				updateIsSavingPaymentGateway( true, {} )
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
				updateIsSavingPaymentGateway( true, null )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				savingError: null,
				isSaving: true,
			} );
		} );
	} );
} );
