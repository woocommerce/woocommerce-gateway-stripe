import {
	STRIPE_JS_ORIGIN,
	verifyStripeJsOrigin,
	assertStripeJsOrigin,
} from '../verify-stripe-js-origin';

/**
 * Creates a detached document containing the given script tags, in order.
 *
 * @param {...Object} tags       The script tags to add. Pass none to create an empty document.
 * @param {string}    [tags.id]  The id attribute for a tag.
 * @param {string}    [tags.src] The src attribute for a tag.
 * @return {Document} The created document.
 */
const createDocumentWithScript = ( ...tags ) => {
	const doc = document.implementation.createHTMLDocument( 'test' );
	tags.forEach( ( tag ) => {
		const script = doc.createElement( 'script' );
		if ( tag.id ) {
			script.id = tag.id;
		}
		if ( tag.src ) {
			script.setAttribute( 'src', tag.src );
		}
		doc.body.appendChild( script );
	} );
	return doc;
};

describe( 'verifyStripeJsOrigin', () => {
	it( 'accepts the official origin with the /v3/ path', () => {
		const doc = createDocumentWithScript( {
			id: 'stripe-js',
			src: 'https://js.stripe.com/v3/',
		} );

		expect( verifyStripeJsOrigin( doc ) ).toEqual( {
			ok: true,
			detectedSrc: 'https://js.stripe.com/v3/',
			detectedOrigin: STRIPE_JS_ORIGIN,
		} );
	} );

	it( 'accepts the official origin with a release train path', () => {
		const doc = createDocumentWithScript( {
			id: 'stripe-js',
			src: 'https://js.stripe.com/clover/stripe.js',
		} );

		expect( verifyStripeJsOrigin( doc ) ).toMatchObject( {
			ok: true,
			detectedOrigin: STRIPE_JS_ORIGIN,
		} );
	} );

	it( 'accepts a tag found via the src fallback when the id is missing', () => {
		const doc = createDocumentWithScript( {
			src: 'https://js.stripe.com/v3/stripe.js',
		} );

		expect( verifyStripeJsOrigin( doc ) ).toMatchObject( {
			ok: true,
			detectedOrigin: STRIPE_JS_ORIGIN,
		} );
	} );

	it( 'rejects a look-alike origin', () => {
		const doc = createDocumentWithScript( {
			id: 'stripe-js',
			src: 'https://js-stripe.example/v3/',
		} );

		expect( verifyStripeJsOrigin( doc ) ).toEqual( {
			ok: false,
			detectedSrc: 'https://js-stripe.example/v3/',
			detectedOrigin: 'https://js-stripe.example',
		} );
	} );

	it( 'rejects a subdomain-suffix look-alike origin', () => {
		const doc = createDocumentWithScript( {
			id: 'stripe-js',
			src: 'https://js.stripe.com.evil.example/v3/',
		} );

		expect( verifyStripeJsOrigin( doc ) ).toMatchObject( {
			ok: false,
			detectedOrigin: 'https://js.stripe.com.evil.example',
		} );
	} );

	it( 'rejects a tampered #stripe-js tag even when a legitimate Stripe script appears earlier in the DOM', () => {
		const doc = createDocumentWithScript(
			{ src: 'https://js.stripe.com/v3/extras.js' },
			{
				id: 'stripe-js',
				src: 'https://js.stripe.com.evil.example/clover/stripe.js',
			}
		);

		expect( verifyStripeJsOrigin( doc ) ).toMatchObject( {
			ok: false,
			detectedOrigin: 'https://js.stripe.com.evil.example',
		} );
	} );

	it( 'keeps the script handle authoritative when a non-script #stripe-js precedes it', () => {
		// A non-script id collision injected before the real handle must not
		// divert to the fallback (where a benign official-origin script could
		// mask a repointed handle); the real script#stripe-js stays authoritative.
		const doc = document.implementation.createHTMLDocument( 'test' );
		const collision = doc.createElement( 'div' );
		collision.id = 'stripe-js';
		doc.body.appendChild( collision );
		const benign = doc.createElement( 'script' );
		benign.setAttribute( 'src', 'https://js.stripe.com/v3/' );
		doc.body.appendChild( benign );
		const handle = doc.createElement( 'script' );
		handle.id = 'stripe-js';
		handle.setAttribute( 'src', 'https://js.stripe.com.evil.example/v3/' );
		doc.body.appendChild( handle );

		expect( verifyStripeJsOrigin( doc ) ).toMatchObject( {
			ok: false,
			detectedOrigin: 'https://js.stripe.com.evil.example',
		} );
	} );

	it( 'ignores a non-script element sharing the stripe-js id and falls back to a legitimate script', () => {
		const doc = document.implementation.createHTMLDocument( 'test' );
		const collision = doc.createElement( 'div' );
		collision.id = 'stripe-js';
		doc.body.appendChild( collision );
		const script = doc.createElement( 'script' );
		script.setAttribute( 'src', 'https://js.stripe.com/v3/' );
		doc.body.appendChild( script );

		expect( verifyStripeJsOrigin( doc ) ).toMatchObject( {
			ok: true,
			detectedOrigin: STRIPE_JS_ORIGIN,
		} );
	} );

	it( 'fails closed when no Stripe.js tag is present', () => {
		const doc = createDocumentWithScript();

		expect( verifyStripeJsOrigin( doc ) ).toEqual( {
			ok: false,
			detectedSrc: null,
			detectedOrigin: null,
		} );
	} );

	it( 'fails closed when the tag has no src', () => {
		const doc = createDocumentWithScript( { id: 'stripe-js' } );

		expect( verifyStripeJsOrigin( doc ) ).toEqual( {
			ok: false,
			detectedSrc: null,
			detectedOrigin: null,
		} );
	} );

	it( 'does not fall back when a srcless script#stripe-js shadows a legitimate script', () => {
		// An attacker-injected srcless handle must not let an unrelated
		// official-origin script satisfy the check while window.Stripe is loaded
		// from elsewhere. The handle is authoritative whenever it is a <script>.
		const doc = createDocumentWithScript(
			{ id: 'stripe-js' },
			{ src: 'https://js.stripe.com/v3/' }
		);

		expect( verifyStripeJsOrigin( doc ) ).toEqual( {
			ok: false,
			detectedSrc: null,
			detectedOrigin: null,
		} );
	} );

	it( 'fails closed when the src cannot be parsed as a URL', () => {
		const doc = {
			querySelector: () => ( { src: 'https://' } ),
		};

		expect( verifyStripeJsOrigin( doc ) ).toEqual( {
			ok: false,
			detectedSrc: 'https://',
			detectedOrigin: null,
		} );
	} );
} );

