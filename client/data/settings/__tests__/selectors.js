/**
 * Internal dependencies
 */
import {
	getSettings,
	// TODO Uncomment code below once settings data API is fully ported.
	// getIsWCPayEnabled,
	// getEnabledPaymentMethodIds,
	// getIsManualCaptureEnabled,
	// getAccountStatementDescriptor,
	isSavingSettings,
	// TODO Uncomment code below once settings data API is fully ported.
	// getPaymentRequestLocations,
	getIsPaymentRequestEnabled,
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

	// TODO Uncomment code below once settings data API is fully ported.
	// describe( 'getIsWCPayEnabled()', () => {
	// 	test( 'returns the value of state.settings.data.is_wcpay_enabled', () => {
	// 		const state = {
	// 			settings: {
	// 				data: {
	// 					is_wcpay_enabled: true,
	// 				},
	// 			},
	// 		};
	//
	// 		expect( getIsWCPayEnabled( state ) ).toBeTruthy();
	// 	} );
	//
	// 	test.each( [
	// 		[ undefined ],
	// 		[ {} ],
	// 		[ { settings: {} } ],
	// 		[ { settings: { data: {} } } ],
	// 	] )( 'returns false if missing (tested state: %j)', ( state ) => {
	// 		expect( getIsWCPayEnabled( state ) ).toBeFalsy();
	// 	} );
	// } );
	//
	// describe( 'getEnabledPaymentMethodIds()', () => {
	// 	test( 'returns the value of state.settings.data.enabled_payment_method_ids', () => {
	// 		const state = {
	// 			settings: {
	// 				data: {
	// 					enabled_payment_method_ids: [ 'foo', 'bar' ],
	// 				},
	// 			},
	// 		};
	//
	// 		expect( getEnabledPaymentMethodIds( state ) ).toEqual( [
	// 			'foo',
	// 			'bar',
	// 		] );
	// 	} );
	//
	// 	test.each( [
	// 		[ undefined ],
	// 		[ {} ],
	// 		[ { settings: {} } ],
	// 		[ { settings: { data: {} } } ],
	// 	] )( 'returns [] if missing (tested state: %j)', ( state ) => {
	// 		expect( getEnabledPaymentMethodIds( state ) ).toEqual( [] );
	// 	} );
	// } );

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

	// TODO Uncomment code below once settings data API is fully ported.
	// describe( 'getIsManualCaptureEnabled()', () => {
	// 	test( 'returns the value of state.settings.data.is_manual_capture_enabled', () => {
	// 		const state = {
	// 			settings: {
	// 				data: {
	// 					is_manual_capture_enabled: true,
	// 				},
	// 			},
	// 		};
	//
	// 		expect( getIsManualCaptureEnabled( state ) ).toBeTruthy();
	// 	} );
	//
	// 	test.each( [
	// 		[ undefined ],
	// 		[ {} ],
	// 		[ { settings: {} } ],
	// 		[ { settings: { data: {} } } ],
	// 	] )( 'returns false if missing (tested state: %j)', ( state ) => {
	// 		expect( getIsManualCaptureEnabled( state ) ).toBeFalsy();
	// 	} );
	// } );
	//
	// describe( 'getAccountStatementDescriptor()', () => {
	// 	test( 'returns the value of state.settings.data.account_statement_descriptor', () => {
	// 		const state = {
	// 			settings: {
	// 				data: {
	// 					account_statement_descriptor: 'my account statement',
	// 				},
	// 			},
	// 		};
	//
	// 		expect( getAccountStatementDescriptor( state ) ).toEqual(
	// 			'my account statement'
	// 		);
	// 	} );
	//
	// 	test.each( [
	// 		[ undefined ],
	// 		[ {} ],
	// 		[ { settings: {} } ],
	// 		[ { settings: { data: {} } } ],
	// 	] )( 'returns false if missing (tested state: %j)', ( state ) => {
	// 		expect( getAccountStatementDescriptor( state ) ).toEqual( '' );
	// 	} );
	// } );

	describe( 'getIsPaymentRequestEnabled()', () => {
		test( 'returns the value of state.settings.data.is_payment_request_enabled', () => {
			const state = {
				settings: {
					data: {
						is_payment_request_enabled: true,
					},
				},
			};

			expect( getIsPaymentRequestEnabled( state ) ).toBeTruthy();
		} );

		test.each( [
			[ undefined ],
			[ {} ],
			[ { settings: {} } ],
			[ { settings: { data: {} } } ],
		] )( 'returns false if missing (tested state: %j)', ( state ) => {
			expect( getIsPaymentRequestEnabled( state ) ).toBeFalsy();
		} );
	} );

	// TODO Uncomment code below once settings data API is fully ported.
	// describe( 'getPaymentRequestLocations()', () => {
	// 	test( 'returns the value of state.settings.data.payment_request_enabled_locations', () => {
	// 		const state = {
	// 			settings: {
	// 				data: {
	// 					payment_request_enabled_locations: [
	// 						'product',
	// 						'cart',
	// 					],
	// 				},
	// 			},
	// 		};
	//
	// 		expect( getPaymentRequestLocations( state ) ).toEqual( [
	// 			'product',
	// 			'cart',
	// 		] );
	// 	} );
	//
	// 	test.each( [
	// 		[ undefined ],
	// 		[ {} ],
	// 		[ { settings: {} } ],
	// 		[ { settings: { data: {} } } ],
	// 	] )( 'returns [] if missing (tested state: %j)', ( state ) => {
	// 		expect( getPaymentRequestLocations( state ) ).toEqual( [] );
	// 	} );
	// } );
} );
