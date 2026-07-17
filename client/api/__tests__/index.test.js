import WCStripeAPI from '..';
import { getStripeServerData } from 'wcstripe/stripe-utils';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeServerData: jest.fn(),
	getStripeDevWidgetOptions: jest.fn( () => ( {} ) ),
} ) );

const addStripeScriptTag = ( src ) => {
	const script = document.createElement( 'script' );
	script.id = 'stripe-js';
	script.setAttribute( 'src', src );
	document.body.appendChild( script );
};

describe( 'WCStripeAPI', () => {
	describe( 'getStripe', () => {
		let warnSpy;

		beforeEach( () => {
			global.Stripe = jest.fn( () => ( {} ) );
			warnSpy = jest
				.spyOn( console, 'warn' )
				.mockImplementation( () => {} );
		} );

		afterEach( () => {
			warnSpy.mockRestore();
			delete global.Stripe;
			document.getElementById( 'stripe-js' )?.remove();
		} );

		it( 'instantiates Stripe when Stripe.js was loaded from the official origin', () => {
			addStripeScriptTag( 'https://js.stripe.com/dahlia/stripe.js' );
			const api = new WCStripeAPI( { key: 'pk_test_123', locale: 'en' } );

			expect( api.getStripe() ).toBeTruthy();
			expect( global.Stripe ).toHaveBeenCalledWith( 'pk_test_123', {
				locale: 'en',
			} );
			expect( warnSpy ).not.toHaveBeenCalled();
		} );

		it( 'warns and blocks when Stripe.js was loaded from an unexpected origin', () => {
			addStripeScriptTag(
				'https://js.stripe.com.evil.example/dahlia/stripe.js'
			);
			const api = new WCStripeAPI( { key: 'pk_test_123', locale: 'en' } );

			expect( () => api.getStripe() ).toThrow(
				/provenance check failed/
			);
			expect( global.Stripe ).not.toHaveBeenCalled();
			expect( warnSpy ).toHaveBeenCalled();
		} );

		it( 'warns and blocks when no Stripe.js tag is present', () => {
			const api = new WCStripeAPI( { key: 'pk_test_123', locale: 'en' } );

			expect( () => api.getStripe() ).toThrow(
				/provenance check failed/
			);
			expect( global.Stripe ).not.toHaveBeenCalled();
			expect( warnSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'checkoutSessionsUpdateSession', () => {
		const options = {
			ajax_url: '/?wc-ajax=%%endpoint%%',
			updateCheckoutSessionNonce: 'nonce_123',
		};

		it( 'resolves when the server reports success', async () => {
			const request = jest.fn().mockResolvedValue( {
				success: true,
				data: { result: 'success' },
			} );
			const api = new WCStripeAPI( options, request );

			await expect(
				api.checkoutSessionsUpdateSession( 'cs_test' )
			).resolves.toEqual( {
				success: true,
				data: { result: 'success' },
			} );
			expect( request ).toHaveBeenCalledWith(
				'/?wc-ajax=wc_stripe_update_checkout_session',
				{
					security: 'nonce_123',
					checkout_session_id: 'cs_test',
				}
			);
		} );

		// wp_send_json_error replies with HTTP 200 { success: false }, so the
		// request resolves; this must surface as a rejection so a stale session
		// is not silently accepted.
		it( 'rejects with the server message when success is false', async () => {
			const request = jest.fn().mockResolvedValue( {
				success: false,
				data: { message: 'Checkout session ID is required.' },
			} );
			const api = new WCStripeAPI( options, request );

			await expect(
				api.checkoutSessionsUpdateSession( 'cs_test' )
			).rejects.toThrow( 'Checkout session ID is required.' );
		} );
	} );
} );
