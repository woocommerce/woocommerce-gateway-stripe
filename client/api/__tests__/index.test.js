import WCStripeAPI from '..';
import apiFetch from '@wordpress/api-fetch';
import { addFilter, removeFilter } from '@wordpress/hooks';
import { REGISTRY_KEY } from 'wcstripe/stripe-utils/shared-stripe-instance';

jest.mock( '@wordpress/api-fetch' );

jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeServerData: jest.fn(),
	getStripeDevWidgetOptions: jest.fn( () => ( {} ) ),
} ) );

describe( 'WCStripeAPI', () => {
	describe( 'getStripe', () => {
		let warnSpy;

		const addStripeScriptTag = ( src ) => {
			const script = document.createElement( 'script' );
			script.id = 'stripe-js';
			script.setAttribute( 'src', src );
			document.body.appendChild( script );
		};

		beforeEach( () => {
			delete window[ REGISTRY_KEY ];
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

		it( 'shares one Stripe instance across repeated calls and separately constructed API objects', () => {
			addStripeScriptTag( 'https://js.stripe.com/dahlia/stripe.js' );
			const options = { key: 'pk_test_123', locale: 'en' };

			const paymentElementApi = new WCStripeAPI( options );
			const expressCheckoutApi = new WCStripeAPI( options );

			const first = paymentElementApi.getStripe();

			expect( paymentElementApi.getStripe() ).toBe( first );
			expect( expressCheckoutApi.getStripe() ).toBe( first );
			expect( global.Stripe ).toHaveBeenCalledTimes( 1 );
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

	describe( 'getAjaxUrl', () => {
		it( 'interpolates the endpoint with the default prefix', () => {
			const api = new WCStripeAPI( {
				ajax_url: '/?wc-ajax=%%endpoint%%',
			} );

			expect( api.getAjaxUrl( 'create_payment_intent' ) ).toBe(
				'/?wc-ajax=wc_stripe_create_payment_intent'
			);
		} );

		it( 'supports core WooCommerce endpoints through an empty prefix', () => {
			const api = new WCStripeAPI( {
				ajax_url: '/?wc-ajax=%%endpoint%%',
			} );

			expect( api.getAjaxUrl( 'checkout', '' ) ).toBe(
				'/?wc-ajax=checkout'
			);
		} );

		it( 'returns undefined when no AJAX URL was provided', () => {
			expect(
				new WCStripeAPI( {} ).getAjaxUrl( 'checkout' )
			).toBeUndefined();
		} );
	} );

	describe( 'loadStripe', () => {
		let warnSpy;

		beforeEach( () => {
			delete window[ REGISTRY_KEY ];
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

		it( 'resolves with the shared Stripe instance', async () => {
			const script = document.createElement( 'script' );
			script.id = 'stripe-js';
			script.setAttribute(
				'src',
				'https://js.stripe.com/dahlia/stripe.js'
			);
			document.body.appendChild( script );
			const api = new WCStripeAPI( { key: 'pk_test_123', locale: 'en' } );

			await expect( api.loadStripe() ).resolves.toBe( api.getStripe() );
		} );

		// Callers render nothing on failure, so the error is handed back as a
		// value to keep it out of the shopper's console.
		it( 'resolves with the error instead of rejecting when Stripe cannot be created', async () => {
			const api = new WCStripeAPI( { key: 'pk_test_123', locale: 'en' } );

			const result = await api.loadStripe();

			expect( result.error.message ).toMatch( /provenance check failed/ );
		} );
	} );

	describe( 'intent request failures', () => {
		const options = { ajax_url: '/?wc-ajax=%%endpoint%%' };

		it( 'rejects with the server error when the response is unsuccessful', async () => {
			const serverError = { message: 'Order not found.' };
			const request = jest.fn().mockResolvedValue( {
				success: false,
				data: { error: serverError },
			} );
			const api = new WCStripeAPI( options, request );

			await expect( api.createIntent( 1 ) ).rejects.toBe( serverError );
			await expect( api.initSetupIntent( 'card' ) ).rejects.toBe(
				serverError
			);
		} );

		// A failed jqXHR carries no message, and only its statusText (a string)
		// reaches the message lookup, so the timeout/abort wording is never
		// selected: every transport failure reads as the generic message.
		it.each( [ [ 'timeout' ], [ 'abort' ], [ 'error' ] ] )(
			'reports the generic connection error for a "%s" transport failure',
			async ( statusText ) => {
				const request = jest.fn().mockRejectedValue( { statusText } );
				const api = new WCStripeAPI( options, request );
				const genericMessage =
					'An error occurred while connecting to the server. Please try again.';

				await expect( api.createIntent( 1 ) ).rejects.toThrow(
					genericMessage
				);
				await expect( api.initSetupIntent( 'card' ) ).rejects.toThrow(
					genericMessage
				);
				await expect(
					api.processCheckout( 'pi_123', {} )
				).rejects.toThrow( genericMessage );
			}
		);
	} );

	describe( 'confirmIntent', () => {
		it( 'returns true when the redirect URL carries no confirmation hash', () => {
			const api = new WCStripeAPI( {}, jest.fn() );

			expect(
				api.confirmIntent( 'https://shop.com/order-received/123/' )
			).toBe( true );
		} );
	} );

	describe( 'Store API requests', () => {
		beforeEach( () => {
			apiFetch.mockReset();
			apiFetch.mockResolvedValue( {} );
			global.wc_stripe_express_checkout_params = {
				nonce: {
					wc_store_api: 'store_nonce',
					wc_store_api_express_checkout: 'ece_nonce',
				},
			};
		} );

		afterEach( () => {
			delete global.wc_stripe_express_checkout_params;
			document.body.innerHTML = '';
		} );

		it( 'reads the cart from the Store API', async () => {
			await new WCStripeAPI( {} ).expressCheckoutGetCartDetails();

			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					method: 'GET',
					path: '/wc/store/v1/cart',
				} )
			);
		} );

		it( 'adds a product using the Store API quantity parameter', async () => {
			await new WCStripeAPI( {} ).expressCheckoutAddToCart( {
				id: 12,
				qty: 3,
			} );

			expect( apiFetch ).toHaveBeenCalledWith( {
				method: 'POST',
				path: '/wc/store/v1/cart/add-item',
				headers: { Nonce: 'store_nonce' },
				data: { id: 12, quantity: 3 },
			} );
		} );

		it( 'defaults the quantity to 1 and lets extensions filter the add-to-cart data', async () => {
			addFilter(
				'wcstripe.express-checkout.cart-add-item',
				'wcstripe/test',
				( data ) => ( { ...data, extra: 'value' } )
			);

			await new WCStripeAPI( {} ).expressCheckoutAddToCart( { id: 12 } );

			removeFilter(
				'wcstripe.express-checkout.cart-add-item',
				'wcstripe/test'
			);
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					data: { id: 12, quantity: 1, extra: 'value' },
				} )
			);
		} );

		it( 'creates the order with the express checkout headers and the customer note', async () => {
			document.body.innerHTML =
				'<form class="checkout"><textarea name="order_comments">Leave at the door</textarea></form>';

			await new WCStripeAPI( {} ).expressCheckoutECECreateOrder( {
				payment_method: 'stripe',
			} );

			expect( apiFetch ).toHaveBeenCalledWith( {
				method: 'POST',
				path: '/wc/store/v1/checkout',
				headers: {
					Nonce: 'store_nonce',
					'X-WCSTRIPE-EXPRESS-CHECKOUT': true,
					'X-WCSTRIPE-EXPRESS-CHECKOUT-NONCE': 'ece_nonce',
				},
				data: {
					payment_method: 'stripe',
					customer_note: 'Leave at the door',
				},
			} );
		} );

		it( 'pays for an existing order using its key and billing email', async () => {
			const shippingAddress = { city: 'Cape Town' };

			await new WCStripeAPI( {} ).expressCheckoutECEPayForOrder(
				45,
				{
					orderKey: 'wc_order_abc',
					billingEmail: 'shopper@example.com',
					shippingAddress,
				},
				{ payment_method: 'stripe' }
			);

			expect( apiFetch ).toHaveBeenCalledWith( {
				method: 'POST',
				path: '/wc/store/v1/checkout/45?key=wc_order_abc&billing_email=shopper@example.com',
				headers: { Nonce: 'store_nonce' },
				data: {
					payment_method: 'stripe',
					shipping_address: shippingAddress,
				},
			} );
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
