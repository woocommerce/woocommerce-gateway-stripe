/*
 * Custom jsdom test environment that overrides jsdom to make `window.location` mutable.
 */
const JSDOMEnvironment = require( 'jest-environment-jsdom' ).default;

// Extract internal jsdom helper. The path works for jsdom 26, but may break in future.
// If the helper is moved, or the API changes, tests that mutate window.location will break.
let idlUtils;
try {
	// eslint-disable-next-line import/no-unresolved
	idlUtils = require( 'jsdom/lib/jsdom/living/generated/utils.js' );
} catch ( e ) {
	idlUtils = null;
}

const DEFAULT_HREF = 'http://localhost/';

function createLocationMock( href = DEFAULT_HREF ) {
	let url;
	try {
		url = new URL( href );
	} catch ( e ) {
		url = new URL( DEFAULT_HREF );
	}

	return {
		href: url.href,
		protocol: url.protocol,
		host: url.host,
		hostname: url.hostname,
		port: url.port,
		origin: url.origin,
		pathname: url.pathname,
		search: url.search,
		hash: url.hash,
		assign() {},
		reload() {},
		replace() {},
		toString() {
			return this.href;
		},
	};
}

class StripeJSDOMEnvironment extends JSDOMEnvironment {
	constructor( ...args ) {
		super( ...args );
		this.installMutableLocation();
	}

	installMutableLocation() {
		if ( ! idlUtils ) {
			return;
		}

		const documentImpl = idlUtils.implForWrapper( this.global.document );
		const locationImpl = documentImpl && documentImpl._location;
		if ( ! locationImpl ) {
			return;
		}

		const mock = createLocationMock( this.global.location.href );
		locationImpl[ idlUtils.wrapperSymbol ] = mock;

		// Per-test reset hook, invoked from tests/js/jest-setup.js `beforeEach`,
		// so stubbed members never leak between tests.
		this.global.__resetWindowLocation = () => {
			Object.keys( mock ).forEach( ( key ) => {
				delete mock[ key ];
			} );
			Object.assign( mock, createLocationMock() );
		};

		this.global.__mockWindowLocation = ( mockWindowProperties ) => {
			Object.keys( mock ).forEach( ( key ) => {
				delete mock[ key ];
			} );
			Object.assign( mock, {
				...createLocationMock(),
				...mockWindowProperties,
			} );
		};
	}
}

module.exports = StripeJSDOMEnvironment;
