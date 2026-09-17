import jQuery from 'jquery';
import { createApiClient, getAjaxUrl } from '../core';

jest.mock( 'jquery', () => ( { post: jest.fn() } ) );

describe( 'createApiClient', () => {
	it( 'keeps the options and the request function it was given', () => {
		const options = { key: 'pk_test_123' };
		const request = jest.fn();

		const client = createApiClient( options, request );

		expect( client.options ).toBe( options );
		expect( client.request ).toBe( request );
	} );

	describe( 'default request', () => {
		// jQuery.post() returns a jqXHR, which reports failures through `.fail()`.
		const mockJqXHR = ( { resolveWith, rejectWith } ) => {
			const jqXHR = {
				then: jest.fn( ( onSuccess ) => {
					if ( resolveWith ) {
						onSuccess( resolveWith );
					}
					return jqXHR;
				} ),
				fail: jest.fn( ( onFailure ) => {
					if ( rejectWith ) {
						onFailure( rejectWith );
					}
					return jqXHR;
				} ),
			};
			return jqXHR;
		};

		beforeEach( () => {
			jQuery.post.mockReset();
		} );

		it( 'posts through jQuery and resolves with the response', async () => {
			const response = { success: true };
			jQuery.post.mockReturnValue(
				mockJqXHR( { resolveWith: response } )
			);

			const client = createApiClient( {} );

			await expect(
				client.request( '/?wc-ajax=test', { foo: 'bar' } )
			).resolves.toBe( response );
			expect( jQuery.post ).toHaveBeenCalledWith( '/?wc-ajax=test', {
				foo: 'bar',
			} );
		} );

		it( 'rejects with the jqXHR when the request fails', async () => {
			const failure = { statusText: 'timeout' };
			jQuery.post.mockReturnValue( mockJqXHR( { rejectWith: failure } ) );

			const client = createApiClient( {} );

			await expect( client.request( '/?wc-ajax=test', {} ) ).rejects.toBe(
				failure
			);
		} );
	} );
} );

describe( 'getAjaxUrl', () => {
	const client = createApiClient(
		{ ajax_url: '/?wc-ajax=%%endpoint%%' },
		jest.fn()
	);

	it( 'interpolates the endpoint with the default prefix', () => {
		expect( getAjaxUrl( client, 'create_payment_intent' ) ).toBe(
			'/?wc-ajax=wc_stripe_create_payment_intent'
		);
	} );

	it( 'supports core WooCommerce endpoints through an empty prefix', () => {
		expect( getAjaxUrl( client, 'checkout', '' ) ).toBe(
			'/?wc-ajax=checkout'
		);
	} );

	it( 'returns undefined when no AJAX URL was provided', () => {
		expect(
			getAjaxUrl( createApiClient( {}, jest.fn() ), 'checkout' )
		).toBeUndefined();
	} );
} );
