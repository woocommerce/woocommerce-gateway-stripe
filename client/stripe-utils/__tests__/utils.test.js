import { getFontSizeBaseForOC } from '../utils';

describe( 'utils', () => {
	describe( 'getFontSizeBaseForOC', () => {
		it( 'should increase the provided font size by 2', () => {
			const fontSize = '16px';
			const expectedFontSize = '18px';
			const result = getFontSizeBaseForOC( fontSize );
			expect( result ).toBe( expectedFontSize );
		} );
	} );
} );
