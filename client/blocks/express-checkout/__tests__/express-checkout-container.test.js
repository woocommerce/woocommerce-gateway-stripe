import { render } from '@testing-library/react';
import { ExpressCheckoutContainer } from '../express-checkout-container';
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
	getPaymentMethodTypesForExpressMethod,
	isManualPaymentMethodCreation,
} from 'wcstripe/express-checkout/utils';

// Capture the options prop handed to <Elements> so we can assert when the
// memoised object keeps its reference and when it regenerates.
const capturedOptions = [];
jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: jest.fn( ( { options } ) => {
		capturedOptions.push( options );
		return <div />;
	} ),
} ) );

jest.mock( '../express-checkout-component', () => jest.fn( () => <div /> ) );

jest.mock( 'wcstripe/express-checkout/utils', () => ( {
	getExpressCheckoutButtonAppearance: jest.fn(),
	getExpressCheckoutData: jest.fn(),
	getPaymentMethodTypesForExpressMethod: jest.fn(),
	isManualPaymentMethodCreation: jest.fn(),
} ) );

jest.mock( 'wcstripe/express-checkout/transformers/wc-to-stripe', () => ( {
	transformPriceWithMinorUnits: jest.fn(
		( value, minorUnit ) => value * 10 ** minorUnit
	),
} ) );

const baseProps = ( overrides = {} ) => ( {
	stripe: {},
	expressPaymentMethod: 'applePay',
	billing: {
		cartTotal: { value: 10 },
		currency: { minorUnit: 2, code: 'USD' },
	},
	...overrides,
} );

describe( 'ExpressCheckoutContainer options memoisation', () => {
	beforeEach( () => {
		capturedOptions.length = 0;
		getExpressCheckoutButtonAppearance.mockReturnValue( {} );
		getExpressCheckoutData.mockImplementation( ( key ) =>
			key === 'has_free_trial' ? false : undefined
		);
		getPaymentMethodTypesForExpressMethod.mockReturnValue( [ 'card' ] );
		isManualPaymentMethodCreation.mockReturnValue( false );
	} );

	it( 'keeps the same options reference when unrelated props change', () => {
		const { rerender } = render(
			<ExpressCheckoutContainer { ...baseProps() } />
		);

		// An unrelated cart tick: a new billing object with the same
		// amount/currency must not regenerate the options.
		rerender(
			<ExpressCheckoutContainer
				{ ...baseProps( {
					billing: {
						cartTotal: { value: 10 },
						currency: { minorUnit: 2, code: 'USD' },
					},
				} ) }
			/>
		);

		expect( capturedOptions ).toHaveLength( 2 );
		expect( capturedOptions[ 1 ] ).toBe( capturedOptions[ 0 ] );
	} );

	it.each( [
		[
			'amount',
			{
				billing: {
					cartTotal: { value: 25 },
					currency: { minorUnit: 2, code: 'USD' },
				},
			},
		],
		[
			'currency',
			{
				billing: {
					cartTotal: { value: 10 },
					currency: { minorUnit: 2, code: 'EUR' },
				},
			},
		],
		[ 'expressPaymentMethod', { expressPaymentMethod: 'googlePay' } ],
	] )( 'regenerates options when %s changes', ( _label, overrides ) => {
		const { rerender } = render(
			<ExpressCheckoutContainer { ...baseProps() } />
		);

		rerender( <ExpressCheckoutContainer { ...baseProps( overrides ) } /> );

		expect( capturedOptions ).toHaveLength( 2 );
		expect( capturedOptions[ 1 ] ).not.toBe( capturedOptions[ 0 ] );
	} );

	it( 'regenerates options when hasFreeTrial changes', () => {
		const { rerender } = render(
			<ExpressCheckoutContainer { ...baseProps() } />
		);

		getExpressCheckoutData.mockImplementation( ( key ) =>
			key === 'has_free_trial' ? true : undefined
		);
		rerender( <ExpressCheckoutContainer { ...baseProps() } /> );

		expect( capturedOptions ).toHaveLength( 2 );
		expect( capturedOptions[ 1 ] ).not.toBe( capturedOptions[ 0 ] );
		expect( capturedOptions[ 1 ].mode ).toBe( 'subscription' );
	} );
} );
