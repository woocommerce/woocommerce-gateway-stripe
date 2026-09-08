import { renderHook, waitFor } from '@testing-library/react';
import usePaymentIntents from '../use-payment-intents';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const response = ( data, hasMore = false ) => ( {
	object: 'list',
	has_more: hasMore,
	data,
} );

describe( 'usePaymentIntents', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	it( 'requests the first page without a cursor', async () => {
		apiFetch.mockResolvedValue( response( [ { id: 'pi_1' } ] ) );

		const { result } = renderHook( () =>
			usePaymentIntents( { perPage: 25, cursor: null } )
		);

		await waitFor( () => expect( result.current.isLoading ).toBe( false ) );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wc/v3/wc_stripe/payment_intents?limit=25',
		} );
		expect( result.current.data ).toEqual( [ { id: 'pi_1' } ] );
	} );

	it( 'sends starting_after when given a cursor', async () => {
		apiFetch.mockResolvedValue( response( [], false ) );

		renderHook( () =>
			usePaymentIntents( { perPage: 10, cursor: 'pi_abc' } )
		);

		await waitFor( () => expect( apiFetch ).toHaveBeenCalled() );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wc/v3/wc_stripe/payment_intents?limit=10&starting_after=pi_abc',
		} );
	} );

	it.each( [ [ true ], [ false ] ] )(
		'surfaces has_more as %s',
		async ( hasMore ) => {
			apiFetch.mockResolvedValue(
				response( [ { id: 'pi_1' } ], hasMore )
			);

			const { result } = renderHook( () =>
				usePaymentIntents( { perPage: 25, cursor: null } )
			);

			await waitFor( () =>
				expect( result.current.isLoading ).toBe( false )
			);

			expect( result.current.hasMore ).toBe( hasMore );
		}
	);

	it( 'keeps the previous page on screen when a request fails', async () => {
		apiFetch.mockResolvedValueOnce( response( [ { id: 'pi_1' } ], true ) );

		const { result, rerender } = renderHook(
			( { cursor } ) => usePaymentIntents( { perPage: 25, cursor } ),
			{ initialProps: { cursor: null } }
		);

		await waitFor( () => expect( result.current.isLoading ).toBe( false ) );

		apiFetch.mockRejectedValueOnce( new Error( 'Stripe is unavailable.' ) );
		rerender( { cursor: 'pi_1' } );

		await waitFor( () =>
			expect( result.current.error ).toBe( 'Stripe is unavailable.' )
		);

		expect( result.current.data ).toEqual( [ { id: 'pi_1' } ] );
	} );

	it( 'ignores a stale response that resolves after a newer one', async () => {
		let resolveFirst;
		apiFetch.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveFirst = resolve;
				} )
		);

		const { result, rerender } = renderHook(
			( { cursor } ) => usePaymentIntents( { perPage: 25, cursor } ),
			{ initialProps: { cursor: null } }
		);

		apiFetch.mockResolvedValueOnce( response( [ { id: 'pi_second' } ] ) );
		rerender( { cursor: 'pi_1' } );

		await waitFor( () =>
			expect( result.current.data ).toEqual( [ { id: 'pi_second' } ] )
		);

		resolveFirst( response( [ { id: 'pi_first' } ] ) );

		await waitFor( () => expect( result.current.isLoading ).toBe( false ) );

		expect( result.current.data ).toEqual( [ { id: 'pi_second' } ] );
	} );
} );
