import { getPaymentGateway, isSavingPaymentGateway } from '../selectors';

describe( 'Payment gateway selectors tests', () => {
	describe( 'getPaymentGateway()', () => {
		test( 'returns the value of state.paymentGateway.data', () => {
			const state = {
				paymentGateway: {
					data: {
						foo: 'bar',
					},
				},
			};

			expect( getPaymentGateway( state ) ).toEqual( { foo: 'bar' } );
		} );

		test.each( [ [ undefined ], [ {} ], [ { paymentGateway: {} } ] ] )(
			'returns {} if key is missing (tested state: %j)',
			( state ) => {
				expect( getPaymentGateway( state ) ).toEqual( {} );
			}
		);
	} );

	describe( 'isSavingPaymentGateway()', () => {
		test( 'returns the value of state.paymentGateway.isSaving', () => {
			const state = {
				paymentGateway: {
					isSaving: true,
				},
			};

			expect( isSavingPaymentGateway( state ) ).toBeTruthy();
		} );

		test.each( [ [ undefined ], [ {} ], [ { paymentGateway: {} } ] ] )(
			'returns false if missing (tested state: %j)',
			( state ) => {
				expect( isSavingPaymentGateway( state ) ).toBeFalsy();
			}
		);
	} );
} );
