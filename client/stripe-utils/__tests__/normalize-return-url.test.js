import { normalizeReturnUrl } from '../normalize-return-url';

// jsdom serves pages from http://localhost by default, so that is the origin
// every relative path resolves against here.
const ORIGIN = 'http://localhost';

describe( 'normalizeReturnUrl', () => {
	it( 'resolves a relative path to an absolute same-origin URL', () => {
		expect(
			normalizeReturnUrl( '/order-received/123/?key=wc_order_abc' )
		).toBe( `${ ORIGIN }/order-received/123/?key=wc_order_abc` );
	} );

	it( 'returns an already-absolute same-origin URL effectively unchanged', () => {
		expect(
			normalizeReturnUrl( `${ ORIGIN }/order-received/123/?key=abc` )
		).toBe( `${ ORIGIN }/order-received/123/?key=abc` );
	} );

	it( 'returns null for a cross-origin URL', () => {
		expect( normalizeReturnUrl( 'https://evil.example/steal' ) ).toBeNull();
	} );

	it( 'returns null for an empty value', () => {
		expect( normalizeReturnUrl( '' ) ).toBeNull();
	} );

	it( 'returns null for a null/undefined value', () => {
		expect( normalizeReturnUrl( null ) ).toBeNull();
		expect( normalizeReturnUrl( undefined ) ).toBeNull();
	} );
} );
