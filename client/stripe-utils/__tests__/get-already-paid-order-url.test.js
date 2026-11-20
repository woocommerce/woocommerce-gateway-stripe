import { getAlreadyPaidOrderUrl } from '../get-already-paid-order-url';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const RETURN_URL =
	'https://shop.com/checkout/order-received/42/?key=wc_order_abc&order_id=42&wc_payment_method=stripe&_wpnonce=nonce&wc_stripe_cs=1&pay_for_order=no';

describe( 'getAlreadyPaidOrderUrl', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	it.each( [ 'processing', 'completed' ] )(
		'returns the flagged order-received URL without the session return params when the order is %s',
		async ( status ) => {
			apiFetch.mockResolvedValue( { status } );

			const url = await getAlreadyPaidOrderUrl(
				RETURN_URL,
				'shopper@example.com'
			);

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/store/v1/order/42?key=wc_order_abc&billing_email=shopper%40example.com',
			} );
			expect( url ).toBe(
				'https://shop.com/checkout/order-received/42/?key=wc_order_abc&wc_stripe_duplicate_prevented=1'
			);
		}
	);

	it( 'keeps the query args of plain permalinks', async () => {
		apiFetch.mockResolvedValue( { status: 'processing' } );

		const url = await getAlreadyPaidOrderUrl(
			'https://shop.com/?page_id=7&order-received=42&key=wc_order_abc&order_id=42&wc_stripe_cs=1',
			'shopper@example.com'
		);

		expect( url ).toBe(
			'https://shop.com/?page_id=7&order-received=42&key=wc_order_abc&wc_stripe_duplicate_prevented=1'
		);
	} );

	it.each( [ 'pending', 'failed', 'cancelled', 'on-hold' ] )(
		'returns null when the order is %s',
		async ( status ) => {
			apiFetch.mockResolvedValue( { status } );

			expect(
				await getAlreadyPaidOrderUrl(
					RETURN_URL,
					'shopper@example.com'
				)
			).toBeNull();
		}
	);

	it( 'returns null when the order lookup fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'Invalid billing email.' ) );

		expect( await getAlreadyPaidOrderUrl( RETURN_URL, '' ) ).toBeNull();
	} );

	it( 'does not look the order up when the URL has no order ID', async () => {
		expect(
			await getAlreadyPaidOrderUrl(
				'https://shop.com/checkout/order-received/42/?key=wc_order_abc',
				'shopper@example.com'
			)
		).toBeNull();
		expect( apiFetch ).not.toHaveBeenCalled();
	} );
} );