describe( 'assertStripeJsOrigin', () => {
	it( 'passes silently for the official origin', () => {
		const warnSpy = jest
			.spyOn( console, 'warn' )
			.mockImplementation( () => {} );
		const doc = createDocumentWithScript( {
			id: 'stripe-js',
			src: 'https://js.stripe.com/clover/stripe.js',
		} );

		expect( () => assertStripeJsOrigin( doc ) ).not.toThrow();
		expect( warnSpy ).not.toHaveBeenCalled();
	} );

	it( 'warns with the detected src and throws a generic error for an unexpected origin', () => {
		const warnSpy = jest
			.spyOn( console, 'warn' )
			.mockImplementation( () => {} );
		const doc = createDocumentWithScript( {
			id: 'stripe-js',
			src: 'https://js.stripe.com.evil.example/clover/stripe.js',
		} );

		// The thrown message can be rendered to shoppers by checkout error
		// handling, so it must stay generic; diagnostics go to the console.
		expect( () => assertStripeJsOrigin( doc ) ).toThrow(
			/provenance check failed/
		);
		expect( () => assertStripeJsOrigin( doc ) ).not.toThrow( /evil/ );
		expect( warnSpy ).toHaveBeenCalledWith(
			expect.stringContaining(
				'https://js.stripe.com.evil.example/clover/stripe.js'
			)
		);
	} );

	it( 'warns and throws when no Stripe.js tag is found', () => {
		const warnSpy = jest
			.spyOn( console, 'warn' )
			.mockImplementation( () => {} );
		const doc = createDocumentWithScript();

		expect( () => assertStripeJsOrigin( doc ) ).toThrow(
			/provenance check failed/
		);
		expect( warnSpy ).toHaveBeenCalledWith(
			expect.stringContaining( 'no Stripe.js script tag was found' )
		);
		expect( warnSpy ).not.toHaveBeenCalledWith(
			expect.stringContaining( 'null' )
		);
	} );
} );
