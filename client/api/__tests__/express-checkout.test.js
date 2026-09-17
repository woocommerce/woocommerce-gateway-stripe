import { createApiClient } from '../core';
import {
	expressCheckoutAddToCart,
	expressCheckoutAddToCartLegacy,
	expressCheckoutECECreateOrder,
	expressCheckoutECEPayForOrder,
	expressCheckoutGetCartDetails,
	expressCheckoutGetNonce,
} from '../express-checkout';
import apiFetch from '@wordpress/api-fetch';
import { addFilter, removeFilter } from '@wordpress/hooks';

jest.mock( '@wordpress/api-fetch' );

describe( 'wcstripe/api/express-checkout', () => {
	afterEach( () => {
		delete global.wc_stripe_express_checkout_params;
	} );

	describe( 'on-demand nonces', () => {
		beforeEach( () => {
			global.wc_stripe_express_checkout_params = {
				ajax_url: '/?wc-ajax=%%endpoint%%',
			};
		} );

		it( 'fetches the nonce bundle once and reuses it for later lookups', async () => {
			const request = jest.fn().mockResolvedValue( {
				success: true,
				data: { shipping: 'fresh_shipping', clear_cart: 'fresh_clear' },
			} );
			const client = createApiClient( {}, request );

			await expect(
				expressCheckoutGetNonce( client, 'shipping' )
			).resolves.toBe( 'fresh_shipping' );
			await expect(
				expressCheckoutGetNonce( client, 'clear_cart' )
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
			const client = createApiClient( {}, request );

			await expect(
				expressCheckoutGetNonce( client, 'add_to_cart' )
			).rejects.toThrow( 'network' );
			await expect(
				expressCheckoutGetNonce( client, 'add_to_cart' )
			).resolves.toBe( 'fresh_add' );

			expect( request ).toHaveBeenCalledTimes( 2 );
		} );

		it( 'keeps the fetched bundle separate for each client', async () => {
			const firstRequest = jest.fn().mockResolvedValue( {
				success: true,
				data: { shipping: 'first' },
			} );
			const secondRequest = jest.fn().mockResolvedValue( {
				success: true,
				data: { shipping: 'second' },
			} );

			await expect(
				expressCheckoutGetNonce(
					createApiClient( {}, firstRequest ),
					'shipping'
				)
			).resolves.toBe( 'first' );
			await expect(
				expressCheckoutGetNonce(
					createApiClient( {}, secondRequest ),
					'shipping'
				)
			).resolves.toBe( 'second' );
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
			const client = createApiClient( {}, request );

			await expressCheckoutAddToCartLegacy( client, { product_id: 1 } );

			expect( request ).toHaveBeenCalledWith(
				'/?wc-ajax=wc_stripe_add_to_cart',
				{
					security: 'fresh_add',
					product_id: 1,
				}
			);
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
			document.body.innerHTML = '';
		} );

		it( 'reads the cart from the Store API', async () => {
			await expressCheckoutGetCartDetails();

			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					method: 'GET',
					path: '/wc/store/v1/cart',
				} )
			);
		} );

		it( 'adds a product using the Store API quantity parameter', async () => {
			await expressCheckoutAddToCart( { id: 12, qty: 3 } );

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

			await expressCheckoutAddToCart( { id: 12 } );

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

			await expressCheckoutECECreateOrder( { payment_method: 'stripe' } );

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

			await expressCheckoutECEPayForOrder(
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
} );
