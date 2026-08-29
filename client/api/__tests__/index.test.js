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

	describe( 'express checkout on-demand nonces', () => {
		beforeEach( () => {
			global.wc_stripe_express_checkout_params = {
				ajax_url: '/?wc-ajax=%%endpoint%%',
			};
		} );

		afterEach( () => {
			delete global.wc_stripe_express_checkout_params;
		} );

		it( 'fetches the nonce bundle once and reuses it for later lookups', async () => {
			const request = jest.fn().mockResolvedValue( {
				success: true,
				data: { shipping: 'fresh_shipping', clear_cart: 'fresh_clear' },
			} );
			const api = new WCStripeAPI( {}, request );

			await expect(
				api.expressCheckoutGetNonce( 'shipping' )
			).resolves.toBe( 'fresh_shipping' );
			await expect(
				api.expressCheckoutGetNonce( 'clear_cart' )
			).resolves.toBe( 'fresh_clear' );

			expect( request ).toHaveBeenCalledTimes( 1 );
			expect( request ).toHaveBeenCalledWith(
				'/?wc-ajax=wc_stripe_get_express_checkout_nonces',
				{}
			);
		} );

		// Warm-up fires on mere hover/touch, so a transient failure there must
		// not poison the memo: the next lookup (the real interaction) retries.
		it( 'retries the fetch on the next lookup after a failure', async () => {
			const request = jest
				.fn()
				.mockRejectedValueOnce( new Error( 'network' ) )
				.mockResolvedValueOnce( {
					success: true,
					data: { add_to_cart: 'fresh_add' },
				} );
			const api = new WCStripeAPI( {}, request );

			await expect(
				api.expressCheckoutGetNonce( 'add_to_cart' )
			).rejects.toThrow( 'network' );
			await expect(
				api.expressCheckoutGetNonce( 'add_to_cart' )
			).resolves.toBe( 'fresh_add' );

			expect( request ).toHaveBeenCalledTimes( 2 );
		} );

		it( 'sends the freshly fetched nonce with the wc-ajax request', async () => {
			const request = jest.fn( ( url ) =>
				url.includes( 'get_express_checkout_nonces' )
					? Promise.resolve( {
							success: true,
							data: { add_to_cart: 'fresh_add' },
					  } )
					: Promise.resolve( { success: true } )
			);
			const api = new WCStripeAPI( {}, request );

			await api.expressCheckoutAddToCartLegacy( { product_id: 1 } );

			expect( request ).toHaveBeenCalledWith(
				'/?wc-ajax=wc_stripe_add_to_cart',
				{
					security: 'fresh_add',
					product_id: 1,
				}
			);
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

	describe( 'createIntent', () => {
		it( 'includes the order key in the PaymentIntent AJAX request', async () => {
			const request = jest.fn().mockResolvedValue( {
				success: true,
				data: {
					id: 'pi_test',
					client_secret: 'pi_test_secret',
				},
			} );
			const api = new WCStripeAPI(
				{
					ajax_url: '/?wc-ajax=%%endpoint%%',
					createPaymentIntentNonce: 'nonce_123',
				},
				request
			);

			await expect(
				api.createIntent( 123, 'blik', 'wc_order_test_key' )
			).resolves.toEqual( {
				id: 'pi_test',
				client_secret: 'pi_test_secret',
			} );

			expect( request ).toHaveBeenCalledWith(
				'/?wc-ajax=wc_stripe_create_payment_intent',
				{
					stripe_order_id: 123,
					payment_method_type: 'blik',
					order_key: 'wc_order_test_key',
					_ajax_nonce: 'nonce_123',
				}
			);
		} );
	} );
} );
