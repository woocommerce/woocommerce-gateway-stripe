import {
	getSettings,
	isSavingSettings,
	getOrderedPaymentMethodIds,
	isSavingOrderedPaymentMethodIds,
} from '../selectors';

describe( 'Settings selectors tests', () => {
	describe( 'getSettings()', () => {
		test( 'returns the value of state.settings.data', () => {
			const state = {
				settings: {
					data: {
						foo: 'bar',
					},
				},
			};

			expect( getSettings( state ) ).toEqual( { foo: 'bar' } );
		} );

		test.each( [ [ undefined ], [ {} ], [ { settings: {} } ] ] )(
			'returns {} if key is missing (tested state: %j)',
			( state ) => {
				expect( getSettings( state ) ).toEqual( {} );
			}
		);
	} );

	describe( 'isSavingSettings()', () => {
		test( 'returns the value of state.settings.isSaving', () => {
			const state = {
				settings: {
					isSaving: true,
				},
			};

			expect( isSavingSettings( state ) ).toBeTruthy();
		} );

		test.each( [ [ undefined ], [ {} ], [ { settings: {} } ] ] )(
			'returns false if missing (tested state: %j)',
			( state ) => {
				expect( isSavingSettings( state ) ).toBeFalsy();
			}
		);
	} );

	describe( 'getOrderedPaymentMethodIds()', () => {
		test( 'returns the value of state.settings.data', () => {
			const state = {
				settings: {
					data: {
						foo: 'bar',
						ordered_payment_method_ids: [
							'card',
							'giropay',
							'eps',
						],
					},
				},
			};

			expect( getOrderedPaymentMethodIds( state ) ).toEqual( [
				'card',
				'giropay',
				'eps',
			] );
		} );

		test.each( [ [ undefined ], [ {} ], [ { settings: {} } ] ] )(
			'returns {} if key is missing (tested state: %j)',
			( state ) => {
				expect( getOrderedPaymentMethodIds( state ) ).toEqual( {} );
			}
		);
	} );

	describe( 'isSavingOrderedPaymentMethodIds()', () => {
		test( 'returns the value of state.settings.isSavingOrderedPaymentMethodIds', () => {
			const state = {
				settings: {
					isSavingOrderedPaymentMethodIds: true,
				},
			};

			expect( isSavingOrderedPaymentMethodIds( state ) ).toBeTruthy();
		} );

		test.each( [ [ undefined ], [ {} ], [ { settings: {} } ] ] )(
			'returns false if missing (tested state: %j)',
			( state ) => {
				expect( isSavingOrderedPaymentMethodIds( state ) ).toBeFalsy();
			}
		);
	} );
} );
