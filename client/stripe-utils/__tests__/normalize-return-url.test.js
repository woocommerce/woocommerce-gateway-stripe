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

	it( 'falls back for a cross-origin URL', () => {
		const fallback = `${ ORIGIN }/checkout/order-received/`;
		expect(
			normalizeReturnUrl( 'https://evil.example/steal', fallback )
		).toBe( fallback );
	} );

	it( 'returns null for a cross-origin URL when no fallback is given', () => {
		expect( normalizeReturnUrl( 'https://evil.example/steal' ) ).toBeNull();
	} );

	it( 'falls back for an empty value', () => {
		const fallback = `${ ORIGIN }/checkout/order-received/`;
		expect( normalizeReturnUrl( '', fallback ) ).toBe( fallback );
	} );

	it( 'falls back for a null/undefined value', () => {
		const fallback = `${ ORIGIN }/checkout/order-received/`;
		expect( normalizeReturnUrl( null, fallback ) ).toBe( fallback );
		expect( normalizeReturnUrl( undefined, fallback ) ).toBe( fallback );
	} );

	it( 'defaults the fallback to null', () => {
		expect( normalizeReturnUrl( '' ) ).toBeNull();
	} );
} );
