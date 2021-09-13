/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useSettings, usePaymentRequestEnabledSettings } from '../hooks';
import { STORE_NAME } from '../../constants';

jest.mock( '@wordpress/data' );

describe( 'Settings hooks tests', () => {
	let actions;
	let selectors;

	beforeEach( () => {
		actions = {};
		selectors = {};

		const selectMock = jest.fn( ( storeName ) => {
			return STORE_NAME === storeName ? selectors : {};
		} );
		useDispatch.mockImplementation( ( storeName ) => {
			return STORE_NAME === storeName ? actions : {};
		} );
		useSelect.mockImplementation( ( cb ) => {
			return cb( selectMock );
		} );
	} );

	describe( 'useSettings()', () => {
		beforeEach( () => {
			actions = {
				saveSettings: jest.fn(),
			};

			selectors = {
				getSettings: jest.fn( () => ( { foo: 'bar' } ) ),
				hasFinishedResolution: jest.fn(),
				isResolving: jest.fn(),
				isSavingSettings: jest.fn(),
			};
		} );

		test( 'returns settings from selector', () => {
			const { settings, saveSettings } = useSettings();
			saveSettings( 'bar' );

			expect( settings ).toEqual( { foo: 'bar' } );
			expect( actions.saveSettings ).toHaveBeenCalledWith( 'bar' );
		} );

		test( 'returns isLoading = false when isResolving = false and hasFinishedResolution = true', () => {
			selectors.hasFinishedResolution.mockReturnValue( true );
			selectors.isResolving.mockReturnValue( false );

			const { isLoading } = useSettings();

			expect( isLoading ).toBeFalsy();
		} );

		test.each( [
			[ false, false ],
			[ true, false ],
			[ true, true ],
		] )(
			'returns isLoading = true when isResolving = %s and hasFinishedResolution = %s',
			( isResolving, hasFinishedResolution ) => {
				selectors.hasFinishedResolution.mockReturnValue(
					hasFinishedResolution
				);
				selectors.isResolving.mockReturnValue( isResolving );

				const { isLoading } = useSettings();

				expect( isLoading ).toBeTruthy();
			}
		);
	} );

	describe( 'usePaymentRequestEnabledSettings()', () => {
		test( 'returns the value of getSettings().is_payment_request_enabled', () => {
			selectors = {
				getSettings: jest.fn( () => ( {
					is_payment_request_enabled: true,
				} ) )
			};

			const [ isPaymentRequestEnabled ] = usePaymentRequestEnabledSettings();

			expect( isPaymentRequestEnabled ).toEqual( true );
		} );

		test( 'returns false if setting is missing', () => {
			selectors = {
				getSettings: jest.fn( () => ( {} ) )
			};

			const [ isPaymentRequestEnabled ] = usePaymentRequestEnabledSettings();

			expect( isPaymentRequestEnabled ).toBeFalsy();
		} );

		test( 'returns expected action', () => {
			actions = {
				updateIsPaymentRequestEnabled: jest.fn(),
			};

			selectors = {
				getSettings: jest.fn( () => ( {} ) )
			};

			const [ , action ] = usePaymentRequestEnabledSettings();
			action( true );

			expect(
				actions.updateIsPaymentRequestEnabled
			).toHaveBeenCalledWith( true );
		} );
	} );
} );
