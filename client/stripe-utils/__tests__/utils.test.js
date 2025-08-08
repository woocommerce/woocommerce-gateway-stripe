import { getFontSizeBase } from '../utils';

describe( 'utils', () => {
	describe( 'getFontSizeBase', () => {
		const globalValues = global.wc_stripe_upe_params;

		beforeEach( () => {
			global.wc_stripe_upe_params = {
				isOCEnabled: false,
			};
		} );

		afterEach( () => {
			global.wc_stripe_upe_params = globalValues;
		} );

		it( 'Optimized Checkout - should increase the provided font size by 2', () => {
			global.wc_stripe_upe_params = { isOCEnabled: true };

			const fontSize = '16px';
			const expectedFontSize = '18px';
			const result = getFontSizeBase( fontSize );
			expect( result ).toBe( expectedFontSize );
		} );

		it( 'Optimized Checkout - should increase the provided font size by 2 (decimal value)', () => {
			global.wc_stripe_upe_params = { isOCEnabled: true };

			const fontSize = '16.5px';
			const expectedFontSize = '18.5px';
			const result = getFontSizeBase( fontSize );
			expect( result ).toBe( expectedFontSize );
		} );

		it( 'default size', () => {
			const fontSize = '16px';
			const expectedFontSize = '16px';
			const result = getFontSizeBase( fontSize );
			expect( result ).toBe( expectedFontSize );
		} );
	} );
} );
