import {
	getSharedStripeInstance,
	loadSharedStripeInstance,
	REGISTRY_KEY,
} from '../shared-stripe-instance';

describe( 'shared Stripe instance', () => {
	let warnSpy;

	const addStripeScriptTag = (
		src = 'https://js.stripe.com/dahlia/stripe.js'
	) => {
		document.getElementById( 'stripe-js' )?.remove();
		const script = document.createElement( 'script' );
		script.id = 'stripe-js';
		script.setAttribute( 'src', src );
		document.body.appendChild( script );
	};

	beforeEach( () => {
		delete window[ REGISTRY_KEY ];
		global.Stripe = jest.fn( ( key, options ) => ( { key, options } ) );
		warnSpy = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		warnSpy.mockRestore();
		delete global.Stripe;
		document.getElementById( 'stripe-js' )?.remove();
	} );

	describe( 'getSharedStripeInstance', () => {
		it( 'constructs Stripe once and reuses it for the same key and options', () => {
			addStripeScriptTag();

			const first = getSharedStripeInstance( 'pk_test_123', {
				locale: 'en',
			} );
			const second = getSharedStripeInstance( 'pk_test_123', {
				locale: 'en',
			} );

			expect( second ).toBe( first );
			expect( global.Stripe ).toHaveBeenCalledTimes( 1 );
			expect( global.Stripe ).toHaveBeenCalledWith( 'pk_test_123', {
				locale: 'en',
			} );
		} );

		it( 'reuses the instance regardless of the order the options were built in', () => {
			addStripeScriptTag();

			const first = getSharedStripeInstance( 'pk_test_123', {
				locale: 'en',
				developerTools: { assistant: { enabled: true } },
			} );
			const second = getSharedStripeInstance( 'pk_test_123', {
				developerTools: { assistant: { enabled: true } },
				locale: 'en',
			} );

			expect( second ).toBe( first );
			expect( global.Stripe ).toHaveBeenCalledTimes( 1 );
		} );

		it.each( [
			[ 'a different publishable key', 'pk_test_456', { locale: 'en' } ],
			[ 'a different locale', 'pk_test_123', { locale: 'fr' } ],
			[
				'different constructor options',
				'pk_test_123',
				{
					locale: 'en',
					developerTools: { assistant: { enabled: true } },
				},
			],
		] )(
			'constructs a separate instance for %s',
			( _label, key, options ) => {
				addStripeScriptTag();

				const first = getSharedStripeInstance( 'pk_test_123', {
					locale: 'en',
				} );
				const second = getSharedStripeInstance( key, options );

				expect( second ).not.toBe( first );
				expect( global.Stripe ).toHaveBeenCalledTimes( 2 );
			}
		);

		it( 'keeps throwing on a provenance failure rather than caching it', () => {
			addStripeScriptTag(
				'https://js.stripe.com.evil.example/stripe.js'
			);

			expect( () =>
				getSharedStripeInstance( 'pk_test_123', { locale: 'en' } )
			).toThrow( /provenance check failed/ );
			expect( () =>
				getSharedStripeInstance( 'pk_test_123', { locale: 'en' } )
			).toThrow( /provenance check failed/ );
			expect( global.Stripe ).not.toHaveBeenCalled();
		} );

		it( 'keeps using known good stripe.js after a bad script is injected', () => {
			addStripeScriptTag();

			const first = getSharedStripeInstance( 'pk_test_123', {
				locale: 'en',
			} );

			expect( first ).toBeDefined();

			addStripeScriptTag(
				'https://js.stripe.com.evil.example/stripe.js'
			);

			const second = getSharedStripeInstance( 'pk_test_123', {
				locale: 'en',
			} );

			expect( second ).toBe( first );
			expect( global.Stripe ).toHaveBeenCalledTimes( 1 );
		} );

		it.each( [
			[ 'a string', 'not a map' ],
			[ 'a plain object', {} ],
		] )(
			'replaces %s squatting on the registry key',
			( _label, squatter ) => {
				addStripeScriptTag();
				window[ REGISTRY_KEY ] = squatter;

				expect( () =>
					getSharedStripeInstance( 'pk_test_123', { locale: 'en' } )
				).not.toThrow();
				expect( window[ REGISTRY_KEY ] ).not.toBe( squatter );
			}
		);

		it( 'reuses a Map-like registry it did not create', () => {
			addStripeScriptTag();
			const foreign = new Map();
			window[ REGISTRY_KEY ] = foreign;

			getSharedStripeInstance( 'pk_test_123', { locale: 'en' } );

			expect( window[ REGISTRY_KEY ] ).toBe( foreign );
			expect( foreign.size ).toBe( 1 );
		} );
	} );

	describe( 'loadSharedStripeInstance', () => {
		it( 'resolves with the instance built by the synchronous path', async () => {
			addStripeScriptTag();
			const loadStripeJs = jest.fn();

			const instance = getSharedStripeInstance( 'pk_test_123', {
				locale: 'en',
			} );

			await expect(
				loadSharedStripeInstance(
					'pk_test_123',
					{ locale: 'en' },
					loadStripeJs
				)
			).resolves.toBe( instance );
			expect( loadStripeJs ).not.toHaveBeenCalled();
		} );

		it( 'builds the instance synchronously when Stripe.js has already executed', async () => {
			addStripeScriptTag();
			const loadStripeJs = jest.fn();

			const resolved = await loadSharedStripeInstance(
				'pk_test_123',
				{ locale: 'en' },
				loadStripeJs
			);

			expect( loadStripeJs ).not.toHaveBeenCalled();
			expect(
				getSharedStripeInstance( 'pk_test_123', { locale: 'en' } )
			).toBe( resolved );
		} );

		it( 'falls back to the loader while Stripe.js has not executed yet', async () => {
			addStripeScriptTag();
			delete global.Stripe;
			const loaded = {};
			const loadStripeJs = jest.fn( () => Promise.resolve( loaded ) );

			const first = loadSharedStripeInstance(
				'pk_test_123',
				{ locale: 'en' },
				loadStripeJs
			);
			const second = loadSharedStripeInstance(
				'pk_test_123',
				{ locale: 'en' },
				loadStripeJs
			);

			await expect( first ).resolves.toBe( loaded );
			await expect( second ).resolves.toBe( loaded );
			expect( loadStripeJs ).toHaveBeenCalledTimes( 1 );
			expect( loadStripeJs ).toHaveBeenCalledWith( 'pk_test_123', {
				locale: 'en',
			} );
		} );

		it( 'hands a loader-built instance to later synchronous callers', async () => {
			addStripeScriptTag();
			delete global.Stripe;
			const loaded = {};

			await loadSharedStripeInstance(
				'pk_test_123',
				{ locale: 'en' },
				() => Promise.resolve( loaded )
			);

			global.Stripe = jest.fn( () => ( {} ) );

			expect(
				getSharedStripeInstance( 'pk_test_123', { locale: 'en' } )
			).toBe( loaded );
			expect( global.Stripe ).not.toHaveBeenCalled();
		} );

		it( 'throws on a provenance failure without calling the loader', async () => {
			addStripeScriptTag(
				'https://js.stripe.com.evil.example/stripe.js'
			);
			delete global.Stripe;
			const loadStripeJs = jest.fn();

			expect( () =>
				loadSharedStripeInstance(
					'pk_test_123',
					{ locale: 'en' },
					loadStripeJs
				)
			).toThrow( /provenance check failed/ );
			expect( loadStripeJs ).not.toHaveBeenCalled();
		} );

		it( 'yields one instance when Stripe.js finishes executing mid-load', async () => {
			addStripeScriptTag();
			delete global.Stripe;

			// Held open so a synchronous caller can slip in mid-load.
			let finishLoading;
			const loaded = {};
			const pending = loadSharedStripeInstance(
				'pk_test_123',
				{ locale: 'en' },
				() => new Promise( ( resolve ) => ( finishLoading = resolve ) )
			);

			const constructed = {};
			global.Stripe = jest.fn( () => constructed );
			const synchronous = getSharedStripeInstance( 'pk_test_123', {
				locale: 'en',
			} );

			finishLoading( loaded );

			await expect( pending ).resolves.toBe( synchronous );
			await expect(
				loadSharedStripeInstance(
					'pk_test_123',
					{ locale: 'en' },
					jest.fn()
				)
			).resolves.toBe( synchronous );
			expect(
				getSharedStripeInstance( 'pk_test_123', { locale: 'en' } )
			).toBe( synchronous );
		} );

		it( 'does not cache a rejected load', async () => {
			addStripeScriptTag();
			delete global.Stripe;
			const loaded = {};
			const loadStripeJs = jest
				.fn()
				.mockRejectedValueOnce( new Error( 'network' ) )
				.mockResolvedValueOnce( loaded );

			await expect(
				loadSharedStripeInstance(
					'pk_test_123',
					{ locale: 'en' },
					loadStripeJs
				)
			).rejects.toThrow( 'network' );

			await expect(
				loadSharedStripeInstance(
					'pk_test_123',
					{ locale: 'en' },
					loadStripeJs
				)
			).resolves.toBe( loaded );
			expect( loadStripeJs ).toHaveBeenCalledTimes( 2 );
		} );
	} );

	it( 'shares one instance across bundles that each load their own copy of this module', () => {
		addStripeScriptTag();

		// Re-requiring stands in for a second webpack entry with its own module
		// instances, which a module-scoped cache would fail to share across.
		const paymentElementStripe = getSharedStripeInstance( 'pk_test_123', {
			locale: 'en',
		} );

		let otherBundle;
		jest.isolateModules( () => {
			otherBundle = require( '../shared-stripe-instance' );
		} );
		const expressCheckoutStripe = otherBundle.getSharedStripeInstance(
			'pk_test_123',
			{ locale: 'en' }
		);

		expect( expressCheckoutStripe ).toBe( paymentElementStripe );
		expect( global.Stripe ).toHaveBeenCalledTimes( 1 );
	} );
} );
