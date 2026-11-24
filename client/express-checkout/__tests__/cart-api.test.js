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
	let apiFetch;
	let cartApi;

	beforeEach( () => {
		// Refreshed nonces live at module scope, so load a fresh copy per test.
		jest.resetModules();
		apiFetch = require( '@wordpress/api-fetch' ).default;
		const ExpressCheckoutCartApi = require( '../cart-api' ).default;
		cartApi = new ExpressCheckoutCartApi();
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
} );
