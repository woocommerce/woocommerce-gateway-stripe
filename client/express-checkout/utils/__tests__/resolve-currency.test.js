import { resolveExpressCheckoutCurrency } from '../resolve-currency';
import {
	getResolvedCurrency,
	__resetResolvedCurrencyForTests,
} from '../resolved-currency-cache';
import { addFilter, removeAllFilters } from '@wordpress/hooks';

const FILTER = 'wc-stripe.express-checkout.resolved-currency';

describe( 'resolveExpressCheckoutCurrency', () => {
	afterEach( () => {
		removeAllFilters( FILTER );
		__resetResolvedCurrencyForTests();
	} );

	test( 'returns the lowercase fallback when no resolver is registered', async () => {
		const result = await resolveExpressCheckoutCurrency( 'USD', {
			buttonContext: 'product',
		} );

		expect( result ).toBe( 'usd' );
	} );

	test( 'a resolver can override the currency, normalized to lowercase', async () => {
		addFilter( FILTER, 'test/uppercase', () => Promise.resolve( 'CAD' ) );

		const result = await resolveExpressCheckoutCurrency( 'USD', {
			buttonContext: 'product',
		} );

		expect( result ).toBe( 'cad' );
	} );

	test( 'a thrown resolver does not break ECE init', async () => {
		addFilter( FILTER, 'test/throws', () =>
			Promise.reject( new Error( 'boom' ) )
		);

		const result = await resolveExpressCheckoutCurrency( 'USD', {} );

		expect( result ).toBe( 'usd' );
	} );

	test( 'writes the resolved value to the module cache', async () => {
		addFilter( FILTER, 'test/cache', () => Promise.resolve( 'gbp' ) );

		await resolveExpressCheckoutCurrency( 'USD', {} );

		expect( getResolvedCurrency( 'fallback' ) ).toBe( 'gbp' );
	} );

	test( 'resolvers chain, later resolver receives earlier promise', async () => {
		addFilter( FILTER, 'test/first', () => Promise.resolve( 'eur' ) );
		addFilter( FILTER, 'test/second', ( upstream ) =>
			upstream.then( ( c ) => c + '_x' )
		);

		const result = await resolveExpressCheckoutCurrency( 'USD', {} );

		expect( result ).toBe( 'eur_x' );
	} );

	test( 'a resolver returning a non-promise non-string falls through', async () => {
		addFilter( FILTER, 'test/nonsense', () => 42 );

		const result = await resolveExpressCheckoutCurrency( 'USD', {} );

		expect( result ).toBe( 'usd' );
	} );
} );
