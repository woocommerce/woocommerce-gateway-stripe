import {
	formatStripeAmount,
	getCurrencyExponent,
	getPaymentMethodLabel,
} from '../utils';

describe( 'payment details utils', () => {
	beforeEach( () => {
		global.window.wc_stripe_payment_details_params = {
			locale: 'en-US',
			noDecimalCurrencies: [ 'JPY', 'KRW', 'VND' ],
			threeDecimalCurrencies: [ 'BHD', 'JOD', 'KWD', 'OMR', 'TND' ],
		};
	} );

	afterEach( () => {
		delete global.window.wc_stripe_payment_details_params;
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
			delete global.window.wc_stripe_payment_details_params;

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
			global.window.wc_stripe_payment_details_params.locale = 'de-DE';

			const formatted = formatStripeAmount( 123456, 'eur' ).replace(
				/[\u00a0\u202f]/g,
				' '
			);

			expect( formatted ).toBe( '1.234,56 €' );
		} );
	} );

	describe( 'getPaymentMethodLabel', () => {
		const cardCharge = ( card ) => ( {
			payment_method_details: { type: 'card', card },
		} );

		it( 'renders a card brand with its last four digits', () => {
			expect(
				getPaymentMethodLabel(
					cardCharge( { brand: 'visa', last4: '4242' } )
				)
			).toBe( 'Visa •••• 4242' );
		} );

		it( 'prefers the wallet over the underlying card brand', () => {
			expect(
				getPaymentMethodLabel(
					cardCharge( {
						brand: 'visa',
						last4: '4242',
						wallet: { type: 'apple_pay' },
					} )
				)
			).toBe( 'Apple Pay •••• 4242' );
		} );

		it( 'humanises an unmapped card brand', () => {
			expect(
				getPaymentMethodLabel(
					cardCharge( { brand: 'some_brand', last4: '1111' } )
				)
			).toBe( 'some brand •••• 1111' );
		} );

		it( 'labels a known non-card payment method', () => {
			expect(
				getPaymentMethodLabel( {
					payment_method_details: { type: 'us_bank_account' },
				} )
			).toBe( 'ACH Direct Debit' );
		} );

		it( 'humanises an unmapped payment method type', () => {
			expect(
				getPaymentMethodLabel( {
					payment_method_details: { type: 'brand_new_method' },
				} )
			).toBe( 'brand new method' );
		} );

		it.each( [ [ null ], [ undefined ], [ {} ] ] )(
			'returns an empty string for charge %p',
			( charge ) => {
				expect( getPaymentMethodLabel( charge ) ).toBe( '' );
			}
		);
	} );
} );
