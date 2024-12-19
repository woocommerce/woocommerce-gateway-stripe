import { dispatch, select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { findIndex } from 'lodash';
import {
	saveSettings,
	updateIsSavingSettings,
	saveOrderedPaymentMethodIds,
	updateIsSavingOrderedPaymentMethodIds,
	saveIndividualPaymentMethodSettings,
	updateIsCustomizingPaymentMethod,
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

	describe( 'saveIndividualPaymentMethodSettings()', () => {
		const paymentMethodSettingsMock = {
			method: 'foo',
			isEnabled: true,
			name: 'bar',
			description: 'baz',
			expiration: 123,
		};

		beforeEach( () => {
			const noticesDispatch = {
				createErrorNotice: jest.fn(),
			};

			apiFetch.mockImplementation( () => {} );
			dispatch.mockImplementation( ( storeName ) => {
				if ( storeName === 'core/notices' ) {
					return noticesDispatch;
				}

				return { invalidateResolutionForStoreSelector: () => null };
			} );
		} );

		test( 'makes POST request with payment method settings data', () => {
			apiFetch.mockReturnValue( 'api response' );

			const yielded = [
				...saveIndividualPaymentMethodSettings(
					paymentMethodSettingsMock
				),
			];

			expect( apiFetch ).toHaveBeenCalledWith( {
				method: 'post',
				path: '/wc/v3/wc_stripe/settings/payment_method',
				data: {
					is_enabled: paymentMethodSettingsMock.isEnabled,
					payment_method_id: paymentMethodSettingsMock.method,
					title: paymentMethodSettingsMock.name,
					description: paymentMethodSettingsMock.description,
					expiration: paymentMethodSettingsMock.expiration,
				},
			} );
			expect( yielded ).toContainEqual( 'api response' );
		} );

		test( 'before saving sets isCustomizingPaymentMethod to true, and after - to false', () => {
			apiFetch.mockReturnValue( 'api request' );

			const yielded = [
				...saveIndividualPaymentMethodSettings(
					paymentMethodSettingsMock
				),
			];

			const apiRequestIndex = yielded.indexOf( 'api request' );

			const isCustomizingStartIndex = findIndex(
				yielded,
				updateIsCustomizingPaymentMethod( true, null )
			);

			const isCustomizingEndIndex = findIndex(
				yielded,
				updateIsCustomizingPaymentMethod( false, null )
			);

			expect( apiRequestIndex ).not.toEqual( -1 );
			expect( isCustomizingStartIndex ).toBeLessThan( apiRequestIndex );
			expect( isCustomizingEndIndex ).toBeGreaterThan( apiRequestIndex );
		} );

		test( 'displays error notice if error is thrown', () => {
			const saveGenerator = saveIndividualPaymentMethodSettings(
				paymentMethodSettingsMock
			);

			apiFetch.mockImplementation( () => {
				saveGenerator.throw( 'Some error' );
			} );

			// eslint-disable-next-line no-unused-expressions
			[ ...saveGenerator ];

			expect(
				dispatch( 'core/notices' ).createErrorNotice
			).toHaveBeenCalledWith( 'Error saving payment method.' );
		} );

		test( 'after throwing error, isCustomizingPaymentMethod is reset', () => {
			const saveGenerator = saveIndividualPaymentMethodSettings(
				paymentMethodSettingsMock
			);

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
						type: 'SET_IS_CUSTOMIZING_PAYMENT_METHOD',
						isCustomizingPaymentMethod: false,
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
