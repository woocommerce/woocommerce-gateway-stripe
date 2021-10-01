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

	const testReadWritePairHook = (
		hookName,
		storeKey,
		testValue,
		cb,
		ifMissingReturn
	) => {
		describe( `${ hookName }`, () => {
			test( `returns the value of getSettings().${ storeKey }`, () => {
				selectors = {
					getSettings: jest.fn( () => ( {
						[ storeKey ]: testValue,
					} ) ),
				};

				const { result } = renderHook( cb );
				const [ value ] = result.current;

				expect( value ).toEqual( testValue );
			} );

			test( `returns ${ JSON.stringify(
				ifMissingReturn
			) } if setting is missing`, () => {
				selectors = {
					getSettings: jest.fn( () => ( {} ) ),
				};

				const { result } = renderHook( cb );
				const [ value ] = result.current;

				expect( value ).toEqual( ifMissingReturn );
			} );

			test( 'returns expected action', () => {
				actions = {
					updateSettingsValues: jest.fn(),
				};

				selectors = {
					getSettings: jest.fn( () => ( {} ) ),
				};

				const { result } = renderHook( cb );
				const [ , action ] = result.current;

				act( () => {
					action( testValue );
				} );

				expect( actions.updateSettingsValues ).toHaveBeenCalledWith( {
					[ storeKey ]: testValue,
				} );
			} );
		} );
	};

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

	testReadWritePairHook(
		'useEnabledPaymentMethodIds()',
		'enabled_payment_method_ids',
		[ 'card' ],
		() => useEnabledPaymentMethodIds(),
		[]
	);

	testReadWritePairHook(
		'usePaymentRequestEnabledSettings()',
		'is_payment_request_enabled',
		true,
		() => usePaymentRequestEnabledSettings(),
		false
	);

	testReadWritePairHook(
		'usePaymentRequestLocations()',
		'payment_request_button_locations',
		[ 'checkout', 'cart' ],
		() => usePaymentRequestLocations(),
		[]
	);

	testReadWritePairHook(
		'usePaymentRequestButtonTheme()',
		'payment_request_button_theme',
		'dark',
		() => usePaymentRequestButtonTheme(),
		''
	);
} );
