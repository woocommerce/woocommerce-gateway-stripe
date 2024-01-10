import reducer from '../reducer';
import {
	updateSettings,
	updateIsSavingSettings,
	updateSettingsValues,
	updateIsSavingOrderedPaymentMethodIds,
	updateIsCustomizingPaymentMethod,
} from '../actions';

describe( 'Settings reducer tests', () => {
	test( 'default state equals expected', () => {
		const defaultState = reducer( undefined, { type: 'foo' } );

		expect( defaultState ).toEqual( {
			isSaving: false,
			data: {},
			savingError: null,
			isSavingOrderedPaymentMethodIds: false,
			isCustomizingPaymentMethod: false,
		} );
	} );

	describe( 'SET_SETTINGS', () => {
		test( 'sets the `data` field', () => {
			const settings = {
				foo: 'bar',
			};

			const state = reducer( undefined, updateSettings( settings ) );

			expect( state.data ).toEqual( settings );
		} );

		test( 'overwrites existing settings in the `data` field', () => {
			const oldState = {
				data: {
					foo: 'bar',
				},
			};

			const newSettings = {
				baz: 'quux',
			};

			const state = reducer( oldState, updateSettings( newSettings ) );

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

			const state = reducer( oldState, updateSettings( newSettings ) );

			expect( state ).toEqual( {
				foo: 'bar',
				data: {
					quuz: 'corge',
				},
				savingError: {},
			} );
		} );
	} );

	describe( 'SET_SETTINGS_VALUES', () => {
		test( 'adds specified new fields to the `data` subtree', () => {
			const oldState = {
				data: {
					foo: 'bar',
				},
			};

			const payload = { baz: 'quux', quuz: 'corge' };

			const state = reducer( oldState, updateSettingsValues( payload ) );

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
				updateSettingsValues( { foo: 'baz' } )
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
				updateSettingsValues( { foo: 'baz' } )
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

			const state = reducer( oldState, updateSettingsValues( payload ) );

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
				updateIsSavingSettings( true, {} )
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
				updateIsSavingSettings( true, null )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				savingError: null,
				isSaving: true,
			} );
		} );
	} );

	describe( 'SET_IS_CUSTOMIZING_PAYMENT_METHOD', () => {
		test( 'toggles isCustomizingPaymentMethod', () => {
			const oldState = {
				isCustomizingPaymentMethod: false,
			};

			const state = reducer(
				oldState,
				updateIsCustomizingPaymentMethod( true )
			);

			expect( state.isCustomizingPaymentMethod ).toBeTruthy();
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				isSaving: false,
				savingError: {},
				isCustomizingPaymentMethod: false,
			};

			const state = reducer(
				oldState,
				updateIsCustomizingPaymentMethod( true )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				savingError: {},
				isSaving: false,
				isCustomizingPaymentMethod: true,
			} );
		} );
	} );

	describe( 'SET_IS_SAVING_ORDERED_PAYMENT_METHOD_IDS', () => {
		test( 'toggles isSavingOrderedPaymentMethodIds', () => {
			const oldState = {
				isSavingOrderedPaymentMethodIds: false,
			};

			const state = reducer(
				oldState,
				updateIsSavingOrderedPaymentMethodIds( true )
			);

			expect( state.isSavingOrderedPaymentMethodIds ).toBeTruthy();
		} );

		test( 'leaves other fields unchanged', () => {
			const oldState = {
				foo: 'bar',
				isSaving: false,
				savingError: {},
				isSavingOrderedPaymentMethodIds: false,
			};

			const state = reducer(
				oldState,
				updateIsSavingOrderedPaymentMethodIds( true )
			);

			expect( state ).toEqual( {
				foo: 'bar',
				savingError: {},
				isSaving: false,
				isSavingOrderedPaymentMethodIds: true,
			} );
		} );
	} );
} );
