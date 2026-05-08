import {
	setElementCurrency,
	getElementCurrency,
	__resetElementCurrencyForTests,
} from '../element-currency-cache';

describe( 'element-currency-cache', () => {
	afterEach( () => {
		__resetElementCurrencyForTests();
	} );

	test( 'returns null when nothing has been set', () => {
		expect( getElementCurrency() ).toBeNull();
	} );

	test( 'returns the set value when one has been written', () => {
		setElementCurrency( 'usd' );

		expect( getElementCurrency() ).toBe( 'usd' );
	} );

	test( 'a falsy write reverts to null', () => {
		setElementCurrency( 'usd' );
		setElementCurrency( null );

		expect( getElementCurrency() ).toBeNull();
	} );

	test( 'an empty-string write reverts to null', () => {
		setElementCurrency( 'usd' );
		setElementCurrency( '' );

		expect( getElementCurrency() ).toBeNull();
	} );
} );
