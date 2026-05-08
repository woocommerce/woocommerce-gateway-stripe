import {
	setResolvedCurrency,
	getResolvedCurrency,
	__resetResolvedCurrencyForTests,
} from '../resolved-currency-cache';

describe( 'resolved-currency-cache', () => {
	afterEach( () => {
		__resetResolvedCurrencyForTests();
	} );

	test( 'returns the fallback when nothing has been set', () => {
		expect( getResolvedCurrency( 'usd' ) ).toBe( 'usd' );
	} );

	test( 'returns the set value when one has been written', () => {
		setResolvedCurrency( 'eur' );

		expect( getResolvedCurrency( 'usd' ) ).toBe( 'eur' );
	} );

	test( 'a falsy write reverts to the fallback', () => {
		setResolvedCurrency( 'eur' );
		setResolvedCurrency( null );

		expect( getResolvedCurrency( 'usd' ) ).toBe( 'usd' );
	} );

	test( 'an empty-string write reverts to the fallback', () => {
		setResolvedCurrency( 'eur' );
		setResolvedCurrency( '' );

		expect( getResolvedCurrency( 'usd' ) ).toBe( 'usd' );
	} );
} );
