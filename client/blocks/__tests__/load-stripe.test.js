import { loadStripe as loadStripeFromNpm } from '@stripe/stripe-js';
import { loadStripe } from '../load-stripe';
import { getStripeDevWidgetOptions } from 'wcstripe/stripe-utils';

jest.mock( '@stripe/stripe-js', () => ( {
	loadStripe: jest.fn( () => Promise.resolve( {} ) ),
} ) );
jest.mock( '../utils', () => ( {
	getApiKey: jest.fn( () => 'pk_test_123' ),
	getBlocksConfiguration: jest.fn( () => ( { stripe_locale: 'en' } ) ),
} ) );
jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeDevWidgetOptions: jest.fn( () => ( {} ) ),
} ) );

describe( 'loadStripe', () => {
	let warnSpy;

	const addStripeScriptTag = ( src ) => {
		const script = document.createElement( 'script' );
		script.id = 'stripe-js';
		script.setAttribute( 'src', src );
		document.body.appendChild( script );
	};

	beforeEach( () => {
		loadStripeFromNpm.mockClear();
		getStripeDevWidgetOptions.mockReset();
		getStripeDevWidgetOptions.mockReturnValue( {} );
		warnSpy = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		document.getElementById( 'stripe-js' )?.remove();
		warnSpy.mockRestore();
	} );

	it( 'loads Stripe when Stripe.js was loaded from the official origin', async () => {
		addStripeScriptTag( 'https://js.stripe.com/dahlia/stripe.js' );

		const result = await loadStripe();

		expect( result.error ).toBeUndefined();
		expect( loadStripeFromNpm ).toHaveBeenCalledWith( 'pk_test_123', {
			locale: 'en',
		} );
		expect( warnSpy ).not.toHaveBeenCalled();
	} );

	it( 'warns and blocks when Stripe.js was loaded from an unexpected origin', async () => {
		addStripeScriptTag(
			'https://js.stripe.com.evil.example/dahlia/stripe.js'
		);

		const result = await loadStripe();

		expect( result.error ).toBeInstanceOf( Error );
		expect( result.error.message ).toMatch( /provenance check failed/ );
		expect( loadStripeFromNpm ).not.toHaveBeenCalled();
		expect( warnSpy ).toHaveBeenCalled();
	} );

	it( 'passes developerTools.assistant.enabled false to Stripe loadStripe when disabled', async () => {
		addStripeScriptTag( 'https://js.stripe.com/dahlia/stripe.js' );
		getStripeDevWidgetOptions.mockReturnValue( {
			developerTools: {
				assistant: {
					enabled: false,
				},
			},
		} );

		await loadStripe();

		expect( loadStripeFromNpm ).toHaveBeenCalledWith(
			'pk_test_123',
			expect.objectContaining( {
				locale: 'en',
				developerTools: {
					assistant: {
						enabled: false,
					},
				},
			} )
		);
	} );

	it( 'passes developerTools.assistant.enabled true to Stripe loadStripe when enabled', async () => {
		addStripeScriptTag( 'https://js.stripe.com/dahlia/stripe.js' );
		getStripeDevWidgetOptions.mockReturnValue( {
			developerTools: {
				assistant: {
					enabled: true,
				},
			},
		} );

		await loadStripe();

		expect( loadStripeFromNpm ).toHaveBeenCalledWith(
			'pk_test_123',
			expect.objectContaining( {
				locale: 'en',
				developerTools: {
					assistant: {
						enabled: true,
					},
				},
			} )
		);
	} );
} );
