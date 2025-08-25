import { dispatch, select } from '@wordpress/data';
import { findIndex } from 'lodash';
import { apiFetch } from '@wordpress/data-controls';
import {
	saveSettings,
	updateIsSavingSettings,
	saveOrderedPaymentMethodIds,
	updateIsSavingOrderedPaymentMethodIds,
} from '../actions';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_EPS,
	PAYMENT_METHOD_GIROPAY,
} from 'wcstripe/stripe-utils/constants';

jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/data-controls' );

describe( 'Settings actions tests', () => {
	describe( 'saveSettings()', () => {
		beforeEach( () => {
			const noticesDispatch = {
				createSuccessNotice: jest.fn(),
				createErrorNotice: jest.fn(),
			};

			apiFetch.mockImplementation( () => {} );
			dispatch.mockImplementation( ( storeName ) => {
				if ( storeName === 'core/notices' ) {
					return noticesDispatch;
				}

				return { invalidateResolutionForStoreSelector: () => null };
			} );
			select.mockImplementation( () => ( {
				getSettings: jest.fn(),
			} ) );
		} );

		test( 'makes POST request with settings', () => {
			const settingsMock = {
				enabled_payment_method_ids: [ 'foo', 'bar' ],
			};

			select.mockReturnValue( {
				getSettings: () => settingsMock,
			} );

			apiFetch.mockReturnValue( 'api response' );

			const yielded = [ ...saveSettings() ];

			expect( apiFetch ).toHaveBeenCalledWith( {
				method: 'post',
				path: '/wc/v3/wc_stripe/settings',
				data: settingsMock,
			} );
			expect( yielded ).toContainEqual( 'api response' );
		} );

		test( 'before saving sets isSaving to true, and after - to false', () => {
			apiFetch.mockReturnValue( 'api request' );

			const yielded = [ ...saveSettings() ];

			const apiRequestIndex = yielded.indexOf( 'api request' );

			const isSavingStartIndex = findIndex(
				yielded,
				updateIsSavingSettings( true, null )
			);

			const isSavingEndIndex = findIndex(
				yielded,
				updateIsSavingSettings( false, null )
			);

			expect( apiRequestIndex ).not.toEqual( -1 );
			expect( isSavingStartIndex ).toBeLessThan( apiRequestIndex );
			expect( isSavingEndIndex ).toBeGreaterThan( apiRequestIndex );
		} );

		test( 'displays success notice after saving', () => {
			// eslint-disable-next-line no-unused-expressions
			[ ...saveSettings() ];

			expect(
				dispatch( 'core/notices' ).createSuccessNotice
			).toHaveBeenCalledWith( 'Settings saved.' );
		} );

		test( 'displays error notice if error is thrown', () => {
			const saveGenerator = saveSettings();

			apiFetch.mockImplementation( () => {
				saveGenerator.throw( 'Some error' );
			} );

			// eslint-disable-next-line no-unused-expressions
			[ ...saveGenerator ];

			expect(
				dispatch( 'core/notices' ).createErrorNotice
			).toHaveBeenCalledWith( 'Error saving settings.' );
			expect(
				dispatch( 'core/notices' ).createSuccessNotice
			).not.toHaveBeenCalled();
		} );

		test( 'after throwing error, isSaving is reset', () => {
			const saveGenerator = saveSettings();

			apiFetch.mockImplementation( () => {
				saveGenerator.throw( 'Some error' );
			} );

			const yielded = [ ...saveGenerator ];

			expect(
				dispatch( 'core/notices' ).createErrorNotice
			).toHaveBeenCalled();
			expect( yielded ).toEqual(
				expect.arrayContaining( [
					expect.objectContaining( {
						type: 'SET_IS_SAVING_SETTINGS',
						isSaving: false,
					} ),
				] )
			);
		} );
	} );

	describe( 'saveOrderedPaymentMethodIds()', () => {
		beforeEach( () => {
			const noticesDispatch = {
				createSuccessNotice: jest.fn(),
				createErrorNotice: jest.fn(),
			};

			apiFetch.mockImplementation( () => {} );
			dispatch.mockImplementation( ( storeName ) => {
				if ( storeName === 'core/notices' ) {
					return noticesDispatch;
				}

				return { invalidateResolutionForStoreSelector: () => null };
			} );
			select.mockImplementation( () => ( {
				getOrderedPaymentMethodIds: jest.fn(),
			} ) );
		} );

		test( 'makes POST request with ordered payment method list', () => {
			const orderedPaymentMethodIdsMock = {
				ordered_payment_method_ids: [
					PAYMENT_METHOD_CARD,
					PAYMENT_METHOD_GIROPAY,
					PAYMENT_METHOD_EPS,
				],
			};

			select.mockReturnValue( {
				getOrderedPaymentMethodIds: () => orderedPaymentMethodIdsMock,
			} );

			apiFetch.mockReturnValue( 'api response' );

			const yielded = [ ...saveOrderedPaymentMethodIds() ];

			expect( apiFetch ).toHaveBeenCalledWith( {
				method: 'post',
				path: '/wc/v3/wc_stripe/settings/payment_method_order',
				data: {
					ordered_payment_method_ids: orderedPaymentMethodIdsMock,
				},
			} );
			expect( yielded ).toContainEqual( 'api response' );
		} );

		test( 'before saving sets isSavingOrderedPaymentMethodIds to true, and after - to false', () => {
			apiFetch.mockReturnValue( 'api request' );

			const yielded = [ ...saveOrderedPaymentMethodIds() ];

			const apiRequestIndex = yielded.indexOf( 'api request' );

			const isSavingOrderedPaymentMethodIdsStartIndex = findIndex(
				yielded,
				updateIsSavingOrderedPaymentMethodIds( true )
			);

			const isSavingOrderedPaymentMethodIdsEndIndex = findIndex(
				yielded,
				updateIsSavingOrderedPaymentMethodIds( false )
			);

			expect( apiRequestIndex ).not.toEqual( -1 );
			expect( isSavingOrderedPaymentMethodIdsStartIndex ).toBeLessThan(
				apiRequestIndex
			);
			expect( isSavingOrderedPaymentMethodIdsEndIndex ).toBeGreaterThan(
				apiRequestIndex
			);
		} );

		test( 'displays success notice after saving', () => {
			// eslint-disable-next-line no-unused-expressions
			[ ...saveOrderedPaymentMethodIds() ];

			expect(
				dispatch( 'core/notices' ).createSuccessNotice
			).toHaveBeenCalledWith( 'Saved changed order.' );
		} );

		test( 'displays error notice if error is thrown', () => {
			const saveGenerator = saveOrderedPaymentMethodIds();

			apiFetch.mockImplementation( () => {
				saveGenerator.throw( 'Some error' );
			} );

			// eslint-disable-next-line no-unused-expressions
			[ ...saveGenerator ];

			expect(
				dispatch( 'core/notices' ).createErrorNotice
			).toHaveBeenCalledWith( 'Error saving changed order.' );
			expect(
				dispatch( 'core/notices' ).createSuccessNotice
			).not.toHaveBeenCalled();
		} );

		test( 'after throwing error, isSavingOrderedPaymentMethodIds is reset', () => {
			const saveGenerator = saveOrderedPaymentMethodIds();

			apiFetch.mockImplementation( () => {
				saveGenerator.throw( 'Some error' );
			} );

			const yielded = [ ...saveGenerator ];

			expect(
				dispatch( 'core/notices' ).createErrorNotice
			).toHaveBeenCalled();
			expect( yielded ).toEqual(
				expect.arrayContaining( [
					expect.objectContaining( {
						type: 'SET_IS_SAVING_ORDERED_PAYMENT_METHOD_IDS',
						isSavingOrderedPaymentMethodIds: false,
					} ),
				] )
			);
		} );
	} );
} );
