/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { renderHook, act } from '@testing-library/react-hooks';

/**
 * Internal dependencies
 */
import {
	useSettings,
	usePaymentRequestEnabledSettings,
	usePaymentRequestButtonTheme,
} from '../hooks';
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
				isSavingSettings: jest.fn(),
			};
		} );

		test( 'returns settings from selector', () => {
			const { settings, saveSettings } = useSettings();
			saveSettings( 'bar' );

			expect( settings ).toEqual( { foo: 'bar' } );
			expect( actions.saveSettings ).toHaveBeenCalledWith( 'bar' );
		} );

		test( 'returns isLoading = true when hasFinishedResolution = false', () => {
			selectors.hasFinishedResolution.mockReturnValue( false );

			const { isLoading } = useSettings();

			expect( isLoading ).toBeTruthy();
		} );

		test( 'returns isLoading = false when hasFinishedResolution = true', () => {
			selectors.hasFinishedResolution.mockReturnValue( true );

			const { isLoading } = useSettings();

			expect( isLoading ).toBeFalsy();
		} );
	} );

	describe( 'usePaymentRequestEnabledSettings()', () => {
		test( 'returns the value of getSettings().is_payment_request_enabled', () => {
			selectors = {
				getSettings: jest.fn( () => ( {
					is_payment_request_enabled: true,
				} ) ),
			};

			const [
				isPaymentRequestEnabled,
			] = usePaymentRequestEnabledSettings();

			expect( isPaymentRequestEnabled ).toEqual( true );
		} );

		test( 'returns false if setting is missing', () => {
			selectors = {
				getSettings: jest.fn( () => ( {} ) ),
			};

			const [
				isPaymentRequestEnabled,
			] = usePaymentRequestEnabledSettings();

			expect( isPaymentRequestEnabled ).toBeFalsy();
		} );

		test( 'returns expected action', () => {
			actions = {
				updateIsPaymentRequestEnabled: jest.fn(),
			};

			selectors = {
				getSettings: jest.fn( () => ( {} ) ),
			};

			const [ , action ] = usePaymentRequestEnabledSettings();
			action( true );

			expect(
				actions.updateIsPaymentRequestEnabled
			).toHaveBeenCalledWith( true );
		} );
	} );

	describe( 'usePaymentRequestButtonTheme()', () => {
		test( 'returns the value of getSettings().payment_request_button_theme', () => {
			selectors = {
				getSettings: jest.fn( () => ( {
					payment_request_button_theme: 'dark',
				} ) ),
			};

			const { result } = renderHook( () =>
				usePaymentRequestButtonTheme()
			);
			const [ value ] = result.current;

			expect( value ).toEqual( 'dark' );
		} );

		test( 'returns an empty string if setting is missing', () => {
			selectors = {
				getSettings: jest.fn( () => ( {} ) ),
			};

			const { result } = renderHook( () =>
				usePaymentRequestButtonTheme()
			);
			const [ value ] = result.current;

			expect( value ).toEqual( '' );
		} );

		test( 'allows to update the store', () => {
			const updateSettingsValuesMock = jest.fn();
			actions = {
				updateSettingsValues: updateSettingsValuesMock,
			};

			selectors = {
				getSettings: jest.fn( () => ( {} ) ),
			};

			const { result } = renderHook( () =>
				usePaymentRequestButtonTheme()
			);
			const [ , handler ] = result.current;

			act( () => {
				handler( 'dark' );
			} );

			expect( updateSettingsValuesMock ).toHaveBeenCalledWith( {
				payment_request_button_theme: 'dark',
			} );
		} );
	} );
} );
