import { dispatch, select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { findIndex } from 'lodash';
import { savePaymentGateway, updateIsSavingPaymentGateway } from '../actions';

jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/data-controls' );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( { section: 'stripe_alipay' } ),
} ) );

describe( 'Payment gateway actions tests', () => {
	describe( 'savePaymentGateway()', () => {
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
				getPaymentGateway: jest.fn(),
			} ) );
		} );

		test( 'makes POST request with payment gateway', () => {
			const alipaySettingsMock = {
				is_stripe_alipay_enabled: true,
			};

			select.mockReturnValue( {
				getPaymentGateway: () => alipaySettingsMock,
			} );

			apiFetch.mockReturnValue( 'api response' );

			const yielded = [ ...savePaymentGateway() ];

			expect( apiFetch ).toHaveBeenCalledWith( {
				method: 'post',
				path: '/wc/v3/wc_stripe/payment-gateway/stripe_alipay',
				data: alipaySettingsMock,
			} );
			expect( yielded ).toContainEqual( 'api response' );
		} );

		test( 'before saving sets isSaving to true, and after - to false', () => {
			apiFetch.mockReturnValue( 'api request' );

			const yielded = [ ...savePaymentGateway() ];

			const apiRequestIndex = yielded.indexOf( 'api request' );

			const isSavingStartIndex = findIndex(
				yielded,
				updateIsSavingPaymentGateway( true, null )
			);

			const isSavingEndIndex = findIndex(
				yielded,
				updateIsSavingPaymentGateway( false, null )
			);

			expect( apiRequestIndex ).not.toEqual( -1 );
			expect( isSavingStartIndex ).toBeLessThan( apiRequestIndex );
			expect( isSavingEndIndex ).toBeGreaterThan( apiRequestIndex );
		} );

		test( 'displays success notice after saving', () => {
			// eslint-disable-next-line no-unused-expressions
			[ ...savePaymentGateway() ];

			expect(
				dispatch( 'core/notices' ).createSuccessNotice
			).toHaveBeenCalledWith( 'Settings saved.' );
		} );

		test( 'displays error notice if error is thrown', () => {
			const saveGenerator = savePaymentGateway();

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
			const saveGenerator = savePaymentGateway();

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
						type: 'SET_IS_SAVING_PAYMENT_GATEWAY',
						isSaving: false,
					} ),
				] )
			);
		} );
	} );
} );
