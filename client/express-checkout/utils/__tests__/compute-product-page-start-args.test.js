import { computeProductPageStartArgs } from '../compute-product-page-start-args';

const makeData =
	( {
		product = {
			validVariationSelected: true,
			currency: 'USD',
			total: { amount: 1500 },
			displayItems: [ { label: 'Widget', amount: 1500 } ],
			requestShipping: true,
		},
		checkout = { needs_payer_phone: false },
	} = {} ) =>
	( key ) => {
		if ( key === 'product' ) return product;
		if ( key === 'checkout' ) return checkout;
		return null;
	};

const passthroughTransform = ( items ) =>
	items.map( ( i ) => ( { ...i, t: 1 } ) );

describe( 'computeProductPageStartArgs', () => {
	test( 'returns null when the selected variation is unsupported', async () => {
		const deps = {
			getExpressCheckoutData: makeData( {
				product: { validVariationSelected: false, currency: 'USD' },
			} ),
			resolveExpressCheckoutCurrency: jest.fn(),
			getResolvedCurrency: jest.fn(),
			getSelectedProductData: jest.fn(),
			transformLabeledDisplayItems: passthroughTransform,
			useLegacyDisplayItems: false,
		};

		const result = await computeProductPageStartArgs( deps );

		expect( result ).toBeNull();
		expect( deps.resolveExpressCheckoutCurrency ).not.toHaveBeenCalled();
		expect( deps.getSelectedProductData ).not.toHaveBeenCalled();
	} );

	test( 'fast-path: resolver returns the same currency, no AJAX call', async () => {
		const getSelectedProductData = jest.fn();
		const deps = {
			getExpressCheckoutData: makeData(),
			resolveExpressCheckoutCurrency: jest
				.fn()
				.mockResolvedValue( 'usd' ),
			getResolvedCurrency: jest.fn().mockReturnValue( 'usd' ),
			getSelectedProductData,
			transformLabeledDisplayItems: passthroughTransform,
			useLegacyDisplayItems: false,
		};

		const result = await computeProductPageStartArgs( deps );

		expect( getSelectedProductData ).not.toHaveBeenCalled();
		expect( result ).toEqual( {
			total: 1500,
			currency: 'usd',
			requestShipping: true,
			requestPhone: false,
			displayItems: [ { label: 'Widget', amount: 1500, t: 1 } ],
		} );
	} );

	test( 'currency changed: re-fetches product data and uses fresh values', async () => {
		const fresh = {
			total: { amount: 1300 },
			displayItems: [ { label: 'Widget (EUR)', amount: 1300 } ],
			requestShipping: false,
		};
		const deps = {
			getExpressCheckoutData: makeData(),
			resolveExpressCheckoutCurrency: jest
				.fn()
				.mockResolvedValue( 'eur' ),
			getResolvedCurrency: jest.fn().mockReturnValue( 'eur' ),
			getSelectedProductData: jest.fn().mockResolvedValue( fresh ),
			transformLabeledDisplayItems: passthroughTransform,
			useLegacyDisplayItems: false,
		};

		const result = await computeProductPageStartArgs( deps );

		expect( deps.getSelectedProductData ).toHaveBeenCalledTimes( 1 );
		expect( result ).toEqual( {
			total: 1300,
			currency: 'eur',
			requestShipping: false,
			requestPhone: false,
			displayItems: [ { label: 'Widget (EUR)', amount: 1300, t: 1 } ],
		} );
	} );

	test( 'legacy cart endpoints skip the display-items transform', async () => {
		const deps = {
			getExpressCheckoutData: makeData(),
			resolveExpressCheckoutCurrency: jest
				.fn()
				.mockResolvedValue( 'usd' ),
			getResolvedCurrency: jest.fn().mockReturnValue( 'usd' ),
			getSelectedProductData: jest.fn(),
			transformLabeledDisplayItems: passthroughTransform,
			useLegacyDisplayItems: true,
		};

		const result = await computeProductPageStartArgs( deps );

		expect( result.displayItems ).toEqual( [
			{ label: 'Widget', amount: 1500 },
		] );
	} );

	test( 'AJAX failure on resolved-away path falls back to localized data', async () => {
		const deps = {
			getExpressCheckoutData: makeData(),
			resolveExpressCheckoutCurrency: jest
				.fn()
				.mockResolvedValue( 'eur' ),
			getResolvedCurrency: jest.fn().mockReturnValue( 'eur' ),
			getSelectedProductData: jest
				.fn()
				.mockRejectedValue( new Error( 'network' ) ),
			transformLabeledDisplayItems: passthroughTransform,
			useLegacyDisplayItems: false,
		};

		const result = await computeProductPageStartArgs( deps );

		expect( result.currency ).toBe( 'eur' );
		expect( result.total ).toBe( 1500 );
		expect( result.requestShipping ).toBe( true );
	} );

	test( 'AJAX returning { error } is ignored, localized data is kept', async () => {
		const deps = {
			getExpressCheckoutData: makeData(),
			resolveExpressCheckoutCurrency: jest
				.fn()
				.mockResolvedValue( 'eur' ),
			getResolvedCurrency: jest.fn().mockReturnValue( 'eur' ),
			getSelectedProductData: jest
				.fn()
				.mockResolvedValue( { error: 'nope' } ),
			transformLabeledDisplayItems: passthroughTransform,
			useLegacyDisplayItems: false,
		};

		const result = await computeProductPageStartArgs( deps );

		expect( result.total ).toBe( 1500 );
	} );

	test( 'requestPhone reflects checkout.needs_payer_phone', async () => {
		const deps = {
			getExpressCheckoutData: makeData( {
				checkout: { needs_payer_phone: true },
			} ),
			resolveExpressCheckoutCurrency: jest
				.fn()
				.mockResolvedValue( 'usd' ),
			getResolvedCurrency: jest.fn().mockReturnValue( 'usd' ),
			getSelectedProductData: jest.fn(),
			transformLabeledDisplayItems: passthroughTransform,
			useLegacyDisplayItems: false,
		};

		const result = await computeProductPageStartArgs( deps );

		expect( result.requestPhone ).toBe( true );
	} );
} );
