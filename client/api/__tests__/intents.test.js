import { createApiClient } from '../core';
import {
	confirmIntent,
	createIntent,
	initSetupIntent,
	processCheckout,
} from '../intents';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	getStripeServerData: jest.fn(),
	getStripeDevWidgetOptions: jest.fn( () => ( {} ) ),
} ) );

describe( 'wcstripe/api/intents', () => {
	const options = {
		ajax_url: '/?wc-ajax=%%endpoint%%',
		createPaymentIntentNonce: 'nonce_123',
	};

	describe( 'createIntent', () => {
		it( 'includes the order key in the PaymentIntent AJAX request', async () => {
			const request = jest.fn().mockResolvedValue( {
				success: true,
				data: {
					id: 'pi_test',
					client_secret: 'pi_test_secret',
				},
			} );
			const client = createApiClient( options, request );

			await expect(
				createIntent( client, 123, 'blik', 'wc_order_test_key' )
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

	describe( 'request failures', () => {
		it( 'rejects with the server error when the response is unsuccessful', async () => {
			const serverError = { message: 'Order not found.' };
			const request = jest.fn().mockResolvedValue( {
				success: false,
				data: { error: serverError },
			} );
			const client = createApiClient( options, request );

			await expect( createIntent( client, 1 ) ).rejects.toBe(
				serverError
			);
			await expect( initSetupIntent( client, 'card' ) ).rejects.toBe(
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
				const client = createApiClient( options, request );
				const genericMessage =
					'An error occurred while connecting to the server. Please try again.';

				await expect( createIntent( client, 1 ) ).rejects.toThrow(
					genericMessage
				);
				await expect(
					initSetupIntent( client, 'card' )
				).rejects.toThrow( genericMessage );
				await expect(
					processCheckout( client, 'pi_123', {} )
				).rejects.toThrow( genericMessage );
			}
		);
	} );

	describe( 'confirmIntent', () => {
		it( 'returns true when the redirect URL carries no confirmation hash', () => {
			const client = createApiClient( {}, jest.fn() );

			expect(
				confirmIntent( client, 'https://shop.com/order-received/123/' )
			).toBe( true );
		} );
	} );
} );
