import { useSelect, useDispatch } from '@wordpress/data';
import { renderHook, act } from '@testing-library/react-hooks';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useSettings,
	usePaymentRequestEnabledSettings,
	usePaymentRequestButtonTheme,
	usePaymentRequestLocations,
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

	describe( 'useEnabledPaymentMethodIds()', () => {
		test( 'returns the value of getSettings().enabled_payment_method_ids', () => {
			selectors = {
				getSettings: jest.fn( () => ( {
					enabled_payment_method_ids: [ 'card' ],
				} ) ),
			};

			const { result } = renderHook( () => useEnabledPaymentMethodIds() );
			const [ value ] = result.current;

			expect( value ).toEqual( [ 'card' ] );
		} );

		test( 'returns an empty array if setting is missing', () => {
			selectors = {
				getSettings: jest.fn( () => ( {} ) ),
			};

			const { result } = renderHook( () => useEnabledPaymentMethodIds() );
			const [ value ] = result.current;

			expect( value ).toEqual( [] );
		} );

		test( 'returns expected action', () => {
			actions = {
				updateSettingsValues: jest.fn(),
			};

			selectors = {
				getSettings: jest.fn( () => ( {} ) ),
			};

			const { result } = renderHook( () => useEnabledPaymentMethodIds() );
			const [ , action ] = result.current;

			act( () => {
				action( [ 'giropay' ] );
			} );

			expect( actions.updateSettingsValues ).toHaveBeenCalledWith( {
				enabled_payment_method_ids: [ 'giropay' ],
			} );
		} );
	} );

	describe( 'useGetAvailablePaymentMethodIds()', () => {
		test( 'returns the value of getSettings().available_payment_method_ids', () => {
			selectors = {
				getSettings: jest.fn( () => ( {
					available_payment_method_ids: [ 'card' ],
				} ) ),
			};

			const { result } = renderHook( () =>
				useGetAvailablePaymentMethodIds()
			);

			expect( result.current ).toEqual( [ 'card' ] );
		} );

		test( 'returns an empty array if setting is missing', () => {
			selectors = {
				getSettings: jest.fn( () => ( {} ) ),
			};

			const { result } = renderHook( () =>
				useGetAvailablePaymentMethodIds()
			);

			expect( result.current ).toEqual( [] );
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

	describe( 'usePaymentRequestLocations()', () => {
		test( 'returns and updates payment request locations', () => {
			const locationsBeforeUpdate = [ 'product' ];
			const locationsAfterUpdate = [ 'checkout', 'cart' ];

			actions = {
				updatePaymentRequestLocations: jest.fn(),
			};

			selectors = {
				getSettings: jest.fn( () => ( {
					payment_request_enabled_locations: locationsBeforeUpdate,
				} ) ),
			};

			const [
				paymentRequestLocations,
				updatePaymentRequestLocations,
			] = usePaymentRequestLocations();

			updatePaymentRequestLocations( locationsAfterUpdate );

			expect( paymentRequestLocations ).toEqual( locationsBeforeUpdate );
			expect(
				actions.updatePaymentRequestLocations
			).toHaveBeenCalledWith( locationsAfterUpdate );
		} );

		test( 'returns [] if setting is missing', () => {
			selectors = {
				getSettings: jest.fn( () => ( {} ) ),
			};

			const [ paymentRequestLocations ] = usePaymentRequestLocations();

			expect( paymentRequestLocations ).toEqual( [] );
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
