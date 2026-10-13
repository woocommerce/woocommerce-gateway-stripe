import { formatStripeAmount, getCurrencyExponent } from '../utils';

describe( 'payment details utils', () => {
	beforeEach( () => {
		global.window.wc_stripe_admin_payments_params = {
			locale: 'en-US',
			noDecimalCurrencies: [ 'JPY', 'KRW', 'VND' ],
			threeDecimalCurrencies: [ 'BHD', 'JOD', 'KWD', 'OMR', 'TND' ],
		};
	} );

	afterEach( () => {
		delete global.window.wc_stripe_admin_payments_params;
	} );

	describe( 'getCurrencyExponent', () => {
		it.each( [
			[ 'usd', 2 ],
			[ 'USD', 2 ],
			[ 'jpy', 0 ],
			[ 'KRW', 0 ],
			[ 'kwd', 3 ],
			[ 'BHD', 3 ],
			[ 'zzz', 2 ],
			[ undefined, 2 ],
		] )( 'returns %s -> %i', ( currency, expected ) => {
			expect( getCurrencyExponent( currency ) ).toBe( expected );
		} );

		it( 'defaults to two decimals when the params are absent', () => {
			delete global.window.wc_stripe_admin_payments_params;

			expect( getCurrencyExponent( 'jpy' ) ).toBe( 2 );
		} );
	} );

	describe( 'formatStripeAmount', () => {
		it.each( [
			[ 3789, 'usd', '$37.89' ],
			[ 0, 'usd', '$0.00' ],
			[ -1250, 'usd', '-$12.50' ],
			[ 5000, 'jpy', '¥5,000' ],
			[ 12500, 'kwd', 'KWD 12.500' ],
		] )( 'formats %i %s as %s', ( amount, currency, expected ) => {
			// Intl uses a narrow no-break space in some currency outputs.
			expect(
				formatStripeAmount( amount, currency ).replace(
					/[\u00a0\u202f]/g,
					' '
				)
			).toBe( expected );
		} );

		it.each( [
			[ undefined, 'usd' ],
			[ null, 'usd' ],
			[ 'abc', 'usd' ],
			[ NaN, 'usd' ],
			[ 1000, undefined ],
			[ 1000, '' ],
		] )(
			'returns an empty string for amount %p and currency %p',
			( amount, currency ) => {
				expect( formatStripeAmount( amount, currency ) ).toBe( '' );
			}
		);

		it( 'falls back to a plain rendering for an unrecognised currency code', () => {
			expect( formatStripeAmount( 1000, 'zz' ) ).toBe( '10.00 ZZ' );
		} );

		it( 'respects the locale supplied by PHP', () => {
			global.window.wc_stripe_admin_payments_params.locale = 'de-DE';

			const formatted = formatStripeAmount( 123456, 'eur' ).replace(
				/[\u00a0\u202f]/g,
				' '
			);

			expect( formatted ).toBe( '1.234,56 €' );
		} );
	} );
} );
