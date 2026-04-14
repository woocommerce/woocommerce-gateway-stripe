import { isEeaCountry } from '../eea-countries';

describe( 'isEeaCountry', () => {
	it( 'returns true for EEA country codes', () => {
		expect( isEeaCountry( 'DE' ) ).toBe( true );
		expect( isEeaCountry( 'FR' ) ).toBe( true );
		expect( isEeaCountry( 'NL' ) ).toBe( true );
		expect( isEeaCountry( 'IS' ) ).toBe( true ); // Iceland
		expect( isEeaCountry( 'LI' ) ).toBe( true ); // Liechtenstein
		expect( isEeaCountry( 'NO' ) ).toBe( true ); // Norway
	} );

	it( 'returns false for non-EEA country codes', () => {
		expect( isEeaCountry( 'US' ) ).toBe( false );
		expect( isEeaCountry( 'CA' ) ).toBe( false );
		expect( isEeaCountry( 'GB' ) ).toBe( false );
		expect( isEeaCountry( 'AU' ) ).toBe( false );
	} );

	it( 'is case-insensitive', () => {
		expect( isEeaCountry( 'de' ) ).toBe( true );
		expect( isEeaCountry( 'fr' ) ).toBe( true );
		expect( isEeaCountry( 'us' ) ).toBe( false );
	} );

	it( 'returns false for empty or falsy values', () => {
		expect( isEeaCountry( '' ) ).toBe( false );
		expect( isEeaCountry( null ) ).toBe( false );
		expect( isEeaCountry( undefined ) ).toBe( false );
	} );
} );
