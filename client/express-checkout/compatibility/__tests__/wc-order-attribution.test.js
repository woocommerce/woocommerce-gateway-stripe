import { applyFilters } from '@wordpress/hooks';

describe( 'ECE order attribution compatibility', () => {
	it( 'filters out order attribution data', () => {
		const orderAttributionData = applyFilters(
			'wcstripe.express-checkout.cart-place-order-extension-data',
			{
				session_count: '1',
				session_pages: '79',
				session_start_time: '2025-01-14 14:50:29',
				source_type: 'utm',
				user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
				utm_source: 'Facebook',
			}
		);

		expect( orderAttributionData ).toStrictEqual( {
			session_count: '1',
			session_pages: '79',
			session_start_time: '2025-01-14 14:50:29',
			source_type: 'utm',
			user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
			utm_source: 'Facebook',
		} );
	} );
} );
