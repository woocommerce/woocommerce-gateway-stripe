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
			addStripeScriptTag( 'https://js.stripe.com/clover/stripe.js' );

			const api = new WCStripeAPI( { key: 'pk_test_123', locale: 'en' } );

			expect( api.getStripe() ).toBeTruthy();
			expect( global.Stripe ).toHaveBeenCalledWith( 'pk_test_123', {
				locale: 'en',
			} );
			expect( warnSpy ).not.toHaveBeenCalled();
		} );

		it( 'warns and blocks when Stripe.js was loaded from an unexpected origin', () => {
			addStripeScriptTag(
				'https://js.stripe.com.evil.example/clover/stripe.js'
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

	describe( 'confirmIntent', () => {
		let mockConfirmPayment;
		let mockRequest;
		let api;

		beforeEach( () => {
			addStripeScriptTag( 'https://js.stripe.com/clover/stripe.js' );

			mockConfirmPayment = jest.fn().mockResolvedValue( {
				paymentIntent: { id: 'pi_test123' },
			} );

			global.Stripe = jest.fn( () => ( {
				confirmPayment: mockConfirmPayment,
			} ) );

			mockRequest = jest.fn().mockResolvedValue( {
				success: true,
				data: { return_url: 'https://example.com/order-received/' },
			} );

			getStripeServerData.mockReturnValue( { isChangingPayment: false } );

			api = new WCStripeAPI(
				{
					key: 'pk_test_123',
					locale: 'en',
					return_url: 'https://example.com/return/',
					ajax_url:
						'https://example.com/wp-admin/admin-ajax.php?action=%%endpoint%%',
				},
				mockRequest
			);
		} );

		afterEach( () => {
			document.getElementById( 'stripe-js' )?.remove();
			delete global.Stripe;
			jest.clearAllMocks();
		} );

		it( 'passes return_url inside confirmParams when confirming a payment intent', async () => {
			const redirectUrl =
				'https://example.com/checkout/#wc-stripe-confirm-pi:ORDER123:cs_test_secret:nonce_abc';

			const { request } = api.confirmIntent( redirectUrl, null );
			await request;

			expect( mockConfirmPayment ).toHaveBeenCalledWith(
				expect.objectContaining( {
					clientSecret: 'cs_test_secret',
					redirect: 'if_required',
					confirmParams: expect.objectContaining( {
						return_url: 'https://example.com/return/',
					} ),
				} )
			);
		} );

		it( 'returns true without calling confirmPayment when redirectUrl has no intent hash', () => {
			const result = api.confirmIntent(
				'https://example.com/order-received/',
				null
			);

			expect( result ).toBe( true );
			expect( mockConfirmPayment ).not.toHaveBeenCalled();
		} );
	} );
} );
