import { createApiClient } from '../core';
import { getStripe, loadStripe } from '../stripe';
import { REGISTRY_KEY } from 'wcstripe/stripe-utils/shared-stripe-instance';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeDevWidgetOptions: jest.fn( () => ( {} ) ),
} ) );

describe( 'wcstripe/api/stripe', () => {
	let warnSpy;

	const addStripeScriptTag = ( src ) => {
		const script = document.createElement( 'script' );
		script.id = 'stripe-js';
		script.setAttribute( 'src', src );
		document.body.appendChild( script );
	};

	const createClient = () =>
		createApiClient( { key: 'pk_test_123', locale: 'en' }, jest.fn() );

	beforeEach( () => {
		delete window[ REGISTRY_KEY ];
		global.Stripe = jest.fn( () => ( {} ) );
		warnSpy = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		warnSpy.mockRestore();
		delete global.Stripe;
		document.getElementById( 'stripe-js' )?.remove();
	} );

	describe( 'getStripe', () => {
		it( 'instantiates Stripe when Stripe.js was loaded from the official origin', () => {
			addStripeScriptTag( 'https://js.stripe.com/dahlia/stripe.js' );

			expect( getStripe( createClient() ) ).toBeTruthy();
			expect( global.Stripe ).toHaveBeenCalledWith( 'pk_test_123', {
				locale: 'en',
			} );
			expect( warnSpy ).not.toHaveBeenCalled();
		} );

		it( 'shares one Stripe instance across repeated calls and separately created clients', () => {
			addStripeScriptTag( 'https://js.stripe.com/dahlia/stripe.js' );

			const paymentElementClient = createClient();
			const expressCheckoutClient = createClient();

			const first = getStripe( paymentElementClient );

			expect( getStripe( paymentElementClient ) ).toBe( first );
			expect( getStripe( expressCheckoutClient ) ).toBe( first );
			expect( global.Stripe ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'warns and blocks when Stripe.js was loaded from an unexpected origin', () => {
			addStripeScriptTag(
				'https://js.stripe.com.evil.example/dahlia/stripe.js'
			);

			expect( () => getStripe( createClient() ) ).toThrow(
				/provenance check failed/
			);
			expect( global.Stripe ).not.toHaveBeenCalled();
			expect( warnSpy ).toHaveBeenCalled();
		} );

		it( 'warns and blocks when no Stripe.js tag is present', () => {
			expect( () => getStripe( createClient() ) ).toThrow(
				/provenance check failed/
			);
			expect( global.Stripe ).not.toHaveBeenCalled();
			expect( warnSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'loadStripe', () => {
		it( 'resolves with the shared Stripe instance', async () => {
			addStripeScriptTag( 'https://js.stripe.com/dahlia/stripe.js' );
			const client = createClient();

			await expect( loadStripe( client ) ).resolves.toBe(
				getStripe( client )
			);
		} );

		// Callers render nothing on failure, so the error is handed back as a
		// value to keep it out of the shopper's console.
		it( 'resolves with the error instead of rejecting when Stripe cannot be created', async () => {
			const result = await loadStripe( createClient() );

			expect( result.error.message ).toMatch( /provenance check failed/ );
		} );
	} );
} );
