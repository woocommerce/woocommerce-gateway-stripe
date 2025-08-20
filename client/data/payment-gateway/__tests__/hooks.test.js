import { useSelect, useDispatch } from '@wordpress/data';
import { renderHook, act } from '@testing-library/react-hooks';
import {
	usePaymentGateway,
	useEnabledPaymentGateway,
	usePaymentGatewayName,
	usePaymentGatewayDescription,
} from '../hooks';
import { STORE_NAME } from '../../constants';

jest.mock( '@wordpress/data' );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( { section: 'stripe_alipay' } ),
} ) );

describe( 'Payment gateway hooks tests', () => {
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

	describe( 'usePaymentGateway()', () => {
		beforeEach( () => {
			actions = {
				savePaymentGateway: jest.fn(),
			};

			selectors = {
				getPaymentGateway: jest.fn( () => ( { foo: 'bar' } ) ),
				hasFinishedResolution: jest.fn(),
				isResolving: jest.fn(),
				isSavingPaymentGateway: jest.fn(),
			};
		} );

		test( 'returns payment gateway from selector', () => {
			const { paymentGateway, savePaymentGateway } = usePaymentGateway();
			savePaymentGateway( 'bar' );

			expect( paymentGateway ).toEqual( { foo: 'bar' } );
			expect( actions.savePaymentGateway ).toHaveBeenCalledWith( 'bar' );
		} );

		test( 'returns isLoading = false when isResolving = false and hasFinishedResolution = true', () => {
			selectors.hasFinishedResolution.mockReturnValue( true );
			selectors.isResolving.mockReturnValue( false );

			const { isLoading } = usePaymentGateway();

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

				const { isLoading } = usePaymentGateway();

				expect( isLoading ).toBeTruthy();
			}
		);
	} );

	const generatedHookExpectations = {
		useEnabledPaymentGateway: {
			hook: useEnabledPaymentGateway,
			storeKey: 'is_stripe_alipay_enabled',
			testedValue: true,
			fallbackValue: false,
		},
		usePaymentGatewayName: {
			hook: usePaymentGatewayName,
			storeKey: 'stripe_alipay_name',
			testedValue: 'foo',
			fallbackValue: '',
		},
		usePaymentGatewayDescription: {
			hook: usePaymentGatewayDescription,
			storeKey: 'stripe_alipay_description',
			testedValue: 'bar',
			fallbackValue: '',
		},
	};

	describe.each( Object.entries( generatedHookExpectations ) )(
		'%s()',
		( hookName, { hook, storeKey, testedValue, fallbackValue } ) => {
			test( `returns the value of getPaymentGateway().${ storeKey }`, () => {
				selectors = {
					getPaymentGateway: jest.fn( () => ( {
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
					getPaymentGateway: jest.fn( () => ( {} ) ),
				};

				const { result } = renderHook( hook );
				const [ value ] = result.current;

				expect( value ).toEqual( fallbackValue );
			} );

			test( 'calls updatePaymentGatewayValues() on expected field with provided value', () => {
				actions = {
					updatePaymentGatewayValues: jest.fn(),
				};

				selectors = {
					getPaymentGateway: jest.fn( () => ( {} ) ),
				};

				const { result } = renderHook( hook );
				const [ , action ] = result.current;

				act( () => {
					action( testedValue );
				} );

				expect(
					actions.updatePaymentGatewayValues
				).toHaveBeenCalledWith( {
					[ storeKey ]: testedValue,
				} );
			} );
		}
	);
} );
