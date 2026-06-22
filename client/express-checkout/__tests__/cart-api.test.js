import ExpressCheckoutCartApi from '../cart-api';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );
jest.mock( '@woocommerce/blocks-checkout', () => {}, { virtual: true } );

jest.mock( 'wcstripe/express-checkout/utils', () => ( {
	getExpressCheckoutData: jest.fn( ( key ) => {
		if ( key === 'nonce' ) {
			return {
				wc_store_api: 'test_store_api_nonce',
				wc_store_api_express_checkout: 'test_ece_nonce',
			};
		}
		return null;
	} ),
} ) );

const mockResponse = ( body, headers = {} ) => ( {
	headers: {
		get: ( name ) => headers[ name ] ?? null,
	},
	json: () => Promise.resolve( body ),
} );

describe( 'ExpressCheckoutCartApi', () => {
	let cartApi;

	beforeEach( () => {
		cartApi = new ExpressCheckoutCartApi();
		apiFetch.mockReset();
	} );

	describe( '_request', () => {
		it( 'sends correct headers on every request', async () => {
			apiFetch.mockResolvedValue(
				mockResponse( { items: [] }, { Nonce: 'refreshed_nonce' } )
			);

			await cartApi.getCart();

			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					parse: false,
					headers: expect.objectContaining( {
						Nonce: 'test_store_api_nonce',
						'X-WCSTRIPE-EXPRESS-CHECKOUT': 'true',
						'X-WCSTRIPE-EXPRESS-CHECKOUT-NONCE': 'test_ece_nonce',
					} ),
				} )
			);
		} );

		it( 'refreshes nonce from response headers', async () => {
			apiFetch.mockResolvedValue(
				mockResponse( {}, { Nonce: 'new_nonce_123' } )
			);

			await cartApi.getCart();

			apiFetch.mockResolvedValue(
				mockResponse( {}, { Nonce: 'another_nonce' } )
			);

			await cartApi.getCart();

			const secondCall = apiFetch.mock.calls[ 1 ][ 0 ];
			expect( secondCall.headers.Nonce ).toBe( 'new_nonce_123' );
		} );

		it( 'does not overwrite nonce when response header is null', async () => {
			apiFetch.mockResolvedValue(
				mockResponse( {}, { Nonce: 'good_nonce' } )
			);

			await cartApi.getCart();

			apiFetch.mockResolvedValue( mockResponse( {}, {} ) );

			await cartApi.getCart();

			const thirdCall = apiFetch.mock.calls[ 1 ][ 0 ];
			expect( thirdCall.headers.Nonce ).toBe( 'good_nonce' );
		} );
	} );

	describe( 'getCart', () => {
		it( 'calls GET /wc/store/v1/cart', async () => {
			apiFetch.mockResolvedValue( mockResponse( { items: [] } ) );

			const result = await cartApi.getCart();

			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					method: 'GET',
					path: '/wc/store/v1/cart',
				} )
			);
			expect( result ).toEqual( { items: [] } );
		} );
	} );

	describe( 'updateCustomer', () => {
		it( 'calls POST /wc/store/v1/cart/update-customer with data', async () => {
			const customerData = {
				shipping_address: {
					country: 'US',
					state: 'NY',
					city: 'New York',
					postcode: '10001',
				},
			};

			apiFetch.mockResolvedValue( mockResponse( { totals: {} } ) );

			await cartApi.updateCustomer( customerData );

			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					method: 'POST',
					path: '/wc/store/v1/cart/update-customer',
					data: customerData,
				} )
			);
		} );
	} );

	describe( 'selectShippingRate', () => {
		it( 'calls POST /wc/store/v1/cart/select-shipping-rate with data', async () => {
			const shippingRate = { package_id: 0, rate_id: 'flat_rate:1' };

			apiFetch.mockResolvedValue( mockResponse( { totals: {} } ) );

			await cartApi.selectShippingRate( shippingRate );

			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					method: 'POST',
					path: '/wc/store/v1/cart/select-shipping-rate',
					data: shippingRate,
				} )
			);
		} );
	} );

	describe( 'getStoreApiNonce', () => {
		// Guests on full-page-cached shortcode checkout get a stale/absent page
		// nonce, so the checkout POST reuses the nonce the cart calls already
		// rotated instead.
		it( 'reuses the nonce rotated by an earlier cart request without making a request', async () => {
			apiFetch.mockResolvedValue(
				mockResponse( { totals: {} }, { Nonce: 'refreshed_nonce' } )
			);
			await cartApi.updateCustomer( { shipping_address: {} } );
			apiFetch.mockClear();

			const nonce = await cartApi.getStoreApiNonce();

			expect( nonce ).toBe( 'refreshed_nonce' );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		// Virtual/downloadable carts skip the shipping step, so no cart call has
		// rotated a nonce — fetch the cart (no nonce required on GET) to harvest one.
		it( 'fetches the cart to harvest a fresh nonce when none has been rotated', async () => {
			apiFetch.mockResolvedValue(
				mockResponse( {}, { Nonce: 'fetched_nonce' } )
			);

			const nonce = await cartApi.getStoreApiNonce();

			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					method: 'GET',
					path: '/wc/store/v1/cart',
				} )
			);
			expect( nonce ).toBe( 'fetched_nonce' );
		} );

		it( 'falls back to the page nonce when the cart fetch yields no nonce', async () => {
			apiFetch.mockResolvedValue( mockResponse( {} ) );

			const nonce = await cartApi.getStoreApiNonce();

			expect( nonce ).toBe( 'test_store_api_nonce' );
		} );

		it( 'falls back to the page nonce when the cart fetch fails', async () => {
			apiFetch.mockRejectedValue( new Error( 'network' ) );

			const nonce = await cartApi.getStoreApiNonce();

			expect( nonce ).toBe( 'test_store_api_nonce' );
		} );
	} );
} );
