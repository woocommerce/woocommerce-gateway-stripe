const mockResponse = ( body = {}, headers = {}, status = 200 ) => {
	const response = {
		status,
		headers: {
			get: ( name ) => headers[ name ] ?? null,
		},
		json: () => Promise.resolve( body ),
	};
	response.clone = () => response;
	return response;
};

const invalidNonceResponse = ( headers = {} ) =>
	mockResponse(
		{
			code: 'woocommerce_rest_invalid_nonce',
			message: 'Nonce is invalid.',
		},
		headers,
		403
	);

describe( 'express checkout Store API client', () => {
	let apiFetch;
	let storeApi;

	const sentHeaders = ( callIndex ) =>
		apiFetch.mock.calls[ callIndex ][ 0 ].headers;

	beforeEach( () => {
		// The module keeps the refreshed nonces at module scope, so every test
		// needs its own copy.
		jest.resetModules();
		jest.doMock( '@wordpress/api-fetch' );
		jest.doMock( 'wcstripe/express-checkout/utils', () => ( {
			getExpressCheckoutData: jest.fn( ( key ) =>
				key === 'nonce'
					? {
							wc_store_api: 'printed_store_api_nonce',
							wc_store_api_express_checkout: 'printed_ece_nonce',
					  }
					: null
			),
		} ) );

		apiFetch = require( '@wordpress/api-fetch' ).default;
		storeApi = require( '../store-api' );
	} );

	it( 'reuses the nonce a response carried on the next request', async () => {
		apiFetch
			.mockResolvedValueOnce(
				mockResponse( {}, { Nonce: 'response_nonce' } )
			)
			.mockResolvedValueOnce( mockResponse() )
			.mockResolvedValueOnce( mockResponse() );
		const send = () =>
			storeApi.storeApiFetch(
				{ path: '/wc/store/v1/cart' },
				{ expressCheckoutContext: true }
			);

		await send();
		await send();
		// The second response carried no nonce, which must not blank the header.
		await send();

		// A response never carries the express checkout nonce, so that one
		// stays as printed.
		const expectedHeaders = {
			Nonce: 'response_nonce',
			'X-WCSTRIPE-EXPRESS-CHECKOUT': 'true',
			'X-WCSTRIPE-EXPRESS-CHECKOUT-NONCE': 'printed_ece_nonce',
		};
		expect( sentHeaders( 1 ) ).toEqual( expectedHeaders );
		expect( sentHeaders( 2 ) ).toEqual( expectedHeaders );
	} );

	describe( 'when the Store API rejects the nonce', () => {
		it( 'retries once with the nonce the rejection carried', async () => {
			const success = mockResponse( { items_count: 1 } );
			apiFetch
				.mockRejectedValueOnce(
					invalidNonceResponse( { Nonce: 'fresh_nonce' } )
				)
				.mockResolvedValueOnce( success );

			await expect(
				storeApi.storeApiFetch( {
					method: 'POST',
					path: '/wc/store/v1/cart/add-item',
					data: { id: 1 },
				} )
			).resolves.toBe( success );

			expect( apiFetch ).toHaveBeenCalledTimes( 2 );
			expect( sentHeaders( 0 ).Nonce ).toBe( 'printed_store_api_nonce' );
			expect( sentHeaders( 1 ).Nonce ).toBe( 'fresh_nonce' );
			expect( apiFetch.mock.calls[ 1 ][ 0 ].data ).toEqual( { id: 1 } );
		} );

		it( 'still retries when the refresh fails', async () => {
			storeApi.setStoreApiNonceRefresher(
				jest.fn().mockRejectedValue( new Error( 'network' ) )
			);
			apiFetch
				.mockRejectedValueOnce(
					invalidNonceResponse( { Nonce: 'fresh_nonce' } )
				)
				.mockResolvedValueOnce( mockResponse() );

			await storeApi.storeApiFetch( {
				method: 'POST',
				path: '/wc/store/v1/cart/add-item',
			} );

			expect( sentHeaders( 1 ).Nonce ).toBe( 'fresh_nonce' );
		} );

		// Retrying with a stale express checkout nonce would get past the Store
		// API and be handled as a regular request: no wallet address handling
		// and no check of the required custom checkout fields.
		it( 'gives up on an express checkout request when the refresh fails', async () => {
			storeApi.setStoreApiNonceRefresher(
				jest.fn().mockRejectedValue( new Error( 'network' ) )
			);
			const rejection = invalidNonceResponse( { Nonce: 'fresh_nonce' } );
			apiFetch.mockRejectedValueOnce( rejection );

			await expect(
				storeApi.storeApiFetch(
					{ method: 'POST', path: '/wc/store/v1/checkout' },
					{ expressCheckoutContext: true }
				)
			).rejects.toBe( rejection );

			expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		} );

		// The retry of a regular request renews the Store API nonce, so the
		// stale express checkout nonce would no longer be rejected.
		it( 'holds back express checkout requests until a refresh succeeds', async () => {
			const refresher = jest
				.fn()
				.mockRejectedValue( new Error( 'network' ) );
			storeApi.setStoreApiNonceRefresher( refresher );
			const rejection = invalidNonceResponse( { Nonce: 'fresh_nonce' } );
			apiFetch
				.mockRejectedValueOnce( rejection )
				.mockResolvedValue( mockResponse() );
			const sendExpressCheckoutRequest = () =>
				storeApi.storeApiFetch(
					{ method: 'POST', path: '/wc/store/v1/checkout' },
					{ expressCheckoutContext: true }
				);

			await storeApi.storeApiFetch( {
				method: 'POST',
				path: '/wc/store/v1/cart/add-item',
			} );
			await expect( sendExpressCheckoutRequest() ).rejects.toBe(
				rejection
			);
			expect( apiFetch ).toHaveBeenCalledTimes( 2 );

			refresher.mockImplementation( async () =>
				storeApi.rememberStoreApiNonces( {
					wc_store_api_express_checkout: 'bundle_ece_nonce',
				} )
			);
			await sendExpressCheckoutRequest();

			expect(
				sentHeaders( 2 )[ 'X-WCSTRIPE-EXPRESS-CHECKOUT-NONCE' ]
			).toBe( 'bundle_ece_nonce' );
		} );

		it( 'gives up after one retry', async () => {
			const secondRejection = invalidNonceResponse();
			apiFetch
				.mockRejectedValueOnce( invalidNonceResponse() )
				.mockRejectedValueOnce( secondRejection );

			await expect(
				storeApi.storeApiFetch( {
					method: 'POST',
					path: '/wc/store/v1/cart/add-item',
				} )
			).rejects.toBe( secondRejection );

			expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		} );
	} );

	it( 'does not retry when a request is rejected for another reason', async () => {
		const error = mockResponse(
			{ code: 'woocommerce_rest_cart_error' },
			{},
			403
		);
		const refresher = jest.fn();
		storeApi.setStoreApiNonceRefresher( refresher );
		apiFetch.mockRejectedValueOnce( error );

		await expect(
			storeApi.storeApiFetch( {
				method: 'POST',
				path: '/wc/store/v1/cart/add-item',
			} )
		).rejects.toBe( error );

		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( refresher ).not.toHaveBeenCalled();
	} );

	describe( 'with the WooCommerce Blocks nonce middleware loaded', () => {
		beforeEach( () => {
			apiFetch.setNonce = jest.fn();
		} );

		it( 'hands the middleware a remembered nonce before sending', async () => {
			apiFetch.mockImplementation( () => {
				expect( apiFetch.setNonce ).toHaveBeenCalledWith( {
					Nonce: 'bundle_store_api_nonce',
					'Nonce-Timestamp': 1700000000,
				} );
				return Promise.resolve( mockResponse() );
			} );
			storeApi.rememberStoreApiNonces( {
				wc_store_api: 'bundle_store_api_nonce',
				wc_store_api_timestamp: 1700000000,
			} );

			await storeApi.storeApiFetch( {
				method: 'POST',
				path: '/wc/store/v1/cart/add-item',
			} );

			expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		} );

		// The middleware ignores a nonce whose timestamp is `null`, taking it
		// for older than its own, but stamps one that has none with the time.
		it.each( [
			[
				'the timestamp',
				{ 'Nonce-Timestamp': '1700000100' },
				'1700000100',
			],
			[ 'no timestamp', {}, undefined ],
		] )(
			'hands the middleware the nonce a response carried with %s',
			async ( _label, timestampHeader, expectedTimestamp ) => {
				apiFetch.mockResolvedValue(
					mockResponse(
						{},
						{ Nonce: 'response_nonce', ...timestampHeader }
					)
				);

				await storeApi.storeApiFetch( { path: '/wc/store/v1/cart' } );
				await storeApi.storeApiFetch( { path: '/wc/store/v1/cart' } );

				expect( apiFetch.setNonce ).toHaveBeenLastCalledWith( {
					Nonce: 'response_nonce',
					'Nonce-Timestamp': expectedTimestamp,
				} );
			}
		);
	} );
} );
