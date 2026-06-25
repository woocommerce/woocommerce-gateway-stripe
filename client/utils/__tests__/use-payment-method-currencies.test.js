// `accountCountry` is captured at module load from the localized params, so each case re-imports
// the module with the global set via `jest.isolateModules`.
const withAccountCountry = ( country, run ) => {
	const previous = global.wc_stripe_settings_params;
	global.wc_stripe_settings_params = country
		? { account_country: country }
		: {};
	try {
		jest.isolateModules( () => {
			const {
				isAmazonPayAccountCountrySupported,
			} = require( '../use-payment-method-currencies' );
			run( isAmazonPayAccountCountrySupported );
		} );
	} finally {
		global.wc_stripe_settings_params = previous;
	}
};

describe( 'isAmazonPayAccountCountrySupported', () => {
	it( 'returns true for a supported account country', () => {
		withAccountCountry( 'GB', ( isSupported ) =>
			expect( isSupported() ).toBe( true )
		);
	} );

	it( 'returns false for an unsupported account country', () => {
		withAccountCountry( 'BR', ( isSupported ) =>
			expect( isSupported() ).toBe( false )
		);
	} );

	it( 'defaults to US (supported) when no account country is localized', () => {
		withAccountCountry( null, ( isSupported ) =>
			expect( isSupported() ).toBe( true )
		);
	} );
} );
