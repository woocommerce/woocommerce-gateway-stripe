import { useSelect, useDispatch } from '@wordpress/data';
import { renderHook, act } from '@testing-library/react-hooks';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useSettings,
	useGetOrderedPaymentMethodIds,
	useCustomizePaymentMethodSettings,
	usePaymentRequestEnabledSettings,
	usePaymentRequestButtonTheme,
	usePaymentRequestLocations,
	usePaymentRequestButtonSize,
	usePaymentRequestButtonType,
	useIsStripeEnabled,
	useTestMode,
	useSavedCards,
	useSeparateCardForm,
	useIsShortAccountStatementEnabled,
	useDebugLog,
	useManualCapture,
} from '../hooks';
import { STORE_NAME } from '../../constants';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_EPS,
	PAYMENT_METHOD_GIROPAY,
} from 'wcstripe/stripe-utils/constants';

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

	describe( 'useGetAvailablePaymentMethodIds()', () => {
		test( 'returns the value of getSettings().available_payment_method_ids', () => {
			selectors = {
				getSettings: jest.fn( () => ( {
					available_payment_method_ids: [ PAYMENT_METHOD_CARD ],
				} ) ),
			};

			const { result } = renderHook( () =>
				useGetAvailablePaymentMethodIds()
			);

			expect( result.current ).toEqual( [ PAYMENT_METHOD_CARD ] );
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

	describe( 'useCustomizePaymentMethodSettings()', () => {
		beforeEach( () => {
			actions = {
				saveIndividualPaymentMethodSettings: jest.fn(),
				updateSettingsValues: jest.fn(),
			};

			selectors = {
				getIndividualPaymentMethodSettings: jest.fn( () => ( {
					eps: {
						title: 'EPS',
						description: 'Pay with EPS',
					},
					giropay: {
						title: 'Giropay',
						description: 'Pay with Giropay',
					},
				} ) ),
				isCustomizingPaymentMethod: jest.fn(),
			};
		} );

		test( 'returns individula payment method settings from selector', () => {
			const { result } = renderHook( useCustomizePaymentMethodSettings );
			const {
				individualPaymentMethodSettings,
				customizePaymentMethod,
			} = result.current;

			expect( individualPaymentMethodSettings ).toEqual( {
				eps: {
					title: 'EPS',
					description: 'Pay with EPS',
				},
				giropay: {
					title: 'Giropay',
					description: 'Pay with Giropay',
				},
			} );

			customizePaymentMethod( PAYMENT_METHOD_GIROPAY, true, {
				giropay: {
					name: 'Giropay',
					description: 'Pay with Giropay',
					expiration: '10',
				},
			} );
			expect(
				actions.saveIndividualPaymentMethodSettings
			).toHaveBeenCalledWith( {
				isEnabled: true,
				method: PAYMENT_METHOD_GIROPAY,
				name: 'Giropay',
				description: 'Pay with Giropay',
				expiration: '10',
			} );
		} );
	} );

	describe( 'useGetOrderedPaymentMethodIds()', () => {
		beforeEach( () => {
			actions = {
				updateSettingsValues: jest.fn(),
				saveOrderedPaymentMethodIds: jest.fn(),
			};

			selectors = {
				getSettings: jest.fn( () => ( {
					foo: 'bar',
					ordered_payment_method_ids: [
						PAYMENT_METHOD_CARD,
						PAYMENT_METHOD_EPS,
						PAYMENT_METHOD_GIROPAY,
					],
				} ) ),
				isSavingOrderedPaymentMethodIds: jest.fn(),
			};
		} );

		test( 'returns orderedPaymentMethodIds from selector', () => {
			const { result } = renderHook( useGetOrderedPaymentMethodIds );
			const {
				orderedPaymentMethodIds,
				setOrderedPaymentMethodIds,
			} = result.current;

			expect( orderedPaymentMethodIds ).toEqual( [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_EPS,
				PAYMENT_METHOD_GIROPAY,
			] );

			setOrderedPaymentMethodIds( [
				PAYMENT_METHOD_GIROPAY,
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_EPS,
			] );
			expect( actions.updateSettingsValues ).toHaveBeenCalledWith( {
				ordered_payment_method_ids: [
					PAYMENT_METHOD_GIROPAY,
					PAYMENT_METHOD_CARD,
					PAYMENT_METHOD_EPS,
				],
			} );
		} );
	} );

	const generatedHookExpectations = {
		useEnabledPaymentMethodIds: {
			hook: useEnabledPaymentMethodIds,
			storeKey: 'enabled_payment_method_ids',
			testedValue: [ 'foo', 'bar' ],
			fallbackValue: [],
		},
		usePaymentRequestEnabledSettings: {
			hook: usePaymentRequestEnabledSettings,
			storeKey: 'is_payment_request_enabled',
			testedValue: true,
			fallbackValue: false,
		},
		usePaymentRequestButtonSize: {
			hook: usePaymentRequestButtonSize,
			storeKey: 'payment_request_button_size',
			testedValue: 'large',
			fallbackValue: '',
		},
		usePaymentRequestButtonType: {
			hook: usePaymentRequestButtonType,
			storeKey: 'payment_request_button_type',
			testedValue: '',
			fallbackValue: '',
		},
		usePaymentRequestButtonTheme: {
			hook: usePaymentRequestButtonTheme,
			storeKey: 'payment_request_button_theme',
			testedValue: 'dark',
			fallbackValue: '',
		},
		useIsStripeEnabled: {
			hook: useIsStripeEnabled,
			storeKey: 'is_stripe_enabled',
			testedValue: true,
			fallbackValue: false,
		},
		useTestMode: {
			hook: useTestMode,
			storeKey: 'is_test_mode_enabled',
			testedValue: true,
			fallbackValue: false,
		},
		useSavedCards: {
			hook: useSavedCards,
			storeKey: 'is_saved_cards_enabled',
			testedValue: true,
			fallbackValue: false,
		},
		useManualCapture: {
			hook: useManualCapture,
			storeKey: 'is_manual_capture_enabled',
			testedValue: true,
			fallbackValue: false,
		},
		useSeparateCardForm: {
			hook: useSeparateCardForm,
			storeKey: 'is_separate_card_form_enabled',
			testedValue: true,
			fallbackValue: false,
		},
		useIsShortAccountStatementEnabled: {
			hook: useIsShortAccountStatementEnabled,
			storeKey: 'is_short_statement_descriptor_enabled',
			testedValue: true,
			fallbackValue: false,
		},
		useDebugLog: {
			hook: useDebugLog,
			storeKey: 'is_debug_log_enabled',
			testedValue: true,
			fallbackValue: false,
		},
		usePaymentRequestLocations: {
			hook: usePaymentRequestLocations,
			storeKey: 'payment_request_button_locations',
			testedValue: [ 'checkout', 'cart' ],
			fallbackValue: [],
		},
	};

	describe.each( Object.entries( generatedHookExpectations ) )(
		'%s()',
		( hookName, { hook, storeKey, testedValue, fallbackValue } ) => {
			test( `returns the value of getSettings().${ storeKey }`, () => {
				selectors = {
					getSettings: jest.fn( () => ( {
						[ storeKey ]: testedValue,
					} ) ),
				};

				const { result } = renderHook( hook );
				const [ value ] = result.current;

				expect( value ).toEqual( testedValue );
			} );

			test( `returns ${ JSON.stringify(
				fallbackValue
			) } if setting is missing`, () => {
				selectors = {
					getSettings: jest.fn( () => ( {} ) ),
				};

				const { result } = renderHook( hook );
				const [ value ] = result.current;

				expect( value ).toEqual( fallbackValue );
			} );

			test( 'calls updateSettingsValues() on expected field with provided value', () => {
				actions = {
					updateSettingsValues: jest.fn(),
				};

				selectors = {
					getSettings: jest.fn( () => ( {} ) ),
				};

				const { result } = renderHook( hook );
				const [ , action ] = result.current;

				act( () => {
					action( testedValue );
				} );

				expect( actions.updateSettingsValues ).toHaveBeenCalledWith( {
					[ storeKey ]: testedValue,
				} );
			} );
		}
	);
} );
