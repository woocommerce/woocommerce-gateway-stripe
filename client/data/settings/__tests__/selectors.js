import {
	getSettings,
	isSavingSettings,
	getIndividualPaymentMethodSettings,
	isCustomizingPaymentMethod,
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

	describe( 'getIndividualPaymentMethodSettings()', () => {
		test( 'returns the value of state.settings.data.individual_payment_method_settings', () => {
			const state = {
				settings: {
					data: {
						foo: 'bar',
						individual_payment_method_settings: {
							eps: {
								title: 'EPS',
								description: 'Pay with EPS',
							},
							giropay: {
								title: 'Giropay',
								description: 'Pay with Giropay',
							},
						},
					},
				},
			};

			expect( getIndividualPaymentMethodSettings( state ) ).toEqual( {
				eps: {
					title: 'EPS',
					description: 'Pay with EPS',
				},
				giropay: {
					title: 'Giropay',
					description: 'Pay with Giropay',
				},
			} );
		} );

		test.each( [ [ undefined ], [ {} ], [ { settings: {} } ] ] )(
			'returns {} if key is missing (tested state: %j)',
			( state ) => {
				expect( getIndividualPaymentMethodSettings( state ) ).toEqual(
					{}
				);
			}
		);
	} );

	describe( 'isCustomizingPaymentMethod()', () => {
		test( 'returns the value of state.settings.isCustomizingPaymentMethod', () => {
			const state = {
				settings: {
					isCustomizingPaymentMethod: true,
				},
			};

			expect( isCustomizingPaymentMethod( state ) ).toBeTruthy();
		} );

		test.each( [ [ undefined ], [ {} ], [ { settings: {} } ] ] )(
			'returns false if missing (tested state: %j)',
			( state ) => {
				expect( isCustomizingPaymentMethod( state ) ).toBeFalsy();
			}
		);
	} );
} );
